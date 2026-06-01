#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
    echo "Missing .env — copy .env.production.example to .env and configure GITHUB_OWNER." >&2
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

SHA="$(git rev-parse HEAD)"
IMAGE="ghcr.io/${GITHUB_OWNER}/nerdik:${SHA}"

export NERDIK_IMAGE="$IMAGE"

docker compose -f compose.stack.yaml -f compose.build.yaml -f compose.prod.yaml build app
docker compose -f compose.stack.yaml -f compose.build.yaml -f compose.prod.yaml push app

echo "Published image: ${IMAGE}"
