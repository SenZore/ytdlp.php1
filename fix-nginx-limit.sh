#!/bin/bash

# Robust script to fix Nginx limit_req_zone misplacement
# Removes any line containing 'limit_req_zone' from all site configs
# Ensures the directive is only present in the http block of nginx.conf

SITE_CONFIGS=(/etc/nginx/sites-available/* /etc/nginx/sites-enabled/*)
NGINX_CONF="/etc/nginx/nginx.conf"
LIMIT_DIRECTIVE='limit_req_zone $binary_remote_addr zone=download:10m rate=10r/m;'

# Remove any line containing 'limit_req_zone' from all site configs
for f in "${SITE_CONFIGS[@]}"; do
  if [ -f "$f" ]; then
    if grep -q 'limit_req_zone' "$f"; then
      echo "[INFO] Removing all limit_req_zone lines from $f"
      # Remove all lines containing 'limit_req_zone'
      sed -i '/limit_req_zone/d' "$f"
    fi
  fi
done

# Ensure limit_req_zone is present in the http block of nginx.conf
if ! grep -q "$LIMIT_DIRECTIVE" "$NGINX_CONF"; then
  echo "[INFO] Adding limit_req_zone to http block in $NGINX_CONF"
  awk -v insert="$LIMIT_DIRECTIVE" '
    /http[ ]*{/ && !x { print; print "    " insert; x=1; next }1
  ' "$NGINX_CONF" > /tmp/nginx.conf.tmp && mv /tmp/nginx.conf.tmp "$NGINX_CONF"
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
