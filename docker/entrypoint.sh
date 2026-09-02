#!/bin/bash
set -e

cd /var/www/html

# Ensure an app key exists (generate one if the env didn't supply it).
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null && [ -z "${APP_KEY:-}" ]; then
    php artisan key:generate --force || true
fi

# Wait for the database to accept connections (up to ~60s).
echo "Waiting for the database…"
for i in $(seq 1 30); do
    if php artisan migrate:status >/dev/null 2>&1; then
        echo "Database is ready."
        break
    fi
    sleep 2
done

# Run migrations and warm the production caches.
php artisan migrate --force || echo "migrate skipped/failed — check DB config"
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Boot complete. Starting services…"
exec "$@"
