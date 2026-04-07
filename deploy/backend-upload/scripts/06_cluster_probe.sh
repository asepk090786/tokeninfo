#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "Usage: $0 <main_domain> <node1> [node2] [node3] [node4]"
  exit 1
fi

MAIN_DOMAIN="$1"
shift
NODES=("$@")
TARGETS=("$MAIN_DOMAIN" "${NODES[@]}")
ENDPOINTS=("/api/config.json" "/api/version.json" "/up")

echo "Cluster probe"
echo "Main : $MAIN_DOMAIN"
echo "Nodes: ${NODES[*]}"
echo

for t in "${TARGETS[@]}"; do
  echo "=== $t ==="
  for ep in "${ENDPOINTS[@]}"; do
    body="$(wget -qO- --timeout=10 --no-check-certificate "https://${t}${ep}" || true)"
    code="$(wget -qO- --server-response --timeout=10 --no-check-certificate "https://${t}${ep}" 2>&1 | awk '/HTTP\// {c=$2} END {print c+0}')"
    if [[ -n "$body" ]]; then
      hash="$(printf "%s" "$body" | sha256sum | awk '{print $1}')"
    else
      hash="-"
    fi
    echo "  $ep code=$code sha256=$hash"
  done
  echo

done

echo "Tip: jika hash /api/config.json beda antar node, clear cache di node tersebut (artisan optimize:clear)."
