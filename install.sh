#!/bin/bash

echo "=================================================="
echo " Starting Asset Management System Installation..."
echo "=================================================="

# 1. Update System
echo "Updating Ubuntu packages..."
sudo apt update -y

# 2. Install Apache, MariaDB, PHP
echo "Installing Apache, MariaDB, and PHP..."
sudo apt install -y apache2 mariadb-server php libapache2-mod-php php-mysql unzip

# 3. Setup Web Directory
echo "Copying files to /var/www/html/asset_manager..."
sudo mkdir -p /var/www/html/asset_manager
sudo cp -r ./* /var/www/html/asset_manager/

# 4. Set Permissions
echo "Setting folder permissions..."
sudo chown -R www-data:www-data /var/www/html/asset_manager
sudo chmod -R 775 /var/www/html/asset_manager

# 5. Database Setup (MariaDB)
echo "Setting up MariaDB Database..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS it_asset_db;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'root'@'localhost' IDENTIFIED BY 'Scl@@2017';"
sudo mysql -e "GRANT ALL PRIVILEGES ON it_asset_db.* TO 'root'@'localhost';"

# Adding admin user for db.php connection
sudo mysql -e "CREATE USER IF NOT EXISTS 'admin'@'localhost' IDENTIFIED BY '';"
sudo mysql -e "GRANT ALL PRIVILEGES ON it_asset_db.* TO 'admin'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

if [ -f "database.sql" ]; then
    sudo mysql it_asset_db < database.sql
    echo "Database imported successfully!"
else
    echo "Warning: database.sql not found. Skipping database import."
fi

# 6. Restart Apache
sudo systemctl restart apache2

echo "=================================================="
echo " Installation Complete! "
echo " Please visit: http://localhost/asset_manager"
echo "=================================================="