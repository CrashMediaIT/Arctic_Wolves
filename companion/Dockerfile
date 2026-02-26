FROM python:3.12-slim

LABEL maintainer="Crash Media IT"
LABEL description="Arctic Wolves Video Companion Server - Hardware-accelerated video processing"

# Install FFmpeg with hardware acceleration support
RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application
COPY app.py .
COPY templates/ templates/

# Create default directories
RUN mkdir -p /videos /tmp/companion /config

# Non-root user
RUN groupadd -r companion && useradd -r -g companion -d /app companion
RUN chown -R companion:companion /app /tmp/companion /config
USER companion

EXPOSE 5100

# Only ENCRYPTION_KEY is required at runtime (set via .env file).
# All other settings are managed in the companion web UI and persisted
# in the encrypted config file on the /config volume.

CMD ["gunicorn", "--bind", "0.0.0.0:5100", "--workers", "2", "--timeout", "7200", "app:app"]
