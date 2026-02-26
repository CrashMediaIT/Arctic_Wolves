# Arctic Wolves Video Companion Server

Hardware-accelerated video encoding, decoding, clip extraction, and HLS transcoding with S3/RustFS integration.

## Quick Start

```bash
# 1. Clone or download the companion directory
cd companion

# 2. Copy the example environment file and edit it
cp .env.example .env
# Edit .env with your API key, S3/RustFS credentials, etc.

# 3. Start the container
docker compose up -d

# The dashboard is available at http://localhost:5100
```

> All settings (API key, hardware acceleration, S3/RustFS, HLS watcher) can also
> be configured from the **Settings** tab in the web UI at `http://localhost:5100`.

## GPU Acceleration

### NVIDIA (NVENC)

Requires the [NVIDIA Container Toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/install-guide.html).

```bash
docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d
```

### TrueNAS Scale (NVIDIA)

Enable **Install NVIDIA Drivers** in *Apps → Settings → Advanced Settings*, then:

```bash
docker compose -f docker-compose.yml -f docker-compose.truenas.yml up -d
```

### Intel QSV / VAAPI

Pass through the render device and set `HW_ACCEL`:

```bash
# In your .env or docker-compose override:
HW_ACCEL=vaapi
```

Then add to your compose or run command:

```yaml
services:
  companion:
    devices:
      - /dev/dri:/dev/dri
    environment:
      - HW_ACCEL=vaapi
```

## Building Locally

If you prefer to build the image yourself instead of pulling from GHCR:

```bash
cd companion
docker compose build
docker compose up -d
```

Or build and tag manually:

```bash
docker build -t arctic-wolves-companion:latest .
```

## Pushing to Docker Hub

If you want to host the image on Docker Hub under your own account:

```bash
# 1. Build the image
docker build -t arctic-wolves-companion:latest .

# 2. Tag for Docker Hub (replace YOUR_DOCKERHUB_USER)
docker tag arctic-wolves-companion:latest YOUR_DOCKERHUB_USER/arctic-wolves-companion:latest

# 3. Log in and push
docker login
docker push YOUR_DOCKERHUB_USER/arctic-wolves-companion:latest
```

Then update `docker-compose.yml`:

```yaml
services:
  companion:
    image: YOUR_DOCKERHUB_USER/arctic-wolves-companion:latest
```

## Configuration Reference

All settings can be provided via environment variables, a `.env` file, or the
web UI Settings page.

| Variable | Default | Description |
|---|---|---|
| `API_KEY` | *(empty)* | Shared authentication key |
| `HW_ACCEL` | `auto` | Hardware acceleration: `auto`, `nvenc`, `qsv`, `vaapi`, `amf`, `none` |
| `VIDEO_BASE_PATH` | `/videos` | Mount point for video storage |
| `MAX_CONCURRENT_JOBS` | `2` | Parallel encoding slots |
| `S3_ENDPOINT` | *(empty)* | S3/RustFS endpoint URL |
| `S3_ACCESS_KEY` | *(empty)* | S3 access key |
| `S3_SECRET_KEY` | *(empty)* | S3 secret key |
| `S3_BUCKET` | *(empty)* | S3 bucket name |
| `S3_REGION` | `us-east-1` | S3 region |
| `S3_USE_SSL` | `true` | Use HTTPS for S3 |
| `S3_VERIFY_SSL` | `false` | Verify S3 SSL certificate |
| `HLS_STAGING_PREFIX` | `Images/videos/` | S3 prefix for HLS staging watcher |
| `HLS_POLL_INTERVAL` | `30` | Staging watcher poll interval (seconds) |
| `COMPANION_PORT` | `5100` | Server listen port |
| `TZ` | `America/Toronto` | Timezone |

## Architecture

The companion server runs as a standalone container alongside the main Game Plan
application. It exposes a REST API on port 5100 and a web dashboard for
monitoring and configuration.

```
Game Plan App  ──►  Companion API (:5100)  ──►  FFmpeg (HW accel)
                          │                           │
                          ▼                           ▼
                     S3 / RustFS  ◄──────────  HLS segments
```
