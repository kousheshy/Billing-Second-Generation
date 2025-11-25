#!/bin/bash

# ShowBox Billing Panel - Automated Deployment Script
# Target: Ubuntu 22 Server at 192.168.15.230
# User: root

SERVER="192.168.15.230"
USER="root"
PASSWORD="kami1013"
WEB_DIR="/var/www/showbox"
DB_NAME="showboxt_panel"
DB_USER="showbox_user"
DB_PASS="ShowBox@2025!Secure"

echo "════════════════════════════════════════════════════════════════"
echo "  ShowBox Billing Panel - Deployment Starting"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Phase 1: Update system and install LAMP
echo "[Phase 1] Installing LAMP Stack..."
expect << EOF
spawn ssh -o StrictHostKeyChecking=no $USER@$SERVER
expect "password:"
send "$PASSWORD\r"
expect "# "

send "apt update -y\r"
expect "# "

send "DEBIAN_FRONTEND=noninteractive apt install -y apache2 mysql-server php php-mysql php-cli php-curl php-json php-mbstring php-xml libapache2-mod-php\r"
expect "# " timeout 300

send "systemctl start apache2\r"
expect "# "

send "systemctl enable apache2\r"
expect "# "

send "systemctl start mysql\r"
expect "# "

send "systemctl enable mysql\r"
expect "# "

send "php -v\r"
expect "# "

send "exit\r"
expect eof
EOF

echo "✓ LAMP Stack installed successfully"
echo ""

# Phase 2: Create database
echo "[Phase 2] Creating MySQL database..."
expect << EOF
spawn ssh $USER@$SERVER
expect "password:"
send "$PASSWORD\r"
expect "# "

send "mysql -e \"CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8 COLLATE utf8_general_ci;\"\r"
expect "# "

send "mysql -e \"CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';\"\r"
expect "# "

send "mysql -e \"GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';\"\r"
expect "# "

send "mysql -e \"FLUSH PRIVILEGES;\"\r"
expect "# "

send "exit\r"
expect eof
EOF

echo "✓ Database created: $DB_NAME"
echo "✓ User created: $DB_USER"
echo ""

# Phase 3: Create web directory
echo "[Phase 3] Creating web directory..."
expect << EOF
spawn ssh $USER@$SERVER
expect "password:"
send "$PASSWORD\r"
expect "# "

send "mkdir -p $WEB_DIR\r"
expect "# "

send "mkdir -p $WEB_DIR/logs\r"
expect "# "

send "exit\r"
expect eof
EOF

echo "✓ Web directory created: $WEB_DIR"
echo ""

echo "════════════════════════════════════════════════════════════════"
echo "  Phase 1-3 Complete! Ready for file upload."
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Next: Uploading files to server..."
