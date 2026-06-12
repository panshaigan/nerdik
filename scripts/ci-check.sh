#!/usr/bin/env bash
# Run local checks that mirror GitHub CI (ci.yml + optional docker.yml build).
#
# Usage:
#   ./scripts/ci-check.sh          # all CI checks except Docker image build
#   FULL=1 ./scripts/ci-check.sh   # include production Docker image build
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SAIL="${SAIL:-./vendor/bin/sail}"
FULL="${FULL:-0}"

step() {
    echo ""
    echo "==> $*"
}

run_gitleaks() {
    step "Gitleaks"

    if command -v gitleaks >/dev/null 2>&1; then
        gitleaks detect --source . --config .gitleaks.toml --verbose
        return
    fi

    docker run --rm \
        -v "$ROOT:/repo" \
        -w /repo \
        gitleaks/gitleaks:latest \
        detect --source . --config .gitleaks.toml --verbose
}

run_compose() {
    local compose_env=(--env-file .env.compose.ci)

    step "Compose (production)"
    docker compose "${compose_env[@]}" -f compose.stack.yaml -f compose.prod.yaml config --quiet

    step "Compose (staging)"
    docker compose "${compose_env[@]}" -f compose.stack.yaml -f compose.staging.yaml config --quiet
}

ensure_sail_running() {
    step "Checking Sail"

    if [[ ! -x "$SAIL" ]]; then
        echo "Sail not found at $SAIL. Run: make composer-install" >&2
        exit 1
    fi

    if ! "$SAIL" ps --status running --format '{{.Name}}' 2>/dev/null | grep -q .; then
        echo "Sail containers are not running. Start them with: make up" >&2
        exit 1
    fi
}

run_test_job() {
    step "npm ci"
    "$SAIL" npm ci

    step "npm run build"
    "$SAIL" npm run build

    step "php artisan migrate --force"
    "$SAIL" artisan migrate --force

    step "php artisan test --compact"
    "$SAIL" artisan test --compact

    step "composer audit"
    "$SAIL" composer audit
}

run_pint() {
    step "Pint"
    "$SAIL" bin pint --test
}

run_docker_build() {
    step "Docker image build"
    docker build -f docker/production/Dockerfile --target runtime \
        --build-arg VITE_REVERB_APP_KEY=ci-placeholder \
        --build-arg VITE_REVERB_HOST=localhost \
        --build-arg VITE_REVERB_PORT=443 \
        --build-arg VITE_REVERB_SCHEME=https \
        --build-arg VITE_APP_NAME=nerdik \
        -t nerdik:ci-check .
}

run_gitleaks
run_compose
ensure_sail_running
run_test_job
run_pint

if [[ "$FULL" == "1" ]]; then
    run_docker_build
fi

echo ""
echo "All CI checks passed."
