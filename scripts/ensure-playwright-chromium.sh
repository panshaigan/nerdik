#!/usr/bin/env bash
# Ensure Playwright Chromium is installed for the Sail app user.
#
# OS packages need root (`playwright install-deps`). The browser is installed
# as the app user into that user's cache, outside node_modules, so a later
# `npm ci` can delete node_modules without hitting root-owned files.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=lib/playwright-chromium.sh
source "${ROOT}/scripts/lib/playwright-chromium.sh"

cd "$ROOT"

APP_USER="${PLAYWRIGHT_APP_USER:-sail}"

playwright_sanitize_browsers_path
playwright_remove_node_modules_browsers "$ROOT"

if playwright_chromium_installed "$APP_USER"; then
    exit 0
fi

echo 'Playwright Chromium not found; installing browser and OS dependencies (first run may take a minute)...' >&2

if [[ "$(id -u)" -eq 0 ]]; then
    npx playwright install-deps chromium

    if [[ -n "${PLAYWRIGHT_BROWSERS_PATH:-}" ]]; then
        runuser -u "$APP_USER" -- env PLAYWRIGHT_BROWSERS_PATH="$PLAYWRIGHT_BROWSERS_PATH" \
            npx playwright install chromium
    else
        runuser -u "$APP_USER" -- env -u PLAYWRIGHT_BROWSERS_PATH npx playwright install chromium
    fi

    exit 0
fi

if [[ "$(id -un)" != "$APP_USER" ]]; then
    echo "Run this script as root or as ${APP_USER}." >&2
    exit 1
fi

env -u PLAYWRIGHT_BROWSERS_PATH npx playwright install chromium
