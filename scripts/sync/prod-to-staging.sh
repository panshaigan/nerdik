#!/usr/bin/env bash
# Export production data on the VPS and import into staging (same host).
#
# Usage (from /opt/nerdik on VPS):
#   ./scripts/sync/prod-to-staging.sh [--yes] [--backup] [--dry-run] [--db-only] [--storage-only]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT}/scripts/sync/common.sh"

usage() {
    cat <<'EOF'
Usage: ./scripts/sync/prod-to-staging.sh [--yes] [--backup] [--dry-run] [--db-only] [--storage-only]

Run from the production clone (e.g. /opt/nerdik). Uses SYNC_STAGING_PATH for the staging clone.
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

sync_log "exporting production to ${EXPORT_DIR}"
EXPORT_DIR="$("${ROOT}/scripts/sync/export-from-env.sh" prod "$EXPORT_DIR" "${EXPORT_FLAGS[@]}")"

sync_log "importing into staging at ${SYNC_STAGING_PATH}"
sync_run "${ROOT}/scripts/sync/import-to-env.sh" staging "$EXPORT_DIR" "${IMPORT_FLAGS[@]}"

if [[ "$SYNC_DRY_RUN" != "1" ]]; then
    sync_cleanup_export_dir "$EXPORT_DIR"
fi

sync_log "production → staging sync complete"
