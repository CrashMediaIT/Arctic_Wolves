"""
Arctic Wolves Video Companion Server
Hardware-accelerated video encoding, decoding, clip extraction,
and HLS transcoding with S3/RustFS integration.

The companion is a worker/integration service for the main Arctic Wolves
application.  It generates its own API key which must be entered in the
main application's Game Plan settings.  RustFS / S3 storage credentials
and video-path settings are pushed from the main app or entered in the
companion web UI and persisted in an AES-256-CBC encrypted config file
(matching the main app's PII encryption scheme).
"""

import os
import json
import uuid
import shutil
import subprocess
import threading
import time
import logging
import hashlib
import secrets
import base64
from pathlib import Path

import boto3
import requests as http_requests
from botocore.config import Config as BotoConfig
from flask import Flask, request, jsonify, render_template
from dotenv import load_dotenv

# Optional: cryptography for AES-256-CBC (fallback to a pure-Python impl)
try:
    from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
    from cryptography.hazmat.primitives import padding as sym_padding
    _HAS_CRYPTO = True
except ImportError:
    _HAS_CRYPTO = False

load_dotenv()

app = Flask(__name__)
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("companion")

# ---------------------------------------------------------------------------
# Persistent Encrypted Configuration
# ---------------------------------------------------------------------------
# Settings are stored in an AES-256-CBC encrypted JSON file on a persistent
# Docker volume so they survive container updates.  The encryption matches the
# main application's PII FieldEncryption scheme.
#
# The encryption key itself is stored at /config/encryption.key on the
# persistent volume.  On first start the companion shows a setup page where
# you generate or enter the key.  After a container update the key persists.

CONFIG_DIR = os.getenv("CONFIG_DIR", "/config")
CONFIG_FILE = os.path.join(CONFIG_DIR, "companion_config.enc")
KEY_FILE = os.path.join(CONFIG_DIR, "encryption.key")


def _read_key_file() -> str:
    """Read the hex encryption key from the persistent volume, or ''."""
    if os.path.isfile(KEY_FILE):
        try:
            return open(KEY_FILE, "r").read().strip()
        except Exception:
            return ""
    return ""


def _write_key_file(hex_key: str) -> bool:
    """Write the hex encryption key to the persistent volume."""
    try:
        Path(CONFIG_DIR).mkdir(parents=True, exist_ok=True)
        with open(KEY_FILE, "w") as f:
            f.write(hex_key)
        os.chmod(KEY_FILE, 0o600)
        return True
    except Exception as exc:
        logger.error("Failed to write key file: %s", exc)
        return False


def _get_cipher_key() -> bytes | None:
    """Return the raw 32-byte key from the key file, or None."""
    hex_key = _read_key_file()
    if not hex_key or len(hex_key) < 64:
        return None
    try:
        return bytes.fromhex(hex_key)
    except ValueError:
        return None


def _encrypt_config(plaintext: str) -> str:
    """Encrypt *plaintext* with AES-256-CBC and return base64 (IV + ciphertext).

    Compatible with the main app's FieldEncryption::encrypt().
    """
    key = _get_cipher_key()
    if key is None:
        return plaintext  # no key – store as-is (dev mode)

    iv = os.urandom(16)
    if _HAS_CRYPTO:
        padder = sym_padding.PKCS7(128).padder()
        padded = padder.update(plaintext.encode()) + padder.finalize()
        encryptor = Cipher(algorithms.AES(key), modes.CBC(iv)).encryptor()
        ct = encryptor.update(padded) + encryptor.finalize()
    else:
        # Pure-Python fallback using subprocess + openssl
        import subprocess as _sp
        proc = _sp.run(
            ["openssl", "enc", "-aes-256-cbc", "-nosalt", "-K", key.hex(), "-iv", iv.hex()],
            input=plaintext.encode(), capture_output=True,
        )
        ct = proc.stdout

    return base64.b64encode(iv + ct).decode()


