#!/usr/bin/env bash
# Resolve NERDIK_IMAGE for docker compose CLI (exec, ps, down) on the VPS.
#
# Usage: eval "$(./scripts/compose-env.sh prod)"
#        eval "$(./scripts/compose-env.sh staging)"
#
# Resolution order:
#   1. Running app container image (matches what is actually deployed)
#   2. .nerdik-image written by the last deploy
#   3. NERDIK_IMAGE in .env (optional manual override)
#   4. IMAGE_TAG + GITHUB_OWNER from .env
#   5. git HEAD + GITHUB_OWNER from .env
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

DEPLOY_ENV="${1:-prod}"

case "$DEPLOY_ENV" in
    prod)
        COMPOSE_PROJECT="nerdik-prod"
        ;;
    staging)
        COMPOSE_PROJECT="nerdik-staging"
        ;;
    dev)
        echo "Note: use 'staging' instead of 'dev'." >&2
        COMPOSE_PROJECT="nerdik-staging"
        ;;
    *)
        echo "Usage: $0 <prod|staging>" >&2
        exit 1
        ;;
esac

if [[ ! -f .env ]]; then
    echo "Missing .env in ${ROOT}" >&2
    exit 1
fi

# shellcheck source=scripts/lib/load-dotenv.sh
source "${ROOT}/scripts/lib/load-dotenv.sh"
dotenv_load .env

running_app_image() {
    local container_id=""

    container_id="$(docker ps -q \
        --filter "label=com.docker.compose.project=${COMPOSE_PROJECT}" \
        --filter "label=com.docker.compose.service=app" \
        | head -n1)"

    if [[ -n "$container_id" ]]; then
        docker inspect --format '{{.Config.Image}}' "$container_id"
    fi
}

if [[ -z "${NERDIK_IMAGE:-}" ]]; then
    NERDIK_IMAGE="$(running_app_image || true)"
fi

if [[ -z "${NERDIK_IMAGE:-}" && -f .nerdik-image ]]; then
    # shellcheck disable=SC1091
    source .nerdik-image
fi

if [[ -z "${NERDIK_IMAGE:-}" ]]; then
    if [[ -z "${GITHUB_OWNER:-}" ]]; then
        echo "Could not resolve NERDIK_IMAGE. Deploy the stack first, or set GITHUB_OWNER in .env." >&2
        exit 1
    fi

    tag="${IMAGE_TAG:-$(git rev-parse HEAD)}"
    NERDIK_IMAGE="ghcr.io/${GITHUB_OWNER}/nerdik:${tag}"
fi

printf 'export NERDIK_IMAGE=%q\n' "${NERDIK_IMAGE}"
