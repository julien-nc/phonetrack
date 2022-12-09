#!/bin/bash

# We need to install dependencies only for Docker
[[ ! -e /.dockerenv ]] && exit 0

set -xe

export DEBIAN_FRONTEND=noninteractive
apt-get update -yqq
apt-get install git sed curl unzip libzip-dev libpq-dev sqlite3 libsqlite3-dev libcurl4-openssl-dev libxml2-dev libpng-dev zip -yqq > /dev/null 2>&1
#apt-get install php-xdebug php-sqlite3 php-curl php-xml php-mbstring php-json php-zip php-gd php-intl -yqq
#docker-php-ext-install json
docker-php-ext-install gd
docker-php-ext-install intl
docker-php-ext-install zip

curl --location --output /usr/local/bin/phpunit https://phar.phpunit.de/phpunit.phar
chmod +x /usr/local/bin/phpunit

