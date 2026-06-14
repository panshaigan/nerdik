#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache/data \
    storage/logs \
    storage/app/public/avatars \
    storage/app/public/media/temp/event-logos \
    storage/app/public/media/temp/activity-logos \
    storage/app/private/livewire-tmp \
    bootstrap/cache

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

if ! php artisan storage:link --force --no-interaction; then
    echo "entrypoint: storage:link failed (public/storage may already exist or be misconfigured)" >&2
fi

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
