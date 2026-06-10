#!/bin/sh
set -e

cd /var/www/html

/bin/sh /var/www/html/docker/refresh-bootstrap-cache.sh

interval="${SCHEDULER_INTERVAL_SECONDS:-30}"

while true; do
    php artisan schedule:run --whisper
    sleep "$interval"
done
