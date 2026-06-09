#!/usr/bin/env bash
# Shared helpers for prod → local/staging data sync scripts.
set -euo pipefail

SYNC_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

SYNC_DRY_RUN="${SYNC_DRY_RUN:-0}"
SYNC_YES="${SYNC_YES:-0}"
SYNC_BACKUP="${SYNC_BACKUP:-0}"
SYNC_DB_ONLY="${SYNC_DB_ONLY:-0}"
SYNC_STORAGE_ONLY="${SYNC_STORAGE_ONLY:-0}"

sync_log() {
    printf '[sync] %s\n' "$*" >&2
}

sync_die() {
    sync_log "error: $*"
    exit 1
}

sync_run() {
    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_log "[dry-run] $*"
        return 0
    fi

    sync_log "+ $*"
    "$@"
}

sync_load_dotenv() {
    local env_file="$1"

    if [[ ! -f "$env_file" ]]; then
        sync_die "missing env file: ${env_file}"
    fi

    # shellcheck disable=SC1090
    set -a
    # shellcheck disable=SC1090
    source "$env_file"
    set +a
}

sync_load_sync_config() {
    local sync_env="${SYNC_ROOT}/.env.sync"

    if [[ -f "$sync_env" ]]; then
        sync_load_dotenv "$sync_env"
    fi

    SYNC_SSH_HOST="${SYNC_SSH_HOST:-}"
    SYNC_SSH_USER="${SYNC_SSH_USER:-deploy}"
    SYNC_SSH_KEY="${SYNC_SSH_KEY:-}"
    SYNC_PROD_PATH="${SYNC_PROD_PATH:-/opt/nerdik}"
    SYNC_STAGING_PATH="${SYNC_STAGING_PATH:-/opt/nerdik-staging}"
}

sync_ssh_opts() {
    local -n _out=$1

    _out=()

    if [[ -n "${SYNC_SSH_KEY:-}" ]]; then
        local expanded_key="${SYNC_SSH_KEY/#\~/$HOME}"
        _out+=(-i "$expanded_key")
    fi

    _out+=(-o BatchMode=yes -o StrictHostKeyChecking=accept-new)
}

sync_confirm() {
    local message="$1"

    if [[ "$SYNC_YES" == "1" || "$SYNC_DRY_RUN" == "1" ]]; then
        return 0
    fi

    printf '%s [y/N] ' "$message" >&2
    local answer=""
    read -r answer

    case "$answer" in
        y|Y|yes|YES)
            return 0
            ;;
        *)
            sync_die "aborted"
            ;;
    esac
}

sync_export_timestamp() {
    date +%Y%m%d-%H%M%S
}

sync_default_export_dir() {
    printf '/tmp/nerdik-sync-%s' "$(sync_export_timestamp)"
}

sync_compose_files_for_env() {
    local deploy_env="$1"
    local -n _files=$2

    case "$deploy_env" in
        prod)
            _files=(-f compose.stack.yaml -f compose.prod.yaml)
            ;;
        staging)
            _files=(-f compose.stack.yaml -f compose.staging.yaml)
            ;;
        *)
            sync_die "unsupported deploy env: ${deploy_env} (expected prod or staging)"
            ;;
    esac
}

sync_volume_for_env() {
    local kind="$1"
    local deploy_env="$2"

    case "${kind}:${deploy_env}" in
        storage:prod) echo "nerdik_storage" ;;
        storage:staging) echo "nerdik_staging_storage" ;;
        pgsql:prod) echo "nerdik_pgsql_data" ;;
        pgsql:staging) echo "nerdik_staging_pgsql_data" ;;
        *)
            sync_die "unknown volume kind/env: ${kind}/${deploy_env}"
            ;;
    esac
}

sync_prepare_compose_env() {
    local deploy_env="$1"
    local project_root="$2"

    if [[ ! -f "${project_root}/.env" ]]; then
        sync_die "missing .env in ${project_root}"
    fi

    sync_load_dotenv "${project_root}/.env"

    if [[ "$deploy_env" != "local" ]]; then
        # shellcheck disable=SC1090
        eval "$("${SYNC_ROOT}/scripts/compose-env.sh" "${deploy_env}")"
    fi
}

sync_compose_cmd() {
    local deploy_env="$1"
    local project_root="$2"
    shift 2
    local -a compose_files=()

    sync_compose_files_for_env "$deploy_env" compose_files

    (
        cd "$project_root"
        sync_prepare_compose_env "$deploy_env" "$project_root"
        docker compose "${compose_files[@]}" "$@"
    )
}

sync_sail_cmd() {
  local project_root="$1"
  shift

  (
    cd "$project_root"
    if [[ ! -x "${project_root}/vendor/bin/sail" ]]; then
        sync_die "Sail not found. Run composer install and make up first."
    fi
    "${project_root}/vendor/bin/sail" "$@"
  )
}

sync_require_export_artifacts() {
    local export_dir="$1"
    local require_db="${2:-1}"
    local require_storage="${3:-1}"

    if [[ "$require_db" == "1" && ! -f "${export_dir}/db.sql.gz" ]]; then
        sync_die "missing ${export_dir}/db.sql.gz"
    fi

    if [[ "$require_storage" == "1" && ! -f "${export_dir}/storage-app.tar.gz" ]]; then
        sync_die "missing ${export_dir}/storage-app.tar.gz"
    fi
}

sync_truncate_volatile_tables_sql() {
    cat <<'SQL'
TRUNCATE TABLE
    failed_jobs,
    job_batches,
    jobs,
    cache_locks,
    cache,
    sessions
RESTART IDENTITY CASCADE;
SQL
}

sync_artisan_post_import() {
    local deploy_env="$1"
    local project_root="$2"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_log "[dry-run] artisan post-import steps for ${deploy_env}"
        return 0
    fi

    if [[ "$deploy_env" == "local" ]]; then
        sync_sail_cmd "$project_root" artisan storage:link --force
        sync_sail_cmd "$project_root" artisan optimize:clear
        sync_sail_cmd "$project_root" artisan tags:recalculate-popularity
        return 0
    fi

    sync_compose_cmd "$deploy_env" "$project_root" exec -T app php artisan storage:link --force
    sync_compose_cmd "$deploy_env" "$project_root" exec -T app php artisan optimize:clear
    sync_compose_cmd "$deploy_env" "$project_root" exec -T app php artisan tags:recalculate-popularity
}

sync_truncate_volatile_tables() {
    local deploy_env="$1"
    local project_root="$2"
    local sql
    sql="$(sync_truncate_volatile_tables_sql)"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_log "[dry-run] truncate volatile tables on ${deploy_env}"
        return 0
    fi

    if [[ "$deploy_env" == "local" ]]; then
        sync_sail_cmd "$project_root" exec -T pgsql psql \
            -U "${DB_USERNAME}" -d "${DB_DATABASE}" \
            -v ON_ERROR_STOP=1 \
            -c "$sql"
        return 0
    fi

    sync_compose_cmd "$deploy_env" "$project_root" exec -T pgsql psql \
        -U "${DB_USERNAME}" -d "${DB_DATABASE}" \
        -v ON_ERROR_STOP=1 \
        -c "$sql"
}

sync_cleanup_export_dir() {
    local export_dir="$1"

    if [[ -d "$export_dir" && "$export_dir" == /tmp/nerdik-sync-* ]]; then
        sync_run rm -rf "$export_dir"
    fi
}
