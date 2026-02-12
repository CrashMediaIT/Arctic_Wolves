"""
Arctic Wolves Video Companion Server
Hardware-accelerated video encoding, decoding, and clip extraction.
"""

import os
import json
import uuid
import shutil
import subprocess
import threading
import time
from pathlib import Path

from flask import Flask, request, jsonify
from dotenv import load_dotenv

load_dotenv()

app = Flask(__name__)

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
API_KEY = os.getenv("API_KEY", "")
VIDEO_BASE_PATH = os.getenv("VIDEO_BASE_PATH", "/videos")
HW_ACCEL = os.getenv("HW_ACCEL", "auto")
FFMPEG_PATH = os.getenv("FFMPEG_PATH", "ffmpeg")
FFPROBE_PATH = os.getenv("FFPROBE_PATH", "ffprobe")
MAX_CONCURRENT_JOBS = int(os.getenv("MAX_CONCURRENT_JOBS", "2"))
TEMP_DIR = os.getenv("TEMP_DIR", "/tmp/companion")
COMPANION_HOST = os.getenv("COMPANION_HOST", "0.0.0.0")
COMPANION_PORT = int(os.getenv("COMPANION_PORT", "5100"))

Path(TEMP_DIR).mkdir(parents=True, exist_ok=True)

# In-memory job store (job_id -> dict)
jobs: dict = {}
job_lock = threading.Lock()
job_semaphore = threading.Semaphore(MAX_CONCURRENT_JOBS)

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _require_api_key():
    """Validate X-API-Key header. Returns error response or None."""
    if not API_KEY:
        return None  # no key configured – skip auth (dev mode)
    key = request.headers.get("X-API-Key", "")
    if key != API_KEY:
        return jsonify({"error": "Unauthorized"}), 401
    return None


def _safe_path(relative: str) -> str | None:
    """Resolve a relative video path against VIDEO_BASE_PATH safely."""
    base = Path(VIDEO_BASE_PATH).resolve()
    target = (base / relative).resolve()
    if not str(target).startswith(str(base)):
        return None
    return str(target)


def _detect_hw_accel() -> dict:
    """Detect available hardware acceleration methods via FFmpeg."""
    result = {
        "available": [],
        "selected": HW_ACCEL,
        "encoders": [],
        "decoders": [],
    }
    try:
        proc = subprocess.run(
            [FFMPEG_PATH, "-hide_banner", "-hwaccels"],
            capture_output=True, text=True, timeout=10,
        )
        for line in proc.stdout.strip().splitlines()[1:]:
            method = line.strip()
            if method:
                result["available"].append(method)
    except Exception:
        pass

    # Check for hardware encoders
    try:
        proc = subprocess.run(
            [FFMPEG_PATH, "-hide_banner", "-encoders"],
            capture_output=True, text=True, timeout=10,
        )
        for line in proc.stdout.splitlines():
            for hw_enc in ["h264_nvenc", "hevc_nvenc", "h264_qsv", "hevc_qsv",
                           "h264_vaapi", "hevc_vaapi", "h264_amf", "hevc_amf",
                           "av1_nvenc", "av1_qsv", "av1_vaapi"]:
                if hw_enc in line:
                    result["encoders"].append(hw_enc)
    except Exception:
        pass

    # Check for hardware decoders
    try:
        proc = subprocess.run(
            [FFMPEG_PATH, "-hide_banner", "-decoders"],
            capture_output=True, text=True, timeout=10,
        )
        for line in proc.stdout.splitlines():
            for hw_dec in ["h264_cuvid", "hevc_cuvid", "h264_qsv", "hevc_qsv",
                           "vp9_cuvid", "av1_cuvid", "vp9_qsv", "av1_qsv"]:
                if hw_dec in line:
                    result["decoders"].append(hw_dec)
    except Exception:
        pass

    return result


