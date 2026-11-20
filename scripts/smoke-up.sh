#!/usr/bin/env bash
# Smoke-check a health URL with an initial settle delay and retries.
#
# Usage:
#   ./scripts/smoke-up.sh [url]
#
# Env:
#   SMOKE_URL             Health URL (default: first arg, or https://nerdik.app/up)
#   SMOKE_INITIAL_DELAY   Seconds to wait before the first attempt (default: 10)
#   SMOKE_ATTEMPTS        Total attempts before failing (default: 3)
#   SMOKE_INTERVAL        Seconds between failed attempts (default: 10)
#   SMOKE_CONNECT_TIMEOUT curl --connect-timeout (default: 10)
#   SMOKE_MAX_TIME        curl --max-time (default: 15)
set -euo pipefail

SMOKE_URL="${SMOKE_URL:-${1:-https://nerdik.app/up}}"
SMOKE_INITIAL_DELAY="${SMOKE_INITIAL_DELAY:-10}"
SMOKE_ATTEMPTS="${SMOKE_ATTEMPTS:-3}"
SMOKE_INTERVAL="${SMOKE_INTERVAL:-10}"
SMOKE_CONNECT_TIMEOUT="${SMOKE_CONNECT_TIMEOUT:-10}"
SMOKE_MAX_TIME="${SMOKE_MAX_TIME:-15}"

if [[ -z "$SMOKE_URL" ]]; then
    echo "Usage: $0 <url>" >&2
    exit 2
fi

if [[ "$SMOKE_ATTEMPTS" -lt 1 ]]; then
    echo "SMOKE_ATTEMPTS must be >= 1" >&2
    exit 2
fi

if [[ "$SMOKE_INITIAL_DELAY" -gt 0 ]]; then
    echo "Waiting ${SMOKE_INITIAL_DELAY}s before first check of ${SMOKE_URL}…"
    sleep "$SMOKE_INITIAL_DELAY"
fi

attempt=1
while (( attempt <= SMOKE_ATTEMPTS )); do
    echo "Attempt ${attempt}/${SMOKE_ATTEMPTS}: ${SMOKE_URL}"
    if curl -fsS \
        --connect-timeout "$SMOKE_CONNECT_TIMEOUT" \
        --max-time "$SMOKE_MAX_TIME" \
        "$SMOKE_URL" >/dev/null; then
        echo "Health check OK: ${SMOKE_URL}"
        exit 0
    fi

    if (( attempt < SMOKE_ATTEMPTS )); then
        echo "Not ready yet; retrying in ${SMOKE_INTERVAL}s…"
        sleep "$SMOKE_INTERVAL"
    fi

    attempt=$((attempt + 1))
done

echo "Health check FAILED after ${SMOKE_ATTEMPTS} attempts: ${SMOKE_URL}" >&2
exit 1
