#!/usr/bin/env bash
# Restore production from a backup directory or .tar.gz archive.
#
# Usage:
#   ./scripts/backup/restore-prod.sh /path/to/backup.tar.gz
#   ./scripts/backup/restore-prod.sh /home/deploy/backups/nerdik/prod/2026-06-12-030001 --yes
#   ./scripts/backup/restore-prod.sh backup.tar.gz --restore-env --backup
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT}/scripts/backup/common.sh"

SOURCE=""
RESTORE_YES=0
RESTORE_BACKUP=0
RESTORE_ENV=0
RESTORE_DB_ONLY=0
RESTORE_STORAGE_ONLY=0

usage() {
    cat <<'EOF'
Usage: ./scripts/backup/restore-prod.sh <backup_dir_or.tar.gz> [--yes] [--backup] [--restore-env] [--dry-run] [--db-only] [--storage-only]

Restores production from:
  <backup>/db.sql.gz
  <backup>/storage-app.tar.gz
  optional <backup>/env.tar.gz.gpg (with --restore-env)

Examples:
  make restore-prod ARCHIVE=/home/deploy/backups/nerdik/prod/2026-06-12-030001 YES=1
  make restore-prod ARCHIVE=/tmp/nerdik-backup.tar.gz YES=1 RESTORE_BACKUP=1
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --yes)
            RESTORE_YES=1
            ;;
        --backup)
            RESTORE_BACKUP=1
            ;;
        --restore-env)
            RESTORE_ENV=1
            ;;
        --dry-run)
            BACKUP_DRY_RUN=1
            ;;
        --db-only)
            RESTORE_DB_ONLY=1
            ;;
        --storage-only)
            RESTORE_STORAGE_ONLY=1
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            if [[ -z "$SOURCE" ]]; then
                SOURCE="$1"
            else
                backup_die "unexpected argument: $1"
            fi
            ;;
    esac
    shift
done

if [[ -z "$SOURCE" ]]; then
    usage
    exit 1
fi

if [[ "$RESTORE_DB_ONLY" == "1" && "$RESTORE_STORAGE_ONLY" == "1" ]]; then
    backup_die "use only one of --db-only or --storage-only"
fi

restore_env_file() {
    local export_dir="$1"

    if [[ ! -f "${export_dir}/env.tar.gz.gpg" ]]; then
        backup_die "backup does not include env.tar.gz.gpg"
    fi

    if [[ ! -f "${BACKUP_GPG_PASSPHRASE_FILE}" ]]; then
        backup_die "missing GPG passphrase file: ${BACKUP_GPG_PASSPHRASE_FILE}"
    fi

    if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
        backup_log "[dry-run] decrypt env.tar.gz.gpg into ${BACKUP_ROOT}/.env"
        return 0
    fi

    gpg --batch --yes --passphrase-file "${BACKUP_GPG_PASSPHRASE_FILE}" \
        -d "${export_dir}/env.tar.gz.gpg" \
        | tar xzf - -C "${BACKUP_ROOT}" .env

    backup_log "restored ${BACKUP_ROOT}/.env from backup"
}

restore_main() {
    local export_dir import_flags=()

    backup_load_config

    export_dir="$(backup_resolve_source "$SOURCE")"
    backup_log "using backup source: ${export_dir}"

    if [[ "$BACKUP_DRY_RUN" != "1" ]]; then
        if [[ "$RESTORE_DB_ONLY" != "1" && "$RESTORE_STORAGE_ONLY" != "1" ]]; then
            backup_require_artifacts "$export_dir" 1 1
        elif [[ "$RESTORE_DB_ONLY" == "1" ]]; then
            backup_require_artifacts "$export_dir" 1 0
        else
            backup_require_artifacts "$export_dir" 0 1
        fi
    fi

    if [[ "$RESTORE_ENV" == "1" ]]; then
        if [[ "$RESTORE_YES" != "1" && "$BACKUP_DRY_RUN" != "1" ]]; then
            printf 'This will overwrite production .env. Continue? [y/N] ' >&2
            local answer=""
            read -r answer
            case "$answer" in
                y|Y|yes|YES) ;;
                *)
                    backup_die "aborted"
                    ;;
            esac
        fi

        restore_env_file "$export_dir"
    fi

    import_flags=(prod "$export_dir")

    if [[ "$RESTORE_YES" == "1" ]]; then
        import_flags+=(--yes)
    fi

    if [[ "$RESTORE_BACKUP" == "1" ]]; then
        import_flags+=(--backup)
    fi

    if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
        import_flags+=(--dry-run)
    fi

    if [[ "$RESTORE_DB_ONLY" == "1" ]]; then
        import_flags+=(--db-only)
    fi

    if [[ "$RESTORE_STORAGE_ONLY" == "1" ]]; then
        import_flags+=(--storage-only)
    fi

    "${ROOT}/scripts/sync/import-to-env.sh" "${import_flags[@]}"

    backup_log "production restore complete"
}

trap 'exit_code=$?; backup_cleanup_staging; exit "$exit_code"' EXIT

cd "$ROOT"
restore_main
