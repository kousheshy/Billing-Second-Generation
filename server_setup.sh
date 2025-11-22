#!/bin/bash

# ShowBox Billing Panel - Server Setup Script
# Run this script ON THE SERVER as root
# Version: 1.7.9

set -e  # Exit on error

echo "════════════════════════════════════════════════════════════════"
echo "  ShowBox Billing Panel - Server Setup"
echo "  Version: 1.7.9"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Configuration
WEB_DIR="/var/www/showbox"
DB_NAME="showboxt_panel"
DB_USER="showbox_user"
DB_PASS="ShowBox_2025_Secure"
DOMAIN="192.168.15.230"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
   echo "ERROR: Please run as root"
   exit 1
fi

echo "[1/11] Updating system packages..."
apt update -y > /dev/null 2>&1
echo "✓ System packages updated"
echo ""

echo "[2/11] Installing Apache..."
if ! command -v apache2 &> /dev/null; then
    DEBIAN_FRONTEND=noninteractive apt install -y apache2
fi
systemctl start apache2
systemctl enable apache2
echo "✓ Apache installed and started"
echo ""

echo "[3/11] Installing MySQL..."
if ! command -v mysql &> /dev/null; then
    DEBIAN_FRONTEND=noninteractive apt install -y mysql-server mysql-client
fi
systemctl start mysql
systemctl enable mysql
echo "✓ MySQL installed and started"
echo ""

echo "[4/11] Installing PHP and extensions..."
DEBIAN_FRONTEND=noninteractive apt install -y \
    php \
    php-mysql \
    php-cli \
    php-curl \
    php-json \
    php-mbstring \
    php-xml \
    libapache2-mod-php
echo "✓ PHP installed with all required extensions"
echo ""

echo "[5/11] Creating MySQL database and user..."
mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8 COLLATE utf8_general_ci;" 2>/dev/null || true
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';" 2>/dev/null || true
mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';" 2>/dev/null || true
mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true
echo "✓ Database: $DB_NAME"
echo "✓ User: $DB_USER"
echo "✓ Password: $DB_PASS"
echo ""

echo "[6/11] Creating web directory..."
mkdir -p $WEB_DIR
mkdir -p $WEB_DIR/logs
echo "✓ Directory created: $WEB_DIR"
echo ""

echo "[7/11] Configuring Apache virtual host..."
cat > /etc/apache2/sites-available/showbox.conf << VHOST
<VirtualHost *:80>
    ServerAdmin admin@showbox.local
    ServerName $DOMAIN
    DocumentRoot $WEB_DIR

    <Directory $WEB_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/showbox_error.log
    CustomLog \${APACHE_LOG_DIR}/showbox_access.log combined
</VirtualHost>
VHOST

a2dissite 000-default.conf > /dev/null 2>&1 || true
a2ensite showbox.conf > /dev/null 2>&1
a2enmod rewrite > /dev/null 2>&1
systemctl reload apache2
echo "✓ Apache virtual host configured"
echo ""

echo "[8/11] Setting directory permissions..."
chown -R www-data:www-data $WEB_DIR
chmod -R 755 $WEB_DIR
chmod -R 775 $WEB_DIR/logs
echo "✓ Permissions set"
echo ""

echo "[9/11] Configuring PHP..."
PHP_INI=$(php -i | grep "Loaded Configuration File" | awk '{print $5}')
if [ -f "$PHP_INI" ]; then
    sed -i 's/upload_max_filesize = .*/upload_max_filesize = 50M/' "$PHP_INI"
    sed -i 's/post_max_size = .*/post_max_size = 50M/' "$PHP_INI"
    sed -i 's/max_execution_time = .*/max_execution_time = 300/' "$PHP_INI"
    sed -i 's/memory_limit = .*/memory_limit = 256M/' "$PHP_INI"
    echo "✓ PHP configured"
else
    echo "⚠ PHP ini file not found, skipping PHP configuration"
fi
echo ""

echo "[10/11] Configuring firewall..."
if command -v ufw &> /dev/null; then
    ufw allow 22/tcp > /dev/null 2>&1 || true
    ufw allow 80/tcp > /dev/null 2>&1 || true
    ufw allow 443/tcp > /dev/null 2>&1 || true
    echo "✓ Firewall configured (SSH, HTTP, HTTPS allowed)"
else
    echo "⚠ UFW not installed, skipping firewall configuration"
fi
echo ""

echo "[11/11] Final checks..."
systemctl restart apache2
systemctl restart mysql
echo "✓ Services restarted"
echo ""

echo "════════════════════════════════════════════════════════════════"
echo "  ✅ SERVER SETUP COMPLETE!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "📊 Configuration Summary:"
echo "  • Web Directory: $WEB_DIR"
echo "  • Database Name: $DB_NAME"
echo "  • Database User: $DB_USER"
echo "  • Database Password: $DB_PASS"
echo "  • Server IP: $DOMAIN"
echo ""
echo "📝 Next Steps:"
echo "  1. Upload application files to: $WEB_DIR"
echo "  2. Update config.php with database credentials"
echo "  3. Run database migrations"
echo "  4. Access: http://$DOMAIN"
echo ""
echo "🔐 Important: Save these database credentials!"
echo ""
