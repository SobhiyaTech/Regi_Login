#!/bin/bash
# AWS EC2 Deployment Script for GUVI Login/Register App
# Run this script on your EC2 instance after uploading the code

set -e  # Exit on error

echo "========================================================"
echo "  GUVI App - AWS EC2 Setup Script"
echo "========================================================"
echo ""

# Update system packages
echo "📦 Updating system packages..."
sudo apt update && sudo apt upgrade -y

# Install Apache2
echo "🌐 Installing Apache2..."
sudo apt install -y apache2

# Install PHP 8.x and required extensions
echo "🐘 Installing PHP and extensions..."
sudo apt install -y php php-cli php-fpm php-mysql php-redis php-mongodb php-curl php-xml php-mbstring php-zip libapache2-mod-php

# Install MySQL
echo "🗄️  Installing MySQL..."
sudo apt install -y mysql-server

# Install Redis
echo "⚡ Installing Redis..."
sudo apt install -y redis-server

# Install MongoDB
echo "🍃 Installing MongoDB..."
wget -qO - https://www.mongodb.org/static/pgp/server-6.0.asc | sudo apt-key add -
echo "deb [ arch=amd64,arm64 ] https://repo.mongodb.org/apt/ubuntu $(lsb_release -cs)/mongodb-org/6.0 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-6.0.list
sudo apt update
sudo apt install -y mongodb-org

# Start and enable services
echo "🚀 Starting services..."
sudo systemctl start apache2
sudo systemctl enable apache2
sudo systemctl start mysql
sudo systemctl enable mysql
sudo systemctl start redis-server
sudo systemctl enable redis-server
sudo systemctl start mongod
sudo systemctl enable mongod

# Configure Apache
echo "⚙️  Configuring Apache..."
sudo a2enmod rewrite
sudo a2enmod headers

# Create Apache virtual host config
sudo tee /etc/apache2/sites-available/guvi-app.conf > /dev/null <<'EOF'
<VirtualHost *:80>
    ServerAdmin admin@localhost
    DocumentRoot /var/www/guvi-app
    
    <Directory /var/www/guvi-app>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Enable pretty URLs
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteBase /
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^(.*)$ index.php [QSA,L]
        </IfModule>
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/guvi-app-error.log
    CustomLog ${APACHE_LOG_DIR}/guvi-app-access.log combined
</VirtualHost>
EOF

# Create application directory
echo "📁 Setting up application directory..."
sudo mkdir -p /var/www/guvi-app
sudo chown -R $USER:www-data /var/www/guvi-app
sudo chmod -R 755 /var/www/guvi-app

# Copy application files (assumes script is run from project root)
echo "📋 Copying application files..."
if [ -d "public" ]; then
    sudo cp -r ./* /var/www/guvi-app/
    sudo chown -R www-data:www-data /var/www/guvi-app
    sudo chmod -R 755 /var/www/guvi-app
fi

# Setup MySQL database
echo "🗄️  Setting up MySQL database..."
sudo mysql <<EOF
CREATE DATABASE IF NOT EXISTS guvi_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'guvi'@'localhost' IDENTIFIED WITH mysql_native_password BY 'Guvi@2024@Secure!';
GRANT ALL ON guvi_app.* TO 'guvi'@'localhost';
FLUSH PRIVILEGES;
EOF

# Load database schema
if [ -f "php/db/schema.sql" ]; then
    echo "📊 Loading database schema..."
    sudo mysql guvi_app < php/db/schema.sql
fi

# Initialize MongoDB
echo "🍃 Initializing MongoDB..."
mongosh --eval '
use guvi_app;
db.createCollection("profiles");
db.profiles.createIndex({ "user_id": 1 }, { unique: true });
db.profiles.createIndex({ "updated_at": -1 });
print("✓ MongoDB initialized");
'

# Create environment configuration file
echo "⚙️  Creating environment configuration..."
sudo tee /var/www/guvi-app/.env > /dev/null <<EOF
# MySQL Configuration
MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_USER=guvi
MYSQL_PASSWORD=Guvi@2024@Secure!
MYSQL_DB=guvi_app

# Redis Configuration
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_DB=0
SESSION_TTL=604800

# MongoDB Configuration
MONGO_URI=mongodb://localhost:27017
MONGO_DB=guvi_app
MONGO_COLLECTION=profiles
EOF

# Load environment variables in Apache
sudo tee -a /etc/apache2/envvars > /dev/null <<'EOF'

# GUVI App Environment Variables
export MYSQL_HOST=localhost
export MYSQL_PORT=3306
export MYSQL_USER=guvi
export MYSQL_PASSWORD=Guvi@2024@Secure!
export MYSQL_DB=guvi_app
export REDIS_HOST=localhost
export REDIS_PORT=6379
export REDIS_DB=0
export SESSION_TTL=604800
export MONGO_URI=mongodb://localhost:27017
export MONGO_DB=guvi_app
export MONGO_COLLECTION=profiles
EOF

# Enable site and disable default
echo "🌐 Enabling site..."
sudo a2dissite 000-default.conf
sudo a2ensite guvi-app.conf

# Restart Apache
echo "🔄 Restarting Apache..."
sudo systemctl restart apache2

# Configure firewall (if UFW is installed)
if command -v ufw &> /dev/null; then
    echo "🔒 Configuring firewall..."
    sudo ufw allow 'Apache Full'
    sudo ufw allow 22/tcp
    sudo ufw --force enable
fi

# Test services
echo ""
echo "========================================================"
echo "  ✅ Installation Complete!"
echo "========================================================"
echo ""
echo "Service Status:"
echo "---------------"
systemctl is-active apache2 && echo "✓ Apache2: Running" || echo "✗ Apache2: Not running"
systemctl is-active mysql && echo "✓ MySQL: Running" || echo "✗ MySQL: Not running"
systemctl is-active redis-server && echo "✓ Redis: Running" || echo "✗ Redis: Not running"
systemctl is-active mongod && echo "✓ MongoDB: Running" || echo "✗ MongoDB: Not running"
echo ""
echo "Next Steps:"
echo "-----------"
echo "1. Get your EC2 public IP: curl http://169.254.169.254/latest/meta-data/public-ipv4"
echo "2. Access your app: http://YOUR_EC2_PUBLIC_IP"
echo "3. Test diagnostics: http://YOUR_EC2_PUBLIC_IP/php/db/diagnostics.php"
echo ""
echo "Security Recommendations:"
echo "-------------------------"
echo "1. Change default passwords in /var/www/guvi-app/.env"
echo "2. Configure SSL/HTTPS with Let's Encrypt"
echo "3. Set up regular database backups"
echo "4. Configure security groups in AWS to allow only HTTP/HTTPS"
echo ""