#!/usr/bin/env bash
# Ensure Playwright Chromium is present in the Sail app container (PLAYWRIGHT_BROWSERS_PATH=0).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

export PLAYWRIGHT_BROWSERS_PATH=0

playwright_chromium_ready() {
    find node_modules/playwright-core/.local-browsers \
        -maxdepth 4 \
        -type f \
        -name chrome \
        -path '*/chrome-linux64/*' \
        2>/dev/null \
        | head -n 1 \
        | grep -q .
}

if playwright_chromium_ready; then
    exit 0
fi

echo 'Playwright Chromium not found; installing browser and OS dependencies (first run may take a minute)...' >&2
npx playwright install --with-deps chromium
