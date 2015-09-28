#!/bin/bash

mkdir /var/www/html/wp-content/plugins/paddle-woocommerce
sudo ln -s /vagrant/woocommerce-paddle-checkout.php /var/www/html/wp-content/plugins/paddle-woocommerce/woocommerce-paddle-checkout.php
sudo ln -s /vagrant/images /var/www/html/wp-content/plugins/paddle-woocommerce/images
sudo ln -s /vagrant/js /var/www/html/wp-content/plugins/paddle-woocommerce/js

cd /var/www/html
sudo wp plugin activate "paddle-woocommerce" --allow-root

echo 'Paddle-Woocommerce set up'