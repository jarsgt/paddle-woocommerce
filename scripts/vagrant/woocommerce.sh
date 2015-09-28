#!/bin/bash

cd /var/www/html
sudo wp plugin install http://downloads.wordpress.org/plugin/woocommerce.zip --activate --allow-root

echo 'Woocommerce set up'