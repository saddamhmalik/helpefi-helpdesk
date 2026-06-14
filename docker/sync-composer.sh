#!/bin/sh
set -e

cd /var/www/html

if [ ! -f composer.lock ]; then
    echo "composer.lock is missing."
    exit 1
fi

LOCK_HASH=$(md5sum composer.lock | awk '{print $1}')
STAMP_FILE=vendor/.docker-composer-lock-hash

if [ "${DOCKER_FORCE_COMPOSER_INSTALL:-false}" != "true" ] && [ -f vendor/autoload.php ] && [ -f "$STAMP_FILE" ] && [ "$(cat "$STAMP_FILE")" = "$LOCK_HASH" ]; then
    echo "Composer dependencies up to date."
    exit 0
fi

composer install \
    --no-interaction \
    --prefer-dist \
    --no-scripts

composer dump-autoload --optimize --no-scripts

echo "$LOCK_HASH" > "$STAMP_FILE"
