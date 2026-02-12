# Arctic Wolves Video Companion Server

A backend companion application for hardware-accelerated video encoding, decoding, and clip extraction. Designed to work alongside the [Arctic Wolves Game Plan](https://github.com/CrashMediaIT/ACVideoReview) video review system at `gameplan.arcticwolves.ca`.

## Features

- **Hardware-Accelerated Encoding** — NVIDIA NVENC, Intel QSV, AMD AMF, and VAAPI support
- **Hardware-Accelerated Decoding** — GPU-based video decoding for faster processing
- **Clip Extraction** — Cut clips from source videos by time range with re-encoding or stream copy
- **Video Transcoding** — Convert between formats (MP4, MKV, MOV, WebM) with configurable quality
- **Thumbnail Generation** — Extract frame thumbnails at specified timestamps
- **Video Probe** — Retrieve metadata (duration, resolution, codecs, bitrate) from video files
- **REST API** — JSON-based API for all operations
- **Docker Support** — Run in Docker with optional GPU passthrough
- **NFS/SMB Mount Support** — Access video files on network shares accessible to both Game Plan and this server
- **Health Monitoring** — API endpoint reports hardware capabilities and server status

## Quick Start

### Docker (Recommended)

```bash
cd companion

# CPU-only
docker compose up -d

# With NVIDIA GPU support
docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d
```

### Manual Installation

```bash
cd companion
pip install -r requirements.txt

# Ensure FFmpeg is installed with hardware acceleration support
# Ubuntu/Debian: apt install ffmpeg
# With NVIDIA: install CUDA + NVENC-capable FFmpeg build

python app.py
```

## Configuration

Set environment variables or edit `.env`:

| Variable | Default | Description |
|---|---|---|
| `COMPANION_PORT` | `5100` | API listen port |
| `COMPANION_HOST` | `0.0.0.0` | API listen address |
| `VIDEO_BASE_PATH` | `/videos` | Base path for video file storage |
| `API_KEY` | *(required)* | Shared secret for API authentication |
| `HW_ACCEL` | `auto` | Hardware acceleration: `auto`, `nvenc`, `qsv`, `vaapi`, `amf`, `none` |
| `FFMPEG_PATH` | `ffmpeg` | Path to FFmpeg binary |
| `FFPROBE_PATH` | `ffprobe` | Path to FFprobe binary |
| `MAX_CONCURRENT_JOBS` | `2` | Maximum parallel encoding jobs |
| `TEMP_DIR` | `/tmp/companion` | Temporary working directory |

## API Endpoints

All endpoints require the `X-API-Key` header.

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/health` | Server status and hardware capabilities |
| `POST` | `/api/probe` | Get video file metadata |
| `POST` | `/api/clip` | Extract a clip from a source video |
| `POST` | `/api/transcode` | Transcode a video to a different format/codec |
| `POST` | `/api/thumbnail` | Generate a thumbnail at a given timestamp |
| `GET` | `/api/job/{id}` | Check job status |
| `DELETE` | `/api/job/{id}` | Cancel a running job |

### Example: Extract a Clip

```bash
curl -X POST http://localhost:5100/api/clip \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{
    "source": "uploads/videos/game_2024_01_15.mp4",
    "start_time": 120.5,
    "end_time": 135.0,
    "output": "clips/highlight_001.mp4",
    "hw_accel": true
  }'
```

## Video Storage (NFS/SMB)

Both the Game Plan app and the companion server should have access to the same video file storage. Mount the network share to the same path on both servers:

### NFS Mount
```bash
# On both Game Plan and Companion servers:
mount -t nfs nas.local:/volume1/videos /videos
```

### SMB/CIFS Mount
```bash
# On both servers:
mount -t cifs //nas.local/videos /videos -o username=user,password=pass,uid=911,gid=911
```

### Docker Volume Mount
In `docker-compose.yml`, mount your NFS/SMB share:
```yaml
volumes:
  - /your/nfs/mount:/videos
```

## License

Private — Arctic Wolves / Crash Media IT
