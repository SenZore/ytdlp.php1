#!/bin/bash

# Script to fix Nginx limit_req_zone misplacement
# Moves limit_req_zone from site configs to the http block in nginx.conf

SITE_CONFIGS=(/etc/nginx/sites-available/* /etc/nginx/sites-enabled/*)
NGINX_CONF="/etc/nginx/nginx.conf"
LIMIT_LINE='limit_req_zone $binary_remote_addr zone=download:10m rate=10r/m;'

# Remove limit_req_zone from all site configs
for f in "${SITE_CONFIGS[@]}"; do
  if grep -q "$LIMIT_LINE" "$f" 2>/dev/null; then
    echo "[INFO] Removing limit_req_zone from $f"
    sed -i "/$LIMIT_LINE/d" "$f"
  fi
done

# Add limit_req_zone to http block in nginx.conf if not present
if ! grep -q "$LIMIT_LINE" "$NGINX_CONF"; then
  echo "[INFO] Adding limit_req_zone to http block in $NGINX_CONF"
  # Insert after the first 'http {' line
  sed -i "/http {/a \\    $LIMIT_LINE" "$NGINX_CONF"
else
  echo "[INFO] limit_req_zone already present in $NGINX_CONF"
fi

# Test nginx config
if nginx -t; then
  echo "[SUCCESS] Nginx config is valid. Reloading nginx..."
  systemctl reload nginx
  echo "[DONE] Nginx reloaded successfully."
else
  echo "[ERROR] Nginx config test failed. Please check your config manually."
fi 
