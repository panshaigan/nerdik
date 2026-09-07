#!/usr/bin/env bash
# Laravel Boost helpers for local development (guidelines, skills, MCP).
#
# Usage:
#   source scripts/lib/boost.sh
#   boost_update_and_verify ./vendor/bin/sail
#   boost_update_and_verify php
#
#   ./scripts/lib/boost.sh update ./vendor/bin/sail
#   ./scripts/lib/boost.sh verify ./vendor/bin/sail
#   ./scripts/lib/boost.sh update-and-verify ./vendor/bin/sail
#
# Runner is either a Sail binary path (runs "<sail> artisan ...") or "php"
# (runs "php artisan ...") for CI / host PHP without Sail.

boost_artisan() {
    local runner="${1:?runner required (Sail path or php)}"
    shift

    if [[ "$runner" == "php" ]]; then
        php artisan "$@"
    else
        if [[ ! -x "$runner" ]]; then
            echo "Sail not found at ${runner}." >&2
            return 1
        fi
        "$runner" artisan "$@"
    fi
}

boost_assert_mcp_config() {
    local config="${1:-.cursor/mcp.json}"

    if [[ ! -f "$config" ]]; then
        echo "Missing ${config}; Boost MCP is not configured for Cursor." >&2
        return 1
    fi

    if ! grep -q 'boost:mcp' "$config"; then
        echo "${config} does not reference boost:mcp." >&2
        return 1
    fi
}

boost_verify_mcp() {
    local runner="${1:?runner required (Sail path or php)}"
    local config="${2:-.cursor/mcp.json}"
    local init_payload response

    echo "==> Verifying Laravel Boost MCP..."

    boost_assert_mcp_config "$config" || return 1

    init_payload='{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"nerdik-make-up","version":"1.0.0"}}}'

    # Boost only registers commands in local (or debug) environments.
    # Force local so verify works under PHPUnit (APP_ENV=testing) and matches
    # how Cursor invokes Sail MCP.
    if [[ "$runner" == "php" ]]; then
        response="$(
            printf '%s\n' "$init_payload" \
                | timeout 20 env APP_ENV=local php artisan boost:mcp --no-interaction 2>/dev/null \
                || true
        )"
    else
        if [[ ! -x "$runner" ]]; then
            echo "Sail not found at ${runner}." >&2
            return 1
        fi
        response="$(
            printf '%s\n' "$init_payload" \
                | timeout 20 env APP_ENV=local "$runner" artisan boost:mcp --no-interaction 2>/dev/null \
                || true
        )"
    fi

    if ! grep -q '"name":"Laravel Boost"' <<<"$response"; then
        echo "Boost MCP failed to respond to initialize." >&2
        if [[ -n "$response" ]]; then
            echo "$response" >&2
        fi
        return 1
    fi

    echo "Boost MCP OK."
}

boost_update_guidelines() {
    local runner="${1:?runner required (Sail path or php)}"

    echo "==> Updating Laravel Boost guidelines & skills..."
    boost_artisan "$runner" boost:update --no-interaction
}

boost_update_and_verify() {
    local runner="${1:?runner required (Sail path or php)}"

    boost_update_guidelines "$runner"
    boost_verify_mcp "$runner"
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    set -euo pipefail

    ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
    cd "$ROOT"

    CMD="${1:-}"
    RUNNER="${2:-./vendor/bin/sail}"
    MCP_CONFIG="${3:-.cursor/mcp.json}"

    case "$CMD" in
        update)
            boost_update_guidelines "$RUNNER"
            ;;
        verify)
            boost_verify_mcp "$RUNNER" "$MCP_CONFIG"
            ;;
        update-and-verify)
            boost_update_and_verify "$RUNNER"
            ;;
        -h|--help|"")
            cat <<'EOF'
Usage: ./scripts/lib/boost.sh <command> [runner] [mcp-config]

Commands:
  update              Run artisan boost:update
  verify              Check Cursor MCP config + boost:mcp handshake
  update-and-verify   Update then verify

Runner:
  ./vendor/bin/sail   (default) — Sail artisan
  php                 — host PHP artisan (CI / no Sail)

mcp-config:
  .cursor/mcp.json    (default) — path checked by verify
EOF
            [[ -n "$CMD" ]]
            ;;
        *)
            echo "Unknown boost command: ${CMD}" >&2
            exit 1
            ;;
    esac
fi
