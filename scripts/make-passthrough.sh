#!/usr/bin/env bash
# Run Make wildcard commands with a real argv (no Make option/assignment parsing).
#
# Usage: ./scripts/make-passthrough.sh <artisan|npm|composer|test|maintenance> [args...]
#
# Prefer invoking via ./bin/make so `make artisan … --dsn=…` works from a normal shell.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <artisan|npm|composer|test|maintenance> [args...]" >&2
    exit 1
fi

TARGET="$1"
shift

if [[ "${NERDIK_PASSTHROUGH_DRY_RUN:-}" == "1" ]]; then
    printf '%s' "$TARGET"
    if [[ $# -gt 0 ]]; then
        printf ' %s' "$@"
    fi
    printf '\n'
    exit 0
fi

# shellcheck source=scripts/lib/runtime.sh
source "${ROOT}/scripts/lib/runtime.sh"

SAIL="${SAIL:-./vendor/bin/sail}"

require_sail() {
    runtime_load "$ROOT"
    if [[ "$RUNTIME" != "sail" ]]; then
        echo "make ${TARGET} is Sail-only (APP_ENV=local)." >&2
        exit 1
    fi
    if [[ ! -x "$SAIL" ]]; then
        echo "Sail not found at ${SAIL}. Run composer install locally." >&2
        exit 1
    fi
}

case "$TARGET" in
    artisan)
        exec ./scripts/app-cmd.sh artisan "$@"
        ;;
    npm)
        require_sail
        exec "$SAIL" npm "$@"
        ;;
    composer)
        require_sail
        exec "$SAIL" composer "$@"
        ;;
    test)
        require_sail
        exec "$SAIL" artisan test "$@"
        ;;
    maintenance)
        exec ./scripts/maintenance.sh "$@"
        ;;
    *)
        echo "Unknown passthrough target: ${TARGET}" >&2
        exit 1
        ;;
esac
