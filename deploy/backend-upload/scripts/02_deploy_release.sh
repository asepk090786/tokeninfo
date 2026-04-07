#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <release_name>"
  exit 1
fi

RELEASE_NAME="$1"
APP_ROOT="/var/www/token-app"
RELEASE_DIR="$APP_ROOT/releases/$RELEASE_NAME"
CURRENT_LINK="$APP_ROOT/current"
SHARED_DIR="$APP_ROOT/shared"
PHP_BIN="${PHP_BIN:-php}"

if [[ ! -d "$RELEASE_DIR" ]]; then
  echo "Release folder not found: $RELEASE_DIR"
  exit 1
fi

if [[ ! -f "$SHARED_DIR/.env" ]]; then
  echo "Missing shared env: $SHARED_DIR/.env"
  exit 1
fi

cd "$RELEASE_DIR"

ln -nfs "$SHARED_DIR/.env" .env
rm -rf storage
ln -nfs "$SHARED_DIR/storage" storage
mkdir -p bootstrap/cache
chown -R www-data:www-data "$RELEASE_DIR"

if command -v composer >/dev/null 2>&1; then
  composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi

"$PHP_BIN" artisan config:clear || true
"$PHP_BIN" artisan route:clear || true
"$PHP_BIN" artisan view:clear || true
"$PHP_BIN" artisan cache:clear || true

"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

ln -nfs "$RELEASE_DIR" "$CURRENT_LINK"

"$PHP_BIN" artisan migrate --force

systemctl reload php8.3-fpm || true
systemctl reload nginx || true

echo "[OK] Release activated: $RELEASE_NAME"
