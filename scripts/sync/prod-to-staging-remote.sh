#!/usr/bin/env bash
# Run prod-to-staging sync on the VPS via SSH (from local machine).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck disable=SC1091
source "${ROOT}/scripts/sync/common.sh"

REMOTE_FLAGS=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --yes)
            REMOTE_FLAGS+=("YES=1")
            ;;
        --backup)
            REMOTE_FLAGS+=("BACKUP=1")
            ;;
        --dry-run)
            REMOTE_FLAGS+=("DRY_RUN=1")
            ;;
        --db-only|--storage-only)
            sync_die "use prod-to-staging-sync on the VPS for partial syncs"
            ;;
        -h|--help)
            echo "Usage: ./scripts/sync/prod-to-staging-remote.sh [--yes] [--backup] [--dry-run]"
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
sync_ssh_opts SSH_OPTS

REMOTE_MAKE_FLAGS="${REMOTE_FLAGS[*]}"
REMOTE_CMD="cd $(printf '%q' "$SYNC_PROD_PATH") && make prod-to-staging-sync ${REMOTE_MAKE_FLAGS}"

sync_log "running on ${SSH_TARGET}: ${REMOTE_CMD}"
sync_run ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "$REMOTE_CMD"
