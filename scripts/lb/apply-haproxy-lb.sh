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
HAPROXY_OUTPUT_PATH="${HAPROXY_OUTPUT_PATH:-/etc/haproxy/haproxy.cfg}"

if [[ "$DRY_RUN" == "true" ]]; then
  "$SCRIPT_DIR/generate-haproxy-lb.sh" "$ENV_FILE" --dry-run
  echo "[OK] Dry-run only. HAProxy was not validated or reloaded."
  exit 0
fi

"$SCRIPT_DIR/generate-haproxy-lb.sh" "$ENV_FILE"

if command -v haproxy >/dev/null 2>&1; then
  haproxy -c -f "$HAPROXY_OUTPUT_PATH"
else
  echo "haproxy command not found"
  exit 1
fi

if command -v systemctl >/dev/null 2>&1; then
  systemctl reload haproxy
else
  service haproxy reload
fi

echo "[OK] HAProxy reloaded. Active config: $HAPROXY_OUTPUT_PATH"
