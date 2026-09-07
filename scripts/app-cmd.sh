#!/usr/bin/env bash
# Route day-to-day Make commands to Sail (local) or compose stack (VPS).
#
# Usage: ./scripts/app-cmd.sh <command> [args...]
#
# Commands: up down restart ps logs shell tinker migrate init fresh refresh
#           seed cache artisan regenerate-welcome-image boost
#
# Production: fresh/refresh/seed/init and destructive artisan require typing
# "production" (or YES=1). See runtime_confirm_production_destructive.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/runtime.sh
source "${ROOT}/scripts/lib/runtime.sh"
# shellcheck source=scripts/lib/boost.sh
source "${ROOT}/scripts/lib/boost.sh"

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
            # Boost is require-dev / local agents only — keep guidelines + MCP fresh.
            boost_update_and_verify "$SAIL"
        else
            stack_compose up -d
        fi
        ;;
    boost)
        if [[ "$RUNTIME" != "sail" ]]; then
            echo "make boost / app-cmd boost is Sail-only (local APP_ENV)." >&2
            exit 1
        fi
        boost_update_and_verify "$SAIL"
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
        runtime_confirm_production_destructive \
            'make init / app:init' \
            'Wipes all tables and leftover media, seeds base production data, and creates the first admin.' \
            || exit 1
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd artisan app:init "$@"
        else
            stack_exec app php artisan app:init --force "$@"
        fi
        ;;
    fresh)
        runtime_confirm_production_destructive \
            'make fresh / migrate:fresh' \
            'DROPS ALL TABLES and rebuilds the schema. All production data will be lost.' \
            || exit 1
        if [[ "$RUNTIME" == "sail" ]]; then
            sail_cmd artisan migrate:fresh "$@"
            sail_cmd artisan tags:recalculate-popularity
        else
            stack_exec_t app php artisan migrate:fresh --force "$@"
            stack_exec_t app php artisan tags:recalculate-popularity
        fi
        ;;
    refresh)
        runtime_confirm_production_destructive \
            'make refresh / migrate:refresh --seed' \
            'Rolls back and re-runs migrations, then seeds (including sample users). All production data will be lost.' \
            || exit 1
        if [[ "$RUNTIME" == "sail" ]]; then
            SEED_DATASET="${SEED_DATASET}" sail_cmd artisan migrate:refresh --seed "$@"
            sail_cmd artisan tags:recalculate-popularity
        else
            stack_exec_t app php artisan migrate:refresh --seed --force "$@"
            stack_exec_t app php artisan tags:recalculate-popularity
        fi
        ;;
    seed)
        runtime_confirm_production_destructive \
            'make seed / db:seed' \
            'Runs database seeders against production (may overwrite or duplicate data).' \
            || exit 1
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
        if runtime_is_destructive_artisan "${1:-}"; then
            runtime_confirm_production_destructive \
                "make artisan $*" \
                'Destructive Artisan command against the production database.' \
                || exit 1
        fi
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
