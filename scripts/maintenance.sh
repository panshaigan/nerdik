#!/usr/bin/env bash
# Toggle production maintenance mode via Caddy flag file.
#
# Usage: ./scripts/maintenance.sh {on|off|status}
#        make maintenance on|off|status
#
# Environment:
#   NERDIK_MAINTENANCE_STATE_DIR  Override state directory (for tests; skips APP_ENV check)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STATE_DIR="${NERDIK_MAINTENANCE_STATE_DIR:-${ROOT}/docker/caddy/state}"
FLAG_FILE="${STATE_DIR}/maintenance"

usage() {
    cat <<'EOF'
Usage: ./scripts/maintenance.sh {on|off|status}

Commands:
  on      Enable maintenance mode (serve static page at the edge)
  off     Disable maintenance mode
  status  Print ON or OFF (exit 0 when ON, 1 when OFF)

Requires APP_ENV=production in .env (unless NERDIK_MAINTENANCE_STATE_DIR is set for tests).
EOF
}

if [[ -z "${NERDIK_MAINTENANCE_STATE_DIR:-}" ]]; then
    # shellcheck source=scripts/lib/runtime.sh
    source "${ROOT}/scripts/lib/runtime.sh"
    runtime_load "$ROOT"
    if [[ "${APP_ENV}" != "production" ]]; then
        echo "maintenance requires APP_ENV=production (got ${APP_ENV}). Run from the prod checkout." >&2
        exit 1
    fi
fi

maintenance_on() {
    mkdir -p "${STATE_DIR}"
    touch "${FLAG_FILE}"
    echo "Production maintenance mode enabled."
}

maintenance_off() {
    rm -f "${FLAG_FILE}"
    echo "Production maintenance mode disabled."
}

maintenance_status() {
    if [[ -f "${FLAG_FILE}" ]]; then
        echo "ON"
        return 0
    fi

    echo "OFF"
    return 1
}

if [[ $# -ne 1 ]]; then
    usage
    exit 1
fi

case "$1" in
    on)
        maintenance_on
        ;;
    off)
        maintenance_off
        ;;
    status)
        maintenance_status
        ;;
    -h|--help)
        usage
        exit 0
        ;;
    *)
        echo "Unknown command: $1" >&2
        usage
        exit 1
        ;;
esac
