#!/usr/bin/env bash
# Semver helpers for VERSION file bumps (make release).
#
# Usage (sourced):
#   source scripts/lib/version.sh
#   version_resolve "feature" "1.10.13"
#
# Usage (CLI for tests):
#   ./scripts/lib/version.sh normalize v1.2.3
#   ./scripts/lib/version.sh bump-minor 1.10.13
#   ./scripts/lib/version.sh bump-feature 1.10.13
#   ./scripts/lib/version.sh bump-major 1.10.13
#   ./scripts/lib/version.sh resolve feature 1.10.13
#   ./scripts/lib/version.sh validate 1.2.3
set -euo pipefail

version_normalize() {
    local v="${1:-}"
    v="${v#v}"
    v="${v//[[:space:]]/}"
    printf '%s' "$v"
}

version_validate() {
    local v
    v="$(version_normalize "$1")"
    [[ "$v" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]
}

version_bump_minor() {
    local current major minor patch
    current="$(version_normalize "$1")"
    if ! version_validate "$current"; then
        echo "Invalid semver: ${1}" >&2
        return 1
    fi
    IFS='.' read -r major minor patch <<< "$current"
    printf '%s.%s.%s' "$major" "$minor" "$((patch + 1))"
}

version_bump_feature() {
    local current major minor patch
    current="$(version_normalize "$1")"
    if ! version_validate "$current"; then
        echo "Invalid semver: ${1}" >&2
        return 1
    fi
    IFS='.' read -r major minor patch <<< "$current"
    printf '%s.%s.0' "$major" "$((minor + 1))"
}

version_bump_major() {
    local current major minor patch
    current="$(version_normalize "$1")"
    if ! version_validate "$current"; then
        echo "Invalid semver: ${1}" >&2
        return 1
    fi
    IFS='.' read -r major minor patch <<< "$current"
    printf '%s.0.0' "$((major + 1))"
}

version_resolve() {
    local mode="${1:-}"
    local current="${2:-}"

    if [[ -z "$mode" ]]; then
        mode="minor"
    fi

    case "$mode" in
        minor)
            version_bump_minor "$current"
            ;;
        feature)
            version_bump_feature "$current"
            ;;
        major)
            version_bump_major "$current"
            ;;
        *)
            local strict
            strict="$(version_normalize "$mode")"
            if version_validate "$strict"; then
                printf '%s' "$strict"
            else
                echo "Invalid version: ${mode}. Use minor, feature, major, or MAJOR.MINOR.PATCH." >&2
                return 1
            fi
            ;;
    esac
}

version_read_file() {
    local file="$1"
    if [[ ! -f "$file" ]]; then
        echo "VERSION file not found: ${file}" >&2
        return 1
    fi
    version_normalize "$(tr -d '\n' < "$file")"
}

version_write_file() {
    local file="$1"
    local next="$2"
    if ! version_validate "$next"; then
        echo "Refusing to write invalid semver: ${next}" >&2
        return 1
    fi
    printf '%s\n' "$next" > "$file"
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    cmd="${1:-}"
    shift || true

    case "$cmd" in
        normalize)
            version_normalize "${1:-}"
            ;;
        validate)
            if version_validate "${1:-}"; then
                echo ok
            else
                exit 1
            fi
            ;;
        bump-minor)
            version_bump_minor "${1:-}"
            ;;
        bump-feature)
            version_bump_feature "${1:-}"
            ;;
        bump-major)
            version_bump_major "${1:-}"
            ;;
        resolve)
            version_resolve "${1:-}" "${2:-}"
            ;;
        *)
            echo "Usage: $0 {normalize|validate|bump-minor|bump-feature|bump-major|resolve} ..." >&2
            exit 1
            ;;
    esac
fi
