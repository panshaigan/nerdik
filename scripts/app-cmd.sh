#!/usr/bin/env bash
# Route day-to-day Make commands to Sail (local) or compose stack (VPS).
#
# Usage: ./scripts/app-cmd.sh <command> [args...]
#
# Commands: up down restart ps logs shell tinker migrate init fresh refresh
#           seed cache artisan regenerate-welcome-image
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/runtime.sh
source "${ROOT}/scripts/lib/runtime.sh"

SAIL="${SAIL:-./vendor/bin/sail}"
SEED_DATASET="${SEED_DATASET:-minimal}"

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <command> [args...]" >&2
    exit 1
fi

CMD="$1"
shift

runtime_load "$ROOT"

sail_cmd() {
    if [[ ! -x "$SAIL" ]]; then
        echo "Sail not found at ${SAIL}. Run composer install locally." >&2
        exit 1
    fi
    "$SAIL" "$@"
}

stack_exec() {
    ./scripts/compose-exec.sh "${DEPLOY_ENV}" exec "$@"
}

stack_exec_t() {
    ./scripts/compose-exec.sh "${DEPLOY_ENV}" exec -T "$@"
}

stack_compose() {
    ./scripts/compose-exec.sh "${DEPLOY_ENV}" "$@"
}

case "$CMD" in
    up)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd up -d
        else
            stack_compose up -d
        fi
        ;;
    down)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd down
        else
            stack_compose down
        fi
        ;;
    restart)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd down
            sail_cmd up -d
        else
            stack_compose restart
        fi
        ;;
    ps)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd ps
        else
            stack_compose ps
        fi
        ;;
    logs)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd logs -f
        else
            stack_compose logs -f
        fi
        ;;
    shell)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd shell
        else
            stack_exec app bash
        fi
        ;;
    tinker)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd tinker
        else
            stack_exec app php artisan tinker
        fi
        ;;
    migrate)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd artisan migrate "$@"
        else
            stack_exec_t app php artisan migrate --force "$@"
        fi
        ;;
    init)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd artisan app:init "$@"
        else
            stack_exec app php artisan app:init --force "$@"
        fi
        ;;
    fresh)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd artisan migrate:fresh "$@"
            sail_cmd artisan tags:recalculate-popularity
        else
            stack_exec_t app php artisan migrate:fresh --force "$@"
            stack_exec_t app php artisan tags:recalculate-popularity
        fi
        ;;
    refresh)
        if [[ "$RUNTIME" == "sail" ]]; then
            SEED_DATASET="${SEED_DATASET}" sail_cmd artisan migrate:refresh --seed "$@"
            sail_cmd artisan tags:recalculate-popularity
        else
            stack_exec_t app php artisan migrate:refresh --seed --force "$@"
            stack_exec_t app php artisan tags:recalculate-popularity
        fi
        ;;
    seed)
        if [[ "$RUNTIME" == "sail" ]]; then
            SEED_DATASET="${SEED_DATASET}" sail_cmd artisan db:seed "$@"
        else
            SEED_DATASET="${SEED_DATASET}" stack_exec_t app php artisan db:seed --force "$@"
        fi
        ;;
    cache)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd artisan optimize:clear "$@"
        else
            stack_exec_t app php artisan optimize:clear "$@"
        fi
        ;;
    artisan)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd artisan "$@"
        else
            # Interactive by default; callers that need -T can use compose-exec directly.
            if [[ -t 0 ]]; then
                stack_exec app php artisan "$@"
            else
                stack_exec_t app php artisan "$@"
            fi
        fi
        ;;
    regenerate-welcome-image)
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd artisan cache:forget welcome.hero_tag_image
        else
            stack_exec_t app php artisan cache:forget welcome.hero_tag_image
        fi
        ;;
    *)
        echo "Unknown app-cmd: ${CMD}" >&2
        exit 1
        ;;
esac
