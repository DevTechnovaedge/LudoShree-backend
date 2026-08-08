#!/usr/bin/env bash
# Set King (Daddy King) WebSocket credentials in server .env (never commit secrets).
# Usage on the server:
#   export KING_WS_API_KEY='your-key'
#   export KING_WS_API_SECRET='your-secret'
#   bash /var/www/html/ludo-shree/scripts/configure-king-env.sh

set -euo pipefail

APP_DIR="/var/www/html/ludo-shree"
ENV_FILE="${APP_DIR}/.env"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing ${ENV_FILE}"
  exit 1
fi

if [[ -z "${KING_WS_API_KEY:-}" || -z "${KING_WS_API_SECRET:-}" ]]; then
  echo "Set KING_WS_API_KEY and KING_WS_API_SECRET before running this script."
  exit 1
fi

set_env() {
  local key="$1"
  local value="$2"
  if grep -q "^${key}=" "$ENV_FILE"; then
    sed -i.bak "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
  else
    echo "${key}=${value}" >> "$ENV_FILE"
  fi
}

set_env KING_WS_ENABLED true
set_env KING_WS_URL "wss://kingws.daddyking.live/ws"
set_env KING_WS_LOBBY LUDO_KING_LOBBY
set_env KING_WS_API_KEY "$KING_WS_API_KEY"
set_env KING_WS_API_SECRET "$KING_WS_API_SECRET"
set_env KING_GAME_TYPE_ID 1

cd "$APP_DIR"
php artisan config:cache

if command -v supervisorctl >/dev/null 2>&1 && supervisorctl status king-listen >/dev/null 2>&1; then
  supervisorctl restart king-listen
  supervisorctl status king-listen
else
  echo "Run once: sudo bash ${APP_DIR}/scripts/setup-king-supervisor.sh"
fi

echo "King credentials applied and config cached."
