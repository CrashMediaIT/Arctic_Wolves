FROM python:3.12-slim

LABEL maintainer="Crash Media IT"
LABEL description="Arctic Wolves Video Companion Server - Hardware-accelerated video processing"

# Install FFmpeg and gosu (for dropping root in entrypoint)
# GPU drivers (Intel QSV / AMD VA-API) are installed at runtime by
# entrypoint.sh based on the HW_ACCEL environment variable, so platforms
# like TrueNAS that cannot pass build-args still get GPU support.
RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg \
    gosu \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application
COPY app.py .
COPY job_store.py .
COPY templates/ templates/

# Create default directories
RUN mkdir -p /videos /tmp/companion /config

# Non-root user (entrypoint.sh drops to this user via gosu after any
# runtime GPU-driver installs that require root)
RUN groupadd -r companion && useradd -r -g companion -d /app companion
RUN chown -R companion:companion /app /tmp/companion /config

# Entrypoint installs GPU drivers at runtime based on HW_ACCEL env var,
# then drops to the companion user before executing the CMD.
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 5100

# The ENCRYPTION_KEY env var is recommended (64-char hex string).  If omitted,
# one is auto-generated on the persistent volume.  All other settings (admin
# account, API key, RustFS credentials, hardware acceleration) are configured
# through the web UI setup page on first start and stored on the persistent
# /config volume.

ENTRYPOINT ["/entrypoint.sh"]
CMD ["gunicorn", "--bind", "0.0.0.0:5100", "--workers", "1", "--threads", "4", "--timeout", "7200", "app:app"]
