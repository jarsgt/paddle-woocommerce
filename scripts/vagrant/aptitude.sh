#!/bin/bash

apt-get update
apt-get upgrade -y
debconf-set-selections <<< 'mysql-server mysql-server/root_password password dbrootpass'
debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password dbrootpass'
debconf-set-selections <<< 'debconf shared/accepted-oracle-license-v1-1 select true'
debconf-set-selections <<< 'debconf shared/accepted-oracle-license-v1-1 seen true'

apt-get install -y apache2 mysql-server mysql-client php5 php5-mcrypt php5-intl php5-odbc php5-mysql php5-gmp php5-curl redis-server realpath git xvfb \
    ruby1.9.1-dev make zlib1g-dev libicu-dev build-essential \

php5enmod mcrypt
