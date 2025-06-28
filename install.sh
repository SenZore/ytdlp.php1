#!/bin/bash

# One-liner installation script for YT-DLP PHP Web Interface
# Run this on your Ubuntu 24.04 VPS as root

echo "🚀 Installing YT-DLP PHP Web Interface..."

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo "❌ This script must be run as root"
   exit 1
fi

# Get domain and email
read -p "Enter your domain name (e.g., example.com): " DOMAIN_NAME
read -p "Enter admin email for SSL: " ADMIN_EMAIL

# Update system
apt update && apt upgrade -y

# Install core tools
apt install -y software-properties-common lsb-release apt-transport-https ca-certificates

# Add PHP repository for latest PHP
add-apt-repository ppa:ondrej/php -y
apt update

# Install PHP and extensions (php8.3-json is not needed)
apt install -y nginx php8.3 php8.3-fpm php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-sqlite3

# Install Python, pip, and yt-dlp
apt install -y python3 python3-pip
pip3 install --upgrade yt-dlp

# Install other dependencies
apt install -y ffmpeg curl wget git unzip certbot python3-certbot-nginx ufw fail2ban

# Check and create Nginx directories if missing
mkdir -p /etc/nginx/sites-available
mkdir -p /etc/nginx/sites-enabled

# Create web directory
mkdir -p /var/www/yt-dlp
cd /var/www/yt-dlp

# Download project files from SenZore's repository
echo "📥 Downloading project files..."
wget -qO- https://github.com/SenZore/ytdlp.php1/archive/main.tar.gz | tar -xz --strip-components=1

# Set permissions
chown -R www-data:www-data /var/www/yt-dlp
chmod -R 755 /var/www/yt-dlp
mkdir -p downloads temp logs
chown -R www-data:www-data downloads temp logs

# Configure PHP
PHP_INI="/etc/php/8.3/fpm/php.ini"
if [ -f "$PHP_INI" ]; then
  sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 2G/' $PHP_INI
  sed -i 's/post_max_size = 8M/post_max_size = 2G/' $PHP_INI
  sed -i 's/memory_limit = 128M/memory_limit = 512M/' $PHP_INI
  sed -i 's/max_execution_time = 30/max_execution_time = 300/' $PHP_INI
fi

# Configure Nginx
cat > /etc/nginx/sites-available/yt-dlp << 'EOF'
server {
    listen 80;
    server_name _;
    root /var/www/yt-dlp;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=download:10m rate=10r/m;
    limit_req zone=download burst=20 nodelay;

    # File upload size
    client_max_body_size 2G;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
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

# Enable site
ln -sf /etc/nginx/sites-available/yt-dlp /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Configure firewall (allow ports manually if Nginx Full profile is missing)
ufw --force enable
ufw allow ssh
ufw allow 80
ufw allow 443

# Start and enable services if installed
for svc in nginx php8.3-fpm fail2ban; do
  if systemctl list-unit-files | grep -q "${svc}.service"; then
    systemctl enable $svc
    systemctl restart $svc
  else
    echo "[WARNING] $svc.service not found, skipping."
  fi
done

# Check if domain resolves to this server's public IP
SERVER_IP=$(curl -s https://api.ipify.org)
DOMAIN_IP=$(dig +short $DOMAIN_NAME | tail -n1)

if [[ "$SERVER_IP" != "$DOMAIN_IP" ]]; then
  echo "❌ ERROR: Your domain ($DOMAIN_NAME) does not point to this server's IP ($SERVER_IP)."
  echo "    Domain resolves to: $DOMAIN_IP"
  echo "    Please update your DNS A record and try again."
  exit 1
else
  echo "✅ Domain $DOMAIN_NAME resolves correctly to this server ($SERVER_IP)."
fi

# Install SSL certificate if certbot is available
if command -v certbot &> /dev/null; then
  certbot --nginx -d $DOMAIN_NAME --email $ADMIN_EMAIL --agree-tos --non-interactive || \
    echo "[WARNING] Certbot failed. You may need to run it manually."
else
  echo "[WARNING] certbot not found, skipping SSL setup."
fi

# Create monitoring script
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

echo "✅ Installation completed!"
echo "🌐 Visit: https://$DOMAIN_NAME"
echo "🔐 Admin: https://$DOMAIN_NAME/login.php"
echo "👤 Default admin: senzore / DeIr48ToCfKKJwp"
echo "📊 Check status: /usr/local/bin/yt-dlp-status.sh" 
