#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/var/www/token-app"

mkdir -p "$APP_ROOT/releases" "$APP_ROOT/shared" "$APP_ROOT/shared/storage" "$APP_ROOT/shared/bootstrap/cache"
mkdir -p "$APP_ROOT/shared/storage/app" "$APP_ROOT/shared/storage/framework" "$APP_ROOT/shared/storage/logs"
chown -R www-data:www-data "$APP_ROOT"
chmod -R 775 "$APP_ROOT/shared/storage" "$APP_ROOT/shared/bootstrap/cache"

echo "[OK] Backend server folders prepared at $APP_ROOT"
echo "[NEXT] Copy shared/cluster.env to $APP_ROOT/shared/cluster.env"
echo "[NEXT] Run scripts/04_apply_cluster_env.sh <node_domain> [main_domain]"
