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

ENV COMPANION_HOST=0.0.0.0
ENV COMPANION_PORT=5100
ENV VIDEO_BASE_PATH=/videos
ENV HW_ACCEL=auto
ENV MAX_CONCURRENT_JOBS=2
ENV TEMP_DIR=/tmp/companion

CMD ["gunicorn", "--bind", "0.0.0.0:5100", "--workers", "2", "--timeout", "7200", "app:app"]
