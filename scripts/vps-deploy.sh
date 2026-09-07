#!/usr/bin/env bash
# VPS deploy: git pull, resolve SHA, verify GHCR image, deploy this checkout's stack.
#
# Env comes from APP_ENV in .env (production → prod, staging → staging).
# Optional override: pass prod|staging, or set NERDIK_DEPLOY_ENV.
#
# Usage:
#   ./scripts/vps-deploy.sh                    # pull + deploy HEAD SHA
#   ./scripts/vps-deploy.sh --dry-run
#   ./scripts/vps-deploy.sh --no-pull
#   IMAGE_TAG=<sha> ./scripts/vps-deploy.sh --no-pull
#   ./scripts/vps-deploy.sh staging --no-pull  # explicit override
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/runtime.sh
source "${ROOT}/scripts/lib/runtime.sh"

usage() {
    cat <<'EOF'
Usage: ./scripts/vps-deploy.sh [prod|staging] [--dry-run] [--no-pull]

Arguments:
  prod|staging  Optional override (default: from APP_ENV in .env)

Options:
  --dry-run   Show commit SHA and GHCR image ref without deploying
  --no-pull   Skip git pull (deploy IMAGE_TAG env or current checkout SHA)

Environment:
  IMAGE_TAG           Optional explicit image tag (used with --no-pull from GitHub Actions)
  NERDIK_DEPLOY_ENV   Optional prod|staging override
EOF
}

OVERRIDE_ENV=""
NO_PULL=0
DRY_RUN=0

for arg in "$@"; do
    case "$arg" in
        prod|staging)
            OVERRIDE_ENV="$arg"
            ;;
        --no-pull)
            NO_PULL=1
            ;;
        --dry-run)
            DRY_RUN=1
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

if [[ -n "$OVERRIDE_ENV" ]]; then
    export NERDIK_DEPLOY_ENV="$OVERRIDE_ENV"
fi

if [[ ! -f .env ]]; then
    echo "Missing .env — copy .env.production.example or .env.staging.example to .env and configure secrets." >&2
    exit 1
fi

runtime_load "$ROOT"

if [[ "$RUNTIME" != "stack" ]]; then
    echo "make deploy / vps-deploy.sh require APP_ENV=staging or production (got ${APP_ENV}). Use Sail locally." >&2
    exit 1
fi

# shellcheck source=scripts/lib/load-dotenv.sh
source "${ROOT}/scripts/lib/load-dotenv.sh"
dotenv_load .env

if [[ -z "${GITHUB_OWNER:-}" ]]; then
    echo "GITHUB_OWNER is required in .env." >&2
    exit 1
fi

if [[ "$NO_PULL" == "0" ]]; then
    echo "Pulling latest changes..."
    git pull --ff-only
fi

if [[ -n "${IMAGE_TAG:-}" ]]; then
    SHA="${IMAGE_TAG}"
else
    SHA="$(git rev-parse HEAD)"
    export IMAGE_TAG="${SHA}"
fi

IMAGE="ghcr.io/${GITHUB_OWNER}/nerdik:${SHA}"

echo "Target environment: ${DEPLOY_ENV}"
echo "Target SHA: ${SHA}"
echo "Target image: ${IMAGE}"

if ! docker manifest inspect "${IMAGE}" >/dev/null 2>&1; then
    echo "GHCR image not found: ${IMAGE}" >&2
    echo "Wait for the Docker workflow on main to finish publishing this SHA, or deploy a different tag with IMAGE_TAG=<sha>." >&2
    exit 1
fi

if [[ "$DRY_RUN" == "1" ]]; then
    echo "Dry run complete — no deploy performed."
    exit 0
fi

echo "Deploying ${DEPLOY_ENV}..."
DEPLOY_BUILD_FLAG=()
if [[ -n "${BUILD:-}" ]]; then
    DEPLOY_BUILD_FLAG+=(--build)
fi

IMAGE_TAG="${IMAGE_TAG}" ./scripts/deploy.sh "${DEPLOY_ENV}" "${DEPLOY_BUILD_FLAG[@]}"

echo ""
echo "Deploy complete."
echo "  Environment: ${DEPLOY_ENV}"
echo "  SHA:         ${SHA}"
echo "  Image:       ${IMAGE}"
echo "  Verify:      curl -fsS \"${APP_URL:-https://localhost}/up\""
