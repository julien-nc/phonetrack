#!/bin/bash

# We need to install dependencies only for Docker
[[ ! -e /.dockerenv ]] && exit 0

set -xe

apt-get update -yqq
apt-get install git sudo php-xdebug php7.1-sqlite3 php7.1-curl php7.1-xml php7.1-mbstring php7.1-json php7.1-zip php7.1-gd unzip curl wget -yqq

curl --location --output /usr/local/bin/phpunit https://phar.phpunit.de/phpunit.phar
chmod +x /usr/local/bin/phpunit

