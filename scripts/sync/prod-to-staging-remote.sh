#!/usr/bin/env bash
# Run prod → staging sync on the VPS via SSH (from local machine).
#
# Prefers the staging checkout: cd /opt/nerdik-staging && make sync-from-prod
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT}/scripts/sync/common.sh"

REMOTE_MAKE_FLAGS=()
TABLE_ARGS=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --yes)
            REMOTE_MAKE_FLAGS+=("YES=1")
            ;;
        --backup)
            REMOTE_MAKE_FLAGS+=("BACKUP=1")
            ;;
        --dry-run)
            REMOTE_MAKE_FLAGS+=("DRY_RUN=1")
            ;;
        --db-only)
            REMOTE_MAKE_FLAGS+=("DB=1")
            ;;
        --storage-only)
            REMOTE_MAKE_FLAGS+=("STORAGE=1")
            ;;
        --tables)
            shift
            while [[ $# -gt 0 && "$1" != --* ]]; do
                SYNC_TABLES+=("$1")
                TABLE_ARGS+=("$1")
                shift
            done
            continue
            ;;
        -h|--help)
            cat <<'EOF'
Usage: ./scripts/sync/prod-to-staging-remote.sh [--yes] [--backup] [--dry-run] [--db-only] [--storage-only] [--tables TABLE ...]

SSHes to the VPS and runs: cd $SYNC_STAGING_PATH && make sync-from-prod …
EOF
            exit 0
            ;;
        *)
            sync_die "unexpected argument: $1"
            ;;
    esac
    shift
done

sync_apply_tables_db_only

sync_load_sync_config

if [[ -z "${SYNC_SSH_HOST}" ]]; then
    sync_die "SYNC_SSH_HOST is not set. Copy .env.sync.example to .env.sync and configure it."
fi

sync_validate_ssh_key

SSH_TARGET="${SYNC_SSH_USER}@${SYNC_SSH_HOST}"
SSH_OPTS=()
sync_ssh_opts SSH_OPTS

REMOTE_CMD="cd $(printf '%q' "$SYNC_STAGING_PATH") && make sync-from-prod ${REMOTE_MAKE_FLAGS[*]}"
if [[ "${#TABLE_ARGS[@]}" -gt 0 ]]; then
    REMOTE_CMD+=" tables ${TABLE_ARGS[*]}"
fi

sync_log "running on ${SSH_TARGET}: ${REMOTE_CMD}"
sync_run ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "$REMOTE_CMD"
