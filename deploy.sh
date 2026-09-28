#!/bin/bash
#
# Safe zero-fuss deploy for a traditional host (Plesk / VPS).
# Run from the project root on the server:  ./deploy.sh
#
# It puts the app into maintenance mode, pulls, installs, migrates, rebuilds the
# production caches, restarts the queue worker, and always lifts maintenance
# mode again — even if a step fails.
set -euo pipefail

PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"
BRANCH="${DEPLOY_BRANCH:-main}"

echo "▶ Deploying branch '$BRANCH'…"

# Always bring the app back up on exit.
cleanup() { $PHP artisan up || true; }
trap cleanup EXIT

# 1. Maintenance mode (with a secret bypass so you can preview).
$PHP artisan down --render="errors::503" --retry=15 || true

# 2. Pull the latest code.
git fetch --all --prune
git reset --hard "origin/$BRANCH"

# 3. Install PHP deps (production).
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# 4. Run migrations.
$PHP artisan migrate --force

# 5. Rebuild caches (clear first so stale caches never linger).
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan storage:link || true

# 6. Restart the queue worker so it picks up new code.
$PHP artisan queue:restart || true

# 7. Reload PHP-FPM so OPcache picks up the new class files.
#    Hosts with opcache.validate_timestamps=0 keep serving the PREVIOUS
#    controllers/services while step 5 recompiles the Blade views — that
#    mismatch (a view reading a key the old controller never set, or code
#    using a constant the old class doesn't have) shows up as a 500.
#    Resetting OPcache from the CLI does NOT help: php-fpm has its own.
if [ -n "${FPM_RELOAD_CMD:-}" ]; then
    echo "▶ Reloading PHP-FPM…"
    eval "$FPM_RELOAD_CMD" || echo "⚠ PHP-FPM reload failed — reload PHP manually if pages 500."
else
    reloaded=0
    for svc in php8.3-fpm php8.2-fpm php-fpm plesk-php83-fpm plesk-php82-fpm; do
        if systemctl reload "$svc" >/dev/null 2>&1; then
            echo "▶ Reloaded $svc — OPcache cleared."
            reloaded=1
            break
        fi
    done
    if [ "$reloaded" -eq 0 ]; then
        echo "⚠ Could not reload PHP-FPM automatically."
        echo "  If pages 500 right after a deploy, restart PHP for the domain (Plesk:"
        echo "  PHP Settings → restart) to clear OPcache, or set FPM_RELOAD_CMD."
    fi
fi

echo "✔ Deploy complete."
# `trap cleanup` lifts maintenance mode here.
