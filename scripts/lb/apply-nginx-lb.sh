#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${1:-$SCRIPT_DIR/lb.env}"

"$SCRIPT_DIR/generate-nginx-lb.sh" "$ENV_FILE"

# shellcheck disable=SC1090
source "$ENV_FILE"
OUTPUT_PATH="${OUTPUT_PATH:-/etc/nginx/sites-available/token-lb.conf}"

if command -v nginx >/dev/null 2>&1; then
  nginx -t
else
  echo "nginx command not found"
  exit 1
fi

# Enable site symlink for Debian/Ubuntu style layout if possible
if [[ -d /etc/nginx/sites-enabled && -f "$OUTPUT_PATH" ]]; then
  link_path="/etc/nginx/sites-enabled/$(basename "$OUTPUT_PATH")"
  ln -sf "$OUTPUT_PATH" "$link_path"
fi

if command -v systemctl >/dev/null 2>&1; then
  systemctl reload nginx
else
  service nginx reload
fi

echo "[OK] Nginx reloaded. Active config: $OUTPUT_PATH"
