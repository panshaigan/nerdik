#!/usr/bin/env bash
# Pull production data into this checkout (local Sail or VPS staging).
#
# Usage:
#   ./scripts/sync/sync-from-prod.sh [--yes] [--backup] [--dry-run] [--db-only] [--storage-only] [tables TABLE ...]
#   make sync-from-prod
#   make sync-from-prod YES=1 DB=1
#   make sync-from-prod tables users activities
#
# APP_ENV=local     → SSH pull prod into local Sail
# APP_ENV=staging   → on VPS: export /opt/nerdik into this staging checkout
# APP_ENV=production → blocked
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT}/scripts/sync/common.sh"
# shellcheck source=scripts/lib/runtime.sh
source "${ROOT}/scripts/lib/runtime.sh"

usage() {
    cat <<'EOF'
Usage: ./scripts/sync/sync-from-prod.sh [--yes] [--backup] [--dry-run] [--db-only] [--storage-only] [tables TABLE ...]

Routes by APP_ENV in this checkout's .env:
  local       SSH pull production into local Sail (.env.sync required)
  staging     On VPS: export production clone into this staging checkout
  production  Blocked

With tables TABLE..., only the listed tables are synced (implies --db-only).
EOF
}

POSITIONAL=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --yes)
            SYNC_YES=1
            ;;
        --backup)
            SYNC_BACKUP=1
            ;;
        --dry-run)
            SYNC_DRY_RUN=1
            ;;
        --db-only)
            SYNC_DB_ONLY=1
            ;;
        --storage-only)
            SYNC_STORAGE_ONLY=1
            ;;
        --tables)
            shift
            while [[ $# -gt 0 && "$1" != --* && "$1" != "tables" ]]; do
                SYNC_TABLES+=("$1")
                shift
            done
            continue
            ;;
        tables)
            shift
            while [[ $# -gt 0 && "$1" != --* ]]; do
                SYNC_TABLES+=("$1")
                shift
            done
            continue
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            sync_die "unexpected argument: $1 (use: tables TABLE... or --tables TABLE...)"
            ;;
    esac
    shift
done

sync_apply_tables_db_only

CHECKOUT_ROOT="${NERDIK_CHECKOUT_ROOT:-$ROOT}"
runtime_load "$CHECKOUT_ROOT"

build_flags() {
    local -n _out=$1
    _out=()
    if [[ "$SYNC_YES" == "1" ]]; then
        _out+=(--yes)
    fi
    if [[ "$SYNC_BACKUP" == "1" ]]; then
        _out+=(--backup)
    fi
    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        _out+=(--dry-run)
    fi
    if [[ "$SYNC_DB_ONLY" == "1" ]]; then
        _out+=(--db-only)
    fi
    if [[ "$SYNC_STORAGE_ONLY" == "1" ]]; then
        _out+=(--storage-only)
    fi
    sync_append_tables_flags _out
}

case "$APP_ENV" in
    local)
        FLAGS=()
        build_flags FLAGS
        # pull-from-prod does not support --backup
        FILTERED=()
        for f in "${FLAGS[@]+"${FLAGS[@]}"}"; do
            if [[ "$f" != "--backup" ]]; then
                FILTERED+=("$f")
            fi
        done
        if [[ "$SYNC_BACKUP" == "1" ]]; then
            sync_log "note: --backup is ignored for local sync-from-prod (use staging path for backups)"
        fi
        exec "${ROOT}/scripts/sync/pull-from-prod.sh" "${FILTERED[@]+"${FILTERED[@]}"}"
        ;;
    staging)
        sync_load_sync_config

        if [[ ! -d "${SYNC_PROD_PATH}" ]]; then
            sync_die "production checkout not found at ${SYNC_PROD_PATH}"
        fi

        EXPORT_DIR="$(sync_default_export_dir)"
        EXPORT_FLAGS=()
        IMPORT_FLAGS=()

        if [[ "$SYNC_DB_ONLY" == "1" ]]; then
            EXPORT_FLAGS+=(--db-only)
            IMPORT_FLAGS+=(--db-only)
        fi
        if [[ "$SYNC_STORAGE_ONLY" == "1" ]]; then
            EXPORT_FLAGS+=(--storage-only)
            IMPORT_FLAGS+=(--storage-only)
        fi
        if [[ "$SYNC_DRY_RUN" == "1" ]]; then
            EXPORT_FLAGS+=(--dry-run)
            IMPORT_FLAGS+=(--dry-run)
        fi
        if [[ "$SYNC_YES" == "1" ]]; then
            IMPORT_FLAGS+=(--yes)
        fi
        if [[ "$SYNC_BACKUP" == "1" ]]; then
            IMPORT_FLAGS+=(--backup)
        fi
        sync_append_tables_flags EXPORT_FLAGS
        sync_append_tables_flags IMPORT_FLAGS

        sync_log "exporting production (${SYNC_PROD_PATH}) to ${EXPORT_DIR}"
        EXPORT_DIR="$("${SYNC_PROD_PATH}/scripts/sync/export-from-env.sh" prod "$EXPORT_DIR" "${EXPORT_FLAGS[@]+"${EXPORT_FLAGS[@]}"}")"

        # Import into this staging checkout (not a path heuristic)
        export SYNC_STAGING_PATH="$ROOT"
        sync_log "importing into staging (${SYNC_STAGING_PATH})"
        sync_run env SYNC_STAGING_PATH="$ROOT" "${ROOT}/scripts/sync/import-to-env.sh" staging "$EXPORT_DIR" "${IMPORT_FLAGS[@]+"${IMPORT_FLAGS[@]}"}"

        if [[ "$SYNC_DRY_RUN" != "1" ]]; then
            sync_cleanup_export_dir "$EXPORT_DIR"
        fi

        sync_log "production → staging sync complete"
        ;;
    production)
        sync_die "sync-from-prod is blocked on production. Run from local or staging."
        ;;
    *)
        sync_die "unsupported APP_ENV=${APP_ENV} for sync-from-prod"
        ;;
esac
