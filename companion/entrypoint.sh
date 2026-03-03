#!/bin/bash
set -e

# ---------------------------------------------------------------------------
# Runtime GPU driver installer
# ---------------------------------------------------------------------------
# Platforms like TrueNAS Scale cannot pass Docker build-args, so GPU driver
# packages are installed at container start based on the HW_ACCEL environment
# variable.  If the required packages are already present (e.g. baked into a
# custom image) the install step is skipped automatically.
#
#   HW_ACCEL=qsv    → Intel Media Driver (iHD) + VA-API libs  (QSV & VA-API)
#   HW_ACCEL=vaapi  → Mesa VA-API driver                      (AMD VA-API)
#   HW_ACCEL=nvenc  → No extra packages needed (NVIDIA driver comes from host)
# ---------------------------------------------------------------------------

install_intel_gpu() {
    if dpkg -s intel-media-va-driver >/dev/null 2>&1; then
        echo "Intel GPU drivers already installed — skipping."
        return
    fi
    echo "HW_ACCEL=qsv detected — installing Intel QSV / VA-API drivers …"
    apt-get update && apt-get install -y --no-install-recommends \
        intel-media-va-driver \
        vainfo \
        libva-drm2 \
        libva2 \
    && rm -rf /var/lib/apt/lists/*
}

install_amd_gpu() {
    if dpkg -s mesa-va-drivers >/dev/null 2>&1; then
        echo "AMD GPU drivers already installed — skipping."
        return
    fi
    echo "HW_ACCEL=vaapi detected — installing AMD VA-API drivers …"
    apt-get update && apt-get install -y --no-install-recommends \
        mesa-va-drivers \
        vainfo \
        libva-drm2 \
        libva2 \
    && rm -rf /var/lib/apt/lists/*
}

case "${HW_ACCEL}" in
    qsv)   install_intel_gpu ;;
    vaapi) install_amd_gpu   ;;
esac

# ---------------------------------------------------------------------------
# Grant the companion user access to the GPU render device.
# The host GID that owns /dev/dri/renderD128 (or the configured device) is
# unlikely to match any group inside the container.  We detect it, create
# a matching group if needed, and add the companion user to it.
# ---------------------------------------------------------------------------
RENDER_DEVICE="${HW_ACCEL_DEVICE:-/dev/dri/renderD128}"

if [ -c "$RENDER_DEVICE" ]; then
    RENDER_GID=$(stat -c '%g' "$RENDER_DEVICE")
    # Find or create a group with that GID
    RENDER_GROUP=$(getent group "$RENDER_GID" | cut -d: -f1)
    if [ -z "$RENDER_GROUP" ]; then
        RENDER_GROUP="render"
        groupadd -g "$RENDER_GID" "$RENDER_GROUP" 2>/dev/null || true
    fi
    usermod -aG "$RENDER_GROUP" companion 2>/dev/null || true
    echo "Added companion user to group $RENDER_GROUP (GID $RENDER_GID) for $RENDER_DEVICE"
fi

# Also grant access to /dev/dri/card0 if present (owned by the video group)
if [ -c /dev/dri/card0 ]; then
    CARD_GID=$(stat -c '%g' /dev/dri/card0)
    CARD_GROUP=$(getent group "$CARD_GID" | cut -d: -f1)
    if [ -z "$CARD_GROUP" ]; then
        CARD_GROUP="video"
        groupadd -g "$CARD_GID" "$CARD_GROUP" 2>/dev/null || true
    fi
    usermod -aG "$CARD_GROUP" companion 2>/dev/null || true
    echo "Added companion user to group $CARD_GROUP (GID $CARD_GID) for /dev/dri/card0"
fi

# Drop to non-root user and exec the CMD
exec gosu companion "$@"
