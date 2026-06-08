#!/usr/bin/env bash
# ============================================================
# Trickle Up HRIS — Plesk Git deployment script
# Paste the body of this into:
#   Plesk > Domain > Git > (your repo) > "Enable additional deployment actions"
# OR run it manually over SSH from the project root after a git pull.
#
# Adjust PHP/Composer paths to match your Plesk server if needed.
# This server runs PHP 8.4 (managed via phpenv / .php-version).
# ============================================================
set -e

# Resolve PHP: prefer the phpenv-managed `php` (honours .php-version = 8.4),
# then fall back to explicit Plesk paths.
if command -v php >/dev/null 2>&1; then
  PHP="php"
elif [ -x /opt/plesk/php/8.4/bin/php ]; then
  PHP="/opt/plesk/php/8.4/bin/php"
elif [ -x /opt/plesk/php/8.3/bin/php ]; then
  PHP="/opt/plesk/php/8.3/bin/php"
else
  PHP="php"
fi
echo "Using PHP: $($PHP -v | head -n1)"

# 1. Install PHP dependencies (no dev deps, optimized autoloader)
$PHP /usr/lib/plesk-9.0/composer.phar install --no-dev --optimize-autoloader --no-interaction \
  || composer install --no-dev --optimize-autoloader --no-interaction

# 2. Run database migrations (no prompts in production)
$PHP artisan migrate --force

# 3. Link storage so uploaded files / avatars are publicly served
$PHP artisan storage:link || true

# 4. Rebuild optimized caches (config, routes, views, events)
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

echo "Deploy complete."
