#!/usr/bin/env bash
# Export database dump and storage/app from prod or staging Docker stack.
#
# Usage:
#   ./scripts/sync/export-from-env.sh prod [export_dir]
#   ./scripts/sync/export-from-env.sh prod /tmp/nerdik-sync-20260101-120000 --dry-run
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT}/scripts/sync/common.sh"

usage() {
    cat <<'EOF'
Usage: ./scripts/sync/export-from-env.sh <prod|staging> [export_dir] [--dry-run] [--db-only] [--storage-only]

Creates:
  <export_dir>/db.sql.gz
  <export_dir>/storage-app.tar.gz
EOF
}

DEPLOY_ENV=""
EXPORT_DIR=""
SYNC_DRY_RUN=0

while [[ $# -gt 0 ]]; do
    case "$1" in
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
            if [[ -z "$DEPLOY_ENV" ]]; then
                DEPLOY_ENV="$1"
            elif [[ -z "$EXPORT_DIR" ]]; then
                EXPORT_DIR="$1"
            else
                sync_die "unexpected argument: $1"
            fi
            ;;
    esac
    shift
done

if [[ -z "$DEPLOY_ENV" ]]; then
    usage
    exit 1
fi

if [[ "$SYNC_DB_ONLY" == "1" && "$SYNC_STORAGE_ONLY" == "1" ]]; then
    sync_die "use only one of --db-only or --storage-only"
fi

if [[ -z "$EXPORT_DIR" ]]; then
    EXPORT_DIR="$(sync_default_export_dir)"
fi

if [[ "$DEPLOY_ENV" == "local" ]]; then
    sync_die "export-from-env is for prod/staging Docker stacks only; use pull-from-prod for local"
fi

cd "$ROOT"
sync_prepare_compose_env "$DEPLOY_ENV" "$ROOT"

STORAGE_VOLUME="$(sync_volume_for_env storage "$DEPLOY_ENV")"

sync_log "exporting from ${DEPLOY_ENV} into ${EXPORT_DIR}"

if [[ "$SYNC_DRY_RUN" != "1" ]]; then
    mkdir -p "$EXPORT_DIR"
fi

if [[ "$SYNC_STORAGE_ONLY" != "1" ]]; then
    sync_log "dumping database ${DB_DATABASE} as ${DB_USERNAME}"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_log "[dry-run] pg_dump ${DB_DATABASE} → ${EXPORT_DIR}/db.sql.gz"
    else
        sync_compose_cmd "$DEPLOY_ENV" "$ROOT" exec -T pgsql \
            pg_dump -U "${DB_USERNAME}" -d "${DB_DATABASE}" --no-owner --no-acl --clean --if-exists \
            | gzip > "${EXPORT_DIR}/db.sql.gz"
    fi
fi

if [[ "$SYNC_DB_ONLY" != "1" ]]; then
    sync_log "archiving storage/app from volume ${STORAGE_VOLUME}"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_log "[dry-run] docker run -v ${STORAGE_VOLUME}:/data:ro alpine tar → ${EXPORT_DIR}/storage-app.tar.gz"
    else
        docker run --rm -v "${STORAGE_VOLUME}:/data:ro" alpine \
            tar czf - -C /data/app . > "${EXPORT_DIR}/storage-app.tar.gz"
    fi
fi

sync_log "export complete: ${EXPORT_DIR}"
printf '%s\n' "$EXPORT_DIR"
