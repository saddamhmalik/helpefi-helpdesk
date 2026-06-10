#!/bin/sh
set -e

cd /var/www/html

if [ ! -f composer.lock ]; then
    echo "composer.lock is missing."
    exit 1
fi

composer install \
    --no-interaction \
    --prefer-dist \
    --no-scripts

composer dump-autoload --optimize --no-scripts
