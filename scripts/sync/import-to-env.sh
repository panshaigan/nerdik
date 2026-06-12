#!/usr/bin/env bash
# Import database dump and storage/app into local Sail or staging Docker stack.
#
# Usage:
#   ./scripts/sync/import-to-env.sh local /path/to/export
#   ./scripts/sync/import-to-env.sh staging /path/to/export --yes --backup
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT}/scripts/sync/common.sh"

usage() {
    cat <<'EOF'
Usage: ./scripts/sync/import-to-env.sh <local|staging|prod> <export_dir> [--yes] [--backup] [--dry-run] [--db-only] [--storage-only]

Imports:
  <export_dir>/db.sql.gz
  <export_dir>/storage-app.tar.gz
EOF
}

TARGET_ENV=""
EXPORT_DIR=""

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
            if [[ -z "$TARGET_ENV" ]]; then
                TARGET_ENV="$1"
            elif [[ -z "$EXPORT_DIR" ]]; then
                EXPORT_DIR="$1"
            else
                sync_die "unexpected argument: $1"
            fi
            ;;
    esac
    shift
done

if [[ -z "$TARGET_ENV" || -z "$EXPORT_DIR" ]]; then
    usage
    exit 1
fi

if [[ "$SYNC_DB_ONLY" == "1" && "$SYNC_STORAGE_ONLY" == "1" ]]; then
    sync_die "use only one of --db-only or --storage-only"
fi

case "$TARGET_ENV" in
    local|staging|prod) ;;
    *)
        sync_die "target must be local, staging, or prod"
        ;;
esac

REQUIRE_DB=1
REQUIRE_STORAGE=1
if [[ "$SYNC_DB_ONLY" == "1" ]]; then
    REQUIRE_STORAGE=0
fi
if [[ "$SYNC_STORAGE_ONLY" == "1" ]]; then
    REQUIRE_DB=0
fi

if [[ "$SYNC_DRY_RUN" != "1" ]]; then
    sync_require_export_artifacts "$EXPORT_DIR" "$REQUIRE_DB" "$REQUIRE_STORAGE"
fi

PROJECT_ROOT="$ROOT"
if [[ "$TARGET_ENV" == "staging" ]]; then
    sync_load_sync_config
    PROJECT_ROOT="${SYNC_STAGING_PATH}"
fi

if [[ "$TARGET_ENV" == "prod" ]]; then
    sync_confirm "This will overwrite PRODUCTION database and storage. Continue?"
else
    sync_confirm "This will overwrite ${TARGET_ENV} database and storage. Continue?"
fi

import_backup_prod() {
    local backup_dir="/tmp/nerdik-prod-backup-$(sync_export_timestamp)"
    local storage_volume
    storage_volume="$(sync_volume_for_env storage prod)"

    sync_log "backing up production to ${backup_dir}"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_run mkdir -p "$backup_dir"
        return 0
    fi

    mkdir -p "$backup_dir"

    sync_prepare_compose_env prod "$PROJECT_ROOT"
    sync_compose_cmd prod "$PROJECT_ROOT" exec -T pgsql \
        pg_dump -U "${DB_USERNAME}" -d "${DB_DATABASE}" --no-owner --no-acl --clean --if-exists \
        | gzip > "${backup_dir}/db.sql.gz"

    docker run --rm -v "${storage_volume}:/data:ro" alpine \
        tar czf - -C /data/app . > "${backup_dir}/storage-app.tar.gz"

    sync_log "production backup saved to ${backup_dir}"
}

import_backup_staging() {
    local backup_dir="/tmp/nerdik-staging-backup-$(sync_export_timestamp)"
    local storage_volume
    storage_volume="$(sync_volume_for_env storage staging)"

    sync_log "backing up staging to ${backup_dir}"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_run mkdir -p "$backup_dir"
        return 0
    fi

    mkdir -p "$backup_dir"

    sync_prepare_compose_env staging "$PROJECT_ROOT"
    sync_compose_cmd staging "$PROJECT_ROOT" exec -T pgsql \
        pg_dump -U "${DB_USERNAME}" -d "${DB_DATABASE}" --no-owner --no-acl --clean --if-exists \
        | gzip > "${backup_dir}/db.sql.gz"

    docker run --rm -v "${storage_volume}:/data:ro" alpine \
        tar czf - -C /data/app . > "${backup_dir}/storage-app.tar.gz"

    sync_log "staging backup saved to ${backup_dir}"
}

import_db_local() {
    sync_prepare_compose_env local "$PROJECT_ROOT"
    sync_log "restoring database into local Sail (${DB_DATABASE})"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_log "[dry-run] gunzip -c ${EXPORT_DIR}/db.sql.gz | psql → ${DB_DATABASE}"
        return 0
    fi

    gunzip -c "${EXPORT_DIR}/db.sql.gz" | sync_sail_cmd "$PROJECT_ROOT" exec -T pgsql psql \
        -U "${DB_USERNAME}" -d "${DB_DATABASE}" \
        -v ON_ERROR_STOP=1 \
        --single-transaction
}

