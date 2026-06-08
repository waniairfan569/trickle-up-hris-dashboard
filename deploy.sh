#!/usr/bin/env bash
# ============================================================
# Trickle Up HRIS — Plesk Git deployment script
# Paste the body of this into:
#   Plesk > Domain > Git > (your repo) > "Enable additional deployment actions"
# OR run it manually over SSH from the project root after a git pull.
#
# IMPORTANT: the app's dependencies (Symfony 8) require PHP >= 8.4.
# Plesk's deployment-action shell may default to an older PHP (e.g. 8.3),
# so we PIN the 8.4 binary explicitly and run composer THROUGH it.
# ============================================================
set -e

# Pin PHP 8.4 (the version the website + SSH use). Try the common Plesk paths.
if   [ -x /opt/plesk/php/8.4/bin/php ]; then PHP="/opt/plesk/php/8.4/bin/php"
elif [ -x /opt/plesk/php/8.5/bin/php ]; then PHP="/opt/plesk/php/8.5/bin/php"
else PHP="php"; fi
echo "Using PHP: $($PHP -v | head -n1)"

# Resolve Composer, then ALWAYS invoke it via $PHP so it runs on 8.4
# regardless of Composer's own shebang / the shell's default php.
COMPOSER="$(command -v composer 2>/dev/null || true)"
if [ -z "$COMPOSER" ]; then
  $PHP -r "copy('https://getcomposer.org/installer','composer-setup.php');"
  $PHP composer-setup.php --quiet
  rm -f composer-setup.php
  COMPOSER="composer.phar"
fi

# 1. Install PHP dependencies (no dev deps, optimized autoloader)
$PHP "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction

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
