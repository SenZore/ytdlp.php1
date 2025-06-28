#!/bin/bash

# YT-DLP PHP Web Interface All-in-One Installer for Ubuntu 24.04
# Run as root (sudo su), do NOT use sudo inside this script

set -e

# --- 1. Prompt for domain and admin email ---
echo "🚀 YT-DLP PHP Web Interface Installer"
read -p "Enter the URL (domain) where the website will be hosted (e.g., example.com): " DOMAIN_NAME
read -p "Enter admin email for SSL: " ADMIN_EMAIL

# --- 2. Show server public IP and check DNS ---
SERVER_IP=$(curl -s https://api.ipify.org)
echo "Detected server public IP: $SERVER_IP"
DOMAIN_IP=$(dig +short $DOMAIN_NAME | tail -n1)
if [[ -z "$DOMAIN_IP" ]]; then
  echo "❌ ERROR: Your domain ($DOMAIN_NAME) does not resolve to any IP address."
  exit 1
fi
if [[ "$SERVER_IP" != "$DOMAIN_IP" ]]; then
  echo "❌ ERROR: Your domain ($DOMAIN_NAME) does not point to this server's IP ($SERVER_IP)."
  echo "    Domain resolves to: $DOMAIN_IP"
  exit 1
else
  echo "✅ Domain $DOMAIN_NAME resolves correctly to this server ($SERVER_IP)."
fi

# --- 3. Remove any limit_req_zone from all site configs ---
SITE_CONFIGS=(/etc/nginx/sites-available/* /etc/nginx/sites-enabled/*)
for f in "${SITE_CONFIGS[@]}"; do
  if [ -f "$f" ]; then
    if grep -q 'limit_req_zone' "$f"; then
      echo "[INFO] Removing all limit_req_zone lines from $f"
      sed -i '/limit_req_zone/d' "$f"
    fi
  fi
done

# --- 4. Ensure limit_req_zone is only in http block of nginx.conf ---
NGINX_CONF="/etc/nginx/nginx.conf"
LIMIT_DIRECTIVE='limit_req_zone $binary_remote_addr zone=download:10m rate=10r/m;'
if ! grep -q "$LIMIT_DIRECTIVE" "$NGINX_CONF"; then
  echo "[INFO] Adding limit_req_zone to http block in $NGINX_CONF"
  awk -v insert="$LIMIT_DIRECTIVE" '/http[ ]*{/ && !x { print; print "    " insert; x=1; next }1' "$NGINX_CONF" > /tmp/nginx.conf.tmp && mv /tmp/nginx.conf.tmp "$NGINX_CONF"
else
  echo "[INFO] limit_req_zone already present in $NGINX_CONF"
fi

# --- 5. Install dependencies ---
apt update && apt upgrade -y
apt install -y software-properties-common lsb-release apt-transport-https ca-certificates
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y nginx php8.3 php8.3-fpm php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-sqlite3 python3 python3-pip ffmpeg curl wget git unzip certbot python3-certbot-nginx ufw fail2ban
pip3 install --upgrade yt-dlp

# --- 6. Create necessary directories ---
mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
mkdir -p /var/www/yt-dlp
cd /var/www/yt-dlp
mkdir -p downloads temp logs

# --- 7. Download project files ---
echo "📥 Downloading project files..."
wget -qO- https://github.com/SenZore/ytdlp.php1/archive/main.tar.gz | tar -xz --strip-components=1

# --- 8. Set permissions ---
chown -R www-data:www-data /var/www/yt-dlp
chmod -R 755 /var/www/yt-dlp
chown -R www-data:www-data downloads temp logs

# --- 9. Configure PHP ---
PHP_INI="/etc/php/8.3/fpm/php.ini"
if [ -f "$PHP_INI" ]; then
  sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 2G/' $PHP_INI
  sed -i 's/post_max_size = 8M/post_max_size = 2G/' $PHP_INI
  sed -i 's/memory_limit = 128M/memory_limit = 512M/' $PHP_INI
  sed -i 's/max_execution_time = 30/max_execution_time = 300/' $PHP_INI
fi

# --- 10. Configure Nginx site ---
cat > /etc/nginx/sites-available/yt-dlp << EOF
server {
    listen 80;
    server_name $DOMAIN_NAME;
    root /var/www/yt-dlp;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Rate limiting
    limit_req zone=download burst=20 nodelay;

    client_max_body_size 2G;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /(config\.php|\.env|\.htaccess) {
        deny all;
    }

    # Cache static files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Download directory protection
    location /downloads/ {
        deny all;
    }

    # Logs
    access_log /var/log/nginx/yt-dlp-access.log;
    error_log /var/log/nginx/yt-dlp-error.log;
}
EOF

ln -sf /etc/nginx/sites-available/yt-dlp /etc/nginx/sites-enabled/yt-dlp
rm -f /etc/nginx/sites-enabled/default

# --- 11. Configure firewall ---
ufw --force enable
ufw allow ssh
ufw allow 80
ufw allow 443

# --- 12. Test and reload Nginx ---
echo "[INFO] Testing Nginx config..."
if nginx -t; then
  echo "[SUCCESS] Nginx config is valid. Reloading nginx..."
  systemctl restart nginx || systemctl start nginx
  echo "[DONE] Nginx reloaded successfully."
else
  echo "[ERROR] Nginx config test failed. Please check your config manually."
  exit 1
fi

# --- 13. SSL with Certbot ---
echo "[INFO] Verifying SSL setup..."
if command -v certbot &> /dev/null; then
  certbot --nginx -d $DOMAIN_NAME --email $ADMIN_EMAIL --agree-tos --non-interactive || \
    echo "[WARNING] Certbot failed. You may need to run it manually."
else
  echo "[WARNING] certbot not found, skipping SSL setup."
fi

# --- 14. Create monitoring script ---
cat > /usr/local/bin/yt-dlp-status.sh << 'EOF'
#!/bin/bash
echo "=== YT-DLP Service Status ==="
echo "yt-dlp: $(command -v yt-dlp && yt-dlp --version || echo 'Not installed')"
echo "ffmpeg: $(command -v ffmpeg && ffmpeg -version | head -n1 || echo 'Not installed')"
echo "PHP: $(php -v | head -n1)"
echo "Nginx: $(nginx -v 2>&1)"
echo "Disk Usage: $(df / | awk 'NR==2 {print $5}')"
echo "Memory Usage: $(free | awk 'NR==2{printf \"%.1f%%\", $3*100/$2}')"
EOF
chmod +x /usr/local/bin/yt-dlp-status.sh

# --- 15. Final output ---
echo "✅ Installation completed!"
echo "🌐 Visit: https://$DOMAIN_NAME"
echo "🔐 Admin: https://$DOMAIN_NAME/login.php"
echo "👤 Default admin: senzore / DeIr48ToCfKKJwp"
echo "📊 Check status: /usr/local/bin/yt-dlp-status.sh" 
