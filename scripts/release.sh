#!/usr/bin/env bash
# Bump VERSION, run local checks, push tag, watch GitHub web UI, smoke-check production.
#
# Usage:
#   ./scripts/release.sh [minor|feature|major|MAJOR.MINOR.PATCH]
#   make release feature
#
# Env:
#   VERSION       Override first positional arg when set and argv empty
#   DRY_RUN=1     Print planned steps without commit/push/tag/watch
#   SKIP_WATCH=1  Push and tag only; skip GitHub web polling
#   SKIP_SMOKE=1  Skip final https://nerdik.app/up check
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/version.sh
source "${ROOT}/scripts/lib/version.sh"

VERSION_FILE="${ROOT}/VERSION"
CI_POLL_INTERVAL=60
RELEASE_POLL_INTERVAL=30
WATCH_TIMEOUT=7200
APP_URL="https://nerdik.app"
HEALTH_URL="${APP_URL}/up"
DEFAULT_GITHUB_REPO="panshaigan/nerdik"
CURL_UA="Mozilla/5.0 (compatible; nerdik-release/1.0)"

RELEASE_VERSION=""
RELEASE_SHA=""
RELEASE_TAG=""
GITHUB_REPO=""
GITHUB_WEB=""
MAIN_EXIT=0

step() {
    echo ""
    echo "==> $*"
}

die() {
    echo "ERROR: $*" >&2
    MAIN_EXIT=1
    exit 1
}

resolve_github_repo() {
    local url owner repo

    url="$(git remote get-url origin 2>/dev/null || true)"
    if [[ "$url" =~ github\.com[:/]([^/]+)/([^/.]+)(\.git)?$ ]]; then
        owner="${BASH_REMATCH[1]}"
        repo="${BASH_REMATCH[2]}"
        printf '%s/%s' "$owner" "$repo"
        return 0
    fi

    printf '%s' "$DEFAULT_GITHUB_REPO"
}

require_command() {
    local cmd="$1"
    if ! command -v "$cmd" >/dev/null 2>&1; then
        die "${cmd} is required but not installed."
    fi
}

require_clean_tree() {
    if [[ -n "$(git status --porcelain)" ]]; then
        die "Working tree is not clean. Commit or stash changes before releasing."
    fi
}

require_main_branch() {
    local branch
    branch="$(git rev-parse --abbrev-ref HEAD)"
    if [[ "$branch" != "main" ]]; then
        die "Release must run on branch main (current: ${branch})."
    fi
}

require_tag_available() {
    local tag="$1"

    if git rev-parse "$tag" >/dev/null 2>&1; then
        die "Tag ${tag} already exists locally."
    fi

    if git ls-remote --tags origin "refs/tags/${tag}" 2>/dev/null | grep -q .; then
        die "Tag ${tag} already exists on origin."
    fi
}

resolve_version_mode() {
    local mode="${1:-}"

    if [[ -z "$mode" && -n "${VERSION:-}" ]]; then
        mode="${VERSION}"
    fi

    printf '%s' "$mode"
}

fetch_html() {
    local url="$1"
    curl -fsSL -A "$CURL_UA" --max-time 30 "$url"
}

# Fetch Actions runs for the release tag and print status aria-labels (one per line).
# Example labels: "completed successfully:  Run 114 of CI. chore: release v1.10.13"
fetch_tag_action_labels() {
    local html
    html="$(fetch_html "${GITHUB_WEB}/actions?query=branch%3A${RELEASE_TAG}")" || return 1
    printf '%s' "$html" | grep -oE 'aria-label="((completed successfully|failed|queued|in progress|cancelled|waiting|skipped)[^"]*)"' \
        | sed -E 's/^aria-label="//; s/"$//' \
        | grep -E 'Run [0-9]+ of ' \
        || true
}

# Returns: pending | success | failure
# success = CI and Docker completed successfully for this tag, nothing failed/running.
classify_tag_ci_status() {
    local labels="$1"
    local line has_ci=0 has_docker=0 lower

    if [[ -z "$labels" ]]; then
        echo "pending"
        return 0
    fi

    while IFS= read -r line; do
        [[ -z "$line" ]] && continue
        lower="$(printf '%s' "$line" | tr '[:upper:]' '[:lower:]')"

        if [[ "$lower" == failed:* ]]; then
            echo "failure"
            return 0
        fi

        if [[ "$lower" == queued:* || "$lower" == in\ progress:* || "$lower" == waiting:* ]]; then
            echo "pending"
            return 0
        fi

        if [[ "$lower" == completed\ successfully:* ]]; then
            if [[ "$line" == *" of CI."* ]]; then
                has_ci=1
            fi
            if [[ "$line" == *" of Docker."* ]]; then
                has_docker=1
            fi
        fi
    done <<<"$labels"

    if [[ "$has_ci" -eq 1 && "$has_docker" -eq 1 ]]; then
        echo "success"
        return 0
    fi

    echo "pending"
}

