#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-https://token.sman1pontang.biz.id}"
USERS="${USERS:-450}"
MINUTES="${MINUTES:-10}"
USER_AGENT="${USER_AGENT:-Exambro/2.0}"
RESULT_DIR="${RESULT_DIR:-$PWD/storage/logs/load-test-$(date +%Y%m%d-%H%M%S)}"

if ! command -v ab >/dev/null 2>&1; then
  echo "ApacheBench (ab) is required but not installed."
  exit 1
fi

mkdir -p "$RESULT_DIR"

startup_requests="$USERS"
version_requests="$USERS"
config_requests="$USERS"
token_info_requests="$USERS"
status_token_requests=$(( USERS * MINUTES * 60 / 8 ))
status_warning_requests=$(( USERS * MINUTES * 60 / 12 ))
status_info_requests=$(( USERS * MINUTES * 60 / 10 ))

status_token_requests=$(( status_token_requests > USERS ? status_token_requests : USERS ))
status_warning_requests=$(( status_warning_requests > USERS ? status_warning_requests : USERS ))
status_info_requests=$(( status_info_requests > USERS ? status_info_requests : USERS ))

startup_concurrency="$USERS"
steady_concurrency="$USERS"

cat <<EOF
Load test configuration
- Base URL: $BASE_URL
- Users: $USERS
- Duration model: $MINUTES minutes
- User-Agent: $USER_AGENT
- Results: $RESULT_DIR

Startup phase request counts
- /: $startup_requests
- /api/version.json: $version_requests
- /api/config.json: $config_requests
- /api/token-info: $token_info_requests

Steady-state request counts
- /api/exambro-status/token: $status_token_requests
- /api/exambro-status/peringatan: $status_warning_requests
- /api/exambro-info: $status_info_requests
EOF

run_ab() {
  local label="$1"
  local requests="$2"
  local concurrency="$3"
  local url="$4"
  shift 4

  echo "Running $label"
  ab -k -n "$requests" -c "$concurrency" -H "User-Agent: $USER_AGENT" "$@" "$url" > "$RESULT_DIR/$label.txt"
}

run_ab homepage "$startup_requests" "$startup_concurrency" "$BASE_URL/"
run_ab version_json "$version_requests" "$startup_concurrency" "$BASE_URL/api/version.json"
run_ab config_json "$config_requests" "$startup_concurrency" "$BASE_URL/api/config.json"
run_ab token_info "$token_info_requests" "$startup_concurrency" "$BASE_URL/api/token-info"

run_ab status_token "$status_token_requests" "$steady_concurrency" "$BASE_URL/api/exambro-status/token" &
pid_token=$!
run_ab status_warning "$status_warning_requests" "$steady_concurrency" "$BASE_URL/api/exambro-status/peringatan" &
pid_warning=$!
run_ab status_info "$status_info_requests" "$steady_concurrency" "$BASE_URL/api/exambro-info" &
pid_info=$!

wait "$pid_token"
wait "$pid_warning"
wait "$pid_info"

echo
printf 'Completed. Result files:\n'
find "$RESULT_DIR" -maxdepth 1 -type f | sort
