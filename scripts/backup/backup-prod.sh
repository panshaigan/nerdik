#!/usr/bin/env bash
# Production backup: export DB + storage, optional encrypted .env, local store, optional remote upload.
#
# Usage:
#   ./scripts/backup/backup-prod.sh
#   DRY_RUN=1 ./scripts/backup/backup-prod.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT}/scripts/backup/common.sh"

backup_main() {
    local folder_name local_dest image_tag manifest_mode file size

    backup_load_config
    backup_acquire_lock

    folder_name="$(backup_timestamp)"
    BACKUP_STAGING_DIR="/tmp/nerdik-backup-${folder_name}"
    local_dest="${BACKUP_LOCAL_PATH}/${folder_name}"

    if backup_remote_configured; then
        manifest_mode="local+remote"
    else
        manifest_mode="local-only"
    fi

    backup_log "starting production backup (${manifest_mode})"

    if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
        backup_log "[dry-run] mkdir -p ${BACKUP_STAGING_DIR}"
    else
        mkdir -p "$BACKUP_STAGING_DIR"
    fi

    if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
        backup_log "[dry-run] export-from-env.sh prod ${BACKUP_STAGING_DIR}"
    else
        "${ROOT}/scripts/sync/export-from-env.sh" prod "$BACKUP_STAGING_DIR"
    fi

    if [[ -f "${BACKUP_ROOT}/.env" && -f "${BACKUP_GPG_PASSPHRASE_FILE}" ]]; then
        if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
            backup_log "[dry-run] gpg encrypt ${BACKUP_ROOT}/.env"
        else
            tar czf - -C "${BACKUP_ROOT}" .env \
                | gpg --batch --yes --passphrase-file "${BACKUP_GPG_PASSPHRASE_FILE}" -c \
                > "${BACKUP_STAGING_DIR}/env.tar.gz.gpg"
        fi
    else
        backup_log "warning: skipping .env encryption (missing .env or ${BACKUP_GPG_PASSPHRASE_FILE})"
    fi

    image_tag=""
    if [[ -f "${BACKUP_ROOT}/.nerdik-image" ]]; then
        image_tag="$(tr -d '[:space:]' < "${BACKUP_ROOT}/.nerdik-image")"
    fi

    if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
        backup_log "[dry-run] write manifest.json in ${BACKUP_STAGING_DIR}"
    else
        {
            printf '{\n'
            printf '  "timestamp": "%s",\n' "$(date -Iseconds)"
            printf '  "hostname": "%s",\n' "$(hostname)"
            printf '  "mode": "%s",\n' "$manifest_mode"
            printf '  "image_tag": "%s",\n' "$image_tag"
            printf '  "files": {\n'

            local first=1
            for file in db.sql.gz storage-app.tar.gz env.tar.gz.gpg; do
                if [[ -f "${BACKUP_STAGING_DIR}/${file}" ]]; then
                    size="$(stat -c '%s' "${BACKUP_STAGING_DIR}/${file}")"
                    if [[ "$first" -eq 1 ]]; then
                        first=0
                    else
                        printf ',\n'
                    fi
                    printf '    "%s": %s' "$file" "$size"
                fi
            done

            printf '\n  }\n'
            printf '}\n'
        } > "${BACKUP_STAGING_DIR}/manifest.json"
    fi

    if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
        backup_log "[dry-run] mkdir -p ${BACKUP_LOCAL_PATH}"
        backup_log "[dry-run] mv ${BACKUP_STAGING_DIR} ${local_dest}"
        BACKUP_STAGING_DIR=""
    else
        mkdir -p "$BACKUP_LOCAL_PATH"
        mv "$BACKUP_STAGING_DIR" "$local_dest"
        BACKUP_STAGING_DIR=""
        backup_log "local backup saved to ${local_dest}"
    fi

    backup_prune_local "$BACKUP_LOCAL_PATH" "$BACKUP_RETENTION_DAYS"

    if backup_remote_configured; then
        backup_validate_remote

        if [[ "$BACKUP_DRY_RUN" == "1" ]]; then
            backup_log "[dry-run] rclone copy ${local_dest} ${BACKUP_REMOTE_NAME}:${BACKUP_REMOTE_PATH}/${folder_name}"
        else
            backup_run rclone copy "$local_dest" \
                "${BACKUP_REMOTE_NAME}:${BACKUP_REMOTE_PATH}/${folder_name}" \
                --checksum --transfers 2
            backup_log "remote backup uploaded to ${BACKUP_REMOTE_NAME}:${BACKUP_REMOTE_PATH}/${folder_name}"
        fi

        backup_prune_remote "$BACKUP_REMOTE_NAME" "$BACKUP_REMOTE_PATH" "$BACKUP_RETENTION_DAYS"
    else
        backup_log "local-only mode, skipping remote upload"
    fi

    backup_log "backup complete"
}

trap 'exit_code=$?; backup_on_exit "$exit_code"; exit "$exit_code"' EXIT

cd "$ROOT"
backup_main