def _decrypt_config(blob: str) -> str:
    """Decrypt a base64 blob produced by _encrypt_config."""
    key = _get_cipher_key()
    if key is None:
        return blob

    raw = base64.b64decode(blob)
    iv = raw[:16]
    ct = raw[16:]

    if _HAS_CRYPTO:
        decryptor = Cipher(algorithms.AES(key), modes.CBC(iv)).decryptor()
        padded = decryptor.update(ct) + decryptor.finalize()
        unpadder = sym_padding.PKCS7(128).unpadder()
        return (unpadder.update(padded) + unpadder.finalize()).decode()
    else:
        import subprocess as _sp
        proc = _sp.run(
            ["openssl", "enc", "-d", "-aes-256-cbc", "-nosalt", "-K", key.hex(), "-iv", iv.hex()],
            input=ct, capture_output=True,
        )
        return proc.stdout.decode()


def _load_persistent_config() -> dict:
    """Load the encrypted config file, returning a dict (empty if missing)."""
    if not os.path.isfile(CONFIG_FILE):
        return {}
    try:
        with open(CONFIG_FILE, "r") as f:
            blob = f.read().strip()
        plaintext = _decrypt_config(blob)
        return json.loads(plaintext)
    except Exception as exc:
        logger.warning("Could not load persistent config: %s", exc)
        return {}


def _save_persistent_config(cfg: dict) -> bool:
    """Encrypt and write config to the persistent volume."""
    try:
        Path(CONFIG_DIR).mkdir(parents=True, exist_ok=True)
        blob = _encrypt_config(json.dumps(cfg))
        with open(CONFIG_FILE, "w") as f:
            f.write(blob)
        return True
    except Exception as exc:
        logger.error("Failed to save persistent config: %s", exc)
        return False


# Load all settings from the encrypted persistent config file.
# No environment variables are used.  The encryption key is stored
# at /config/encryption.key (entered via the setup page on first start).
# Everything else is entered through the companion web UI (Settings tab)
# and persisted in the encrypted config file on the Docker volume.
_persisted = _load_persistent_config()

def _pcfg(key: str, default: str = "") -> str:
    """Return a value from the persisted config, or *default*."""
    return _persisted.get(key, default)

# ---------------------------------------------------------------------------
# Configuration — all values come from the encrypted config file
# ---------------------------------------------------------------------------
API_KEY = _pcfg("api_key")
MAIN_APP_URL = _pcfg("main_app_url")
HW_ACCEL = _pcfg("hw_accel", "auto")
MAX_CONCURRENT_JOBS = int(_pcfg("max_concurrent_jobs", "2"))

# S3 / RustFS connection — entered in the companion Settings UI or pushed
# from the main app.  The companion uses these to download source videos and
# upload transcoded output.  Storage *paths* are determined by the main app.
S3_ENDPOINT = _pcfg("s3_endpoint")
S3_ACCESS_KEY = _pcfg("s3_access_key")
S3_SECRET_KEY = _pcfg("s3_secret_key")
S3_BUCKET = _pcfg("s3_bucket")
S3_REGION = _pcfg("s3_region", "us-east-1")
S3_USE_SSL = _pcfg("s3_use_ssl", "true").lower() in ("true", "1", "yes")
S3_VERIFY_SSL = _pcfg("s3_verify_ssl", "false").lower() in ("true", "1", "yes")

# Internal constants (not user-configurable)
FFMPEG_PATH = "ffmpeg"
FFPROBE_PATH = "ffprobe"
TEMP_DIR = "/tmp/companion"
VIDEO_BASE_PATH = "/videos"
COMPANION_HOST = "0.0.0.0"
COMPANION_PORT = int(os.getenv("COMPANION_PORT", "5100"))
VERSION = "1.2.0"

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
# First-Run Setup — generate or enter the encryption key
# ---------------------------------------------------------------------------

