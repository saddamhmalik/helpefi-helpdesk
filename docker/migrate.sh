#!/bin/sh
set -e

cd /var/www/html

echo "Waiting for MySQL..."
until php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    sleep 2
done

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is missing. Run ./docker/init-env.sh before starting Docker."
    exit 1
fi

/bin/sh /var/www/html/docker/sync-composer.sh
/bin/sh /var/www/html/docker/refresh-bootstrap-cache.sh

php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\CentralDatabaseSeeder --force
php artisan tenants:migrate --force || true
php artisan tenants:upgrade || true
php artisan tenants:sync-routes || true

echo "Migrations complete."
