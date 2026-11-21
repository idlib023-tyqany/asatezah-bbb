#!/bin/sh
set -e

PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_PATH="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_PATH")"
    touch "$DB_PATH"
    chown -R www-data:www-data "$(dirname "$DB_PATH")"
fi

php artisan config:clear || true
php artisan route:clear || true
php artisan migrate --force || true

exec apache2-foreground