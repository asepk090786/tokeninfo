#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <node_domain> [main_domain]"
  exit 1
fi

NODE_DOMAIN="$1"
MAIN_DOMAIN="${2:-}"
DOCROOT="/www/wwwroot/${NODE_DOMAIN}/public"
VHOST="/www/server/panel/vhost/nginx/${NODE_DOMAIN}.conf"

if [[ ! -f "$VHOST" ]]; then
  echo "Vhost not found: $VHOST"
  exit 1
fi

if [[ ! -d "$DOCROOT" ]]; then
  echo "Docroot not found: $DOCROOT"
  exit 1
fi

cp -a "$VHOST" "${VHOST}.bak-$(date +%Y%m%d-%H%M%S)"

sed -i "s#^\s*root\s\+[^;]*;#    root ${DOCROOT};#" "$VHOST"

if ! grep -q "include enable-php-83.conf;" "$VHOST"; then
  sed -i "/#PHP-INFO-START/a\    include enable-php-83.conf;" "$VHOST"
fi

if ! grep -q "try_files \$uri \$uri/ /index.php" "$VHOST"; then
  sed -i '/server_name/a\
    location / {\
        try_files $uri $uri/ /index.php?$query_string;\
    }\
' "$VHOST"
fi

if [[ -n "$MAIN_DOMAIN" ]] && ! grep -q "$MAIN_DOMAIN" "$VHOST"; then
  sed -i "s#^\s*server_name\s\+\(.*\);#    server_name \1 ${MAIN_DOMAIN};#" "$VHOST"
fi

for f in "/www/wwwroot/${NODE_DOMAIN}/.user.ini" "/www/wwwroot/${NODE_DOMAIN}/public/.user.ini"; do
  if [[ -f "$f" ]]; then
    chattr -i "$f" 2>/dev/null || true
    echo "open_basedir=/www/wwwroot/${NODE_DOMAIN}/:/tmp/" > "$f"
    chown www-data:www-data "$f" || true
    chmod 644 "$f" || true
  fi
done

nginx -t
systemctl reload nginx || service nginx reload

echo "[OK] Vhost standardized: $VHOST"
