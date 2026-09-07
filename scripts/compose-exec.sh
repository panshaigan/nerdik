#!/usr/bin/env bash
# Run docker compose against this checkout's stack (or an explicit prod|staging).
#
# Usage:
#   ./scripts/compose-exec.sh exec -T app php artisan migrate --force
#   ./scripts/compose-exec.sh ps
#   ./scripts/compose-exec.sh down
#   ./scripts/compose-exec.sh prod exec -T app php artisan migrate --force
#   ./scripts/compose-exec.sh staging down
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/runtime.sh
source "${ROOT}/scripts/lib/runtime.sh"

DEPLOY_ENV=""

if [[ $# -ge 1 && ( "$1" == "prod" || "$1" == "staging" ) ]]; then
    DEPLOY_ENV="$1"
    shift
fi

if [[ -z "$DEPLOY_ENV" ]]; then
    runtime_load "$ROOT"
    if [[ "$RUNTIME" != "stack" ]]; then
        echo "compose-exec requires APP_ENV=staging or production (got ${APP_ENV:-local})." >&2
        exit 1
    fi
fi

if [[ $# -lt 1 ]]; then
    cat <<'EOF' >&2
Usage: ./scripts/compose-exec.sh [prod|staging] <compose-args...>

Examples:
  ./scripts/compose-exec.sh ps
  ./scripts/compose-exec.sh exec -T app php artisan migrate --force
  ./scripts/compose-exec.sh prod exec -T app php artisan migrate --force
  ./scripts/compose-exec.sh staging down
EOF
    exit 1
fi

case "$DEPLOY_ENV" in
    prod)
        COMPOSE_FILES=(-f compose.stack.yaml -f compose.prod.yaml)
        ;;
    staging)
        COMPOSE_FILES=(-f compose.stack.yaml -f compose.staging.yaml)
        ;;
    *)
        echo "DEPLOY_ENV must be 'prod' or 'staging'." >&2
        exit 1
        ;;
esac

# shellcheck disable=SC1090
eval "$("${ROOT}/scripts/compose-env.sh" "${DEPLOY_ENV}")"

exec docker compose "${COMPOSE_FILES[@]}" "$@"
