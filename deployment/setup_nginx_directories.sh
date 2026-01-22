#!/bin/bash
# Setup script for NGINX directories
# Creates required directories for nginx operation in linuxserver/nginx container
#
# This script should be run INSIDE the nginx container after it starts
# to ensure all required directories exist.
#
# Usage:
#   docker exec nginx /config/www/Arctic_Wolves/deployment/setup_nginx_directories.sh

echo "=========================================="
echo "NGINX Directory Setup"
echo "=========================================="
echo ""

# This script must run inside the container where /config is mounted
if [ ! -d "/config" ]; then
    echo "❌ Error: /config directory not found"
    echo "   This script must be run inside the linuxserver/nginx container"
    echo "   Usage: docker exec nginx /config/www/Arctic_Wolves/deployment/setup_nginx_directories.sh"
    exit 1
fi

echo "✓ Running inside nginx container"
echo ""

# Create required directories
echo "Creating NGINX directories..."

directories=(
    "/config/nginx/log"
    "/config/nginx/client_body_temp"
    "/config/nginx/ssl"
)

created_count=0
exists_count=0

for dir in "${directories[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        echo "  ✓ Created: $dir"
        created_count=$((created_count + 1))
    else
        echo "  ✓ Exists: $dir"
        exists_count=$((exists_count + 1))
    fi
done

echo ""
echo "Summary: $created_count created, $exists_count already existed"
echo ""

# Set proper permissions for abc user (what PHP-FPM runs as)
echo "Setting permissions..."
chown -R abc:abc /config/nginx/log 2>/dev/null || echo "  ⚠️  Warning: Could not set ownership (may already be correct)"
chmod 755 /config/nginx/log 2>/dev/null || echo "  ⚠️  Warning: Could not set permissions (may already be correct)"
chown -R abc:abc /config/nginx/client_body_temp 2>/dev/null || true
chmod 755 /config/nginx/client_body_temp 2>/dev/null || true
echo "✓ Permissions set"
echo ""

# Verify directories
echo "Verifying directories..."
all_good=true
for dir in "${directories[@]}"; do
    if [ -d "$dir" ] && [ -w "$dir" ]; then
        echo "  ✓ OK: $dir (writable)"
    elif [ -d "$dir" ]; then
        echo "  ⚠️  Warning: $dir (exists but not writable)"
        all_good=false
    else
        echo "  ❌ Error: $dir (does not exist)"
        all_good=false
    fi
done

echo ""
echo "=========================================="
if [ "$all_good" = true ]; then
    echo "✅ Setup Complete - All directories ready"
    echo ""
    echo "NGINX can now write logs to:"
    echo "  - /config/nginx/log/arctic_wolves_access.log"
    echo "  - /config/nginx/log/arctic_wolves_error.log"
else
    echo "⚠️  Setup Complete - Some issues detected"
    echo "   Check permissions or run with appropriate user"
fi
echo "=========================================="
echo ""

