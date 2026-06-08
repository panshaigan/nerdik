#!/usr/bin/env bash
# Deploy a pre-built GHCR image to staging or prod via Docker Compose.
#
# Composer and npm dependencies are baked into the image during CI
# (docker/production/Dockerfile). This script does not run composer/npm on the
# host — it pulls NERDIK_IMAGE (or IMAGE_TAG), starts containers, migrates,
# caches config/routes/views, and restarts worker/scheduler/reverb.
#
# For a full VPS update (git pull + deploy latest SHA): ./scripts/vps-deploy.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

usage() {
    cat <<'EOF'
Usage: ./scripts/deploy.sh <staging|prod> [--build] [--pull-only]

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

if [[ "$DEPLOY_ENV" == "dev" ]]; then
    echo "Note: 'dev' deploy was renamed to 'staging'." >&2
    DEPLOY_ENV="staging"
fi

if [[ "$DEPLOY_ENV" != "staging" && "$DEPLOY_ENV" != "prod" ]]; then
    echo "First argument must be 'staging' or 'prod'." >&2
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

if [[ "$DEPLOY_ENV" == "prod" && ! -f docker/caddy/Caddyfile ]]; then
    echo "Missing docker/caddy/Caddyfile — copy docker/caddy/Caddyfile.example and set APP_DOMAIN." >&2
    exit 1
fi

if [[ "$DEPLOY_ENV" == "staging" ]]; then
    if ! docker network inspect nerdik-edge >/dev/null 2>&1; then
        echo "Docker network nerdik-edge not found. Deploy production first (make vps-deploy) so Caddy creates the shared edge network." >&2
        exit 1
    fi
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
    # pgsql uses nerdik-pgsql:local (built from docker/pgsql); it is not on GHCR.
    if "${COMPOSE[@]}" pull --ignore-buildable 2>/dev/null; then
        :
    elif [[ "$DEPLOY_ENV" == "prod" ]]; then
        "${COMPOSE[@]}" pull caddy app
    else
        "${COMPOSE[@]}" pull app
    fi

    "${COMPOSE[@]}" build pgsql
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

log_retention_days="${LOG_DAILY_DAYS:-14}"
"${COMPOSE[@]}" exec -T app sh -c "find storage/logs -type f -name '*.log' -mtime +${log_retention_days} -delete 2>/dev/null || true"

"${COMPOSE[@]}" restart worker scheduler reverb

echo "Deploy complete for ${DEPLOY_ENV}. Verify: curl -fsS \"${APP_URL:-https://localhost}/up\""