SETUP_TEMPLATE = """<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Companion — Setup</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif}
body{background:#0A0A0F;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#16161F;border:1px solid #2D2D3F;border-radius:16px;padding:40px;max-width:540px;width:100%}
h1{font-size:1.8rem;font-weight:900;margin-bottom:8px}
h1 span{color:#6B46C1}
p{color:#A8A8B8;font-size:14px;line-height:1.6;margin-bottom:24px}
label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;color:#A8A8B8}
input{width:100%;padding:10px 14px;border:1px solid #2D2D3F;border-radius:8px;background:#0A0A0F;color:#fff;font-size:14px;font-family:monospace}
.actions{display:flex;gap:10px;margin-top:20px}
.btn{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:800;font-size:13px;text-transform:uppercase}
.btn-primary{background:#6B46C1;color:#fff}.btn-primary:hover{background:#7C3AED}
.btn-secondary{background:#2D2D3F;color:#fff}.btn-secondary:hover{background:#3D3D4F}
.hint{color:#A8A8B8;font-size:12px;margin-top:8px}
.error{color:#EF4444;font-size:13px;margin-top:12px;display:none}
</style></head><body>
<div class="card">
<h1>Video <span>Companion</span></h1>
<p>First-time setup. Enter or generate an AES-256 encryption key.<br>
This key encrypts the config file on the persistent volume. If you are connecting
to an existing main application, use the same key.</p>
<form id="sf">
<label for="ek">Encryption Key (64-char hex)</label>
<input id="ek" name="key" placeholder="Paste existing key or click Generate" autocomplete="off">
<div class="hint">Generate with: <code>python -c "import os; print(os.urandom(32).hex())"</code></div>
<div class="error" id="err"></div>
<div class="actions">
<button type="submit" class="btn btn-primary">Save &amp; Start</button>
<button type="button" class="btn btn-secondary" onclick="genKey()">Generate Key</button>
</div>
</form>
</div>
<script>
function genKey(){
 const a=new Uint8Array(32);crypto.getRandomValues(a);
 document.getElementById('ek').value=Array.from(a,b=>b.toString(16).padStart(2,'0')).join('');
}
document.getElementById('sf').onsubmit=async function(e){
 e.preventDefault();const err=document.getElementById('err');err.style.display='none';
 const key=document.getElementById('ek').value.trim();
 if(!/^[0-9a-fA-F]{64}$/.test(key)){err.textContent='Key must be exactly 64 hex characters.';err.style.display='block';return;}
 const r=await fetch('/api/setup',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key})});
 const d=await r.json();
 if(d.success){window.location.href='/';}else{err.textContent=d.error||'Setup failed.';err.style.display='block';}
};
</script></body></html>"""


@app.route("/setup")
def setup_page():
    """Show the first-run setup page (only when no encryption key exists)."""
    if _get_cipher_key() is not None:
        return flask_redirect("/")
    return SETUP_TEMPLATE, 200, {"Content-Type": "text/html"}


@app.route("/api/setup", methods=["POST"])
def setup_save():
    """Save the encryption key from the setup page."""
    if _get_cipher_key() is not None:
        return jsonify({"success": False, "error": "Already configured. Use Settings to change the key."}), 400

    data = request.get_json(silent=True) or {}
    hex_key = str(data.get("key", "")).strip()

    if not hex_key or len(hex_key) != 64:
        return jsonify({"success": False, "error": "Key must be exactly 64 hex characters."}), 400

    try:
        bytes.fromhex(hex_key)
    except ValueError:
        return jsonify({"success": False, "error": "Key must be valid hexadecimal."}), 400

    if not _write_key_file(hex_key):
        return jsonify({"success": False, "error": "Failed to write key file. Check volume permissions."}), 500

    # Reload the persisted config now that we have a key
    global _persisted, API_KEY, MAIN_APP_URL, HW_ACCEL, MAX_CONCURRENT_JOBS
    global S3_ENDPOINT, S3_ACCESS_KEY, S3_SECRET_KEY, S3_BUCKET, S3_REGION
    global S3_USE_SSL, S3_VERIFY_SSL
    _persisted = _load_persistent_config()

    logger.info("Setup complete — encryption key saved")
    return jsonify({"success": True})


# Import redirect helper
from flask import redirect as flask_redirect