def _select_encoder(hw_info: dict, codec: str = "h264") -> list[str]:
    """Return FFmpeg encoder flags based on available hardware and config."""
    accel = HW_ACCEL.lower()
    encoders = hw_info.get("encoders", [])

    # Map of preference: acceleration method -> encoder name
    preferences = {
        "h264": [
            ("nvenc", "h264_nvenc"),
            ("qsv", "h264_qsv"),
            ("vaapi", "h264_vaapi"),
            ("amf", "h264_amf"),
        ],
        "h265": [
            ("nvenc", "hevc_nvenc"),
            ("qsv", "hevc_qsv"),
            ("vaapi", "hevc_vaapi"),
            ("amf", "hevc_amf"),
        ],
        "av1": [
            ("nvenc", "av1_nvenc"),
            ("qsv", "av1_qsv"),
            ("vaapi", "av1_vaapi"),
        ],
    }

    codec_prefs = preferences.get(codec, preferences["h264"])

    if accel == "none":
        return ["-c:v", f"lib{codec.replace('h265', 'x265').replace('h264', 'x264')}"]

    if accel == "auto":
        for _, enc in codec_prefs:
            if enc in encoders:
                return _encoder_flags(enc)
    else:
        for method, enc in codec_prefs:
            if method == accel and enc in encoders:
                return _encoder_flags(enc)

    # Fallback to software
    sw = codec.replace("h265", "x265").replace("h264", "x264")
    return ["-c:v", f"lib{sw}"]


def _encoder_flags(encoder: str) -> list[str]:
    """Return FFmpeg flags for a specific hardware encoder."""
    flags = ["-c:v", encoder]
    if "nvenc" in encoder:
        flags += ["-preset", "p4", "-rc", "vbr"]
    elif "qsv" in encoder:
        flags += ["-preset", "medium"]
    elif "vaapi" in encoder:
        flags = ["-vaapi_device", "/dev/dri/renderD128"] + flags
    return flags


def _hwaccel_decode_flags() -> list[str]:
    """Return FFmpeg input flags for hardware-accelerated decoding."""
    accel = HW_ACCEL.lower()
    if accel == "none":
        return []
    if accel in ("nvenc", "auto"):
        return ["-hwaccel", "cuda", "-hwaccel_output_format", "cuda"]
    if accel == "qsv":
        return ["-hwaccel", "qsv"]
    if accel == "vaapi":
        return ["-hwaccel", "vaapi", "-hwaccel_device", "/dev/dri/renderD128"]
    return []


def _probe_file(filepath: str) -> dict:
    """Use ffprobe to get video metadata."""
    cmd = [
        FFPROBE_PATH, "-v", "quiet", "-print_format", "json",
        "-show_format", "-show_streams", filepath,
    ]
    proc = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    if proc.returncode != 0:
        raise RuntimeError(f"ffprobe failed: {proc.stderr[:500]}")
    return json.loads(proc.stdout)


def _run_job(job_id: str, cmd: list[str]):
    """Execute an FFmpeg command as a background job."""
    with job_semaphore:
        with job_lock:
            if job_id not in jobs:
                return
            jobs[job_id]["status"] = "running"
            jobs[job_id]["started_at"] = time.time()

        try:
            proc = subprocess.run(
                cmd, capture_output=True, text=True, timeout=7200,
            )
            with job_lock:
                if proc.returncode == 0:
                    jobs[job_id]["status"] = "completed"
                else:
                    jobs[job_id]["status"] = "failed"
                    jobs[job_id]["error"] = proc.stderr[:2000]
                jobs[job_id]["finished_at"] = time.time()
        except subprocess.TimeoutExpired:
            with job_lock:
                jobs[job_id]["status"] = "failed"
                jobs[job_id]["error"] = "Job timed out after 2 hours"
                jobs[job_id]["finished_at"] = time.time()
        except Exception as exc:
            with job_lock:
                jobs[job_id]["status"] = "failed"
                jobs[job_id]["error"] = str(exc)[:2000]
                jobs[job_id]["finished_at"] = time.time()


def _create_job(cmd: list[str], description: str, output_path: str) -> dict:
    """Create and start a background encoding job."""
    job_id = str(uuid.uuid4())
    job = {
        "id": job_id,
        "status": "queued",
        "description": description,
        "output": output_path,
        "created_at": time.time(),
        "started_at": None,
        "finished_at": None,
        "error": None,
    }
    with job_lock:
        jobs[job_id] = job

    thread = threading.Thread(target=_run_job, args=(job_id, cmd), daemon=True)
    thread.start()
    return job


# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------

@app.route("/api/health", methods=["GET"])
def health():
    """Server status and hardware capabilities."""
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    hw = _detect_hw_accel()

    # Check video base path accessibility
    base_accessible = os.path.isdir(VIDEO_BASE_PATH)

    active_jobs = sum(1 for j in jobs.values() if j["status"] in ("queued", "running"))

    return jsonify({
        "status": "ok",
        "version": "1.0.0",
        "video_base_path": VIDEO_BASE_PATH,
        "video_base_accessible": base_accessible,
        "hw_accel": hw,
        "active_jobs": active_jobs,
        "max_concurrent_jobs": MAX_CONCURRENT_JOBS,
    })


@app.route("/api/probe", methods=["POST"])
def probe():
    """Get video file metadata."""
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    data = request.get_json(silent=True) or {}
    source = data.get("source", "")
    if not source:
        return jsonify({"error": "source is required"}), 400

    filepath = _safe_path(source)
    if not filepath or not os.path.isfile(filepath):
        return jsonify({"error": "File not found"}), 404

    try:
        info = _probe_file(filepath)
    except Exception as exc:
        return jsonify({"error": str(exc)}), 500

    # Extract useful summary
    fmt = info.get("format", {})
    streams = info.get("streams", [])
    video_stream = next((s for s in streams if s.get("codec_type") == "video"), None)
    audio_stream = next((s for s in streams if s.get("codec_type") == "audio"), None)

    summary = {
        "filename": os.path.basename(filepath),
        "duration": float(fmt.get("duration", 0)),
        "size": int(fmt.get("size", 0)),
        "format": fmt.get("format_name", ""),
        "bitrate": int(fmt.get("bit_rate", 0)),
    }
    if video_stream:
        summary["video"] = {
            "codec": video_stream.get("codec_name", ""),
            "width": video_stream.get("width"),
            "height": video_stream.get("height"),
            "fps": video_stream.get("r_frame_rate", ""),
            "bitrate": int(video_stream.get("bit_rate", 0) or 0),
        }
    if audio_stream:
        summary["audio"] = {
            "codec": audio_stream.get("codec_name", ""),
            "channels": audio_stream.get("channels"),
            "sample_rate": audio_stream.get("sample_rate", ""),
        }

    return jsonify(summary)


@app.route("/api/clip", methods=["POST"])
def clip():
    """Extract a clip from a source video."""
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    data = request.get_json(silent=True) or {}
    source = data.get("source", "")
    start_time = data.get("start_time")
    end_time = data.get("end_time")
    output = data.get("output", "")
    hw_accel = data.get("hw_accel", True)
    codec = data.get("codec", "copy")

    if not source or start_time is None or end_time is None or not output:
        return jsonify({"error": "source, start_time, end_time, and output are required"}), 400

    source_path = _safe_path(source)
    output_path = _safe_path(output)
    if not source_path or not os.path.isfile(source_path):
        return jsonify({"error": "Source file not found"}), 404
    if not output_path:
        return jsonify({"error": "Invalid output path"}), 400

    # Ensure output directory exists
    os.makedirs(os.path.dirname(output_path), exist_ok=True)

    duration = float(end_time) - float(start_time)

    if codec == "copy":
        cmd = [
            FFMPEG_PATH, "-y", "-ss", str(start_time),
            "-i", source_path, "-t", str(duration),
            "-c", "copy", "-movflags", "+faststart",
            output_path,
        ]
    else:
        hw_info = _detect_hw_accel()
        decode_flags = _hwaccel_decode_flags() if hw_accel else []
        encode_flags = _select_encoder(hw_info, codec) if hw_accel else ["-c:v", f"lib{codec.replace('h265', 'x265').replace('h264', 'x264')}"]

        cmd = [FFMPEG_PATH, "-y"] + decode_flags + [
            "-ss", str(start_time), "-i", source_path,
            "-t", str(duration),
        ] + encode_flags + [
            "-c:a", "aac", "-movflags", "+faststart",
            output_path,
        ]

    desc = f"Clip: {os.path.basename(source)} [{start_time}s - {end_time}s]"
    job = _create_job(cmd, desc, output)
    return jsonify(job), 202


