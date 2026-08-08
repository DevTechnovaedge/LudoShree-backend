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

echo "==> King WebSocket daemon (king:listen)"
if command -v supervisorctl >/dev/null 2>&1; then
  if supervisorctl status king-listen >/dev/null 2>&1; then
    supervisorctl restart king-listen || true
    echo "    restarted king-listen via supervisor"
  else
    echo "    king-listen not registered — attempting first-time supervisor setup..."
    if sudo -n bash "${APP_DIR}/scripts/setup-king-supervisor.sh" 2>/dev/null; then
      echo "    registered and started king-listen"
    else
      echo "    WARNING: auto-setup failed (needs passwordless sudo or manual run):"
      echo "      sudo bash ${APP_DIR}/scripts/setup-king-supervisor.sh"
    fi
  fi
else
  echo "    WARNING: supervisorctl not found; install supervisor then run:"
  echo "      sudo bash ${APP_DIR}/scripts/setup-king-supervisor.sh"
fi

echo "==> Deploy complete at $(git rev-parse --short HEAD)"
