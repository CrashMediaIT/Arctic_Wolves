# Arctic Wolves Video Companion Server

Hardware-accelerated video encoding, decoding, clip extraction, and HLS transcoding with S3/RustFS integration.

The companion is a **worker/integration service** for the main Arctic Wolves application. It receives transcoding jobs from the main app and reports results back via callbacks. Storage locations (where videos live, where HLS output goes) are controlled entirely by the main application.

## Quick Start

```bash
# 1. Start the container
cd companion
docker compose up -d

# 2. Visit http://localhost:5100 — the setup page appears on first run
#    Generate or paste an AES-256 encryption key (use the same key as
#    the main application for matching PII encryption)

# 3. Go to Settings → Generate API Key
# 4. Copy the key into the main app's Game Plan Settings → API Key
# 5. In the main app, click "Push RustFS to Companion" to send S3 credentials
```

> **No environment variables are required.** All settings (encryption key,
> API key, RustFS credentials, hardware acceleration, main app URL) are
> configured through the web UI and stored on the persistent Docker volume.
> The encryption key persists across container updates.  If the volume is
> lost, the setup page appears again on first start.

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

```bash
docker compose -f docker-compose.yml -f docker-compose.intel.yml up -d
```

Or set the environment variable manually (works on TrueNAS and other
platforms that only support environment variables — no build-args needed):

```yaml
services:
  companion:
    environment:
      - HW_ACCEL=qsv
    devices:
      - /dev/dri:/dev/dri
```

The entrypoint automatically installs the Intel Media Driver (iHD) and VA-API
libraries on first start when it sees `HW_ACCEL=qsv`.

### AMD VAAPI

```bash
docker compose -f docker-compose.yml -f docker-compose.amd.yml up -d
```

Or set the environment variable manually:

```yaml
services:
  companion:
    environment:
      - HW_ACCEL=vaapi
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

All settings are managed via the companion web UI and persisted on the
`/config` Docker volume.  No environment variables are needed.

On first start, the companion shows a setup page where you generate or enter
the encryption key.  After setup, all other settings are entered through the
Settings tab.

| Setting | Default | Description |
|---|---|---|
| Encryption Key | *(generated on setup)* | AES-256 key — entered on first-start setup page |
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
