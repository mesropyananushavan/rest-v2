#!/bin/sh
set -eu

if [ -d /var/www/html ]; then
    cd /var/www/html

    mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs

    chown -R www-data:www-data storage bootstrap/cache
    chmod -R ug+rwX storage bootstrap/cache
fi

exec docker-php-entrypoint "$@"
