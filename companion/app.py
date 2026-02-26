"""
Arctic Wolves Video Companion Server
Hardware-accelerated video encoding, decoding, clip extraction,
and HLS transcoding with S3/RustFS integration.
"""

import os
import json
import uuid
import shutil
import subprocess
import threading
import time
import logging
from pathlib import Path

import boto3
from botocore.config import Config as BotoConfig
from flask import Flask, request, jsonify
from dotenv import load_dotenv

load_dotenv()

app = Flask(__name__)
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("companion")

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

# S3 / RustFS configuration
S3_ENDPOINT = os.getenv("S3_ENDPOINT", "")
S3_ACCESS_KEY = os.getenv("S3_ACCESS_KEY", "")
S3_SECRET_KEY = os.getenv("S3_SECRET_KEY", "")
S3_BUCKET = os.getenv("S3_BUCKET", "")
S3_REGION = os.getenv("S3_REGION", "us-east-1")
S3_USE_SSL = os.getenv("S3_USE_SSL", "true").lower() in ("true", "1", "yes")
S3_VERIFY_SSL = os.getenv("S3_VERIFY_SSL", "false").lower() in ("true", "1", "yes")

# HLS staging configuration
HLS_STAGING_PREFIX = os.getenv("HLS_STAGING_PREFIX", "Images/videos/")
HLS_POLL_INTERVAL = int(os.getenv("HLS_POLL_INTERVAL", "30"))
VERSION = "1.1.0"

Path(TEMP_DIR).mkdir(parents=True, exist_ok=True)

# In-memory job store (job_id -> dict)
jobs: dict = {}
job_lock = threading.Lock()
job_semaphore = threading.Semaphore(MAX_CONCURRENT_JOBS)

# ---------------------------------------------------------------------------
# S3 / RustFS Client
# ---------------------------------------------------------------------------

def _get_s3_client():
    """Create a boto3 S3 client configured for RustFS."""
    if not S3_ENDPOINT or not S3_ACCESS_KEY or not S3_SECRET_KEY:
        return None
    scheme = "https" if S3_USE_SSL else "http"
    endpoint = S3_ENDPOINT
    if not endpoint.startswith("http"):
        endpoint = f"{scheme}://{endpoint}"
    return boto3.client(
        "s3",
        endpoint_url=endpoint,
        aws_access_key_id=S3_ACCESS_KEY,
        aws_secret_access_key=S3_SECRET_KEY,
        region_name=S3_REGION,
        config=BotoConfig(
            s3={"addressing_style": "path"},
            signature_version="s3v4",
            retries={"max_attempts": 3},
        ),
        verify=S3_VERIFY_SSL,  # Configurable; often disabled for self-signed certs
    )


def _s3_download(s3, object_key: str, local_path: str) -> bool:
    """Download an object from S3/RustFS to a local file."""
    try:
        s3.download_file(S3_BUCKET, object_key, local_path)
        return True
    except Exception as exc:
        logger.error("S3 download failed for %s: %s", object_key, exc)
        return False


def _s3_upload(s3, local_path: str, object_key: str, content_type: str = "") -> bool:
    """Upload a local file to S3/RustFS."""
    try:
        extra = {}
        if content_type:
            extra["ContentType"] = content_type
        s3.upload_file(local_path, S3_BUCKET, object_key, ExtraArgs=extra if extra else None)
        return True
    except Exception as exc:
        logger.error("S3 upload failed for %s: %s", object_key, exc)
        return False


def _s3_delete(s3, object_key: str) -> bool:
    """Delete an object from S3/RustFS."""
    try:
        s3.delete_object(Bucket=S3_BUCKET, Key=object_key)
        return True
    except Exception as exc:
        logger.error("S3 delete failed for %s: %s", object_key, exc)
        return False


