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

# Install dependencies
apt install -y nginx php8.3 php8.3-fpm php8.3-curl php8.3-json php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-sqlite3 python3 python3-pip ffmpeg curl wget git unzip certbot python3-certbot-nginx ufw fail2ban

# Install yt-dlp
pip3 install --upgrade yt-dlp

# Create web directory
mkdir -p /var/www/yt-dlp
cd /var/www/yt-dlp

# Download project files (replace with your actual GitHub repo URL)
echo "📥 Downloading project files..."
wget -qO- https://github.com/YOUR_USERNAME/yt-dlp-php/archive/main.tar.gz | tar -xz --strip-components=1

# Set permissions
chown -R www-data:www-data /var/www/yt-dlp
chmod -R 755 /var/www/yt-dlp
mkdir -p downloads temp logs
chown -R www-data:www-data downloads temp logs

# Configure PHP
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 2G/' /etc/php/8.3/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 2G/' /etc/php/8.3/fpm/php.ini
sed -i 's/memory_limit = 128M/memory_limit = 512M/' /etc/php/8.3/fpm/php.ini
sed -i 's/max_execution_time = 30/max_execution_time = 300/' /etc/php/8.3/fpm/php.ini

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

# Configure firewall
ufw --force enable
ufw allow ssh
ufw allow 'Nginx Full'

# Start services
systemctl enable nginx php8.3-fpm fail2ban
systemctl restart nginx php8.3-fpm fail2ban

# Install SSL certificate
certbot --nginx -d $DOMAIN_NAME --email $ADMIN_EMAIL --agree-tos --non-interactive

# Create monitoring script
cat > /usr/local/bin/yt-dlp-status.sh << 'EOF'
#!/bin/bash
echo "=== YT-DLP Service Status ==="
echo "yt-dlp: $(command -v yt-dlp && yt-dlp --version || echo 'Not installed')"
echo "ffmpeg: $(command -v ffmpeg && ffmpeg -version | head -n1 || echo 'Not installed')"
echo "PHP: $(php -v | head -n1)"
echo "Nginx: $(nginx -v 2>&1)"
echo "Disk Usage: $(df / | awk 'NR==2 {print $5}')"
echo "Memory Usage: $(free | awk 'NR==2{printf "%.1f%%", $3*100/$2}')"
EOF

chmod +x /usr/local/bin/yt-dlp-status.sh

echo "✅ Installation completed!"
echo "🌐 Visit: https://$DOMAIN_NAME"
echo "🔐 Admin: https://$DOMAIN_NAME/login.php"
echo "👤 Default admin: senzore / DeIr48ToCfKKJwp"
echo "📊 Check status: /usr/local/bin/yt-dlp-status.sh" 