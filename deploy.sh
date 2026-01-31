#!/bin/bash

###############################################################################
# IBC-Intra Deployment Script
# 
# This script sets correct file permissions for production deployment:
# - Directories: 750 (owner: rwx, group: r-x, other: ---)
# - Files: 640 (owner: rw-, group: r--, other: ---)
# 
# Usage: ./deploy.sh
###############################################################################

set -e  # Exit on error

echo "=========================================="
echo "IBC-Intra Deployment Script"
echo "=========================================="
echo ""

# Get the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "[1/4] Setting directory permissions to 750..."
# Set all directories to 750
find . -type d -exec chmod 750 {} \;
echo "✓ Directory permissions set"

echo ""
echo "[2/4] Setting file permissions to 640..."
# Set all files to 640
find . -type f -exec chmod 640 {} \;
echo "✓ File permissions set"

echo ""
echo "[3/4] Making scripts executable..."
# Make shell scripts executable (deploy.sh already set to 750 by find command above)
# Just ensure it's executable in case permissions were too restrictive
chmod 750 deploy.sh
echo "✓ Scripts made executable"

echo ""
echo "[4/4] Setting special permissions for web server..."
# Ensure web server can write to logs directory
if [ -d "logs" ]; then
    chmod 770 logs
    find logs -type f -exec chmod 660 {} \;
    echo "✓ Logs directory permissions set (770 for directory, 660 for files)"
fi

# Ensure web server can write to upload directories if they exist
if [ -d "assets/uploads" ]; then
    chmod 770 assets/uploads
    find assets/uploads -type d -exec chmod 770 {} \;
    find assets/uploads -type f -exec chmod 660 {} \;
    echo "✓ Upload directory permissions set"
fi

# Ensure web server can write to session directory if it exists
if [ -d "sessions" ]; then
    chmod 770 sessions
    find sessions -type f -exec chmod 660 {} \;
    echo "✓ Sessions directory permissions set"
fi

echo ""
echo "=========================================="
echo "Deployment completed successfully!"
echo "=========================================="
echo ""
echo "Summary of permissions:"
echo "  - Directories: 750 (rwxr-x---)"
echo "  - Files: 640 (rw-r-----)"
echo "  - Logs directory: 770 (rwxrwx---)"
echo "  - Upload directories: 770 (rwxrwx---)"
echo "  - Writable files: 660 (rw-rw----)"
echo ""
echo "IMPORTANT: Ensure the web server user is in the same group"
echo "           as the owner of these files for proper operation."
echo ""
