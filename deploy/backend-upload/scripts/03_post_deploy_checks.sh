#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-http://127.0.0.1}"
UA="${2:-ExambroAndroid}"

fetch_status() {
	local url="$1"
	if command -v curl >/dev/null 2>&1; then
		curl -ksS -o /dev/null -w "%{http_code}" -A "$UA" "$url"
	else
		wget -qO- --server-response --no-check-certificate --user-agent="$UA" "$url" 2>&1 \
			| awk '/HTTP\// {code=$2} END {print code+0}'
	fi
}

echo "Checking health endpoints on: $BASE_URL (UA=$UA)"

for ep in /up /api/config.json /api/version.json /api/exambro-info /exambro/connect/primary; do
	code="$(fetch_status "$BASE_URL$ep")"
	if [[ "$code" == "200" || "$code" == "401" ]]; then
		echo "[OK] $ep => $code"
	else
		echo "[FAIL] $ep => $code"
		exit 1
	fi
done

echo "Done."
