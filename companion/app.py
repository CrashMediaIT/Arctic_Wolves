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
from flask import Flask, request, jsonify, render_template, session
from werkzeug.security import generate_password_hash, check_password_hash
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
app.secret_key = secrets.token_hex(32)

# ---------------------------------------------------------------------------
# Logging — stdout + rotating file log in /config/logs/
# ---------------------------------------------------------------------------
LOG_FORMAT = "%(asctime)s [%(levelname)s] %(message)s"
logging.basicConfig(level=logging.INFO, format=LOG_FORMAT)
logger = logging.getLogger("companion")

# Add a rotating file handler so logs persist across container restarts
try:
    from logging.handlers import RotatingFileHandler
    _log_dir = os.path.join(os.environ.get("CONFIG_DIR", "/config"), "logs")
    os.makedirs(_log_dir, exist_ok=True)
    _file_handler = RotatingFileHandler(
        os.path.join(_log_dir, "companion.log"),
        maxBytes=5 * 1024 * 1024,  # 5 MB per file
        backupCount=3,
    )
    _file_handler.setLevel(logging.INFO)
    _file_handler.setFormatter(logging.Formatter(LOG_FORMAT))
    logger.addHandler(_file_handler)
    logging.getLogger().addHandler(_file_handler)  # root logger too
except Exception:
    pass  # Fallback to stdout-only logging if /config is not writable


# Flask error handler — log unhandled exceptions with full request context
@app.errorhandler(Exception)
def _handle_unhandled_exception(exc):
    logger.error("Unhandled exception on %s %s: %s", request.method, request.path, exc, exc_info=True)
    return jsonify({"error": "Internal server error"}), 500


# Log every request and its outcome for debugging connectivity issues
@app.after_request
def _log_request(response):
    # Skip noisy health-check logging at INFO level
    if request.path == "/api/health" and response.status_code == 200:
        logger.debug("%s %s → %s", request.method, request.path, response.status_code)
    else:
        logger.info("%s %s → %s", request.method, request.path, response.status_code)
    return response

# ---------------------------------------------------------------------------
# Persistent Encrypted Configuration
# ---------------------------------------------------------------------------
# Settings are stored in an AES-256-CBC encrypted JSON file on a persistent
# Docker volume so they survive container updates.  The encryption matches the
# main application's PII FieldEncryption scheme.
#
# The encryption key is read from the ENCRYPTION_KEY environment variable.
# If not set, it falls back to /config/encryption.key on the persistent volume.
# On first start the companion shows a setup page to create an admin account.

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
    """Return the raw 32-byte key — from ENCRYPTION_KEY env var or key file."""
    hex_key = os.getenv("ENCRYPTION_KEY", "").strip()
    if not hex_key:
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
# The encryption key comes from the ENCRYPTION_KEY env var (preferred)
# or /config/encryption.key (fallback).  Everything else is entered
# through the companion web UI and persisted in the encrypted config file.
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

# Node support — master/slave architecture for distributed transcoding.
# The master node is what the main application communicates with.  If the
# master is busy (all job slots occupied) it delegates to a slave node.
# Slave nodes are registered in the companion Settings UI.
NODE_ROLE = _pcfg("node_role", "master")  # "master" or "slave"

def _load_slave_nodes() -> list:
    """Return the list of slave node dicts from persistent config."""
    raw = _persisted.get("slave_nodes", [])
    if isinstance(raw, list):
        return raw
    return []

SLAVE_NODES: list = _load_slave_nodes()

# Admin account — created during setup, used for dashboard login.
# Stored in the encrypted config; propagated from master to slaves.
ADMIN_USERNAME = _pcfg("admin_username")
ADMIN_PASSWORD_HASH = _pcfg("admin_password_hash")

# Internal constants (not user-configurable)
FFMPEG_PATH = "ffmpeg"
FFPROBE_PATH = "ffprobe"
TEMP_DIR = "/tmp/companion"
VIDEO_BASE_PATH = "/videos"
COMPANION_HOST = "0.0.0.0"
COMPANION_PORT = int(os.getenv("COMPANION_PORT", "5100"))
VERSION = "1.3.0"

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
        missing = [k for k, v in {"endpoint": S3_ENDPOINT, "access_key": S3_ACCESS_KEY, "secret_key": S3_SECRET_KEY}.items() if not v]
        logger.warning("S3 client unavailable — missing: %s", ", ".join(missing))
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
    """Validate X-API-Key header **or** logged-in session. Returns error response or None."""
    if not API_KEY:
        return None  # no key configured – skip auth (dev mode)
    if session.get("logged_in"):
        return None  # browser dashboard session – skip API key check
    key = request.headers.get("X-API-Key", "")
    if key != API_KEY:
        logger.warning("Unauthorized API request: %s %s (IP=%s)", request.method, request.path, request.remote_addr)
        return jsonify({"error": "Unauthorized"}), 401
    return None


# ---------------------------------------------------------------------------
# Node Helpers — master/slave delegation
# ---------------------------------------------------------------------------

def _is_master_busy() -> bool:
    """Return True if all concurrent job slots on this node are occupied."""
    with job_lock:
        active = sum(1 for j in jobs.values() if j["status"] in ("queued", "running", "downloading", "transcoding", "uploading"))
    return active >= MAX_CONCURRENT_JOBS


def _check_slave_health(node: dict) -> dict:
    """Probe a slave node's /api/health endpoint and return status info."""
    url = node.get("url", "").rstrip("/")
    api_key = node.get("api_key", "")
    result = {"url": url, "name": node.get("name", ""), "online": False, "busy": True, "active_jobs": 0, "max_jobs": 0}
    if not url:
        return result
    # Validate URL scheme to prevent SSRF to non-HTTP destinations
    from urllib.parse import urlparse
    if urlparse(url).scheme not in ("http", "https"):
        return result
    try:
        headers = {"Accept": "application/json"}
        if api_key:
            headers["X-API-Key"] = api_key
        resp = http_requests.get(url + "/api/health", headers=headers, timeout=5, verify=False)  # noqa: S501
        if resp.status_code == 200:
            data = resp.json()
            result["online"] = data.get("status") == "ok"
            result["active_jobs"] = data.get("active_jobs", 0)
            result["max_jobs"] = data.get("max_concurrent_jobs", 1)
            result["busy"] = result["active_jobs"] >= result["max_jobs"]
            result["version"] = data.get("version", "")
    except Exception as exc:
        logger.debug("Slave health check failed for %s: %s", url, exc)
    return result