import_db_staging() {
    sync_prepare_compose_env staging "$PROJECT_ROOT"
    sync_log "restoring database into staging (${DB_DATABASE})"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_log "[dry-run] gunzip -c ${EXPORT_DIR}/db.sql.gz | psql → ${DB_DATABASE} (staging)"
        return 0
    fi

    sync_compose_cmd staging "$PROJECT_ROOT" stop worker || true

    gunzip -c "${EXPORT_DIR}/db.sql.gz" | sync_compose_cmd staging "$PROJECT_ROOT" exec -T pgsql psql \
        -U "${DB_USERNAME}" -d "${DB_DATABASE}" \
        -v ON_ERROR_STOP=1 \
        --single-transaction
}

import_db_prod() {
    sync_prepare_compose_env prod "$PROJECT_ROOT"
    sync_log "restoring database into production (${DB_DATABASE})"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_log "[dry-run] gunzip -c ${EXPORT_DIR}/db.sql.gz | psql → ${DB_DATABASE} (prod)"
        return 0
    fi

    sync_compose_cmd prod "$PROJECT_ROOT" stop worker || true

    gunzip -c "${EXPORT_DIR}/db.sql.gz" | sync_compose_cmd prod "$PROJECT_ROOT" exec -T pgsql psql \
        -U "${DB_USERNAME}" -d "${DB_DATABASE}" \
        -v ON_ERROR_STOP=1 \
        --single-transaction
}

import_storage_local() {
    sync_log "restoring storage/app into local workspace"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_log "[dry-run] extract ${EXPORT_DIR}/storage-app.tar.gz → ${PROJECT_ROOT}/storage/app"
        return 0
    fi

    mkdir -p "${PROJECT_ROOT}/storage/app"
    rm -rf "${PROJECT_ROOT}/storage/app"/*
    tar xzf "${EXPORT_DIR}/storage-app.tar.gz" -C "${PROJECT_ROOT}/storage/app"
}

import_storage_staging() {
    local storage_volume
    storage_volume="$(sync_volume_for_env storage staging)"

    sync_log "restoring storage/app into volume ${storage_volume}"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_log "[dry-run] extract storage into volume ${storage_volume}"
        return 0
    fi

    sync_compose_cmd staging "$PROJECT_ROOT" stop worker || true

    docker run --rm \
        -v "${storage_volume}:/data" \
        -v "${EXPORT_DIR}:/export:ro" \
        alpine sh -c 'rm -rf /data/app/* && mkdir -p /data/app && tar xzf /export/storage-app.tar.gz -C /data/app'
}

import_storage_prod() {
    local storage_volume
    storage_volume="$(sync_volume_for_env storage prod)"

    sync_log "restoring storage/app into volume ${storage_volume}"

    if [[ "$SYNC_DRY_RUN" == "1" ]]; then
        sync_log "[dry-run] extract storage into volume ${storage_volume}"
        return 0
    fi

    sync_compose_cmd prod "$PROJECT_ROOT" stop worker || true

    docker run --rm \
        -v "${storage_volume}:/data" \
        -v "${EXPORT_DIR}:/export:ro" \
        alpine sh -c 'rm -rf /data/app/* && mkdir -p /data/app && tar xzf /export/storage-app.tar.gz -C /data/app'
}

if [[ "$TARGET_ENV" == "prod" && "$SYNC_BACKUP" == "1" ]]; then
    import_backup_prod
fi

if [[ "$TARGET_ENV" == "staging" && "$SYNC_BACKUP" == "1" ]]; then
    import_backup_staging
fi

if [[ "$REQUIRE_DB" == "1" ]]; then
    case "$TARGET_ENV" in
        local) import_db_local ;;
        staging) import_db_staging ;;
        prod) import_db_prod ;;
    esac

    sync_truncate_volatile_tables "$TARGET_ENV" "$PROJECT_ROOT"
fi

if [[ "$REQUIRE_STORAGE" == "1" ]]; then
    case "$TARGET_ENV" in
        local) import_storage_local ;;
        staging) import_storage_staging ;;
        prod) import_storage_prod ;;
    esac
fi

if [[ "$REQUIRE_DB" == "1" || "$REQUIRE_STORAGE" == "1" ]]; then
    sync_artisan_post_import "$TARGET_ENV" "$PROJECT_ROOT"
fi

if [[ "$TARGET_ENV" == "staging" && "$SYNC_DRY_RUN" != "1" ]]; then
    sync_compose_cmd staging "$PROJECT_ROOT" start worker || true
fi

if [[ "$TARGET_ENV" == "prod" && "$SYNC_DRY_RUN" != "1" ]]; then
    sync_compose_cmd prod "$PROJECT_ROOT" start worker || true
fi

if [[ "$SYNC_DRY_RUN" != "1" ]]; then
    sync_prune_old_tmp_backups
fi

sync_log "import into ${TARGET_ENV} complete"
