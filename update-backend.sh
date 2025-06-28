#!/bin/bash

# YT-DLP Backend & Frontend Update Script
# This script updates backend/frontend files and ensures the PHP yt-dlp wrapper is installed

set -e

echo "🔄 Updating YT-DLP Backend & Frontend..."

# Set your GitHub repository details
GITHUB_USER="SenZore"
GITHUB_REPO="ytdlp.php1"
BRANCH="main"

# Base URL for raw GitHub files
BASE_URL="https://raw.githubusercontent.com/$GITHUB_USER/$GITHUB_REPO/$BRANCH"

# Files to update
FILES=("config.php" "download.php" "index.php")

# Backup directory
BACKUP_DIR="/var/www/yt-dlp/backup/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

echo "📦 Creating backup in $BACKUP_DIR"

# Backup current files
for file in "${FILES[@]}"; do
    if [ -f "/var/www/yt-dlp/$file" ]; then
        cp "/var/www/yt-dlp/$file" "$BACKUP_DIR/"
        echo "✅ Backed up $file"
    fi
done

# Download and update files
for file in "${FILES[@]}"; do
    echo "⬇️  Downloading $file..."
    if wget -q "$BASE_URL/$file" -O "/var/www/yt-dlp/$file"; then
        echo "✅ Updated $file"
    else
        echo "❌ Failed to update $file"
    fi
done

# Set proper permissions
chown www-data:www-data /var/www/yt-dlp/*.php
chmod 644 /var/www/yt-dlp/*.php

# Install/update the PHP yt-dlp wrapper
cd /var/www/yt-dlp
if [ ! -f composer.json ]; then
    echo '{"require":{}}' > composer.json
fi
COMPOSER_ALLOW_SUPERUSER=1 composer require norkunas/youtube-dl-php:dev-master --no-interaction

# Composer/PHP-FPM troubleshooting and self-test
if [ ! -f vendor/autoload.php ]; then
    echo "❌ Composer autoload.php missing! Run 'composer install' manually and check for errors."
    exit 1
fi

# Check for required PHP extensions
MISSING_EXTS=""
for ext in curl mbstring json; do
    php -m | grep -q $ext || MISSING_EXTS="$MISSING_EXTS $ext"
done
if [ -n "$MISSING_EXTS" ]; then
    echo "❌ Missing PHP extensions:$MISSING_EXTS"
    echo "Install with: sudo apt install php8.3-curl php8.3-mbstring php8.3-json"
    exit 1
fi

# Test Composer autoload in PHP
cat > test-composer.php <<EOF
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/vendor/autoload.php';
echo 'Composer autoload works!';
EOF
RESULT=$(php test-composer.php 2>&1)
rm -f test-composer.php
if [[ "$RESULT" != *"Composer autoload works!"* ]]; then
    echo "❌ Composer autoload test failed! Output: $RESULT"
    exit 1
else
    echo "✅ Composer autoload test passed."
fi

# Suggest increasing PHP-FPM max_children if needed
CONF_FILE="/etc/php/8.3/fpm/pool.d/www.conf"
if grep -q '^pm.max_children' "$CONF_FILE"; then
    echo "ℹ️  To increase PHP-FPM workers, edit $CONF_FILE and set pm.max_children = 10 (or higher), then run: sudo systemctl restart php8.3-fpm"
fi

# Return to previous directory
cd -

echo "🎉 Backend & frontend update completed!"
echo "📁 Backup saved in: $BACKUP_DIR"
echo "🔄 Restarting PHP-FPM..."
systemctl reload php8.3-fpm

echo "✅ Update complete! Your backend and frontend are now running the latest version with yt-dlp PHP wrapper."
echo "If you still see blank output, check /var/log/php8.3-fpm.log and /var/log/nginx/error.log for fatal errors." 
