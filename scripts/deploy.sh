#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

usage() {
    cat <<'EOF'
Usage: ./scripts/deploy.sh <dev|prod> [--build] [--pull-only]

Options:
  --build      Build locally using compose.build.yaml before deploy
  --pull-only  Pull image only (no up/migrate/cache/restart)
EOF
}

if [[ $# -lt 1 ]]; then
    usage
    exit 1
fi

DEPLOY_ENV="$1"
shift

if [[ "$DEPLOY_ENV" != "dev" && "$DEPLOY_ENV" != "prod" ]]; then
    echo "First argument must be 'dev' or 'prod'." >&2
    usage
    exit 1
fi

USE_BUILD="${DEPLOY_BUILD:-0}"
PULL_ONLY=0

for arg in "$@"; do
    case "$arg" in
        --build)
            USE_BUILD=1
            ;;
        --pull-only)
            PULL_ONLY=1
            ;;
        *)
            echo "Unknown option: $arg" >&2
            usage
            exit 1
            ;;
    esac
done

if [[ ! -f .env ]]; then
    echo "Missing .env — copy .env.production.example or .env.staging.example to .env and configure secrets." >&2
    exit 1
fi

if [[ ! -f docker/caddy/Caddyfile ]]; then
    echo "Missing docker/caddy/Caddyfile — copy docker/caddy/Caddyfile.example and set APP_DOMAIN." >&2
    exit 1
fi

# shellcheck disable=SC1091
set -a
source .env
set +a

if [[ -n "${IMAGE_TAG:-}" ]]; then
    if [[ -z "${GITHUB_OWNER:-}" ]]; then
        echo "IMAGE_TAG was set, but GITHUB_OWNER is missing in .env." >&2
        exit 1
    fi

    export NERDIK_IMAGE="ghcr.io/${GITHUB_OWNER}/nerdik:${IMAGE_TAG}"
fi

if [[ -z "${NERDIK_IMAGE:-}" ]]; then
    echo "NERDIK_IMAGE is required in .env (for example ghcr.io/\${GITHUB_OWNER}/nerdik:main)." >&2
    exit 1
fi

COMPOSE_FILES=(-f compose.stack.yaml -f "compose.${DEPLOY_ENV}.yaml")

if [[ "$USE_BUILD" == "1" ]]; then
    COMPOSE_FILES=(-f compose.stack.yaml -f compose.build.yaml -f "compose.${DEPLOY_ENV}.yaml")
fi

COMPOSE=(docker compose "${COMPOSE_FILES[@]}")

if [[ "$USE_BUILD" == "1" ]]; then
    "${COMPOSE[@]}" build
else
    "${COMPOSE[@]}" pull
fi

if [[ "$PULL_ONLY" == "1" ]]; then
    echo "Pull/build complete for ${DEPLOY_ENV}. Skipping deploy (--pull-only)."
    exit 0
fi

"${COMPOSE[@]}" up -d
"${COMPOSE[@]}" exec -T app php artisan migrate --force
"${COMPOSE[@]}" exec -T app php artisan config:cache
"${COMPOSE[@]}" exec -T app php artisan route:cache
"${COMPOSE[@]}" exec -T app php artisan view:cache
"${COMPOSE[@]}" restart worker scheduler reverb

echo "Deploy complete for ${DEPLOY_ENV}. Verify: curl -fsS \"${APP_URL:-https://localhost}/up\""
