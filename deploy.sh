#!/usr/bin/env bash
# ============================================================
# Trickle Up HRIS — Plesk Git deployment script
# Paste the body of this into:
#   Plesk > Domain > Git > (your repo) > "Enable additional deployment actions"
# OR run it manually over SSH from the project root after a git pull.
#
# Adjust PHP/Composer paths to match your Plesk server if needed:
#   PHP on Plesk is usually:  /opt/plesk/php/8.3/bin/php
# ============================================================
set -e

# Use the project's PHP 8.3. On Plesk, prefer the explicit path:
PHP="${PHP:-/opt/plesk/php/8.3/bin/php}"
command -v "$PHP" >/dev/null 2>&1 || PHP="php"

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
