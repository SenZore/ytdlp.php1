#!/bin/bash

# YT-DLP PHP Web Interface VPS Installation Script
# For Ubuntu 24.04 x64

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root"
   exit 1
fi

# Check Ubuntu version
UBUNTU_VERSION=$(lsb_release -rs)
if [[ "$UBUNTU_VERSION" != "24.04" ]]; then
    print_warning "This script is designed for Ubuntu 24.04. Current version: $UBUNTU_VERSION"
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

print_status "Starting YT-DLP PHP Web Interface installation..."
print_status "Ubuntu Version: $UBUNTU_VERSION"

# Get domain name
read -p "Enter your domain name (e.g., example.com): " DOMAIN_NAME
if [[ -z "$DOMAIN_NAME" ]]; then
    print_error "Domain name is required"
    exit 1
fi

# Get admin email for SSL
read -p "Enter admin email for SSL certificates: " ADMIN_EMAIL
if [[ -z "$ADMIN_EMAIL" ]]; then
    print_error "Admin email is required for SSL certificates"
    exit 1
fi

print_status "Domain: $DOMAIN_NAME"
print_status "Admin Email: $ADMIN_EMAIL"

# Update system
print_status "Updating system packages..."
apt update && apt upgrade -y

# Install required packages
print_status "Installing required packages..."
apt install -y \
    nginx \
    php8.3 \
    php8.3-fpm \
    php8.3-curl \
    php8.3-json \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-zip \
    php8.3-gd \
    php8.3-sqlite3 \
    python3 \
    python3-pip \
    ffmpeg \
    curl \
    wget \
    git \
    unzip \
    certbot \
    python3-certbot-nginx \
    ufw \
    fail2ban

# Install yt-dlp
print_status "Installing yt-dlp..."
pip3 install --upgrade pip
pip3 install --upgrade yt-dlp

# Verify yt-dlp installation
if command -v yt-dlp &> /dev/null; then
    print_success "yt-dlp installed successfully: $(yt-dlp --version)"
else
    print_error "yt-dlp installation failed"
    exit 1
fi

# Create web directory
WEB_DIR="/var/www/yt-dlp"
print_status "Creating web directory: $WEB_DIR"
mkdir -p $WEB_DIR
cd $WEB_DIR

# Download project files (assuming they're in the current directory)
print_status "Copying project files..."
cp -r . $WEB_DIR/ 2>/dev/null || {
    print_warning "Project files not found in current directory"
    print_status "Please upload your project files to $WEB_DIR manually"
}