@app.before_request
def _require_setup():
    """If no encryption key exists, redirect everything to the setup page."""
    if _get_cipher_key() is not None:
        return None  # key exists, proceed normally
    # Allow setup endpoints through
    if request.path in ("/setup", "/api/setup"):
        return None
    # Allow static assets (favicon etc.)
    if request.path.startswith("/static"):
        return None
    # Redirect browser requests to setup; return 503 for API calls
    if request.path.startswith("/api/"):
        return jsonify({"error": "Setup required", "setup_url": "/setup"}), 503
    return flask_redirect("/setup")


# ---------------------------------------------------------------------------
# Web UI
# ---------------------------------------------------------------------------

@app.route("/")
def index():
    """Serve the companion dashboard UI."""
    return render_template("index.html")


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
        "hw_accel": hw,
        "active_jobs": active_jobs,
        "max_concurrent_jobs": MAX_CONCURRENT_JOBS,
        "s3_configured": s3_configured,
        "s3_connected": s3_connected,
        "s3_bucket": S3_BUCKET if s3_configured else None,
        "main_app_url": MAIN_APP_URL or None,
        "config_encrypted": _get_cipher_key() is not None,
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


@app.route("/api/jobs", methods=["GET"])
def list_jobs():
    """List all jobs, newest first."""
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    with job_lock:
        all_jobs = sorted(jobs.values(), key=lambda j: j.get("created_at", 0), reverse=True)
    return jsonify(all_jobs)


# ---------------------------------------------------------------------------
# Runtime Configuration
# ---------------------------------------------------------------------------

@app.route("/api/config", methods=["GET"])
def get_config():
    """Return current runtime configuration (secrets are masked)."""
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    return jsonify({
        "api_key_set": bool(API_KEY),
        "main_app_url": MAIN_APP_URL,
        "hw_accel": HW_ACCEL,
        "max_concurrent_jobs": MAX_CONCURRENT_JOBS,
        "s3_endpoint": S3_ENDPOINT,
        "s3_bucket": S3_BUCKET,
        "s3_region": S3_REGION,
        "s3_access_key_set": bool(S3_ACCESS_KEY),
        "s3_secret_key_set": bool(S3_SECRET_KEY),
        "s3_use_ssl": S3_USE_SSL,
        "s3_verify_ssl": S3_VERIFY_SSL,
        "config_encrypted": _get_cipher_key() is not None,
        "config_persisted": os.path.isfile(CONFIG_FILE),
    })


