#!/usr/bin/env bash
# Pull production database + storage from VPS via SSH and import into local Sail.
#
# Usage:
#   ./scripts/sync/pull-from-prod.sh [--yes] [--dry-run] [--db-only] [--storage-only]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT}/scripts/sync/common.sh"

usage() {
    cat <<'EOF'
Usage: ./scripts/sync/pull-from-prod.sh [--yes] [--dry-run] [--db-only] [--storage-only]

Requires .env.sync with SYNC_SSH_HOST (and optionally SYNC_SSH_PORT, SYNC_SSH_KEY, SYNC_PROD_PATH).
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --yes)
            SYNC_YES=1
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
        -h|--help)
            usage
            exit 0
            ;;
        *)
            sync_die "unexpected argument: $1"
            ;;
    esac
    shift
done

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

REMOTE_EXPORT_DIR="$(sync_default_export_dir)"
LOCAL_EXPORT_DIR="$(mktemp -d "${TMPDIR:-/tmp}/nerdik-sync-local.XXXXXX")"

cleanup() {
    if [[ -d "$LOCAL_EXPORT_DIR" ]]; then
        rm -rf "$LOCAL_EXPORT_DIR"
    fi
}
trap cleanup EXIT

sync_log "remote export on ${SSH_TARGET}:${SYNC_PROD_PATH} → ${REMOTE_EXPORT_DIR}"

EXPORT_FLAGS=()
if [[ "$SYNC_DB_ONLY" == "1" ]]; then
    EXPORT_FLAGS+=(--db-only)
fi
if [[ "$SYNC_STORAGE_ONLY" == "1" ]]; then
    EXPORT_FLAGS+=(--storage-only)
fi
if [[ "$SYNC_DRY_RUN" == "1" ]]; then
    EXPORT_FLAGS+=(--dry-run)
fi

REMOTE_CMD="cd $(printf '%q' "$SYNC_PROD_PATH") && ./scripts/sync/export-from-env.sh prod $(printf '%q' "$REMOTE_EXPORT_DIR") ${EXPORT_FLAGS[*]}"

if [[ "$SYNC_DRY_RUN" == "1" ]]; then
    sync_run ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "$REMOTE_CMD"
    IMPORT_FLAGS=(--dry-run)
    if [[ "$SYNC_YES" == "1" ]]; then
        IMPORT_FLAGS+=(--yes)
    fi
    if [[ "$SYNC_DB_ONLY" == "1" ]]; then
        IMPORT_FLAGS+=(--db-only)
    fi
    if [[ "$SYNC_STORAGE_ONLY" == "1" ]]; then
        IMPORT_FLAGS+=(--storage-only)
    fi
    sync_run "${ROOT}/scripts/sync/import-to-env.sh" local "$LOCAL_EXPORT_DIR" "${IMPORT_FLAGS[@]}"
    exit 0
fi

sync_run ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "$REMOTE_CMD"

mkdir -p "$LOCAL_EXPORT_DIR"

if [[ "$SYNC_STORAGE_ONLY" != "1" ]]; then
    sync_log "downloading db.sql.gz"
    sync_run scp "${SCP_OPTS[@]}" "${SSH_TARGET}:${REMOTE_EXPORT_DIR}/db.sql.gz" "${LOCAL_EXPORT_DIR}/"
fi

if [[ "$SYNC_DB_ONLY" != "1" ]]; then
    sync_log "downloading storage-app.tar.gz"
    sync_run scp "${SCP_OPTS[@]}" "${SSH_TARGET}:${REMOTE_EXPORT_DIR}/storage-app.tar.gz" "${LOCAL_EXPORT_DIR}/"
fi

IMPORT_FLAGS=()
if [[ "$SYNC_YES" == "1" ]]; then
    IMPORT_FLAGS+=(--yes)
fi
if [[ "$SYNC_DB_ONLY" == "1" ]]; then
    IMPORT_FLAGS+=(--db-only)
fi
if [[ "$SYNC_STORAGE_ONLY" == "1" ]]; then
    IMPORT_FLAGS+=(--storage-only)
fi

sync_run "${ROOT}/scripts/sync/import-to-env.sh" local "$LOCAL_EXPORT_DIR" "${IMPORT_FLAGS[@]}"

sync_log "cleaning up remote export ${REMOTE_EXPORT_DIR}"
sync_run ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "rm -rf $(printf '%q' "$REMOTE_EXPORT_DIR")"

sync_log "pull from production complete"