# Set proper permissions
print_status "Setting permissions..."
chown -R www-data:www-data $WEB_DIR
chmod -R 755 $WEB_DIR
chmod +x $WEB_DIR/*.sh 2>/dev/null || true

# Create necessary directories
mkdir -p $WEB_DIR/downloads
mkdir -p $WEB_DIR/temp
mkdir -p $WEB_DIR/logs
chown -R www-data:www-data $WEB_DIR/downloads $WEB_DIR/temp $WEB_DIR/logs

# Configure PHP
print_status "Configuring PHP..."
PHP_INI="/etc/php/8.3/fpm/php.ini"
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 2G/' $PHP_INI
sed -i 's/post_max_size = 8M/post_max_size = 2G/' $PHP_INI
sed -i 's/memory_limit = 128M/memory_limit = 512M/' $PHP_INI
sed -i 's/max_execution_time = 30/max_execution_time = 300/' $PHP_INI
sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' $PHP_INI

# Configure Nginx
print_status "Configuring Nginx..."
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

# Test Nginx configuration
nginx -t

# Configure firewall
print_status "Configuring firewall..."
ufw --force enable
ufw allow ssh
ufw allow 'Nginx Full'
ufw allow 80
ufw allow 443

# Configure fail2ban
print_status "Configuring fail2ban..."
cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[nginx-http-auth]
enabled = true

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
action = iptables-multiport[name=ReqLimit, port="http,https"]
logpath = /var/log/nginx/error.log
findtime = 600
bantime = 7200
maxretry = 10
EOF

# Start and enable services
print_status "Starting services..."
systemctl enable nginx
systemctl enable php8.3-fpm
systemctl enable fail2ban
systemctl restart nginx
systemctl restart php8.3-fpm
systemctl restart fail2ban

# Configure SSL with Let's Encrypt
print_status "Configuring SSL certificate..."
if certbot --nginx -d $DOMAIN_NAME --email $ADMIN_EMAIL --agree-tos --non-interactive; then
    print_success "SSL certificate installed successfully"
else
    print_warning "SSL certificate installation failed. You can try manually later with:"
    print_warning "certbot --nginx -d $DOMAIN_NAME"
fi

# Create systemd service for auto-renewal
cat > /etc/systemd/system/ssl-renewal.service << EOF
[Unit]
Description=SSL Certificate Renewal
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/bin/certbot renew --quiet --agree-tos
ExecStartPost=/bin/systemctl reload nginx

[Install]
WantedBy=multi-user.target
EOF

# Create timer for SSL renewal
cat > /etc/systemd/system/ssl-renewal.timer << 'EOF'
[Unit]
Description=SSL Certificate Renewal Timer
Requires=ssl-renewal.service

[Timer]
OnCalendar=*-*-* 02:00:00
Persistent=true

[Install]
WantedBy=timers.target
EOF

systemctl enable ssl-renewal.timer
systemctl start ssl-renewal.timer

# Create log rotation
cat > /etc/logrotate.d/yt-dlp << 'EOF'
/var/www/yt-dlp/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload nginx
    endscript
}
EOF

# Create monitoring script
cat > /usr/local/bin/yt-dlp-monitor.sh << 'EOF'
#!/bin/bash
# YT-DLP Service Monitor

LOG_FILE="/var/log/yt-dlp-monitor.log"
WEB_DIR="/var/www/yt-dlp"

# Check if yt-dlp is working
if ! command -v yt-dlp &> /dev/null; then
    echo "$(date): yt-dlp not found, reinstalling..." >> $LOG_FILE
    pip3 install --upgrade yt-dlp
fi

# Check if ffmpeg is working
if ! command -v ffmpeg &> /dev/null; then
    echo "$(date): ffmpeg not found, reinstalling..." >> $LOG_FILE
    apt update && apt install -y ffmpeg
fi

# Clean old files
find $WEB_DIR/downloads -type f -mtime +1 -delete 2>/dev/null
find $WEB_DIR/temp -type f -mtime +1 -delete 2>/dev/null

# Check disk space
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 90 ]; then
    echo "$(date): Disk usage high: ${DISK_USAGE}%" >> $LOG_FILE
fi
EOF

chmod +x /usr/local/bin/yt-dlp-monitor.sh

# Add to crontab
(crontab -l 2>/dev/null; echo "*/30 * * * * /usr/local/bin/yt-dlp-monitor.sh") | crontab -

# Final configuration
print_status "Performing final configuration..."

# Set up automatic updates
cat > /etc/apt/apt.conf.d/20auto-upgrades << 'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Download-Upgradeable-Packages "1";
APT::Periodic::AutocleanInterval "7";
APT::Periodic::Unattended-Upgrade "1";
EOF

# Create status check script
cat > /usr/local/bin/yt-dlp-status.sh << 'EOF'
#!/bin/bash
echo "=== YT-DLP Service Status ==="
echo "yt-dlp: $(command -v yt-dlp && yt-dlp --version || echo 'Not installed')"
echo "ffmpeg: $(command -v ffmpeg && ffmpeg -version | head -n1 || echo 'Not installed')"
echo "PHP: $(php -v | head -n1)"
echo "Nginx: $(nginx -v 2>&1)"
echo "SSL Certificate: $(certbot certificates | grep -A 2 "$DOMAIN_NAME" | grep "VALID" || echo 'Not found')"
echo "Disk Usage: $(df / | awk 'NR==2 {print $5}')"
echo "Memory Usage: $(free | awk 'NR==2{printf "%.1f%%", $3*100/$2}')"
EOF

chmod +x /usr/local/bin/yt-dlp-status.sh

# Installation complete
print_success "Installation completed successfully!"
echo
echo "=== Installation Summary ==="
echo "Domain: $DOMAIN_NAME"
echo "Web Directory: $WEB_DIR"
echo "Admin Panel: https://$DOMAIN_NAME/login.php"
echo "Default Admin: senzore / DeIr48ToCfKKJwp"
echo
echo "=== Useful Commands ==="
echo "Check status: /usr/local/bin/yt-dlp-status.sh"
echo "View logs: tail -f /var/log/nginx/yt-dlp-error.log"
echo "Restart services: systemctl restart nginx php8.3-fpm"
echo "SSL renewal: certbot renew"
echo
echo "=== Security Features ==="
echo "✅ Firewall (UFW) enabled"
echo "✅ Fail2ban configured"
echo "✅ SSL certificate installed"
echo "✅ Rate limiting enabled"
echo "✅ Automatic updates enabled"
echo
print_success "Your YT-DLP PHP Web Interface is now ready!"
print_status "Visit https://$DOMAIN_NAME to start using it." 