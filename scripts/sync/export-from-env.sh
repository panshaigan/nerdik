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
Usage: ./scripts/sync/export-from-env.sh <prod|staging> [export_dir] [--dry-run] [--db-only] [--storage-only] [--tables TABLE ...]

Creates:
  <export_dir>/db.sql.gz
  <export_dir>/storage-app.tar.gz

With --tables, only the listed tables are dumped (implies --db-only).
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
        --tables)
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

sync_apply_tables_db_only

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
    if [[ "${#SYNC_TABLES[@]}" -gt 0 ]]; then
        sync_log "dumping tables $(sync_tables_summary) from ${DB_DATABASE} as ${DB_USERNAME}"
    else
        sync_log "dumping database ${DB_DATABASE} as ${DB_USERNAME}"
    fi

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        if [[ "${#SYNC_TABLES[@]}" -gt 0 ]]; then
            sync_log "[dry-run] pg_dump ${DB_DATABASE} (tables: $(sync_tables_summary)) → ${EXPORT_DIR}/db.sql.gz"
        else
            sync_log "[dry-run] pg_dump ${DB_DATABASE} → ${EXPORT_DIR}/db.sql.gz"
        fi
    else
        pg_dump_table_args=()
        if [[ "${#SYNC_TABLES[@]}" -gt 0 ]]; then
            sync_pg_dump_table_args pg_dump_table_args
        fi

        sync_compose_cmd "$DEPLOY_ENV" "$ROOT" exec -T pgsql \
            pg_dump -U "${DB_USERNAME}" -d "${DB_DATABASE}" --no-owner --no-acl --clean --if-exists \
            "${pg_dump_table_args[@]}" \
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
