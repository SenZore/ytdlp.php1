# YT-DLP PHP Web Interface

A modern, dark-themed web interface for downloading videos using yt-dlp with admin panel, server monitoring, and automatic SSL configuration.

## 🌟 Features

- **Modern Dark UI** - Beautiful gradient design with smooth animations
- **Video Info Preview** - Check video details before downloading
- **Multiple Format Support** - Choose from various video and audio qualities
- **Streaming Downloads** - Direct browser downloads without server storage
- **Admin Panel** - Server monitoring and configuration management
- **Rate Limiting** - Configurable download limits (admins bypass)
- **Server Status** - Real-time CPU, RAM, and service monitoring
- **Auto SSL** - Automatic Let's Encrypt certificate installation
- **Security** - Firewall, fail2ban, and security headers
- **Mobile Responsive** - Works perfectly on all devices

## 🚀 Quick VPS Installation

### Prerequisites
- Ubuntu 24.04 x64 VPS
- Root access
- Domain name pointing to your VPS
- Admin email for SSL certificates

### One-Command Installation

1. **Upload files to your VPS:**
   ```bash
   # Clone or upload your project files to the VPS
   git clone <your-repo-url>
   cd yt-dlp-php
   ```

2. **Run the installation script:**
   ```bash
   sudo chmod +x install-vps.sh
   sudo ./install-vps.sh
   ```

3. **Follow the prompts:**
   - Enter your domain name (e.g., `example.com`)
   - Enter admin email for SSL certificates

The script will automatically:
- Install all dependencies (PHP 8.3, Nginx, yt-dlp, ffmpeg)
- Configure web server and security
- Set up SSL certificates with Let's Encrypt
- Configure firewall and fail2ban
- Set up monitoring and auto-renewal
- Create admin panel

## 🔐 Admin Access

**Default Admin Credentials:**
- Username: `senzore`
- Password: `Id say nuh uh` ( Nah i decided to hide this :v )

**Admin Panel Features:**
- Server status monitoring (CPU, RAM, Disk, Services)
- Rate limit configuration
- Add new admin users
- Service status (yt-dlp, ffmpeg)
- System uptime and load average

## 📁 File Structure

```
/var/www/yt-dlp/
├── index.php              # Main web interface
├── download.php           # Download handler
├── admin.php              # Admin panel
├── login.php              # Admin login
├── config.php             # Configuration
├── install-vps.sh         # VPS installation script
├── downloads/             # Downloaded files (if any)
├── temp/                  # Temporary files
└── logs/                  # Application logs
```

## 🛠️ Configuration

### Rate Limiting
- Default: 10 downloads per hour per IP
- Admins bypass rate limiting
- Configurable via admin panel

### Supported Platforms
- YouTube
- Vimeo
- Dailymotion
- Twitch
- Bilibili
- And many more (via yt-dlp)

### File Size Limits
- Maximum upload: 2GB
- Memory limit: 512MB
- Execution timeout: 300 seconds

## 🔧 Management Commands

### Check Service Status
```bash
/usr/local/bin/yt-dlp-status.sh
```

### View Logs
```bash
# Application logs
tail -f /var/www/yt-dlp/logs/*.log

# Nginx logs
tail -f /var/log/nginx/yt-dlp-error.log
tail -f /var/log/nginx/yt-dlp-access.log

# System logs
journalctl -u nginx
journalctl -u php8.3-fpm
```

### Restart Services
```bash
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
```

### SSL Certificate Management
```bash
# Renew certificates
sudo certbot renew

# Check certificate status
sudo certbot certificates
```

### Update yt-dlp
```bash
sudo pip3 install --upgrade yt-dlp
```

## 🛡️ Security Features

- **Firewall (UFW)** - Only allows necessary ports
- **Fail2ban** - Protects against brute force attacks
- **Rate Limiting** - Prevents abuse
- **SSL/TLS** - Encrypted connections
- **Security Headers** - XSS protection, content security policy
- **File Access Control** - Protects sensitive files
- **Input Validation** - Sanitizes user inputs

## 📊 Monitoring

### Automatic Monitoring
- Service health checks every 30 minutes
- Automatic yt-dlp and ffmpeg reinstallation if missing
- Disk space monitoring
- Old file cleanup

### Manual Monitoring
```bash
# Check system resources
htop
df -h
free -h

# Check service status
systemctl status nginx
systemctl status php8.3-fpm
systemctl status fail2ban
```

## 🎨 Customization

### Theme Colors
Edit `config.php`:
```php
define('THEME_COLOR', '#00d4ff');      // Primary color
define('THEME_SECONDARY', '#ff6b6b');  // Secondary color
```

### Admin Credentials
Edit `config.php`:
```php
define('ADMIN_USERNAME', 'your_username');
define('ADMIN_PASSWORD', 'your_password');
```

### Rate Limits
Edit `config.php`:
```php
define('MAX_DOWNLOADS_PER_HOUR', 20);  // Downloads per hour
```

## 🐛 Troubleshooting

### Common Issues

**1. yt-dlp not working**
```bash
# Check if installed
which yt-dlp

# Reinstall if needed
sudo pip3 install --upgrade yt-dlp
```

**2. SSL certificate issues**
```bash
# Check certificate status
sudo certbot certificates

# Renew manually
sudo certbot renew --force-renewal
```

**3. Permission errors**
```bash
# Fix permissions
sudo chown -R www-data:www-data /var/www/yt-dlp
sudo chmod -R 755 /var/www/yt-dlp
```

**4. High disk usage**
```bash
# Clean old files
sudo find /var/www/yt-dlp/downloads -type f -mtime +1 -delete
sudo find /var/www/yt-dlp/temp -type f -mtime +1 -delete
```

### Log Locations
- Application: `/var/www/yt-dlp/logs/`
- Nginx: `/var/log/nginx/`
- PHP: `/var/log/php8.3-fpm.log`
- System: `journalctl -u nginx`

## 🔄 Updates

### Automatic Updates
The system is configured for automatic security updates.

### Manual Updates
```bash
# Update system packages
sudo apt update && sudo apt upgrade

# Update yt-dlp
sudo pip3 install --upgrade yt-dlp

# Update PHP packages
sudo apt install --only-upgrade php8.3*
```

## 📞 Support

If you encounter issues:

1. Check the logs mentioned above
2. Verify all services are running
3. Test yt-dlp manually: `yt-dlp --version`
4. Check disk space and permissions
5. Review firewall and fail2ban status

## 🎯 Easter Egg

Hover over "Made by Senz with Love" in the footer to see a special message! 😊

## 📄 License

© 2025 All Rights Reserved

---

**Made by Senz with Love** ❤️

*Built with modern web technologies and designed for optimal user experience.* 
