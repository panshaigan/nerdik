#!/usr/bin/env bash
# Resolve Sail vs Docker stack runtime from APP_ENV in the checkout .env.
#
# Usage:
#   source scripts/lib/runtime.sh
#   runtime_load [/path/to/project/root]
#
# Sets:
#   RUNTIME_ROOT     Absolute project root
#   RUNTIME          sail | stack
#   DEPLOY_ENV       prod | staging  (empty when RUNTIME=sail)
#   APP_ENV          Value from .env (defaults to local if unset)
#
# Optional override: NERDIK_DEPLOY_ENV=prod|staging forces stack + that env.

runtime_load() {
    local root="${1:-}"
    local env_file app_env override

    if [[ -z "$root" ]]; then
        if [[ -n "${RUNTIME_ROOT:-}" ]]; then
            root="$RUNTIME_ROOT"
        else
            root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
        fi
    fi

    RUNTIME_ROOT="$(cd "$root" && pwd)"
    env_file="${RUNTIME_ROOT}/.env"

    if [[ ! -f "$env_file" ]]; then
        echo "Missing .env in ${RUNTIME_ROOT}" >&2
        return 1
    fi

    # shellcheck source=scripts/lib/load-dotenv.sh
    source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/load-dotenv.sh"
    dotenv_load "$env_file"

    app_env="${APP_ENV:-local}"
    APP_ENV="$app_env"
    override="${NERDIK_DEPLOY_ENV:-}"

    if [[ -n "$override" ]]; then
        case "$override" in
            prod|staging)
                RUNTIME=stack
                DEPLOY_ENV="$override"
                return 0
                ;;
            *)
                echo "Invalid NERDIK_DEPLOY_ENV=${override} (expected prod or staging)." >&2
                return 1
                ;;
        esac
    fi

    case "$app_env" in
        local)
            RUNTIME=sail
            DEPLOY_ENV=""
            ;;
        staging)
            RUNTIME=stack
            DEPLOY_ENV=staging
            ;;
        production)
            RUNTIME=stack
            DEPLOY_ENV=prod
            ;;
        *)
            echo "Unsupported APP_ENV=${app_env} (expected local, staging, or production)." >&2
            return 1
            ;;
    esac
}

# Print RUNTIME and DEPLOY_ENV for tests / debugging.
# Usage: runtime_print [/path/to/project/root]
runtime_print() {
    runtime_load "${1:-}" || return 1
    printf 'RUNTIME=%s\n' "$RUNTIME"
    printf 'DEPLOY_ENV=%s\n' "${DEPLOY_ENV:-}"
    printf 'APP_ENV=%s\n' "$APP_ENV"
}

# Allow: ./scripts/lib/runtime.sh --print [/path/to/root]
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    case "${1:-}" in
        --print)
            runtime_print "${2:-}"
            ;;
        -h|--help)
            cat <<'EOF'
Usage: ./scripts/lib/runtime.sh --print [project-root]

Print RUNTIME, DEPLOY_ENV, and APP_ENV for the checkout.
EOF
            ;;
        *)
            echo "Usage: $0 --print [project-root]" >&2
            exit 1
            ;;
    esac
fi
