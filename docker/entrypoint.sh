#!/bin/sh
set -e

cd /var/www/html

if [ "$WAIT_FOR_DB" != "false" ]; then
    echo "Waiting for MySQL..."
    until php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
        sleep 2
    done
fi

if [ "$WAIT_FOR_REDIS" != "false" ]; then
    echo "Waiting for Redis..."
    until php -r "if (! class_exists('Redis')) { exit(1); } \$r = new Redis(); \$r->connect(getenv('REDIS_HOST'), (int) getenv('REDIS_PORT'));" 2>/dev/null; do
        sleep 2
    done
fi

php artisan storage:link --force 2>/dev/null || true

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

/bin/sh /var/www/html/docker/sync-composer.sh
/bin/sh /var/www/html/docker/refresh-bootstrap-cache.sh

if [ "$DOCKER_OPTIMIZE" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
