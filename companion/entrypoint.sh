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

# Drop to non-root user and exec the CMD
exec gosu companion "$@"
