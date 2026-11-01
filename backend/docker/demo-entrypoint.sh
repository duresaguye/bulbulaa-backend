#!/bin/sh
# Starts the demo: a fresh SQLite database with the fictional demo data, then the web server.

set -eu

cd /var/www/html

# Render gives the public address of the service; links and assets must use it.
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT:-10000}}}"

# A new key on every start is fine for a demo: sessions are reset with the data anyway.
if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
fi

rm -f "$DB_DATABASE"
touch "$DB_DATABASE"

php artisan migrate --force --no-interaction
php artisan db:seed --class=DemoSeeder --force --no-interaction
php artisan storage:link --no-interaction >/dev/null 2>&1 || true
php artisan optimize --no-interaction

echo "Bulbulaa demo ready at $APP_URL/admin (sign in as admin@demo.test / demo-password)"

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}" --no-reload
