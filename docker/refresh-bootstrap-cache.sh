#!/bin/sh
set -e

cd /var/www/html

rm -f \
    bootstrap/cache/packages.php \
    bootstrap/cache/services.php \
    bootstrap/cache/config.php \
    bootstrap/cache/routes-v7.php

php artisan package:discover --ansi --no-interaction