def _get_available_slave() -> dict | None:
    """Return the first slave node that is online and not busy, or None."""
    for node in SLAVE_NODES:
        status = _check_slave_health(node)
        if status["online"] and not status["busy"]:
            return node
    return None


def _delegate_to_slave(node: dict, data: dict) -> dict | None:
    """Forward an HLS transcode request to a slave node.

    Returns the slave's response dict on success, or None on failure.
    """
    url = node.get("url", "").rstrip("/")
    api_key = node.get("api_key", "")
    if not url:
        return None
    # Validate URL scheme to prevent SSRF to non-HTTP destinations
    from urllib.parse import urlparse
    if urlparse(url).scheme not in ("http", "https"):
        return None
    try:
        headers = {"Content-Type": "application/json"}
        if api_key:
            headers["X-API-Key"] = api_key
        resp = http_requests.post(url + "/api/hls", json=data, headers=headers, timeout=15, verify=False)  # noqa: S501
        if resp.status_code in (200, 202):
            result = resp.json()
            result["delegated_to"] = url
            return result
    except Exception as exc:
        logger.warning("Failed to delegate HLS job to slave %s: %s", url, exc)
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

        logger.info("Job %s started: %s", job_id, " ".join(cmd[:6]))
        try:
            proc = subprocess.run(
                cmd, capture_output=True, text=True, timeout=7200,
            )
            with job_lock:
                if proc.returncode == 0:
                    jobs[job_id]["status"] = "completed"
                    logger.info("Job %s completed successfully", job_id)
                else:
                    jobs[job_id]["status"] = "failed"
                    jobs[job_id]["error"] = proc.stderr[:2000]
                    logger.error("Job %s failed (exit code %d): %s", job_id, proc.returncode, proc.stderr[:500])
                jobs[job_id]["finished_at"] = time.time()
        except subprocess.TimeoutExpired:
            with job_lock:
                jobs[job_id]["status"] = "failed"
                jobs[job_id]["error"] = "Job timed out after 2 hours"
                jobs[job_id]["finished_at"] = time.time()
            logger.error("Job %s timed out after 2 hours", job_id)
        except Exception as exc:
            with job_lock:
                jobs[job_id]["status"] = "failed"
                jobs[job_id]["error"] = str(exc)[:2000]
                jobs[job_id]["finished_at"] = time.time()
            logger.error("Job %s exception: %s", job_id, exc)


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
# Login Page
# ---------------------------------------------------------------------------

