#!/usr/bin/env bash
# One-time server setup: register king:listen with Supervisor.
# Run on the server as a user with sudo:
#   sudo bash /var/www/html/ludo-shree/scripts/setup-king-supervisor.sh

set -euo pipefail

APP_DIR="/var/www/html/ludo-shree"
CONF_SRC="${APP_DIR}/scripts/supervisor/king-listen.conf"
CONF_DST="/etc/supervisor/conf.d/king-listen.conf"

if [[ ! -f "$CONF_SRC" ]]; then
  echo "Missing ${CONF_SRC}. Deploy the repo first."
  exit 1
fi

if ! command -v supervisorctl >/dev/null 2>&1; then
  echo "supervisorctl not found. Install supervisor first, e.g.:"
  echo "  apt-get install -y supervisor"
  exit 1
fi

echo "==> Installing ${CONF_DST}"
cp "$CONF_SRC" "$CONF_DST"
chmod 644 "$CONF_DST"

echo "==> Reloading supervisor"
supervisorctl reread
supervisorctl update
supervisorctl start king-listen || supervisorctl restart king-listen
supervisorctl status king-listen

echo "==> King WebSocket daemon ready (tail -f /var/log/king-listen.log)"