def _s3_list_objects(s3, prefix: str) -> list:
    """List objects with a given prefix in S3/RustFS."""
    try:
        result = []
        paginator = s3.get_paginator("list_objects_v2")
        for page in paginator.paginate(Bucket=S3_BUCKET, Prefix=prefix):
            for obj in page.get("Contents", []):
                result.append(obj["Key"])
        return result
    except Exception as exc:
        logger.error("S3 list failed for prefix %s: %s", prefix, exc)
        return []

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

    # Check S3/RustFS connectivity
    s3_configured = bool(S3_ENDPOINT and S3_ACCESS_KEY and S3_SECRET_KEY)
    s3_connected = False
    if s3_configured:
        try:
            s3 = _get_s3_client()
            if s3:
                s3.head_bucket(Bucket=S3_BUCKET)
                s3_connected = True
        except Exception:
            pass

    return jsonify({
        "status": "ok",
        "version": VERSION,
        "video_base_path": VIDEO_BASE_PATH,
        "video_base_accessible": base_accessible,
        "hw_accel": hw,
        "active_jobs": active_jobs,
        "max_concurrent_jobs": MAX_CONCURRENT_JOBS,
        "s3_configured": s3_configured,
        "s3_connected": s3_connected,
        "s3_bucket": S3_BUCKET if s3_configured else None,
        "hls_staging_watcher": _watcher_running,
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
# HLS Transcoding
# ---------------------------------------------------------------------------

# Standard HLS quality ladder (height, video bitrate, audio bitrate)
HLS_VARIANTS = [
    {"height": 360,  "vbitrate": "800k",  "abitrate": "96k",  "label": "360p"},
    {"height": 480,  "vbitrate": "1400k", "abitrate": "128k", "label": "480p"},
    {"height": 720,  "vbitrate": "2800k", "abitrate": "128k", "label": "720p"},
    {"height": 1080, "vbitrate": "5000k", "abitrate": "192k", "label": "1080p"},
]


def _hls_transcode_s3(job_id: str, s3_source_key: str, s3_output_prefix: str,
                       delete_original: bool = True):
    """Download source from S3, transcode to HLS, upload output, optionally
    delete original.  Runs inside _run_job with the job semaphore."""
    s3 = _get_s3_client()
    if not s3:
        with job_lock:
            jobs[job_id]["status"] = "failed"
            jobs[job_id]["error"] = "S3 not configured"
            jobs[job_id]["finished_at"] = time.time()
        return

    work_dir = os.path.join(TEMP_DIR, job_id)
    os.makedirs(work_dir, exist_ok=True)

    local_source = os.path.join(work_dir, "source" + os.path.splitext(s3_source_key)[1])

    try:
        with job_lock:
            jobs[job_id]["status"] = "downloading"
            jobs[job_id]["started_at"] = time.time()

        # Download source video
        if not _s3_download(s3, s3_source_key, local_source):
            raise RuntimeError(f"Failed to download {s3_source_key}")

        # Probe the source to determine resolution
        probe = _probe_file(local_source)
        video_stream = next(
            (s for s in probe.get("streams", []) if s.get("codec_type") == "video"),
            None,
        )
        source_height = int(video_stream.get("height", 1080)) if video_stream else 1080

        # Filter variants to those at or below source resolution
        variants = [v for v in HLS_VARIANTS if v["height"] <= source_height]
        if not variants:
            variants = [HLS_VARIANTS[0]]  # At minimum, produce 360p

        with job_lock:
            jobs[job_id]["status"] = "transcoding"

        hw_info = _detect_hw_accel()
        hls_output = os.path.join(work_dir, "hls")
        os.makedirs(hls_output, exist_ok=True)

        # Transcode each variant
        for v in variants:
            label = v["label"]
            variant_dir = os.path.join(hls_output, label)
            os.makedirs(variant_dir, exist_ok=True)

            encode_flags = _select_encoder(hw_info, "h264")
            decode_flags = _hwaccel_decode_flags()

            cmd = [FFMPEG_PATH, "-y"] + decode_flags + [
                "-i", local_source,
            ] + encode_flags + [
                "-vf", f"scale=-2:{v['height']}",
                "-b:v", v["vbitrate"],
                "-maxrate", v["vbitrate"],
                "-bufsize", str(int(v["vbitrate"].replace("k", "")) * 2) + "k",
                "-c:a", "aac", "-b:a", v["abitrate"],
                "-f", "hls",
                "-hls_time", "6",
                "-hls_list_size", "0",
                "-hls_segment_filename", os.path.join(variant_dir, "seg_%03d.ts"),
                os.path.join(variant_dir, "playlist.m3u8"),
            ]

            logger.info("HLS transcode %s → %s: %s", s3_source_key, label, " ".join(cmd))
            proc = subprocess.run(cmd, capture_output=True, text=True, timeout=7200)
            if proc.returncode != 0:
                logger.error("FFmpeg failed for %s: %s", label, proc.stderr[:1000])
                raise RuntimeError(f"Transcode failed for {label}: {proc.stderr[:500]}")

        # Build master playlist
        master_lines = ["#EXTM3U"]
        for v in variants:
            bandwidth = int(v["vbitrate"].replace("k", "")) * 1000
            master_lines.append(
                f'#EXT-X-STREAM-INF:BANDWIDTH={bandwidth},'
                f'RESOLUTION={_resolution_width(v["height"])}x{v["height"]},'
                f'NAME="{v["label"]}"'
            )
            master_lines.append(f'{v["label"]}/playlist.m3u8')

        master_path = os.path.join(hls_output, "master.m3u8")
        with open(master_path, "w") as f:
            f.write("\n".join(master_lines) + "\n")

        with job_lock:
            jobs[job_id]["status"] = "uploading"

        # Upload all HLS files to S3
        output_prefix = s3_output_prefix.rstrip("/")
        for root, _dirs, files in os.walk(hls_output):
            for filename in files:
                local_file = os.path.join(root, filename)
                relative = os.path.relpath(local_file, hls_output)
                s3_key = f"{output_prefix}/{relative}"
                ct = "application/vnd.apple.mpegurl" if filename.endswith(".m3u8") else "video/mp2t"
                if not _s3_upload(s3, local_file, s3_key, ct):
                    raise RuntimeError(f"Failed to upload {s3_key}")

        # Delete original source from S3 if requested
        if delete_original:
            _s3_delete(s3, s3_source_key)

        with job_lock:
            jobs[job_id]["status"] = "completed"
            jobs[job_id]["hls_manifest"] = f"{output_prefix}/master.m3u8"
            jobs[job_id]["finished_at"] = time.time()
            jobs[job_id]["variants"] = [v["label"] for v in variants]

    except Exception as exc:
        logger.error("HLS transcode job %s failed: %s", job_id, exc)
        with job_lock:
            jobs[job_id]["status"] = "failed"
            jobs[job_id]["error"] = str(exc)[:2000]
            jobs[job_id]["finished_at"] = time.time()
    finally:
        # Clean up temp files
        shutil.rmtree(work_dir, ignore_errors=True)


def _resolution_width(height: int) -> int:
    """Estimate width from height assuming 16:9 aspect ratio."""
    return (height * 16 // 9 + 1) & ~1  # Round to even number


def _run_hls_job(job_id: str, s3_source_key: str, s3_output_prefix: str,
                 delete_original: bool):
    """Wrapper that acquires the semaphore and runs HLS transcode."""
    with job_semaphore:
        _hls_transcode_s3(job_id, s3_source_key, s3_output_prefix, delete_original)


@app.route("/api/hls", methods=["POST"])
def hls_transcode():
    """Transcode a video from S3/RustFS to multi-quality HLS and upload back.

    POST JSON body:
        source_key:       S3 object key of the source video
        output_prefix:    S3 prefix where HLS files are written
        delete_original:  (optional, default true) delete source after transcoding
        callback_url:     (optional) URL to POST result when complete
    """
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    s3 = _get_s3_client()
    if not s3:
        return jsonify({"error": "S3/RustFS is not configured on companion server"}), 503

    data = request.get_json(silent=True) or {}
    source_key = data.get("source_key", "")
    output_prefix = data.get("output_prefix", "")
    delete_original = data.get("delete_original", True)

    if not source_key:
        return jsonify({"error": "source_key is required"}), 400
    if not output_prefix:
        # Derive output prefix from source key (replace extension with /hls/)
        base = os.path.splitext(source_key)[0]
        output_prefix = base + "/hls"

    job_id = str(uuid.uuid4())
    job = {
        "id": job_id,
        "status": "queued",
        "description": f"HLS transcode: {os.path.basename(source_key)}",
        "source_key": source_key,
        "output_prefix": output_prefix,
        "hls_manifest": None,
        "variants": [],
        "created_at": time.time(),
        "started_at": None,
        "finished_at": None,
        "error": None,
    }
    with job_lock:
        jobs[job_id] = job

    thread = threading.Thread(
        target=_run_hls_job,
        args=(job_id, source_key, output_prefix, delete_original),
        daemon=True,
    )
    thread.start()
    return jsonify(job), 202


# ---------------------------------------------------------------------------
# HLS Staging Watcher
# ---------------------------------------------------------------------------

_watcher_running = False


def _start_staging_watcher():
    """Start a background thread that watches for new video uploads in S3 and
    automatically queues HLS transcoding jobs."""
    global _watcher_running
    if _watcher_running:
        return
    _watcher_running = True

    s3 = _get_s3_client()
    if not s3:
        logger.warning("S3 not configured – HLS staging watcher disabled")
        _watcher_running = False
        return

    processed_keys: set = set()
    video_extensions = {".mp4", ".mkv", ".mov", ".avi", ".webm"}

    def _watcher_loop():
        nonlocal processed_keys
        logger.info("HLS staging watcher started (prefix=%s, interval=%ds)",
                     HLS_STAGING_PREFIX, HLS_POLL_INTERVAL)
        while True:
            try:
                objects = _s3_list_objects(s3, HLS_STAGING_PREFIX)
                for key in objects:
                    ext = os.path.splitext(key)[1].lower()
                    if ext not in video_extensions:
                        continue
                    # Skip if already processed or HLS output
                    if key in processed_keys:
                        continue
                    if "/hls/" in key:
                        continue

                    # Check if HLS output already exists
                    hls_prefix = os.path.splitext(key)[0] + "/hls/"
                    existing_hls = _s3_list_objects(s3, hls_prefix)
                    if any(k.endswith("master.m3u8") for k in existing_hls):
                        processed_keys.add(key)
                        continue

                    logger.info("Staging watcher: queuing HLS transcode for %s", key)
                    processed_keys.add(key)

                    # Queue a transcode job
                    job_id = str(uuid.uuid4())
                    output_prefix = os.path.splitext(key)[0] + "/hls"
                    job = {
                        "id": job_id,
                        "status": "queued",
                        "description": f"Auto HLS: {os.path.basename(key)}",
                        "source_key": key,
                        "output_prefix": output_prefix,
                        "hls_manifest": None,
                        "variants": [],
                        "created_at": time.time(),
                        "started_at": None,
                        "finished_at": None,
                        "error": None,
                    }
                    with job_lock:
                        jobs[job_id] = job

                    thread = threading.Thread(
                        target=_run_hls_job,
                        args=(job_id, key, output_prefix, True),
                        daemon=True,
                    )
                    thread.start()

            except Exception as exc:
                logger.error("Staging watcher error: %s", exc)

            time.sleep(HLS_POLL_INTERVAL)

    watcher = threading.Thread(target=_watcher_loop, daemon=True)
    watcher.start()


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
if __name__ == "__main__":
    print(f"Arctic Wolves Video Companion Server v{VERSION}")
    print(f"Video base path: {VIDEO_BASE_PATH}")
    print(f"Hardware acceleration: {HW_ACCEL}")
    print(f"S3 endpoint: {S3_ENDPOINT or '(not configured)'}")
    print(f"Listening on {COMPANION_HOST}:{COMPANION_PORT}")
    _start_staging_watcher()
    app.run(host=COMPANION_HOST, port=COMPANION_PORT, debug=False)
