#!/usr/bin/env bash
# Deploy a pre-built GHCR image to this checkout's stack via Docker Compose.
#
# Composer and npm dependencies are baked into the image during CI
# (docker/production/Dockerfile). This script does not run composer/npm on the
# host — it pulls NERDIK_IMAGE (or IMAGE_TAG), starts containers, migrates,
# runs optimize + filament:optimize, and restarts worker/scheduler/reverb.
#
# On production, maintenance mode is enabled before pull/build and disabled
# after a successful deploy (SKIP_MAINTENANCE=1 to bypass).
#
# Env is taken from APP_ENV in .env (production → prod, staging → staging),
# or pass an explicit first argument: prod|staging.
#
# For a full VPS update (git pull + deploy latest SHA): ./scripts/vps-deploy.sh
# or: make deploy
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/runtime.sh
source "${ROOT}/scripts/lib/runtime.sh"

usage() {
    cat <<'EOF'
Usage: ./scripts/deploy.sh [staging|prod] [--build] [--pull-only]

When staging|prod is omitted, APP_ENV in .env selects the stack
(production → prod, staging → staging).

Options:
  --build      Build locally using compose.build.yaml before deploy
  --pull-only  Pull image only (no up/migrate/cache/restart)
EOF
}

DEPLOY_ENV=""
USE_BUILD="${DEPLOY_BUILD:-0}"
PULL_ONLY=0

for arg in "$@"; do
    case "$arg" in
        prod|staging)
            DEPLOY_ENV="$arg"
            ;;
        dev)
            echo "Note: 'dev' deploy was renamed to 'staging'." >&2
            DEPLOY_ENV="staging"
            ;;
        --build)
            USE_BUILD=1
            ;;
        --pull-only)
            PULL_ONLY=1
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $arg" >&2
            usage
            exit 1
            ;;
    esac
done

if [[ -z "$DEPLOY_ENV" ]]; then
    runtime_load "$ROOT"
    if [[ "$RUNTIME" != "stack" ]]; then
        echo "deploy.sh requires APP_ENV=staging or production (got ${APP_ENV:-local}). Use Sail locally." >&2
        exit 1
    fi
fi

if [[ ! -f .env ]]; then
    echo "Missing .env — copy .env.production.example or .env.staging.example to .env and configure secrets." >&2
    exit 1
fi

# shellcheck source=scripts/lib/load-dotenv.sh
source "${ROOT}/scripts/lib/load-dotenv.sh"
dotenv_load .env

if [[ "$DEPLOY_ENV" == "prod" ]]; then
    if [[ ! -f docker/caddy/entrypoint.sh ]]; then
        echo "Missing docker/caddy/entrypoint.sh — pull the latest code from git." >&2
        exit 1
    fi

    if [[ -z "${APP_DOMAIN:-}" || -z "${ACME_EMAIL:-}" ]]; then
        echo "APP_DOMAIN and ACME_EMAIL are required in .env for production Caddy." >&2
        exit 1
    fi
fi

if [[ "$DEPLOY_ENV" == "staging" ]]; then
    if ! docker network inspect nerdik-edge >/dev/null 2>&1; then
        echo "Docker network nerdik-edge not found. Deploy production first (make deploy) so Caddy creates the shared edge network." >&2
        exit 1
    fi

    if [[ -z "${MAILPIT_UI_AUTH:-}" ]]; then
        echo "MAILPIT_UI_AUTH is required in staging .env (username:bcrypt for Mailpit UI)." >&2
        echo "Generate: docker run --rm axllent/mailpit:latest mailpit bcrypt 'your-secret'" >&2
        exit 1
    fi
fi

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

# Block public traffic before pull/build so visitors never hit a half-updated stack.
# Skip for --pull-only (no restart). Leave on if later steps fail (intentional).
if [[ "$PULL_ONLY" != "1" && "$DEPLOY_ENV" == "prod" && "${SKIP_MAINTENANCE:-0}" != "1" ]]; then
    "${ROOT}/scripts/maintenance.sh" on
fi

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
"${COMPOSE[@]}" exec -T app php artisan optimize
"${COMPOSE[@]}" exec -T app php artisan filament:optimize

log_retention_days="${LOG_DAILY_DAYS:-14}"
"${COMPOSE[@]}" exec -T app sh -c "find storage/logs -type f -name '*.log' -mtime +${log_retention_days} -delete 2>/dev/null || true"

"${COMPOSE[@]}" restart worker scheduler reverb

if ! "${COMPOSE[@]}" exec -T app php -r 'exit((gd_info()["WebP Support"] ?? false) ? 0 : 1);'; then
    echo "WARN: GD WebP support missing — image uploads will fail until the image is rebuilt." >&2
fi

if ! "${COMPOSE[@]}" exec -T app php -r 'exit(function_exists("imageavif") ? 0 : 1);'; then
    echo "WARN: GD AVIF support missing — AVIF conversions are skipped until the image is rebuilt." >&2
fi

printf 'NERDIK_IMAGE=%s\n' "${NERDIK_IMAGE}" > .nerdik-image
chmod 600 .nerdik-image

if [[ "$DEPLOY_ENV" == "prod" && "${SKIP_MAINTENANCE:-0}" != "1" ]]; then
    "${ROOT}/scripts/maintenance.sh" off
fi

echo "Deploy complete for ${DEPLOY_ENV}. Verify: curl -fsS \"${APP_URL:-https://localhost}/up\""
