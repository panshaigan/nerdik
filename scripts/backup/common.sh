#!/usr/bin/env bash
# Shared helpers for production backup scripts.
set -euo pipefail

BACKUP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

BACKUP_DRY_RUN="${DRY_RUN:-0}"
BACKUP_ERROR=""
BACKUP_STAGING_DIR=""
BACKUP_LOCK_FD=""

backup_log() {
    printf '[backup] %s\n' "$*" >&2
}

backup_die() {
    BACKUP_ERROR="${1:-backup failed}"
    backup_log "error: ${BACKUP_ERROR}"
    exit 1
}

backup_run() {
    if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
        backup_log "[dry-run] $*"
        return 0
    fi

    backup_log "+ $*"
    "$@"
}

backup_load_config() {
    local env_file=""

    if [[ -f "${BACKUP_ROOT}/.env.backup" ]]; then
        env_file="${BACKUP_ROOT}/.env.backup"
    elif [[ -f "/opt/nerdik/.env.backup" ]]; then
        env_file="/opt/nerdik/.env.backup"
    fi

    if [[ -n "$env_file" ]]; then
        # shellcheck source=scripts/lib/load-dotenv.sh
        source "${BACKUP_ROOT}/scripts/lib/load-dotenv.sh"
        dotenv_load "$env_file"
    fi

    BACKUP_LOCAL_PATH="${BACKUP_LOCAL_PATH:-/home/deploy/backups/nerdik/prod}"
    BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
    BACKUP_GPG_PASSPHRASE_FILE="${BACKUP_GPG_PASSPHRASE_FILE:-/opt/nerdik/.backup-gpg-passphrase}"
    BACKUP_REMOTE_NAME="${BACKUP_REMOTE_NAME:-}"
    BACKUP_REMOTE_PATH="${BACKUP_REMOTE_PATH:-nerdik-backups/prod}"
    BACKUP_LOCK_FILE="${BACKUP_LOCK_FILE:-/tmp/nerdik-backup.lock}"
    BACKUP_NOTIFY="${BACKUP_NOTIFY:-1}"
}

backup_timestamp() {
    date +%Y-%m-%d-%H%M%S
}

backup_acquire_lock() {
    exec {BACKUP_LOCK_FD}>"${BACKUP_LOCK_FILE}"

    if ! flock -n "$BACKUP_LOCK_FD"; then
        backup_log "another backup is already running (lock: ${BACKUP_LOCK_FILE})"
        exit 0
    fi
}

backup_release_lock() {
    if [[ -n "$BACKUP_LOCK_FD" ]]; then
        flock -u "$BACKUP_LOCK_FD" 2>/dev/null || true
        eval "exec ${BACKUP_LOCK_FD}>&-"
        BACKUP_LOCK_FD=""
    fi
}

backup_cleanup_staging() {
    if [[ -n "$BACKUP_STAGING_DIR" && -d "$BACKUP_STAGING_DIR" && ( "$BACKUP_STAGING_DIR" == /tmp/nerdik-backup-* || "$BACKUP_STAGING_DIR" == /tmp/nerdik-restore-* ) ]]; then
        if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
            backup_log "[dry-run] rm -rf ${BACKUP_STAGING_DIR}"
        else
            rm -rf "$BACKUP_STAGING_DIR"
        fi
    fi
}

backup_on_exit() {
    local exit_code="$1"

    if [[ "$exit_code" -ne 0 && -z "$BACKUP_ERROR" ]]; then
        BACKUP_ERROR="backup failed with exit code ${exit_code}"
    fi

    backup_cleanup_staging
    backup_release_lock

    if [[ "$exit_code" -ne 0 && -n "$BACKUP_ERROR" && "$BACKUP_NOTIFY" == "1" && "$BACKUP_DRY_RUN" != "1" ]]; then
        backup_notify_failure "$BACKUP_ERROR" || true
    fi

    return "$exit_code"
}

backup_notify_failure() {
    local message="$1"

    backup_log "sending failure notification"

    if [[ ! -x "${BACKUP_ROOT}/scripts/compose-exec.sh" ]]; then
        backup_log "compose-exec.sh not found; cannot send notification"
        return 1
    fi

    "${BACKUP_ROOT}/scripts/compose-exec.sh" prod exec -T app \
        php artisan backup:notify-failure --message="${message}" || return 1
}

backup_folder_age_days() {
    local folder_name="$1"
    local folder_date folder_epoch now_epoch age_seconds

    if [[ ! "$folder_name" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{6}$ ]]; then
        return 1
    fi

    folder_date="${folder_name:0:10}"
    folder_epoch="$(date -d "${folder_date} 00:00:00" +%s 2>/dev/null || return 1)"
    now_epoch="$(date +%s)"
    age_seconds=$((now_epoch - folder_epoch))

    echo $((age_seconds / 86400))
}

