#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -z "${GITHUB_OWNER:-}" && -f .env ]]; then
    # shellcheck disable=SC1091
    set -a
    source .env
    set +a
fi

if [[ -z "${GITHUB_OWNER:-}" ]]; then
    echo "GITHUB_OWNER is required (set in the environment or in .env)." >&2
    exit 1
fi

SHA="${GIT_SHA:-${GITHUB_SHA:-}}"
if [[ -z "$SHA" ]]; then
    SHA="$(git rev-parse HEAD)"
fi

IMAGE="ghcr.io/${GITHUB_OWNER}/nerdik:${SHA}"

export NERDIK_IMAGE="$IMAGE"

docker compose -f compose.stack.yaml -f compose.build.yaml -f compose.prod.yaml build app
docker compose -f compose.stack.yaml -f compose.build.yaml -f compose.prod.yaml push app

echo "Published image: ${IMAGE}"
