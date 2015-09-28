#!/bin/bash

echo 'CREATE DATABASE IF NOT EXISTS wordpress;' | mysql -uroot -pdbrootpass
echo "GRANT ALL PRIVILEGES ON wordpress . * TO 'root'@'localhost' IDENTIFIED BY 'root';" | mysql -uroot -pdbrootpass

#Setup installer
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

#download and install Wordpress
cd /var/www/html
sudo wp core download --allow-root
sudo wp core config --dbname=wordpress --dbuser=root --dbpass=root --allow-root
sudo wp core install --title="Paddle Wordpress" --url="http://localhost:9080" --admin_user="admin" --admin_password="Passw0rd" --admin_email="example@example.com" --allow-root
sudo rm index.html

echo 'Wordpress set up'