@app.route("/api/config", methods=["PUT"])
def update_config():
    """Update runtime configuration values.

    Accepts a JSON object whose keys match the configuration names.
    Only the fields present in the request body are updated.
    Changes take effect immediately and are persisted to the encrypted
    config file so they survive container restarts.

    Storage *paths* (where videos live, HLS prefixes) are NOT configurable
    here — they are controlled by the main application which tells the
    companion exactly where each source file is and where to write output.
    """
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    global API_KEY, MAIN_APP_URL, HW_ACCEL, MAX_CONCURRENT_JOBS
    global S3_ENDPOINT, S3_ACCESS_KEY, S3_SECRET_KEY, S3_BUCKET, S3_REGION
    global S3_USE_SSL, S3_VERIFY_SSL

    data = request.get_json(silent=True) or {}
    updated = []

    if "api_key" in data:
        API_KEY = str(data["api_key"])
        updated.append("api_key")

    if "main_app_url" in data:
        MAIN_APP_URL = str(data["main_app_url"]).rstrip("/")
        updated.append("main_app_url")

    if "hw_accel" in data:
        allowed = ("auto", "nvenc", "qsv", "vaapi", "amf", "none")
        val = str(data["hw_accel"]).lower()
        if val not in allowed:
            return jsonify({"error": f"hw_accel must be one of {allowed}"}), 400
        HW_ACCEL = val
        updated.append("hw_accel")

    if "max_concurrent_jobs" in data:
        try:
            MAX_CONCURRENT_JOBS = max(1, int(data["max_concurrent_jobs"]))
        except (ValueError, TypeError):
            return jsonify({"error": "max_concurrent_jobs must be a positive integer"}), 400
        updated.append("max_concurrent_jobs")

    if "s3_endpoint" in data:
        S3_ENDPOINT = str(data["s3_endpoint"])
        updated.append("s3_endpoint")

    if "s3_access_key" in data:
        S3_ACCESS_KEY = str(data["s3_access_key"])
        updated.append("s3_access_key")

    if "s3_secret_key" in data:
        S3_SECRET_KEY = str(data["s3_secret_key"])
        updated.append("s3_secret_key")

    if "s3_bucket" in data:
        S3_BUCKET = str(data["s3_bucket"])
        updated.append("s3_bucket")

    if "s3_region" in data:
        S3_REGION = str(data["s3_region"])
        updated.append("s3_region")

    if "s3_use_ssl" in data:
        S3_USE_SSL = str(data["s3_use_ssl"]).lower() in ("true", "1", "yes")
        updated.append("s3_use_ssl")

    if "s3_verify_ssl" in data:
        S3_VERIFY_SSL = str(data["s3_verify_ssl"]).lower() in ("true", "1", "yes")
        updated.append("s3_verify_ssl")

    if not updated:
        return jsonify({"error": "No recognized configuration fields in request"}), 400

    # Persist to encrypted config file
    cfg = {
        "api_key": API_KEY,
        "main_app_url": MAIN_APP_URL,
        "hw_accel": HW_ACCEL,
        "max_concurrent_jobs": str(MAX_CONCURRENT_JOBS),
        "s3_endpoint": S3_ENDPOINT,
        "s3_access_key": S3_ACCESS_KEY,
        "s3_secret_key": S3_SECRET_KEY,
        "s3_bucket": S3_BUCKET,
        "s3_region": S3_REGION,
        "s3_use_ssl": str(S3_USE_SSL).lower(),
        "s3_verify_ssl": str(S3_VERIFY_SSL).lower(),
    }
    persisted = _save_persistent_config(cfg)

    logger.info("Configuration updated: %s (persisted=%s)", ", ".join(updated), persisted)
    return jsonify({"updated": updated, "persisted": persisted})


@app.route("/api/generate-key", methods=["POST"])
def generate_key():
    """Generate a new API key for authenticating requests from the main app.

    The companion creates the key; the admin then copies it into the main
    application's Game Plan Settings → Companion Server → API Key field.
    """
    # If an API key is already set, require it to generate a new one
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    global API_KEY
    new_key = secrets.token_hex(32)  # 64-char hex string, same as main app's bin2hex(random_bytes(32))
    API_KEY = new_key

    # Persist the new key
    cfg = _load_persistent_config()
    cfg["api_key"] = new_key
    _save_persistent_config(cfg)

    logger.info("New API key generated")
    return jsonify({
        "api_key": new_key,
        "message": "Copy this key into the main application's Game Plan Settings → Companion Server → API Key.",
    })


# ---------------------------------------------------------------------------
# Callback — notify the main app when a job completes
# ---------------------------------------------------------------------------

