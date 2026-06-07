#!/usr/bin/env bash
# Production VPS deploy: git pull, resolve SHA, verify GHCR image, prod-deploy.
#
# Usage:
#   ./scripts/vps-deploy.sh              # pull + deploy HEAD SHA
#   ./scripts/vps-deploy.sh --dry-run    # show SHA and image without deploying
#   ./scripts/vps-deploy.sh --no-pull    # deploy current checkout SHA (or IMAGE_TAG)
#   IMAGE_TAG=<sha> ./scripts/vps-deploy.sh --no-pull
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

usage() {
    cat <<'EOF'
Usage: ./scripts/vps-deploy.sh [--dry-run] [--no-pull]

Options:
  --dry-run   Show commit SHA and GHCR image ref without deploying
  --no-pull   Skip git pull (deploy IMAGE_TAG env or current checkout SHA)

Environment:
  IMAGE_TAG   Optional explicit image tag (used with --no-pull from GitHub Actions)
EOF
}

NO_PULL=0
DRY_RUN=0

for arg in "$@"; do
    case "$arg" in
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

if [[ ! -f .env ]]; then
    echo "Missing .env — copy .env.production.example to .env and configure secrets." >&2
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

echo "Deploying production..."
make prod-deploy

echo ""
echo "Deploy complete."
echo "  SHA:    ${SHA}"
echo "  Image:  ${IMAGE}"
echo "  Verify: curl -fsS \"${APP_URL:-https://localhost}/up\""