LOGIN_TEMPLATE = """<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Companion — Login</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif}
body{background:#0A0A0F;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#16161F;border:1px solid #2D2D3F;border-radius:16px;padding:40px;max-width:400px;width:100%}
h1{font-size:1.8rem;font-weight:900;margin-bottom:8px}
h1 span{color:#6B46C1}
p{color:#A8A8B8;font-size:14px;line-height:1.6;margin-bottom:24px}
label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;color:#A8A8B8}
input{width:100%;padding:10px 14px;border:1px solid #2D2D3F;border-radius:8px;background:#0A0A0F;color:#fff;font-size:14px;margin-bottom:16px}
.btn{width:100%;padding:12px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:800;font-size:13px;text-transform:uppercase;background:#6B46C1;color:#fff}
.btn:hover{background:#7C3AED}
.error{color:#EF4444;font-size:13px;margin-bottom:12px;display:none}
</style></head><body>
<div class="card">
<h1>Video <span>Companion</span></h1>
<p>Sign in to the companion dashboard.</p>
<form id="login-form" onsubmit="return doLogin(event)">
<label for="username">Username</label>
<input id="username" type="text" placeholder="Username" autocomplete="username" required>
<label for="password">Password</label>
<input id="password" type="password" placeholder="Password" autocomplete="current-password" required>
<div class="error" id="err"></div>
<button type="submit" class="btn">Sign In</button>
</form>
</div>
<script>
async function doLogin(e){
 e.preventDefault();
 var err=document.getElementById('err');err.style.display='none';
 var u=document.getElementById('username').value.trim();
 var p=document.getElementById('password').value;
 if(!u||!p){err.textContent='Please enter username and password.';err.style.display='block';return false;}
 try{
  var r=await fetch('/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:u,password:p})});
  var d=await r.json();
  if(r.ok&&d.success){window.location.href='/';}
  else{err.textContent=d.error||'Login failed.';err.style.display='block';}
 }catch{err.textContent='Network error.';err.style.display='block';}
 return false;
}
</script></body></html>"""


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
.card{background:#16161F;border:1px solid #2D2D3F;border-radius:16px;padding:40px;max-width:600px;width:100%}
h1{font-size:1.8rem;font-weight:900;margin-bottom:8px}
h1 span{color:#6B46C1}
p{color:#A8A8B8;font-size:14px;line-height:1.6;margin-bottom:24px}
label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;color:#A8A8B8}
input,select{width:100%;padding:10px 14px;border:1px solid #2D2D3F;border-radius:8px;background:#0A0A0F;color:#fff;font-size:14px}
input[type=text],input[type=password]{font-family:monospace}
select{cursor:pointer;appearance:none}
.actions{display:flex;gap:10px;margin-top:20px}
.btn{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:800;font-size:13px;text-transform:uppercase}
.btn-primary{background:#6B46C1;color:#fff}.btn-primary:hover{background:#7C3AED}
.btn-secondary{background:#2D2D3F;color:#fff}.btn-secondary:hover{background:#3D3D4F}
.hint{color:#A8A8B8;font-size:12px;margin-top:8px}
.error{color:#EF4444;font-size:13px;margin-top:12px;display:none}
.success{color:#22c55e;font-size:13px;margin-top:12px;display:none}
.step{display:none}.step.active{display:block}
.step-indicator{display:flex;gap:8px;margin-bottom:24px}
.step-dot{width:10px;height:10px;border-radius:50%;background:#2D2D3F}
.step-dot.active{background:#6B46C1}
.step-dot.done{background:#22c55e}
.role-cards{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px}
.role-card{border:2px solid #2D2D3F;border-radius:12px;padding:20px;cursor:pointer;text-align:center;transition:0.2s}
.role-card:hover{border-color:#6B46C1}
.role-card.selected{border-color:#6B46C1;background:rgba(107,70,193,0.1)}
.role-card h3{font-size:16px;margin-bottom:6px}
.role-card p{font-size:12px;margin-bottom:0}
.role-icon{font-size:32px;margin-bottom:8px}
.form-group{margin-bottom:16px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.api-key-box{background:#0A0A0F;border:1px solid #2D2D3F;border-radius:8px;padding:14px;font-family:monospace;font-size:13px;word-break:break-all;color:#22c55e;display:none}
</style></head><body>
<div class="card">
<h1>Video <span>Companion</span></h1>

<!-- Step 1: Node Role Selection -->
<div class="step active" id="step-role">
<p>Welcome to first-time setup. Select the role for this companion node.</p>
<div class="role-cards">
<div class="role-card" onclick="selectRole('master')" id="role-master">
<div class="role-icon">&#9733;</div>
<h3>Master Node</h3>
<p>Receives jobs from the main application. Can delegate to slave nodes when busy.</p>
</div>
<div class="role-card" onclick="selectRole('slave')" id="role-slave">
<div class="role-icon">&#9830;</div>
<h3>Slave Node</h3>
<p>Accepts delegated jobs from a master node. Settings are synced from the master.</p>
</div>
</div>
<div class="error" id="err-role"></div>
<div class="actions">
<button type="button" class="btn btn-primary" onclick="nextFromRole()">Continue</button>
</div>
</div>

<!-- Step 2 (Master): Create Account -->
<div class="step" id="step-master-account">
<div class="step-indicator"><span class="step-dot done"></span><span class="step-dot active"></span><span class="step-dot"></span><span class="step-dot"></span></div>
<p><strong>Master Setup — Step 1:</strong> Create an admin account for the companion dashboard.
This account will also be synced to any slave nodes you connect later.</p>
<div class="form-group">
<label for="admin-user">Username</label>
<input id="admin-user" type="text" placeholder="Enter a username" autocomplete="off">
</div>
<div class="form-group">
<label for="admin-pass">Password</label>
<input id="admin-pass" type="password" placeholder="Enter a password" autocomplete="new-password">
</div>
<div class="form-group">
<label for="admin-pass2">Confirm Password</label>
<input id="admin-pass2" type="password" placeholder="Confirm password" autocomplete="new-password">
</div>
<div class="error" id="err-account"></div>
<div class="actions">
<button type="button" class="btn btn-primary" onclick="saveAccount()">Create Account &amp; Continue</button>
</div>
</div>

<!-- Step 3 (Master): RustFS / S3 Setup -->
<div class="step" id="step-master-rustfs">
<div class="step-indicator"><span class="step-dot done"></span><span class="step-dot done"></span><span class="step-dot active"></span><span class="step-dot"></span></div>
<p><strong>Master Setup — Step 2:</strong> Configure S3 / RustFS storage credentials.
These are used to download source videos and upload transcoded output.
You can also push these later from the main app.</p>
<div class="form-row">
<div class="form-group">
<label for="setup-s3-endpoint">S3 Endpoint URL</label>
<input id="setup-s3-endpoint" type="text" placeholder="https://rustfs.example.com">
</div>
<div class="form-group">
<label for="setup-s3-bucket">Bucket Name</label>
<input id="setup-s3-bucket" type="text" placeholder="your-bucket-name">
</div>
</div>
<div class="form-row">
<div class="form-group">
<label for="setup-s3-region">Region</label>
<input id="setup-s3-region" type="text" placeholder="us-east-1" value="us-east-1">
</div>
<div class="form-group">
<label for="setup-s3-access">Access Key</label>
<input id="setup-s3-access" type="password" placeholder="Access key" autocomplete="off">
</div>
</div>
<div class="form-group">
<label for="setup-s3-secret">Secret Key</label>
<input id="setup-s3-secret" type="password" placeholder="Secret key" autocomplete="off">
</div>
<div class="error" id="err-rustfs"></div>
<div class="actions">
<button type="button" class="btn btn-primary" onclick="saveMasterRustFS()">Save &amp; Continue</button>
<button type="button" class="btn btn-secondary" onclick="skipRustFS()">Skip (configure later)</button>
</div>
</div>

<!-- Step 4 (Master): API Key Generation -->
<div class="step" id="step-master-apikey">
<div class="step-indicator"><span class="step-dot done"></span><span class="step-dot done"></span><span class="step-dot done"></span><span class="step-dot active"></span></div>
<p><strong>Master Setup — Step 3:</strong> Generate an API key. Copy this key into the
main application's Game Plan Settings &rarr; Companion Server &rarr; API Key field.</p>
<div class="form-group">
<label>API Key</label>
<div id="setup-api-key-box" class="api-key-box"></div>
<div class="hint" id="setup-api-hint">Click Generate to create a new API key.</div>
</div>
<div class="error" id="err-apikey"></div>
<div class="actions">
<button type="button" class="btn btn-primary" id="gen-api-btn" onclick="setupGenerateApiKey()">Generate API Key</button>
<button type="button" class="btn btn-secondary" id="finish-master-btn" onclick="finishSetup()" style="display:none">Finish Setup</button>
</div>
</div>

<!-- Step 2 (Slave): API Key Generation -->
<div class="step" id="step-slave-apikey">
<div class="step-indicator"><span class="step-dot done"></span><span class="step-dot active"></span></div>
<p><strong>Slave Setup:</strong> Generate an API key for this slave node.
Copy this key and enter it when adding this slave on the master node.
Once connected, all settings (S3, hardware acceleration, etc.) will be synced from the master.</p>
<div class="form-group">
<label>API Key</label>
<div id="slave-api-key-box" class="api-key-box"></div>
<div class="hint" id="slave-api-hint">Click Generate to create a new API key for this slave.</div>
</div>
<div class="error" id="err-slave"></div>
<div class="actions">
<button type="button" class="btn btn-primary" id="gen-slave-btn" onclick="slaveGenerateApiKey()">Generate API Key</button>
<button type="button" class="btn btn-secondary" id="finish-slave-btn" onclick="finishSetup()" style="display:none">Finish Setup</button>
</div>
</div>

</div>
<script>
var selectedRole='';
function selectRole(role){
 selectedRole=role;
 document.getElementById('role-master').className='role-card'+(role==='master'?' selected':'');
 document.getElementById('role-slave').className='role-card'+(role==='slave'?' selected':'');
}
function nextFromRole(){
 var err=document.getElementById('err-role');
 if(!selectedRole){err.textContent='Please select a node role.';err.style.display='block';return;}
 err.style.display='none';
 document.getElementById('step-role').className='step';
 if(selectedRole==='master'){
  document.getElementById('step-master-account').className='step active';
 } else {
  saveSlaveSetup();
 }
}
async function saveAccount(){
 var err=document.getElementById('err-account');err.style.display='none';
 var u=document.getElementById('admin-user').value.trim();
 var p=document.getElementById('admin-pass').value;
 var p2=document.getElementById('admin-pass2').value;
 if(!u||u.length<3){err.textContent='Username must be at least 3 characters.';err.style.display='block';return;}
 if(!p||p.length<6){err.textContent='Password must be at least 6 characters.';err.style.display='block';return;}
 if(p!==p2){err.textContent='Passwords do not match.';err.style.display='block';return;}
 var r=await fetch('/api/setup',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({node_role:'master',admin_username:u,admin_password:p})});
 var d=await r.json();
 if(!d.success){err.textContent=d.error||'Setup failed.';err.style.display='block';return;}
 if(d.config_loaded&&d.s3_configured){
  document.getElementById('setup-s3-endpoint').value=d.config.s3_endpoint||'';
  document.getElementById('setup-s3-bucket').value=d.config.s3_bucket||'';
  document.getElementById('setup-s3-region').value=d.config.s3_region||'us-east-1';
 }
 document.getElementById('step-master-account').className='step';
 document.getElementById('step-master-rustfs').className='step active';
}
async function saveMasterRustFS(){
 var err=document.getElementById('err-rustfs');err.style.display='none';
 var payload={};
 var v=function(id){return document.getElementById(id).value.trim()};
 if(v('setup-s3-endpoint'))payload.s3_endpoint=v('setup-s3-endpoint');
 if(v('setup-s3-bucket'))payload.s3_bucket=v('setup-s3-bucket');
 if(v('setup-s3-region'))payload.s3_region=v('setup-s3-region');
 if(v('setup-s3-access'))payload.s3_access_key=v('setup-s3-access');
 if(v('setup-s3-secret'))payload.s3_secret_key=v('setup-s3-secret');
 if(Object.keys(payload).length>0){
  var r=await fetch('/api/config',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  var d=await r.json();
  if(!r.ok){err.textContent=d.error||'Failed to save.';err.style.display='block';return;}
 }
 document.getElementById('step-master-rustfs').className='step';
 document.getElementById('step-master-apikey').className='step active';
}
function skipRustFS(){
 document.getElementById('step-master-rustfs').className='step';
 document.getElementById('step-master-apikey').className='step active';
}
async function setupGenerateApiKey(){
 var err=document.getElementById('err-apikey');err.style.display='none';
 var r=await fetch('/api/generate-key',{method:'POST',headers:{'Content-Type':'application/json'}});
 var d=await r.json();
 if(r.ok&&d.api_key){
  var box=document.getElementById('setup-api-key-box');
  box.textContent=d.api_key;box.style.display='block';
  localStorage.setItem('companion_api_key',d.api_key);
  document.getElementById('setup-api-hint').textContent='Copy this key into the main app\\'s Game Plan Settings.';
  document.getElementById('gen-api-btn').style.display='none';
  document.getElementById('finish-master-btn').style.display='';
 } else {err.textContent=d.error||'Failed to generate key.';err.style.display='block';}
}
async function saveSlaveSetup(){
 var r=await fetch('/api/setup',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({node_role:'slave'})});
 var d=await r.json();
 if(d.success){
  document.getElementById('step-role').className='step';
  document.getElementById('step-slave-apikey').className='step active';
 }
}
async function slaveGenerateApiKey(){
 var err=document.getElementById('err-slave');err.style.display='none';
 var r=await fetch('/api/generate-key',{method:'POST',headers:{'Content-Type':'application/json'}});
 var d=await r.json();
 if(r.ok&&d.api_key){
  var box=document.getElementById('slave-api-key-box');
  box.textContent=d.api_key;box.style.display='block';
  localStorage.setItem('companion_api_key',d.api_key);
  document.getElementById('slave-api-hint').textContent='Copy this key and enter it on the master node when adding this slave.';
  document.getElementById('gen-slave-btn').style.display='none';
  document.getElementById('finish-slave-btn').style.display='';
 } else {err.textContent=d.error||'Failed to generate key.';err.style.display='block';}
}
function finishSetup(){window.location.href='/';}
</script></body></html>"""


@app.route("/setup")
def setup_page():
    """Show the setup page.  Accessible even after initial setup so users
    can re-run the wizard after a companion update."""
    return SETUP_TEMPLATE, 200, {"Content-Type": "text/html"}


@app.route("/api/setup", methods=["POST"])
def setup_save():
    """Save the node role and admin account from the setup wizard.

    Master setup: requires admin_username and admin_password.  If an existing
    encrypted config file can be read (using the ENCRYPTION_KEY env var) the
    saved settings are loaded and returned so the wizard can pre-populate
    the RustFS fields.

    Slave setup: auto-generates an encryption key file if ENCRYPTION_KEY env
    var is not set.  The admin account will be synced from the master later.
    """
    data = request.get_json(silent=True) or {}
    node_role = str(data.get("node_role", "master")).lower()
    if node_role not in ("master", "slave"):
        node_role = "master"

    global _persisted, API_KEY, MAIN_APP_URL, HW_ACCEL, MAX_CONCURRENT_JOBS
    global S3_ENDPOINT, S3_ACCESS_KEY, S3_SECRET_KEY, S3_BUCKET, S3_REGION
    global S3_USE_SSL, S3_VERIFY_SSL, NODE_ROLE, SLAVE_NODES
    global ADMIN_USERNAME, ADMIN_PASSWORD_HASH

    # Ensure we have an encryption key (from env or generate one)
    if _get_cipher_key() is None:
        hex_key = os.getenv("ENCRYPTION_KEY", "").strip()
        if not hex_key:
            hex_key = secrets.token_hex(32)
        if not _write_key_file(hex_key):
            return jsonify({"success": False, "error": "Failed to write key file. Check volume permissions."}), 500

    if node_role == "slave":
        # Slave nodes — minimal config; account synced from master later
        _persisted = _load_persistent_config()
        NODE_ROLE = "slave"
        cfg = _persisted.copy() if _persisted else {}
        cfg["node_role"] = "slave"
        _save_persistent_config(cfg)
        SLAVE_NODES = _load_slave_nodes()
        logger.info("Slave setup complete")
        return jsonify({"success": True, "node_role": "slave"})

    # Master setup — requires admin account
    username = str(data.get("admin_username", "")).strip()
    password = str(data.get("admin_password", ""))

    if not username or len(username) < 3:
        return jsonify({"success": False, "error": "Username must be at least 3 characters."}), 400
    if not password or len(password) < 6:
        return jsonify({"success": False, "error": "Password must be at least 6 characters."}), 400

    # Load existing config if available
    _persisted = _load_persistent_config()
    SLAVE_NODES = _load_slave_nodes()

    config_loaded = bool(_persisted)
    s3_configured = bool(_persisted.get("s3_endpoint"))

    NODE_ROLE = "master"
    ADMIN_USERNAME = username
    ADMIN_PASSWORD_HASH = generate_password_hash(password)

    if _persisted:
        API_KEY = _persisted.get("api_key", API_KEY)
        MAIN_APP_URL = _persisted.get("main_app_url", MAIN_APP_URL)
        HW_ACCEL = _persisted.get("hw_accel", HW_ACCEL)
        try:
            MAX_CONCURRENT_JOBS = int(_persisted.get("max_concurrent_jobs", MAX_CONCURRENT_JOBS))
        except (ValueError, TypeError):
            pass
        S3_ENDPOINT = _persisted.get("s3_endpoint", S3_ENDPOINT)
        S3_ACCESS_KEY = _persisted.get("s3_access_key", S3_ACCESS_KEY)
        S3_SECRET_KEY = _persisted.get("s3_secret_key", S3_SECRET_KEY)
        S3_BUCKET = _persisted.get("s3_bucket", S3_BUCKET)
        S3_REGION = _persisted.get("s3_region", S3_REGION)
        S3_USE_SSL = str(_persisted.get("s3_use_ssl", S3_USE_SSL)).lower() in ("true", "1", "yes")
        S3_VERIFY_SSL = str(_persisted.get("s3_verify_ssl", S3_VERIFY_SSL)).lower() in ("true", "1", "yes")

    # Persist config with account and master role
    cfg = _persisted.copy() if _persisted else {}
    cfg["node_role"] = "master"
    cfg["admin_username"] = ADMIN_USERNAME
    cfg["admin_password_hash"] = ADMIN_PASSWORD_HASH
    _save_persistent_config(cfg)

    # Set session so subsequent setup steps (S3, API key) work without login
    session["logged_in"] = True

    response = {
        "success": True,
        "node_role": "master",
        "config_loaded": config_loaded,
        "s3_configured": s3_configured,
    }
    if config_loaded:
        response["config"] = {
            "s3_endpoint": S3_ENDPOINT or "",
            "s3_bucket": S3_BUCKET or "",
            "s3_region": S3_REGION or "us-east-1",
            "main_app_url": MAIN_APP_URL or "",
            "hw_accel": HW_ACCEL or "auto",
        }

    logger.info("Master setup complete — admin account created (config_loaded=%s)", config_loaded)
    return jsonify(response)


# Import redirect helper
from flask import redirect as flask_redirect


@app.route("/login")
def login_page():
    """Show the login page."""
    # If already logged in, go to dashboard
    if session.get("logged_in"):
        return flask_redirect("/")
    # If no admin account exists, go to setup
    if not ADMIN_USERNAME:
        return flask_redirect("/setup")
    return LOGIN_TEMPLATE, 200, {"Content-Type": "text/html"}


@app.route("/api/login", methods=["POST"])
def api_login():
    """Authenticate and create a session."""
    data = request.get_json(silent=True) or {}
    username = str(data.get("username", "")).strip()
    password = str(data.get("password", ""))

    if not ADMIN_USERNAME or not ADMIN_PASSWORD_HASH:
        return jsonify({"success": False, "error": "No account configured. Run setup first."}), 400

    if not secrets.compare_digest(username, ADMIN_USERNAME) or not check_password_hash(ADMIN_PASSWORD_HASH, password):
        return jsonify({"success": False, "error": "Invalid username or password."}), 401

    session["logged_in"] = True
    return jsonify({"success": True})


@app.route("/logout")
def logout():
    """Clear the session and redirect to login."""
    session.clear()
    return flask_redirect("/login")


@app.before_request
def _before_request():
    """Handle setup redirect and login enforcement."""
    allowed = ("/setup", "/api/setup", "/login", "/api/login", "/logout")
    if request.path in allowed or request.path.startswith("/static"):
        return None

    # No encryption key → need setup
    if _get_cipher_key() is None:
        if request.path.startswith("/api/"):
            return jsonify({"error": "Setup required", "setup_url": "/setup"}), 503
        return flask_redirect("/setup")

    # No admin account → need setup
    if not ADMIN_USERNAME:
        if request.path.startswith("/api/"):
            return jsonify({"error": "Setup required", "setup_url": "/setup"}), 503
        return flask_redirect("/setup")

    # Browser requests need login
    if not request.path.startswith("/api/"):
        if not session.get("logged_in"):
            return flask_redirect("/login")

    return None


# ---------------------------------------------------------------------------
# Web UI
# ---------------------------------------------------------------------------

@app.route("/")
def index():
    """Serve the companion dashboard UI."""
    return render_template("index.html")


@app.route("/history")
def history_page():
    """Serve the job history page."""
    return render_template("history.html")


@app.route("/settings")
def settings_page():
    """Serve the settings page."""
    return render_template("settings.html")


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
        except Exception as exc:
            logger.warning("S3 health check failed (endpoint=%s bucket=%s): %s", S3_ENDPOINT, S3_BUCKET, exc)

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
        "node_role": NODE_ROLE,
        "slave_node_count": len(SLAVE_NODES),
        "config_encrypted": _get_cipher_key() is not None,
    })


@app.route("/api/test", methods=["POST"])
def run_diagnostics():
    """Run active diagnostics to verify hardware acceleration and RustFS connectivity.

    Unlike /api/health (which only detects capabilities), this endpoint
    performs real tests:
      - hw_encode: encodes a short synthetic video clip using the configured
        hardware encoder to confirm it actually works.
      - rustfs: uploads, downloads, and deletes a small test file in the
        configured S3/RustFS bucket to confirm full read/write access.

    Returns JSON with a "tests" dict containing results for each test.
    """
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    results = {}

    # ── Hardware acceleration test ────────────────────────────────────────
    hw_test = {"passed": False, "encoder": None, "error": None}
    try:
        hw_info = _detect_hw_accel()
        encode_flags = _select_encoder(hw_info, "h264")
        encoder_name = "unknown"
        for i, flag in enumerate(encode_flags):
            if flag == "-c:v" and i + 1 < len(encode_flags):
                encoder_name = encode_flags[i + 1]
                break

        hw_test["encoder"] = encoder_name

        # Generate a 1-second silent test clip using lavfi sources
        test_dir = os.path.join(TEMP_DIR, "diag_" + str(uuid.uuid4())[:8])
        os.makedirs(test_dir, exist_ok=True)
        test_output = os.path.join(test_dir, "test.mp4")
        try:
            decode_flags = _hwaccel_decode_flags()
            cmd = [FFMPEG_PATH, "-y"] + decode_flags + [
                "-f", "lavfi", "-i", "color=c=black:s=320x240:d=1:r=25",
                "-f", "lavfi", "-i", "anullsrc=r=44100:cl=mono",
                "-t", "1",
            ] + encode_flags + [
                "-c:a", "aac", "-b:a", "64k",
                test_output,
            ]
            proc = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
            if proc.returncode == 0 and os.path.isfile(test_output) and os.path.getsize(test_output) > 0:
                hw_test["passed"] = True
            else:
                hw_test["error"] = (proc.stderr or "unknown error")[:500]
        finally:
            shutil.rmtree(test_dir, ignore_errors=True)
    except Exception as exc:
        hw_test["error"] = str(exc)[:500]

    results["hw_encode"] = hw_test

    # ── RustFS / S3 connectivity test ─────────────────────────────────────
    s3_test = {"passed": False, "upload": False, "download": False, "delete": False, "error": None}
    try:
        s3 = _get_s3_client()
        if not s3:
            s3_test["error"] = "S3/RustFS is not configured"
        else:
            test_key = "_companion_diag_test_" + str(uuid.uuid4())[:8] + ".txt"
            test_body = b"companion-diagnostic-ok"

            # Upload
            s3.put_object(Bucket=S3_BUCKET, Key=test_key, Body=test_body, ContentType="text/plain")
            s3_test["upload"] = True

            # Download and verify
            obj = s3.get_object(Bucket=S3_BUCKET, Key=test_key)
            data = obj["Body"].read()
            s3_test["download"] = (data == test_body)

            # Delete
            s3.delete_object(Bucket=S3_BUCKET, Key=test_key)
            s3_test["delete"] = True

            s3_test["passed"] = s3_test["upload"] and s3_test["download"] and s3_test["delete"]
    except Exception as exc:
        s3_test["error"] = str(exc)[:500]

    results["rustfs"] = s3_test

    # ── Main App connectivity test ────────────────────────────────────────
    main_app_test = {"passed": False, "url": MAIN_APP_URL or None, "error": None}
    if MAIN_APP_URL:
        # Validate URL scheme to prevent SSRF — only allow http/https
        from urllib.parse import urlparse
        parsed = urlparse(MAIN_APP_URL)
        if parsed.scheme not in ("http", "https") or not parsed.hostname:
            main_app_test["error"] = "Invalid main app URL — must be an http:// or https:// URL"
        else:
            try:
                test_url = parsed.scheme + "://" + parsed.netloc + "/api/v1/companion/ping"
                resp = http_requests.get(test_url, timeout=10, verify=False)  # noqa: S113
                main_app_test["status_code"] = resp.status_code
                main_app_test["passed"] = resp.status_code < 500
            except http_requests.exceptions.ConnectionError:
                main_app_test["error"] = "Connection refused — main app may be offline or URL is incorrect"
            except http_requests.exceptions.Timeout:
                main_app_test["error"] = "Connection timed out after 10 seconds"
            except Exception as exc:
                main_app_test["error"] = str(exc)[:500]
    else:
        main_app_test["error"] = "Main app URL is not configured"

    results["main_app"] = main_app_test

    all_passed = all(t.get("passed") for t in results.values())
    logger.info("Diagnostic tests completed: %s", "ALL PASSED" if all_passed else "SOME FAILED")
    return jsonify({"all_passed": all_passed, "tests": results})


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
        logger.error("Probe failed for %s: %s", source, exc)
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
            logger.error("Thumbnail generation failed for %s: %s", source, proc.stderr[:500])
            return jsonify({"error": f"Thumbnail generation failed: {proc.stderr[:500]}"}), 500
    except Exception as exc:
        logger.error("Thumbnail exception for %s: %s", source, exc)
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


@app.route("/api/logs", methods=["GET"])
def get_logs():
    """Return the most recent log entries from the persistent log file.

    Query params:
        lines  — number of lines to return (default 200, max 2000)
        level  — filter by minimum level: DEBUG, INFO, WARNING, ERROR (default INFO)
    """
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    max_lines = min(int(request.args.get("lines", 200)), 2000)
    level_filter = request.args.get("level", "").upper()

    log_path = os.path.join(os.environ.get("CONFIG_DIR", "/config"), "logs", "companion.log")
    if not os.path.isfile(log_path):
        return jsonify({"lines": [], "message": "Log file not found — logs are only on stdout"})

    try:
        with open(log_path, "r") as f:
            all_lines = f.readlines()

        # Tail the requested number of lines
        tail = all_lines[-max_lines:] if len(all_lines) > max_lines else all_lines

        # Optional level filter
        if level_filter in ("DEBUG", "INFO", "WARNING", "ERROR"):
            level_order = {"DEBUG": 0, "INFO": 1, "WARNING": 2, "ERROR": 3}
            min_level = level_order.get(level_filter, 0)
            filtered = []
            for line in tail:
                for lvl, order in level_order.items():
                    if f"[{lvl}]" in line and order >= min_level:
                        filtered.append(line.rstrip("\n"))
                        break
            tail = filtered
        else:
            tail = [l.rstrip("\n") for l in tail]

        return jsonify({"lines": tail, "total": len(all_lines), "returned": len(tail)})
    except Exception as exc:
        logger.error("Failed to read log file: %s", exc)
        return jsonify({"error": str(exc)}), 500


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
        "node_role": NODE_ROLE,
        "slave_node_count": len(SLAVE_NODES),
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
    global S3_USE_SSL, S3_VERIFY_SSL, NODE_ROLE
    global ADMIN_USERNAME, ADMIN_PASSWORD_HASH

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

    if "node_role" in data:
        role = str(data["node_role"]).lower()
        if role not in ("master", "slave"):
            return jsonify({"error": "node_role must be 'master' or 'slave'"}), 400
        NODE_ROLE = role
        updated.append("node_role")

    if "admin_username" in data:
        ADMIN_USERNAME = str(data["admin_username"])
        updated.append("admin_username")

    if "admin_password_hash" in data:
        ADMIN_PASSWORD_HASH = str(data["admin_password_hash"])
        updated.append("admin_password_hash")

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
        "node_role": NODE_ROLE,
        "slave_nodes": SLAVE_NODES,
        "admin_username": ADMIN_USERNAME,
        "admin_password_hash": ADMIN_PASSWORD_HASH,
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
# Node Management — master/slave distributed transcoding
# ---------------------------------------------------------------------------

@app.route("/api/nodes", methods=["GET"])
def list_nodes():
    """List all configured slave nodes with their current status."""
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    nodes_status = []
    for node in SLAVE_NODES:
        status = _check_slave_health(node)
        status["id"] = node.get("id", "")
        nodes_status.append(status)

    return jsonify({
        "node_role": NODE_ROLE,
        "slave_nodes": nodes_status,
    })


@app.route("/api/nodes", methods=["POST"])
def add_node():
    """Register a new slave node.

    POST JSON body:
        url:      Base URL of the slave companion server (required)
        api_key:  API key for the slave node (required)
        name:     Friendly name for the node (optional)
    """
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    global SLAVE_NODES

    data = request.get_json(silent=True) or {}
    node_url = str(data.get("url", "")).strip().rstrip("/")
    node_api_key = str(data.get("api_key", "")).strip()
    node_name = str(data.get("name", "")).strip() or node_url

    if not node_url:
        return jsonify({"error": "url is required"}), 400

    # Validate URL scheme
    from urllib.parse import urlparse
    parsed = urlparse(node_url)
    if parsed.scheme not in ("http", "https"):
        return jsonify({"error": "url must use http or https scheme"}), 400

    # Check for duplicate URLs
    for existing in SLAVE_NODES:
        if existing.get("url", "").rstrip("/") == node_url:
            return jsonify({"error": "A node with this URL is already registered"}), 409

    node_id = str(uuid.uuid4())
    new_node = {"id": node_id, "url": node_url, "api_key": node_api_key, "name": node_name}
    SLAVE_NODES.append(new_node)

    # Persist
    cfg = _load_persistent_config()
    cfg["slave_nodes"] = SLAVE_NODES
    _save_persistent_config(cfg)

    logger.info("Slave node added: %s (%s)", node_name, node_url)

    # Automatically sync settings to the new slave node
    sync_result = _sync_settings_to_slave(new_node)

    return jsonify({
        "success": True,
        "node": {"id": node_id, "url": node_url, "name": node_name},
        "settings_synced": sync_result,
    }), 201


@app.route("/api/nodes/<node_id>", methods=["DELETE"])
def remove_node(node_id):
    """Remove a slave node by its ID."""
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    global SLAVE_NODES

    original_len = len(SLAVE_NODES)
    SLAVE_NODES = [n for n in SLAVE_NODES if n.get("id") != node_id]

    if len(SLAVE_NODES) == original_len:
        return jsonify({"error": "Node not found"}), 404

    # Persist
    cfg = _load_persistent_config()
    cfg["slave_nodes"] = SLAVE_NODES
    _save_persistent_config(cfg)

    logger.info("Slave node removed: %s", node_id)
    return jsonify({"success": True, "removed": node_id})


def _get_master_settings_payload() -> dict:
    """Build the settings payload that the master pushes to slave nodes.

    Hardware acceleration (hw_accel) is intentionally excluded because slave
    nodes may have different GPU hardware and should configure this locally.
    Includes admin account so the same login works across all nodes.
    """
    return {
        "s3_endpoint": S3_ENDPOINT,
        "s3_access_key": S3_ACCESS_KEY,
        "s3_secret_key": S3_SECRET_KEY,
        "s3_bucket": S3_BUCKET,
        "s3_region": S3_REGION,
        "s3_use_ssl": S3_USE_SSL,
        "s3_verify_ssl": S3_VERIFY_SSL,
        "main_app_url": MAIN_APP_URL,
        "max_concurrent_jobs": MAX_CONCURRENT_JOBS,
        "admin_username": ADMIN_USERNAME,
        "admin_password_hash": ADMIN_PASSWORD_HASH,
    }


def _sync_settings_to_slave(node: dict) -> bool:
    """Push the master's settings to a slave node via its /api/config endpoint.

    Returns True if the slave accepted the settings, False otherwise.
    """
    url = node.get("url", "").rstrip("/")
    api_key = node.get("api_key", "")
    if not url:
        return False
    # Validate URL scheme to prevent SSRF to non-HTTP destinations
    from urllib.parse import urlparse
    parsed = urlparse(url)
    if parsed.scheme not in ("http", "https"):
        logger.warning("Slave sync URL rejected (invalid scheme): %s", url)
        return False
    try:
        headers = {"Content-Type": "application/json"}
        if api_key:
            headers["X-API-Key"] = api_key
        payload = _get_master_settings_payload()
        resp = http_requests.put(url + "/api/config", json=payload, headers=headers, timeout=10, verify=False)  # noqa: S501
        if resp.status_code == 200:
            logger.info("Settings synced to slave %s", url)
            return True
        else:
            logger.warning("Failed to sync settings to slave %s: %s", url, resp.status_code)
            return False
    except Exception as exc:
        logger.warning("Failed to sync settings to slave %s: %s", url, exc)
        return False


@app.route("/api/nodes/sync", methods=["POST"])
def sync_nodes():
    """Push the master's settings to all registered slave nodes.

    Called from the master's UI when the admin wants to push updated settings
    (e.g. after changing S3 credentials) to all slave nodes at once.
    """
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    if NODE_ROLE != "master":
        return jsonify({"error": "Only master nodes can sync settings to slaves"}), 400

    results = {}
    for node in SLAVE_NODES:
        node_name = node.get("name", node.get("url", "unknown"))
        results[node_name] = _sync_settings_to_slave(node)

    return jsonify({"synced": results})


@app.route("/api/nodes/pull-settings", methods=["GET"])
def pull_settings():
    """Return the master's settings so a slave can pull them.

    This endpoint is called by slave nodes to retrieve the master's
    configuration (S3, HW accel, etc.).  Requires API key authentication.
    """
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    return jsonify(_get_master_settings_payload())


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
        logger.info("No callback URL configured — skipping result notification for job %s", payload.get("job_id", "?"))
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

        logger.info("HLS job %s: downloading source %s", job_id, s3_source_key)
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

        logger.info("HLS job %s: uploading segments to S3 prefix %s", job_id, s3_output_prefix)
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

        logger.info("HLS job %s completed: %d variants, manifest=%s", job_id, len(variants), f"{output_prefix}/master.m3u8")

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
        logger.error("HLS transcode request rejected: S3/RustFS is not configured (endpoint=%s)", S3_ENDPOINT or "(empty)")
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

    # Node delegation: if this master node is busy and slave nodes are
    # configured, delegate the transcode to an available slave.
    if NODE_ROLE == "master" and SLAVE_NODES and _is_master_busy():
        slave = _get_available_slave()
        if slave:
            logger.info("Master busy — delegating HLS job to slave %s", slave.get("url"))
            slave_result = _delegate_to_slave(slave, data)
            if slave_result:
                return jsonify(slave_result), 202

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
# Presigned upload URLs
# ---------------------------------------------------------------------------
@app.route("/api/presign", methods=["POST"])
def presign_upload():
    """Generate a presigned PUT URL for direct browser-to-RustFS uploads.

    Uses boto3's generate_presigned_url (official SDK) so the signature is
    guaranteed to match what RustFS expects.

    POST JSON body:
        object_key:       S3 object key (required, e.g. 'Images/videos/athlete/file.mp4')
        content_type:     MIME type (optional, default 'application/octet-stream')
        expires:          URL validity in seconds (optional, default 3600)
        public_endpoint:  Browser-facing S3 base URL (optional). When the companion
                          talks to RustFS via an internal address (e.g. http://rustfs:9000)
                          but the browser must reach it via a public URL (e.g.
                          https://rustfs.example.com), pass the public URL here so the
                          presigned URL is reachable from the browser.
    """
    auth_err = _require_api_key()
    if auth_err:
        return auth_err

    s3 = _get_s3_client()
    if not s3:
        return jsonify({"success": False, "error": "S3/RustFS is not configured on companion server"}), 503

    data = request.get_json(silent=True) or {}
    object_key = data.get("object_key", "").strip()
    content_type = data.get("content_type", "application/octet-stream")
    public_endpoint = data.get("public_endpoint", "").strip()
    try:
        expires = int(data.get("expires", 3600))
    except (ValueError, TypeError):
        return jsonify({"success": False, "error": "expires must be a numeric value"}), 400

    if not object_key:
        return jsonify({"success": False, "error": "object_key is required"}), 400

    if expires < 60 or expires > 604800:
        expires = 3600

    try:
        # When a public_endpoint is provided, create a temporary client so the
        # presigned URL uses the browser-reachable host (and the signature is
        # computed against that host).
        client = s3
        if public_endpoint:
            ep = public_endpoint
            if not ep.startswith("http"):
                scheme = "https" if S3_USE_SSL else "http"
                ep = f"{scheme}://{ep}"
            client = boto3.client(
                "s3",
                endpoint_url=ep,
                aws_access_key_id=S3_ACCESS_KEY,
                aws_secret_access_key=S3_SECRET_KEY,
                region_name=S3_REGION,
                config=BotoConfig(
                    s3={"addressing_style": "path"},
                    signature_version="s3v4",
                    retries={"max_attempts": 1},
                ),
                verify=S3_VERIFY_SSL,
            )

        # Do NOT include ContentType in Params — RustFS / MinIO can return
        # SignatureDoesNotMatch when the browser's Content-Type header differs
        # even slightly from the value used at signing time.  Only sign the
        # host header (via Bucket/Key) which matches the PHP fallback behaviour.
        presigned_url = client.generate_presigned_url(
            ClientMethod="put_object",
            Params={
                "Bucket": S3_BUCKET,
                "Key": object_key,
            },
            ExpiresIn=expires,
        )
        logger.info("Generated presigned PUT URL for key=%s (expires=%ds, public_endpoint=%s)",
                     object_key, expires, public_endpoint or "(default)")
        return jsonify({
            "success": True,
            "url": presigned_url,
            "object_key": object_key,
            # Echoed back so the browser knows what Content-Type header to send
            # with the PUT request (not signed, but still useful metadata).
            "content_type": content_type,
        })
    except Exception as exc:
        logger.error("Presign error for key=%s: %s", object_key, exc)
        return jsonify({"success": False, "error": str(exc)}), 500


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
if __name__ == "__main__":
    print(f"Arctic Wolves Video Companion Server v{VERSION}")
    print(f"Hardware acceleration: {HW_ACCEL}")
    print(f"S3 endpoint: {S3_ENDPOINT or '(not configured)'}")
    print(f"Node role: {NODE_ROLE}")
    if NODE_ROLE == "master" and SLAVE_NODES:
        print(f"Slave nodes: {len(SLAVE_NODES)}")
    print(f"Config encrypted: {_get_cipher_key() is not None}")
    print(f"Listening on {COMPANION_HOST}:{COMPANION_PORT}")
    app.run(host=COMPANION_HOST, port=COMPANION_PORT, debug=False)
