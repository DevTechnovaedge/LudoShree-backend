#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/html/ludo-shree"
BRANCH="${DEPLOY_BRANCH:-main}"

cd "$APP_DIR"

# Allow git operations when repo owner differs from the deploy user
export GIT_CONFIG_COUNT=1
export GIT_CONFIG_KEY_0=safe.directory
export GIT_CONFIG_VALUE_0="$APP_DIR"

echo "==> Deploying LudoShree backend ($(date -u +%Y-%m-%dT%H:%M:%SZ))"
echo "==> Branch: $BRANCH"

git fetch --prune origin "$BRANCH"
git checkout "$BRANCH"
git reset --hard "origin/$BRANCH"

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Running migrations"
php artisan migrate --force

echo "==> Clearing and rebuilding caches"
php artisan optimize:clear
php artisan config:cache
# Livewire can conflict with route:cache; keep deploy moving if it fails
php artisan route:cache || php artisan route:clear
php artisan view:cache || true

echo "==> Fixing storage permissions"
chmod -R ug+rwx storage bootstrap/cache || true

echo "==> Deploy complete at $(git rev-parse --short HEAD)"
