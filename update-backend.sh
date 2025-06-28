#!/bin/bash

# YT-DLP Backend Update Script
echo "🔄 Updating YT-DLP Backend..."

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

echo "🎉 Backend update completed!"
echo "📁 Backup saved in: $BACKUP_DIR"
echo "🔄 Restarting PHP-FPM..."
systemctl reload php8.3-fpm

echo "✅ Update complete! Your backend is now running the latest version." 