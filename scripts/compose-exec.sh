#!/usr/bin/env bash
# Run docker compose against prod or staging with NERDIK_IMAGE resolved automatically.
#
# Usage:
#   ./scripts/compose-exec.sh prod exec -T app php artisan migrate --force
#   ./scripts/compose-exec.sh prod ps
#   ./scripts/compose-exec.sh staging down
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ $# -lt 2 ]]; then
    cat <<'EOF' >&2
Usage: ./scripts/compose-exec.sh <prod|staging> <compose-args...>

Examples:
  ./scripts/compose-exec.sh prod exec -T app php artisan migrate --force
  ./scripts/compose-exec.sh prod ps
  ./scripts/compose-exec.sh staging down
EOF
    exit 1
fi

DEPLOY_ENV="$1"
shift

case "$DEPLOY_ENV" in
    prod)
        COMPOSE_FILES=(-f compose.stack.yaml -f compose.prod.yaml)
        ;;
    staging)
        COMPOSE_FILES=(-f compose.stack.yaml -f compose.staging.yaml)
        ;;
    *)
        echo "First argument must be 'prod' or 'staging'." >&2
        exit 1
        ;;
esac

# shellcheck disable=SC1090
eval "$("${ROOT}/scripts/compose-env.sh" "${DEPLOY_ENV}")"

exec docker compose "${COMPOSE_FILES[@]}" "$@"
