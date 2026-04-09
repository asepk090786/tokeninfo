#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DRY_RUN=false
ENV_FILE=""

for arg in "$@"; do
  case "$arg" in
    --dry-run)
      DRY_RUN=true
      ;;
    *)
      if [[ -z "$ENV_FILE" ]]; then
        ENV_FILE="$arg"
      else
        echo "Unexpected argument: $arg"
        echo "Usage: $0 [path/to/lb.env] [--dry-run]"
        exit 1
      fi
      ;;
  esac
done

ENV_FILE="${ENV_FILE:-$SCRIPT_DIR/lb.env}"

# shellcheck disable=SC1090
source "$ENV_FILE"
OUTPUT_PATH="${OUTPUT_PATH:-/etc/nginx/sites-available/token-lb.conf}"
NGINX_OUTPUT_PATH="${NGINX_OUTPUT_PATH:-$OUTPUT_PATH}"

if [[ "$DRY_RUN" == "true" ]]; then
  "$SCRIPT_DIR/generate-nginx-lb.sh" "$ENV_FILE" --dry-run
  echo "[OK] Dry-run only. Nginx was not tested or reloaded."
  exit 0
fi

"$SCRIPT_DIR/generate-nginx-lb.sh" "$ENV_FILE"

if command -v nginx >/dev/null 2>&1; then
  nginx -t
else
  echo "nginx command not found"
  exit 1
fi

# Enable site symlink for Debian/Ubuntu style layout if possible
if [[ -d /etc/nginx/sites-enabled && -f "$NGINX_OUTPUT_PATH" ]]; then
  link_path="/etc/nginx/sites-enabled/$(basename "$NGINX_OUTPUT_PATH")"
  ln -sf "$NGINX_OUTPUT_PATH" "$link_path"
fi

if command -v systemctl >/dev/null 2>&1; then
  systemctl reload nginx
else
  service nginx reload
fi

echo "[OK] Nginx reloaded. Active config: $NGINX_OUTPUT_PATH"
