#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache/data \
    storage/logs \
    bootstrap/cache

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

php artisan storage:link --force --no-interaction 2>/dev/null || true

case "$1" in
    /usr/bin/supervisord)
        exec "$@"
        ;;
    *)
        if [ "$(id -u)" = "0" ]; then
            exec gosu www-data "$@"
        fi
        exec "$@"
        ;;
esac
