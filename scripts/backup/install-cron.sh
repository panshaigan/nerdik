#!/usr/bin/env bash
# Install the documented nightly production backup cron entry for the deploy user.
#
# Usage:
#   ./scripts/backup/install-cron.sh
#   DRY_RUN=1 ./scripts/backup/install-cron.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CRON_MARKER="# nerdik-backup-prod"
CRON_LINE="0 3 * * * cd ${ROOT} && ./scripts/backup/backup-prod.sh >> /home/deploy/logs/nerdik-backup.log 2>&1 ${CRON_MARKER}"
DRY_RUN="${DRY_RUN:-0}"

if [[ "$(id -un)" != "deploy" ]]; then
    echo "warning: run as the deploy user on the VPS (current user: $(id -un))" >&2
fi

if [[ "$DRY_RUN" == "1" ]]; then
    echo "[dry-run] mkdir -p /home/deploy/logs"
    echo "[dry-run] would install cron:"
    echo "$CRON_LINE"
    exit 0
fi

mkdir -p /home/deploy/logs

existing="$(crontab -l 2>/dev/null || true)"

if printf '%s\n' "$existing" | grep -Fq "$CRON_MARKER"; then
    echo "Backup cron already installed."
    exit 0
fi

{
    if [[ -n "$existing" ]]; then
        printf '%s\n' "$existing"
    fi
    printf '%s\n' "$CRON_LINE"
} | crontab -

echo "Installed nightly backup cron for ${ROOT}."
echo "Logs: /home/deploy/logs/nerdik-backup.log"
echo "Configure logrotate on the host to rotate that file (see docs/deployment.md)."