backup_prune_local() {
    local base_path="$1"
    local retention_days="$2"
    local entry age

    if [[ ! -d "$base_path" ]]; then
        return 0
    fi

    backup_log "pruning local backups older than ${retention_days} days in ${base_path}"

    for entry in "${base_path}"/*; do
        [[ -d "$entry" ]] || continue

        age="$(backup_folder_age_days "$(basename "$entry")" || echo "")"
        if [[ -z "$age" ]]; then
            continue
        fi

        if [[ "$age" -gt "$retention_days" ]]; then
            backup_run rm -rf "$entry"
        fi
    done
}

backup_prune_remote() {
    local remote_name="$1"
    local remote_path="$2"
    local retention_days="$3"
    local entry folder_name age remote_dir

    if ! command -v rclone >/dev/null 2>&1; then
        backup_die "rclone is required when BACKUP_REMOTE_NAME is set"
    fi

    remote_dir="${remote_name}:${remote_path}"
    backup_log "pruning remote backups older than ${retention_days} days in ${remote_dir}"

    while IFS= read -r entry; do
        [[ -n "$entry" ]] || continue
        folder_name="${entry%/}"
        age="$(backup_folder_age_days "$folder_name" || echo "")"
        if [[ -z "$age" ]]; then
            continue
        fi

        if [[ "$age" -gt "$retention_days" ]]; then
            if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
                backup_log "[dry-run] rclone purge ${remote_dir}/${folder_name}"
            else
                backup_run rclone purge "${remote_dir}/${folder_name}"
            fi
        fi
    done < <(rclone lsf --dirs-only "${remote_dir}/" 2>/dev/null || true)
}

backup_remote_configured() {
    [[ -n "${BACKUP_REMOTE_NAME:-}" ]]
}

backup_validate_remote() {
    if ! command -v rclone >/dev/null 2>&1; then
        backup_die "rclone is not installed but BACKUP_REMOTE_NAME is set"
    fi

    if ! rclone listremotes | grep -Fx "${BACKUP_REMOTE_NAME}:"; then
        backup_die "rclone remote not found: ${BACKUP_REMOTE_NAME}:"
    fi
}

backup_require_artifacts() {
    local export_dir="$1"
    local require_db="${2:-1}"
    local require_storage="${3:-1}"

    if [[ "$require_db" == "1" && ! -f "${export_dir}/db.sql.gz" ]]; then
        backup_die "missing ${export_dir}/db.sql.gz"
    fi

    if [[ "$require_storage" == "1" && ! -f "${export_dir}/storage-app.tar.gz" ]]; then
        backup_die "missing ${export_dir}/storage-app.tar.gz"
    fi
}

backup_dir_has_artifacts() {
    local dir="$1"

    [[ -f "${dir}/db.sql.gz" || -f "${dir}/storage-app.tar.gz" ]]
}

backup_resolve_source() {
    local input="$1"
    local resolved=""

    if [[ -z "$input" ]]; then
        backup_die "backup source path is required"
    fi

    if [[ ! -e "$input" ]]; then
        backup_die "backup source not found: ${input}"
    fi

    if [[ -d "$input" ]]; then
        if backup_dir_has_artifacts "$input"; then
            printf '%s\n' "$input"
            return 0
        fi

        backup_die "backup directory is missing db.sql.gz and storage-app.tar.gz: ${input}"
    fi

    case "$input" in
        *.tar.gz|*.tgz)
            BACKUP_STAGING_DIR="/tmp/nerdik-restore-$(backup_timestamp)"
            if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
                backup_log "[dry-run] mkdir -p ${BACKUP_STAGING_DIR}"
                backup_log "[dry-run] tar xzf ${input} -C ${BACKUP_STAGING_DIR}"
                printf '%s\n' "$BACKUP_STAGING_DIR"
                return 0
            fi

            mkdir -p "$BACKUP_STAGING_DIR"
            tar xzf "$input" -C "$BACKUP_STAGING_DIR"

            if backup_dir_has_artifacts "$BACKUP_STAGING_DIR"; then
                printf '%s\n' "$BACKUP_STAGING_DIR"
                return 0
            fi

            resolved="$(find "$BACKUP_STAGING_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1 || true)"
            if [[ -n "$resolved" ]] && backup_dir_has_artifacts "$resolved"; then
                printf '%s\n' "$resolved"
                return 0
            fi

            backup_die "archive does not contain a valid backup (expected db.sql.gz and/or storage-app.tar.gz)"
            ;;
        *)
            backup_die "unsupported backup source (use a directory or .tar.gz archive): ${input}"
            ;;
    esac
}
