#!/usr/bin/env bash
# Playwright Chromium location for the Sail app user.
#
# Browsers must not live under node_modules. `make test-e2e` installs OS
# packages as root; PLAYWRIGHT_BROWSERS_PATH=0 then stores Chromium inside
# node_modules as root. The next `npm ci` runs as the unprivileged app user
# and fails with EACCES while deleting that tree.

# "0" is Playwright's alias for node_modules/<package>/.local-browsers.
playwright_sanitize_browsers_path() {
    if [[ "${PLAYWRIGHT_BROWSERS_PATH:-}" == "0" ]]; then
        unset PLAYWRIGHT_BROWSERS_PATH
    fi
}

playwright_home_for_user() {
    local app_user="$1"
    local home=""

    if [[ "$(id -un 2>/dev/null || true)" == "$app_user" && -n "${HOME:-}" ]]; then
        home="$HOME"
    else
        home="$(getent passwd "$app_user" | cut -d: -f6)"
    fi

    if [[ -z "$home" || "$home" == "/" ]]; then
        echo "Could not resolve home directory for ${app_user}." >&2
        return 1
    fi

    printf '%s\n' "$home"
}

# Cache directory for the app user. A custom PLAYWRIGHT_BROWSERS_PATH is kept.
# "0" and empty fall back to ~/.cache/ms-playwright so npm can replace node_modules.
playwright_browser_cache_dir() {
    local app_user="$1"

    playwright_sanitize_browsers_path

    if [[ -n "${PLAYWRIGHT_BROWSERS_PATH:-}" ]]; then
        printf '%s\n' "$PLAYWRIGHT_BROWSERS_PATH"
        return
    fi

    printf '%s\n' "$(playwright_home_for_user "$app_user")/.cache/ms-playwright"
}

playwright_node_modules_browsers_dir() {
    local root="$1"

    printf '%s\n' "${root%/}/node_modules/playwright-core/.local-browsers"
}

# Remove browsers previously installed inside node_modules.
# Caller must be able to delete the directory (root, when those files are root-owned).
playwright_remove_node_modules_browsers() {
    local dir
    dir="$(playwright_node_modules_browsers_dir "$1")"

    if [[ -d "$dir" ]]; then
        rm -rf "$dir"
    fi
}

playwright_chromium_installed() {
    local app_user="$1"
    local cache
    local has_chrome has_headless_shell

    cache="$(playwright_browser_cache_dir "$app_user")"

    has_chrome="$(find "$cache" -type f -name chrome -path '*/chrome-linux64/*' -print -quit 2>/dev/null || true)"
    has_headless_shell="$(find "$cache" -type f -name chrome-headless-shell -path '*/chrome-headless-shell-linux64/*' -print -quit 2>/dev/null || true)"

    [[ -n "$has_chrome" && -n "$has_headless_shell" ]]
}