@app.route("/api/transcode", methods=["POST"])
def transcode():
    """Transcode a video to a different format/codec."""
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    data = request.get_json(silent=True) or {}
    source = data.get("source", "")
    output = data.get("output", "")
    codec = data.get("codec", "h264")
    hw_accel = data.get("hw_accel", True)
    crf = data.get("crf", 23)
    resolution = data.get("resolution")  # e.g. "1920x1080"

    if not source or not output:
        return jsonify({"error": "source and output are required"}), 400

    source_path = _safe_path(source)
    output_path = _safe_path(output)
    if not source_path or not os.path.isfile(source_path):
        return jsonify({"error": "Source file not found"}), 404
    if not output_path:
        return jsonify({"error": "Invalid output path"}), 400

    os.makedirs(os.path.dirname(output_path), exist_ok=True)

    hw_info = _detect_hw_accel()
    decode_flags = _hwaccel_decode_flags() if hw_accel else []
    encode_flags = _select_encoder(hw_info, codec) if hw_accel else ["-c:v", f"lib{codec.replace('h265', 'x265').replace('h264', 'x264')}"]

    cmd = [FFMPEG_PATH, "-y"] + decode_flags + ["-i", source_path] + encode_flags

    # CRF (only for software encoders)
    if "lib" in " ".join(encode_flags):
        cmd += ["-crf", str(crf)]

    if resolution:
        cmd += ["-vf", f"scale={resolution.replace('x', ':')}"]

    cmd += ["-c:a", "aac", "-movflags", "+faststart", output_path]

    desc = f"Transcode: {os.path.basename(source)} → {codec}"
    job = _create_job(cmd, desc, output)
    return jsonify(job), 202


@app.route("/api/thumbnail", methods=["POST"])
def thumbnail():
    """Generate a thumbnail at a given timestamp."""
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    data = request.get_json(silent=True) or {}
    source = data.get("source", "")
    timestamp = data.get("timestamp", 0)
    output = data.get("output", "")
    width = data.get("width", 640)

    if not source or not output:
        return jsonify({"error": "source and output are required"}), 400

    source_path = _safe_path(source)
    output_path = _safe_path(output)
    if not source_path or not os.path.isfile(source_path):
        return jsonify({"error": "Source file not found"}), 404
    if not output_path:
        return jsonify({"error": "Invalid output path"}), 400

    os.makedirs(os.path.dirname(output_path), exist_ok=True)

    cmd = [
        FFMPEG_PATH, "-y", "-ss", str(timestamp),
        "-i", source_path, "-vframes", "1",
        "-vf", f"scale={width}:-1",
        output_path,
    ]

    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
        if proc.returncode != 0:
            return jsonify({"error": f"Thumbnail generation failed: {proc.stderr[:500]}"}), 500
    except Exception as exc:
        return jsonify({"error": str(exc)}), 500

    return jsonify({"status": "completed", "output": output})


@app.route("/api/job/<job_id>", methods=["GET"])
def get_job(job_id):
    """Check job status."""
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    with job_lock:
        job = jobs.get(job_id)
    if not job:
        return jsonify({"error": "Job not found"}), 404
    return jsonify(job)


@app.route("/api/job/<job_id>", methods=["DELETE"])
def cancel_job(job_id):
    """Cancel / remove a job."""
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    with job_lock:
        job = jobs.pop(job_id, None)
    if not job:
        return jsonify({"error": "Job not found"}), 404
    return jsonify({"status": "cancelled", "id": job_id})


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
if __name__ == "__main__":
    print(f"Arctic Wolves Video Companion Server v1.0.0")
    print(f"Video base path: {VIDEO_BASE_PATH}")
    print(f"Hardware acceleration: {HW_ACCEL}")
    print(f"Listening on {COMPANION_HOST}:{COMPANION_PORT}")
    app.run(host=COMPANION_HOST, port=COMPANION_PORT, debug=False)
