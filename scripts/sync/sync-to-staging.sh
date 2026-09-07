#!/usr/bin/env bash
# Push local Sail data to VPS staging (make staging look like current dev).
#
# Usage:
#   ./scripts/sync/sync-to-staging.sh [--yes] [--backup] [--dry-run] [--db-only] [--storage-only] [tables TABLE ...]
#   make sync-to-staging
#   make sync-to-staging YES=1 BACKUP=1
#
# Requires APP_ENV=local and .env.sync with SYNC_SSH_HOST.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT}/scripts/sync/common.sh"
# shellcheck source=scripts/lib/runtime.sh
source "${ROOT}/scripts/lib/runtime.sh"

usage() {
    cat <<'EOF'
Usage: ./scripts/sync/sync-to-staging.sh [--yes] [--backup] [--dry-run] [--db-only] [--storage-only] [tables TABLE ...]

Export local Sail (APP_ENV=local), SCP to the VPS, and import into staging
(/opt/nerdik-staging by default).

Requires .env.sync with SYNC_SSH_HOST (and optionally SYNC_SSH_KEY, SYNC_SSH_PORT, SYNC_STAGING_PATH).
EOF
}

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

if [[ "$APP_ENV" != "local" ]]; then
    sync_die "sync-to-staging is local-only (got APP_ENV=${APP_ENV}). Run from your Sail checkout."
fi

sync_load_sync_config

if [[ -z "${SYNC_SSH_HOST}" ]]; then
    sync_die "SYNC_SSH_HOST is not set. Copy .env.sync.example to .env.sync and configure it."
fi

sync_validate_ssh_key

SSH_TARGET="${SYNC_SSH_USER}@${SYNC_SSH_HOST}"
SSH_OPTS=()
SCP_OPTS=()
sync_ssh_opts SSH_OPTS ssh
sync_ssh_opts SCP_OPTS scp

LOCAL_EXPORT_DIR="$(sync_default_export_dir)"
REMOTE_EXPORT_DIR="/tmp/nerdik-sync-from-local-$(sync_export_timestamp)"

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

cleanup() {
    if [[ -d "$LOCAL_EXPORT_DIR" && "$LOCAL_EXPORT_DIR" == /tmp/nerdik-sync-* ]]; then
        rm -rf "$LOCAL_EXPORT_DIR"
    fi
}
trap cleanup EXIT

sync_log "exporting local Sail to ${LOCAL_EXPORT_DIR}"
LOCAL_EXPORT_DIR="$("${ROOT}/scripts/sync/export-from-env.sh" local "$LOCAL_EXPORT_DIR" "${EXPORT_FLAGS[@]+"${EXPORT_FLAGS[@]}"}")"

if [[ "$SYNC_DRY_RUN" == "1" ]]; then
    sync_log "[dry-run] scp ${LOCAL_EXPORT_DIR} → ${SSH_TARGET}:${REMOTE_EXPORT_DIR}"
    REMOTE_IMPORT="cd $(printf '%q' "$SYNC_STAGING_PATH") && ./scripts/sync/import-to-env.sh staging $(printf '%q' "$REMOTE_EXPORT_DIR") ${IMPORT_FLAGS[*]}"
    sync_run ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "$REMOTE_IMPORT"
    sync_log "local → staging dry-run complete"
    exit 0
fi

sync_log "uploading export to ${SSH_TARGET}:${REMOTE_EXPORT_DIR}"
sync_run ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "mkdir -p $(printf '%q' "$REMOTE_EXPORT_DIR")"

if [[ "$SYNC_STORAGE_ONLY" != "1" ]]; then
    sync_run scp "${SCP_OPTS[@]}" "${LOCAL_EXPORT_DIR}/db.sql.gz" "${SSH_TARGET}:${REMOTE_EXPORT_DIR}/"
fi

if [[ "$SYNC_DB_ONLY" != "1" ]]; then
    sync_run scp "${SCP_OPTS[@]}" "${LOCAL_EXPORT_DIR}/storage-app.tar.gz" "${SSH_TARGET}:${REMOTE_EXPORT_DIR}/"
fi

REMOTE_IMPORT="cd $(printf '%q' "$SYNC_STAGING_PATH") && ./scripts/sync/import-to-env.sh staging $(printf '%q' "$REMOTE_EXPORT_DIR") ${IMPORT_FLAGS[*]}"
sync_log "importing into staging on ${SSH_TARGET}"
sync_run ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "$REMOTE_IMPORT"

sync_log "cleaning up remote export ${REMOTE_EXPORT_DIR}"
sync_run ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "rm -rf $(printf '%q' "$REMOTE_EXPORT_DIR")"

sync_log "local → staging sync complete"
