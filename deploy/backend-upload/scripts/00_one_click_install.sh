#!/usr/bin/env bash
set -euo pipefail

# One-click installer for a new app node.
# Runs: prepare -> release deploy -> apply cluster env -> vhost standardize -> post checks
#
# Usage:
#   ./00_one_click_install.sh <node_domain> <release_name> [main_domain]
#
# Optional env:
#   RELEASE_ARCHIVE=/path/to/source.tar.gz   # if set, release is extracted automatically
#   APP_ROOT=/var/www/token-app
#   PHP_BIN=/www/server/php/83/bin/php

if [[ $# -lt 2 ]]; then
  echo "Usage: $0 <node_domain> <release_name> [main_domain]"
  exit 1
fi

NODE_DOMAIN="$1"
RELEASE_NAME="$2"
MAIN_DOMAIN="${3:-token.sman1pontang.biz.id}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PKG_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
APP_ROOT="${APP_ROOT:-/var/www/token-app}"
SHARED_DIR="$APP_ROOT/shared"
RELEASE_DIR="$APP_ROOT/releases/$RELEASE_NAME"

log() {
  printf '[STEP] %s\n' "$1"
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing command: $1"
    exit 1
  fi
}

require_cmd bash
require_cmd sed
require_cmd awk

log "Prepare server directories"
"$SCRIPT_DIR/01_prepare_server.sh"

# Ensure cluster env is available on server path.
if [[ ! -f "$SHARED_DIR/cluster.env" ]]; then
  if [[ -f "$PKG_ROOT/shared/cluster.env" ]]; then
    cp -a "$PKG_ROOT/shared/cluster.env" "$SHARED_DIR/cluster.env"
  elif [[ -f "$PKG_ROOT/shared/cluster.env.example" ]]; then
    cp -a "$PKG_ROOT/shared/cluster.env.example" "$SHARED_DIR/cluster.env"
    echo "Generated $SHARED_DIR/cluster.env from example. Please edit real values and re-run."
    exit 1
  else
    echo "Missing cluster env file. Expected: $SHARED_DIR/cluster.env"
    exit 1
  fi
fi

# Create/extract release automatically when RELEASE_ARCHIVE is provided.
if [[ ! -d "$RELEASE_DIR" ]]; then
  mkdir -p "$RELEASE_DIR"
fi

if [[ -n "${RELEASE_ARCHIVE:-}" ]]; then
  log "Extract release archive: $RELEASE_ARCHIVE"
  if [[ ! -f "$RELEASE_ARCHIVE" ]]; then
    echo "RELEASE_ARCHIVE not found: $RELEASE_ARCHIVE"
    exit 1
  fi

  # Clean current target release folder before extraction.
  find "$RELEASE_DIR" -mindepth 1 -maxdepth 1 -exec rm -rf {} +

  case "$RELEASE_ARCHIVE" in
    *.tar.gz|*.tgz)
      tar -xzf "$RELEASE_ARCHIVE" -C "$RELEASE_DIR"
      ;;
    *.zip)
      require_cmd unzip
      unzip -q "$RELEASE_ARCHIVE" -d "$RELEASE_DIR"
      ;;
    *)
      echo "Unsupported archive format: $RELEASE_ARCHIVE"
      exit 1
      ;;
  esac

  # Flatten single top-level directory archives.
  top_count="$(find "$RELEASE_DIR" -mindepth 1 -maxdepth 1 -type d | wc -l | awk '{print $1}')"
  file_count="$(find "$RELEASE_DIR" -mindepth 1 -maxdepth 1 | wc -l | awk '{print $1}')"
  if [[ "$top_count" == "1" && "$file_count" == "1" ]]; then
    top_dir="$(find "$RELEASE_DIR" -mindepth 1 -maxdepth 1 -type d | head -1)"
    tmp_dir="$RELEASE_DIR.__tmp__"
    mkdir -p "$tmp_dir"
    cp -a "$top_dir"/. "$tmp_dir"/
    find "$RELEASE_DIR" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
    cp -a "$tmp_dir"/. "$RELEASE_DIR"/
    rm -rf "$tmp_dir"
  fi
fi

if [[ ! -f "$RELEASE_DIR/artisan" ]]; then
  echo "Release content invalid. Missing artisan at: $RELEASE_DIR"
  echo "Tip: set RELEASE_ARCHIVE=/path/to/source.tar.gz or upload extracted source to $RELEASE_DIR"
  exit 1
fi

log "Deploy release: $RELEASE_NAME"
"$SCRIPT_DIR/02_deploy_release.sh" "$RELEASE_NAME"

log "Apply cluster env for node"
"$SCRIPT_DIR/04_apply_cluster_env.sh" "$NODE_DOMAIN" "$MAIN_DOMAIN"

# Refresh config after final .env is generated.
if [[ -L "$APP_ROOT/current" ]]; then
  CUR_DIR="$(readlink -f "$APP_ROOT/current")"
  PHP_BIN="${PHP_BIN:-php}"
  if command -v "$PHP_BIN" >/dev/null 2>&1; then
    (
      cd "$CUR_DIR"
      "$PHP_BIN" artisan config:clear || true
      "$PHP_BIN" artisan cache:clear || true
      "$PHP_BIN" artisan config:cache || true
    )
  fi
fi

log "Standardize node vhost"
"$SCRIPT_DIR/05_enable_node_vhost.sh" "$NODE_DOMAIN" "$MAIN_DOMAIN"

log "Run post-deploy checks"
"$SCRIPT_DIR/03_post_deploy_checks.sh" "https://$NODE_DOMAIN"

echo "[OK] One-click install finished for node: $NODE_DOMAIN"
echo "[INFO] Release: $RELEASE_NAME"
echo "[INFO] Main domain: $MAIN_DOMAIN"
