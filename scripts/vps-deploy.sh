#!/usr/bin/env bash
# VPS deploy: git pull, resolve SHA, verify GHCR image, deploy prod or staging.
#
# Usage:
#   ./scripts/vps-deploy.sh                    # prod: pull + deploy HEAD SHA
#   ./scripts/vps-deploy.sh staging          # staging: pull + deploy HEAD SHA
#   ./scripts/vps-deploy.sh --dry-run          # prod dry run
#   ./scripts/vps-deploy.sh staging --no-pull  # staging deploy current checkout SHA
#   IMAGE_TAG=<sha> ./scripts/vps-deploy.sh staging --no-pull
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

usage() {
    cat <<'EOF'
Usage: ./scripts/vps-deploy.sh [prod|staging] [--dry-run] [--no-pull]

Arguments:
  prod       Deploy production (default)
  staging    Deploy staging

Options:
  --dry-run   Show commit SHA and GHCR image ref without deploying
  --no-pull   Skip git pull (deploy IMAGE_TAG env or current checkout SHA)

Environment:
  IMAGE_TAG   Optional explicit image tag (used with --no-pull from GitHub Actions)
EOF
}

DEPLOY_ENV="prod"
NO_PULL=0
DRY_RUN=0

for arg in "$@"; do
    case "$arg" in
        prod|staging)
            DEPLOY_ENV="$arg"
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

env_example=".env.production.example"
deploy_make_target="prod-deploy"

if [[ "$DEPLOY_ENV" == "staging" ]]; then
    env_example=".env.staging.example"
    deploy_make_target="staging-deploy"
fi

if [[ ! -f .env ]]; then
    echo "Missing .env — copy ${env_example} to .env and configure secrets." >&2
    exit 1
fi

# shellcheck disable=SC1091
set -a
source .env
set +a

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
make "${deploy_make_target}"

echo ""
echo "Deploy complete."
echo "  Environment: ${DEPLOY_ENV}"
echo "  SHA:         ${SHA}"
echo "  Image:       ${IMAGE}"
echo "  Verify:      curl -fsS \"${APP_URL:-https://localhost}/up\""
