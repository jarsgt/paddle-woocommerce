#!/bin/bash

apt-get update
apt-get upgrade -y
debconf-set-selections <<< 'mysql-server mysql-server/root_password password dbrootpass'
debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password dbrootpass'
debconf-set-selections <<< 'debconf shared/accepted-oracle-license-v1-1 select true'
debconf-set-selections <<< 'debconf shared/accepted-oracle-license-v1-1 seen true'

apt-get install -y apache2 mysql-server mysql-client php5 php5-mcrypt php5-intl php5-mysql php5-curl \

php5enmod mcrypt
