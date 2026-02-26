# Arctic Wolves Video Companion Server

Hardware-accelerated video encoding, decoding, clip extraction, and HLS transcoding with S3/RustFS integration.

The companion is a **worker/integration service** for the main Arctic Wolves application. It receives transcoding jobs from the main app and reports results back via callbacks. Storage locations (where videos live, where HLS output goes) are controlled entirely by the main application.

## Quick Start

```bash
# 1. Clone or download the companion directory
cd companion

# 2. Copy the example environment file and set your encryption key
cp .env.example .env
# Edit .env — set ENCRYPTION_KEY to match the main application

# 3. Start the container
docker compose up -d

# 4. Visit http://localhost:5100 → Settings → Generate API Key
# 5. Copy the key into the main app's Game Plan Settings → API Key
# 6. In the main app, click "Push RustFS to Companion" to send S3 credentials
```

> The **only** environment variable required is `ENCRYPTION_KEY`. All other
> settings (API key, RustFS credentials, hardware acceleration, main app URL)
> are configured through the **Settings** tab in the web UI at
> `http://localhost:5100` and stored in an AES-256-CBC encrypted config file
> on a persistent Docker volume.

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

Pass through the render device and set `HW_ACCEL` in the companion's Settings UI,
or add a compose override:

```yaml
services:
  companion:
    devices:
      - /dev/dri:/dev/dri
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

## Configuration Reference

All settings are managed via the companion **Settings** web UI and persisted in
an encrypted config file on the `/config` Docker volume. The only environment
variable is `ENCRYPTION_KEY`.

| Setting | Default | Description |
|---|---|---|
| API Key | *(generated)* | Generated in the companion; enter in the main app's Game Plan Settings |
| Main App URL | *(empty)* | URL of the main application (for transcode-complete callbacks) |
| Hardware Acceleration | `auto` | Method: `auto`, `nvenc`, `qsv`, `vaapi`, `amf`, `none` |
| Max Concurrent Jobs | `2` | Parallel encoding slots |
| S3 Endpoint | *(empty)* | RustFS endpoint URL (pushed from main app or entered manually) |
| S3 Access Key | *(empty)* | RustFS access key |
| S3 Secret Key | *(empty)* | RustFS secret key |
| S3 Bucket | *(empty)* | RustFS bucket name |
| S3 Region | `us-east-1` | RustFS region |
| S3 Use SSL | `true` | Use HTTPS for RustFS |
| S3 Verify SSL | `false` | Verify RustFS SSL certificate |

| Env Variable | Required | Description |
|---|---|---|
| `ENCRYPTION_KEY` | **Yes** | AES-256-CBC key (hex, 64 chars). Must match the main application. |

## Architecture

The companion server runs as a standalone container alongside the main Game Plan
application. The main app controls storage locations — it tells the companion
where each source file is and where to write the transcoded output. When
transcoding is complete, the companion calls back the main app to update the
database.

```
Game Plan App  ──►  Companion API (:5100)  ──►  FFmpeg (HW accel)
     │                     │                           │
     │  (upload location)  │                           ▼
     │  (output prefix)    │                     HLS segments
     │                     ▼                           │
     │               S3 / RustFS  ◄────────────────────┘
     │                     │
     ◄─────────────────────┘
       (callback: transcode complete + file locations)
```