fetch_latest_release_tag() {
    local headers location

    headers="$(curl -fsSIL -A "$CURL_UA" --max-time 30 "${GITHUB_WEB}/releases/latest" 2>&1)" || return 1
    location="$(printf '%s' "$headers" | tr -d '\r' | awk 'tolower($1)=="location:" {print $2; exit}')"

    if [[ "$location" =~ /releases/tag/(v[^/?#]+) ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
        return 0
    fi

    # Fallback: parse the landed HTML for the first releases/tag link
    local html
    html="$(fetch_html "${GITHUB_WEB}/releases/latest")" || return 1
    if [[ "$html" =~ /releases/tag/(v[0-9]+\.[0-9]+\.[0-9]+) ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
        return 0
    fi

    return 1
}

watch_commit_ci() {
    local deadline=$((SECONDS + WATCH_TIMEOUT))
    local labels status

    step "Watching CI/CD on ${GITHUB_WEB} (tag ${RELEASE_TAG}) — every ${CI_POLL_INTERVAL}s"

    while (( SECONDS < deadline )); do
        if ! labels="$(fetch_tag_action_labels)"; then
            echo "CI/CD: could not fetch Actions page; retrying..."
            sleep "$CI_POLL_INTERVAL"
            continue
        fi

        status="$(classify_tag_ci_status "$labels")"

        if [[ -n "$labels" ]]; then
            while IFS= read -r line; do
                [[ -n "$line" ]] && echo "  ${line}"
            done <<<"$labels"
        else
            echo "CI/CD: no Actions runs listed yet for ${RELEASE_TAG}"
        fi

        case "$status" in
            success)
                echo ""
                echo "CI/CD complete for ${RELEASE_TAG} (${GITHUB_WEB}/commit/${RELEASE_SHA})"
                return 0
                ;;
            failure)
                echo "" >&2
                echo "CI/CD failed for ${RELEASE_TAG}." >&2
                echo "See: ${GITHUB_WEB}/actions?query=branch%3A${RELEASE_TAG}" >&2
                die "CI/CD checks failed."
                ;;
            pending)
                echo "CI/CD: still pending..."
                ;;
        esac

        sleep "$CI_POLL_INTERVAL"
    done

    die "Timed out after ${WATCH_TIMEOUT}s waiting for CI/CD."
}

watch_github_release() {
    local deadline=$((SECONDS + WATCH_TIMEOUT))
    local latest

    step "Watching GitHub Release for ${RELEASE_TAG} — every ${RELEASE_POLL_INTERVAL}s"

    while (( SECONDS < deadline )); do
        if ! latest="$(fetch_latest_release_tag)"; then
            echo "Release: could not read latest release; retrying..."
            sleep "$RELEASE_POLL_INTERVAL"
            continue
        fi

        echo "Latest release: ${latest} (want ${RELEASE_TAG})"

        if [[ "$latest" == "$RELEASE_TAG" ]]; then
            echo ""
            echo "Release published: ${GITHUB_WEB}/releases/tag/${RELEASE_TAG}"
            return 0
        fi

        sleep "$RELEASE_POLL_INTERVAL"
    done

    die "Timed out after ${WATCH_TIMEOUT}s waiting for GitHub Release ${RELEASE_TAG}."
}

smoke_check_production() {
    if [[ "${SKIP_SMOKE:-}" == "1" ]]; then
        return 0
    fi

    step "Production smoke check"

    if curl -fsS --max-time 15 "$HEALTH_URL" >/dev/null; then
        echo "Production health check OK: ${HEALTH_URL}"
        return 0
    fi

    echo "Production health check FAILED: ${HEALTH_URL}" >&2
    return 1
}

on_exit() {
    local code="$?"
    local smoke_ok=0

    if [[ "$MAIN_EXIT" -ne 0 ]]; then
        code="$MAIN_EXIT"
    fi

    if smoke_check_production; then
        smoke_ok=1
    fi

    echo ""
    echo "App: ${APP_URL}"

    if [[ "$code" -ne 0 ]]; then
        exit "$code"
    fi

    if [[ "$smoke_ok" -eq 0 ]]; then
        exit 1
    fi

    exit 0
}

main() {
    local mode current next

    trap on_exit EXIT

    mode="$(resolve_version_mode "${1:-}")"

    require_command git
    require_command curl

    if [[ "${DRY_RUN:-}" != "1" ]]; then
        require_clean_tree
        require_main_branch
    fi

    current="$(version_read_file "$VERSION_FILE")"
    next="$(version_resolve "$mode" "$current")"
    RELEASE_VERSION="$next"
    RELEASE_TAG="v${RELEASE_VERSION}"
    GITHUB_REPO="$(resolve_github_repo)"
    GITHUB_WEB="https://github.com/${GITHUB_REPO}"

    step "Release ${RELEASE_VERSION} (from ${current}, mode=${mode:-minor})"

    if [[ "${DRY_RUN:-}" == "1" ]]; then
        echo "DRY_RUN: would write VERSION=${RELEASE_VERSION}"
        echo "DRY_RUN: would commit, run make check, push, tag ${RELEASE_TAG}"
        echo "DRY_RUN: would watch ${GITHUB_WEB} for CI/CD then Release ${RELEASE_TAG}"
        return 0
    fi

    require_tag_available "$RELEASE_TAG"

    version_write_file "$VERSION_FILE" "$RELEASE_VERSION"

    step "Commit version bump"
    git add VERSION
    git commit -m "chore: release v${RELEASE_VERSION}"

    step "Local CI checks (make check)"
    if ! make check; then
        die "make check failed. Fix issues or reset the release commit."
    fi

    step "Push branch and tag"
    git push origin HEAD
    git tag "$RELEASE_TAG"
    git push origin "$RELEASE_TAG"

    RELEASE_SHA="$(git rev-parse HEAD)"
    echo "Pushed ${RELEASE_TAG} (${RELEASE_SHA}) to ${GITHUB_REPO}"

    if [[ "${SKIP_WATCH:-}" == "1" ]]; then
        echo "SKIP_WATCH=1 — skipping GitHub web polling."
        return 0
    fi

    watch_commit_ci
    watch_github_release
}

main "$@"