def _send_callback(callback_url: str, payload: dict):
    """POST job results back to the main application (fire-and-forget).

    Note: SSL verification is disabled because companion and main app
    typically run on an internal network behind a reverse proxy (HAProxy)
    with self-signed or internal certificates.
    """
    if not callback_url:
        return
    # Validate URL scheme to prevent SSRF to non-HTTP destinations
    from urllib.parse import urlparse
    parsed = urlparse(callback_url)
    if parsed.scheme not in ("http", "https"):
        logger.warning("Callback URL rejected (invalid scheme): %s", callback_url)
        return
    try:
        headers = {"Content-Type": "application/json"}
        if API_KEY:
            headers["X-API-Key"] = API_KEY
        http_requests.post(callback_url, json=payload, headers=headers, timeout=10, verify=False)  # noqa: S501
        logger.info("Callback sent to %s for job %s", callback_url, payload.get("job_id"))
    except Exception as exc:
        logger.warning("Callback to %s failed: %s", callback_url, exc)


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
                       delete_original: bool = True, callback_url: str = ""):
    """Download source from S3, transcode to HLS, upload output, optionally
    delete original.  Runs inside _run_job with the job semaphore."""
    s3 = _get_s3_client()
    if not s3:
        with job_lock:
            jobs[job_id]["status"] = "failed"
            jobs[job_id]["error"] = "S3 not configured"
            jobs[job_id]["finished_at"] = time.time()
        return

    # Resolve callback URL once for both success and failure paths
    cb_url = callback_url or (MAIN_APP_URL + "/api/v1/companion/callback" if MAIN_APP_URL else "")

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

        # Notify the main application that transcoding is complete
        variant_playlists = {v["label"]: f"{output_prefix}/{v['label']}/playlist.m3u8" for v in variants}
        with job_lock:
            vid_id = jobs[job_id].get("video_id")
        _send_callback(cb_url, {
            "job_id": job_id,
            "video_id": vid_id,
            "status": "completed",
            "source_key": s3_source_key,
            "hls_manifest": f"{output_prefix}/master.m3u8",
            "hls_segments_path": output_prefix,
            "variants": variant_playlists,
        })

    except Exception as exc:
        logger.error("HLS transcode job %s failed: %s", job_id, exc)
        with job_lock:
            jobs[job_id]["status"] = "failed"
            jobs[job_id]["error"] = str(exc)[:2000]
            jobs[job_id]["finished_at"] = time.time()

        # Notify the main application about failure too
        with job_lock:
            vid_id = jobs[job_id].get("video_id")
        _send_callback(cb_url, {
            "job_id": job_id,
            "video_id": vid_id,
            "status": "failed",
            "source_key": s3_source_key,
            "error": str(exc)[:2000],
        })
    finally:
        # Clean up temp files
        shutil.rmtree(work_dir, ignore_errors=True)


def _resolution_width(height: int) -> int:
    """Estimate width from height assuming 16:9 aspect ratio."""
    return (height * 16 // 9 + 1) & ~1  # Round to even number


def _run_hls_job(job_id: str, s3_source_key: str, s3_output_prefix: str,
                 delete_original: bool, callback_url: str = ""):
    """Wrapper that acquires the semaphore and runs HLS transcode."""
    with job_semaphore:
        _hls_transcode_s3(job_id, s3_source_key, s3_output_prefix, delete_original, callback_url)


@app.route("/api/hls", methods=["POST"])
def hls_transcode():
    """Transcode a video from S3/RustFS to multi-quality HLS and upload back.

    The main application controls storage locations — it specifies both the
    source_key (where the uploaded video lives) and output_prefix (where the
    transcoded HLS segments should be written).  When transcoding finishes
    the companion POSTs results to callback_url so the main app can update
    the database with the final file locations.

    POST JSON body:
        source_key:       S3 object key of the source video (required)
        output_prefix:    S3 prefix where HLS files are written (required)
        delete_original:  (optional, default true) delete source after transcoding
        callback_url:     (optional) URL to POST result when complete;
                          falls back to MAIN_APP_URL/api/v1/companion/callback
        video_id:         (optional) main-app video row ID, echoed in the callback
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
    callback_url = data.get("callback_url", "")
    video_id = data.get("video_id")

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
        "video_id": video_id,
        "created_at": time.time(),
        "started_at": None,
        "finished_at": None,
        "error": None,
    }
    with job_lock:
        jobs[job_id] = job

    thread = threading.Thread(
        target=_run_hls_job,
        args=(job_id, source_key, output_prefix, delete_original, callback_url),
        daemon=True,
    )
    thread.start()
    return jsonify(job), 202


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
if __name__ == "__main__":
    print(f"Arctic Wolves Video Companion Server v{VERSION}")
    print(f"Hardware acceleration: {HW_ACCEL}")
    print(f"S3 endpoint: {S3_ENDPOINT or '(not configured)'}")
    print(f"Config encrypted: {_get_cipher_key() is not None}")
    print(f"Listening on {COMPANION_HOST}:{COMPANION_PORT}")
    app.run(host=COMPANION_HOST, port=COMPANION_PORT, debug=False)
