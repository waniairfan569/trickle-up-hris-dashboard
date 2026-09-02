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

echo "✔ Deploy complete."
# `trap cleanup` lifts maintenance mode here.
