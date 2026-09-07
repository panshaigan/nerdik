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

# Confirm a destructive Make/Artisan action when APP_ENV=production.
# Bypass with YES=1 (e.g. make fresh YES=1). Requires typing "production".
#
# Usage: runtime_confirm_production_destructive <label> <detail>
runtime_confirm_production_destructive() {
    local label="${1:-destructive command}"
    local detail="${2:-This will destroy or overwrite production data.}"

    if [[ "${APP_ENV:-}" != "production" ]]; then
        return 0
    fi

    if [[ "${YES:-0}" == "1" ]]; then
        return 0
    fi

    cat >&2 <<EOF

!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  WARNING: DESTRUCTIVE COMMAND ON PRODUCTION
!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
  Action:    ${label}
  APP_ENV:   ${APP_ENV}
  DEPLOY_ENV:${DEPLOY_ENV:-prod}

  ${detail}

  This targets the LIVE production database / stack.
  Prefer: make backup-prod
  Bypass (automation only): YES=1
!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

EOF

    if [[ ! -t 0 && "${NERDIK_DESTRUCTIVE_REQUIRE_TTY:-1}" == "1" ]]; then
        echo "Refusing destructive production command without a TTY. Re-run with YES=1 to confirm." >&2
        return 1
    fi

    printf 'Type "production" to continue: ' >&2
    local answer=""
    read -r answer || true

    if [[ "$answer" != "production" ]]; then
        echo "Aborted." >&2
        return 1
    fi

    return 0
}

# True when the first artisan argument is a destructive DB command.
runtime_is_destructive_artisan() {
    case "${1:-}" in
        migrate:fresh|migrate:refresh|migrate:reset|migrate:rollback|db:wipe|db:seed|app:init)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
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
