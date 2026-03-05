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

    # Enable non-free repository components for the Intel oneVPL GPU
    # runtime (libmfx-gen) which is required for QSV encoding through
    # the libvpl dispatcher already bundled with FFmpeg.
    if [ -f /etc/apt/sources.list.d/debian.sources ] && ! grep -q 'non-free' /etc/apt/sources.list.d/debian.sources; then
        sed -i 's/^Components: main$/Components: main non-free non-free-firmware/' \
            /etc/apt/sources.list.d/debian.sources
    fi

    apt-get update

    # Core: Intel iHD VA-API driver + VA-API libraries
    apt-get install -y --no-install-recommends \
        intel-media-va-driver \
        vainfo \
        libva-drm2 \
        libva2

    # QSV: oneVPL GPU runtime — enables h264_qsv / hevc_qsv encoders
    # via the libvpl dispatcher already bundled with FFmpeg.  Installed
    # separately so a missing package does not block the VA-API install.
    apt-get install -y --no-install-recommends libmfx-gen1.2 2>/dev/null \
        || echo "Note: libmfx-gen1.2 not available — QSV will fall back to VAAPI"

    rm -rf /var/lib/apt/lists/*
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
    qsv)
        install_intel_gpu
        # Explicitly tell libva which driver to load.  In container
        # environments the auto-detection can fail even when the driver
        # package is installed, causing "No VA display found" errors.
        export LIBVA_DRIVER_NAME=iHD
        ;;
    vaapi) install_amd_gpu ;;
esac

# ---------------------------------------------------------------------------
# Grant the companion user access to ALL GPU render and card devices.
# The host GID that owns /dev/dri/* nodes is unlikely to match any group
# inside the container.  We detect each unique GID, create a matching
# group if needed, and add the companion user to it.
#
# BUG FIX: Previously used hard-coded names ("render", "video") which can
# collide with existing Debian groups at a *different* GID — silently
# adding the companion user to the wrong group.  Now uses unique names
# like "gpu_107" to avoid collisions.
# ---------------------------------------------------------------------------
_grant_dri_access() {
    local DEV="$1"
    local DEV_GID
    DEV_GID=$(stat -c '%g' "$DEV")
    # See if a group already exists with this GID
    local GRP
    GRP=$(getent group "$DEV_GID" | cut -d: -f1)
    if [ -z "$GRP" ]; then
        # No group with this GID — create one with a collision-safe name
        GRP="gpu_${DEV_GID}"
        groupadd -g "$DEV_GID" "$GRP" 2>/dev/null || true
    fi
    usermod -aG "$GRP" companion 2>/dev/null || true
    echo "Granted companion access to $DEV via group $GRP (GID $DEV_GID)"
}

for DRI_NODE in /dev/dri/renderD* /dev/dri/card*; do
    [ -c "$DRI_NODE" ] && _grant_dri_access "$DRI_NODE"
done

# Ensure the persistent /config volume is writable by the companion user.
# When a Docker volume or bind-mount is created, the directory may be owned
# by root — the build-time chown does not carry over.  Fix ownership here
# (while we are still root) so that SQLite, the config file, and the log
# directory can all be written after we drop privileges.
chown -R companion:companion /config 2>/dev/null \
    || echo "Warning: could not chown /config — companion user may lack write access"

# Drop to non-root user and exec the CMD
exec gosu companion "$@"
