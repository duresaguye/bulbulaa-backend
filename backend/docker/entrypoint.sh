#!/bin/sh

set -eu

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

mkdir -p storage/app

if [ -f storage/app/.app_key ]; then
    APP_KEY="$(cat storage/app/.app_key)"
elif grep -q '^APP_KEY=base64:' .env; then
    APP_KEY="$(sed -n 's/^APP_KEY=//p' .env)"
else
    APP_KEY="$(php artisan key:generate --show --no-interaction)"
    sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env
fi

printf '%s' "$APP_KEY" > storage/app/.app_key
export APP_KEY
php artisan migrate --force --no-interaction
chown -R www-data:www-data storage/app

exec "$@"
