#!/usr/bin/env bash
# Run local checks, then bump VERSION, push tag, watch GitHub web UI, smoke-check production.
#
# Usage:
#   ./scripts/release.sh [minor|feature|major|MAJOR.MINOR.PATCH]
#   make release feature
#   SKIP_CHECK=1 ./scripts/release.sh          # make release-push
#   REPUSH=1 ./scripts/release.sh              # make release-repush
#
# Env:
#   VERSION       Override first positional arg when set and argv empty
#   DRY_RUN=1     Print planned steps without commit/push/tag/watch
#   SKIP_CHECK=1  Skip make check. Still bumps VERSION, commits, and pushes the tag.
#   REPUSH=1      Do not bump VERSION. Move the existing v* tag onto HEAD and push it again.
#   SKIP_WATCH=1  Push and tag only; skip GitHub web polling
#   SKIP_SMOKE=1  Skip final https://nerdik.app/up check
#   RELEASE_ROOT  Checkout to operate on (tests). Defaults to the repo root.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/lib/version.sh
source "${SCRIPT_DIR}/lib/version.sh"

ROOT="${RELEASE_ROOT:-$(cd "${SCRIPT_DIR}/.." && pwd)}"
cd "$ROOT"

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
    local hint="To move it onto the current commit after a failed GitHub run, use make release-repush."

    if git rev-parse --verify --quiet "refs/tags/${tag}" >/dev/null; then
        die "Tag ${tag} already exists locally. ${hint}"
    fi

    if git ls-remote --tags origin "refs/tags/${tag}" 2>/dev/null | grep -q .; then
        die "Tag ${tag} already exists on origin. ${hint}"
    fi
}

tag_exists_locally() {
    local tag="$1"

    git rev-parse --verify --quiet "refs/tags/${tag}" >/dev/null 2>&1
}

tag_exists_on_origin() {
    local tag="$1"

    git ls-remote --tags origin "refs/tags/${tag}" 2>/dev/null | grep -q .
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

# Highest Run number seen for CI and Docker. Prints "ci_run docker_run".
max_workflow_runs() {
    local labels="$1"
    local line run workflow
    local ci=0 docker=0

    while IFS= read -r line; do
        [[ -z "$line" ]] && continue
        if [[ "$line" =~ Run\ ([0-9]+)\ of\ (CI|Docker)\. ]]; then
            run="${BASH_REMATCH[1]}"
            workflow="${BASH_REMATCH[2]}"
            if [[ "$workflow" == "CI" && "$run" -gt "$ci" ]]; then
                ci="$run"
            fi
            if [[ "$workflow" == "Docker" && "$run" -gt "$docker" ]]; then
                docker="$run"
            fi
        fi
    done <<<"$labels"

    printf '%s %s\n' "$ci" "$docker"
}

workflow_run_state() {
    local lower="$1"

    if [[ "$lower" == failed:* || "$lower" == cancelled:* ]]; then
        printf 'failure'
        return 0
    fi

    if [[ "$lower" == completed\ successfully:* ]]; then
        printf 'success'
        return 0
    fi

    printf 'pending'
}

# Returns: pending | success | failure
# Uses the newest CI run and the newest Docker run. Runs at or below
# min_ci_run / min_docker_run are the previous attempt and do not count.
classify_tag_ci_status() {
    local labels="$1"
    local min_ci="${2:-0}"
    local min_docker="${3:-0}"
    local line lower run workflow state
    local ci_run=0 docker_run=0
    local ci_state="" docker_state=""

    if [[ -z "$labels" ]]; then
        echo "pending"
        return 0
    fi

    while IFS= read -r line; do
        [[ -z "$line" ]] && continue
        if [[ ! "$line" =~ Run\ ([0-9]+)\ of\ (CI|Docker)\. ]]; then
            continue
        fi

        run="${BASH_REMATCH[1]}"
        workflow="${BASH_REMATCH[2]}"
        lower="$(printf '%s' "$line" | tr '[:upper:]' '[:lower:]')"
        state="$(workflow_run_state "$lower")"

        if [[ "$workflow" == "CI" && "$run" -gt "$ci_run" ]]; then
            ci_run="$run"
            ci_state="$state"
        fi
        if [[ "$workflow" == "Docker" && "$run" -gt "$docker_run" ]]; then
            docker_run="$run"
            docker_state="$state"
        fi
    done <<<"$labels"

    if [[ "$ci_run" -le "$min_ci" ]]; then
        ci_state=""
    fi
    if [[ "$docker_run" -le "$min_docker" ]]; then
        docker_state=""
    fi

    if [[ "$ci_state" == "failure" || "$docker_state" == "failure" ]]; then
        echo "failure"
        return 0
    fi

    if [[ "$ci_state" == "success" && "$docker_state" == "success" ]]; then
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
    local min_ci="${1:-0}"
    local min_docker="${2:-0}"
    local deadline=$((SECONDS + WATCH_TIMEOUT))
    local labels status

    step "Watching CI/CD on ${GITHUB_WEB} (tag ${RELEASE_TAG}) — every ${CI_POLL_INTERVAL}s"

    while (( SECONDS < deadline )); do
        if ! labels="$(fetch_tag_action_labels)"; then
            echo "CI/CD: could not fetch Actions page; retrying..."
            sleep "$CI_POLL_INTERVAL"
            continue
        fi

        status="$(classify_tag_ci_status "$labels" "$min_ci" "$min_docker")"

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

snapshot_workflow_runs() {
    local labels

    if ! labels="$(fetch_tag_action_labels)"; then
        printf '0 0\n'
        return 0
    fi

    max_workflow_runs "$labels"
}

finish_after_tag_push() {
    local min_ci="${1:-0}"
    local min_docker="${2:-0}"

    RELEASE_SHA="$(git rev-parse HEAD)"
    echo "Pushed ${RELEASE_TAG} (${RELEASE_SHA}) to ${GITHUB_REPO}"

    if [[ "${SKIP_WATCH:-}" == "1" ]]; then
        echo "SKIP_WATCH=1 — skipping GitHub web polling."
        return 0
    fi

    watch_commit_ci "$min_ci" "$min_docker"
    watch_github_release
}

repush_release() {
    local requested current min_runs min_ci min_docker

    requested="$(version_normalize "${1:-}")"
    current="$(version_read_file "$VERSION_FILE")"

    if [[ -n "$requested" ]]; then
        if ! version_validate "$requested"; then
            die "Invalid version: ${1}. Use the existing MAJOR.MINOR.PATCH, or omit it to use VERSION."
        fi
        RELEASE_VERSION="$requested"
    else
        RELEASE_VERSION="$current"
    fi

    RELEASE_TAG="v${RELEASE_VERSION}"
    GITHUB_REPO="$(resolve_github_repo)"
    GITHUB_WEB="https://github.com/${GITHUB_REPO}"

    if [[ "${DRY_RUN:-}" != "1" ]]; then
        require_clean_tree
        require_main_branch
    fi

    if ! tag_exists_locally "$RELEASE_TAG" && ! tag_exists_on_origin "$RELEASE_TAG"; then
        die "Tag ${RELEASE_TAG} does not exist. Use make release-push to create it."
    fi

    step "Repush ${RELEASE_TAG} at $(git rev-parse --short HEAD)"

    if [[ "${DRY_RUN:-}" == "1" ]]; then
        echo "DRY_RUN: would push HEAD, move ${RELEASE_TAG} to HEAD, delete it on origin, and push the tag again"
        echo "DRY_RUN: would watch ${GITHUB_WEB} for a new CI/CD run"
        return 0
    fi

    min_ci=0
    min_docker=0
    if [[ "${SKIP_WATCH:-}" != "1" ]]; then
        min_runs="$(snapshot_workflow_runs)"
        read -r min_ci min_docker <<<"$min_runs"
    fi

    git push origin HEAD
    git tag -f "$RELEASE_TAG" HEAD

    if tag_exists_on_origin "$RELEASE_TAG"; then
        git push origin ":refs/tags/${RELEASE_TAG}"
    fi

    git push origin "$RELEASE_TAG"
    finish_after_tag_push "$min_ci" "$min_docker"
}

publish_release() {
    local mode current next

    mode="$(resolve_version_mode "${1:-}")"

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
        if [[ "${SKIP_CHECK:-}" == "1" ]]; then
            echo "DRY_RUN: would skip make check, then write VERSION=${RELEASE_VERSION}"
        else
            echo "DRY_RUN: would run make check, then write VERSION=${RELEASE_VERSION}"
        fi
        echo "DRY_RUN: would commit, push, tag ${RELEASE_TAG}"
        echo "DRY_RUN: would watch ${GITHUB_WEB} for CI/CD then Release ${RELEASE_TAG}"
        return 0
    fi

    require_tag_available "$RELEASE_TAG"

    if [[ "${SKIP_CHECK:-}" == "1" ]]; then
        step "Skipping local CI checks (SKIP_CHECK=1)"
    else
        step "Local CI checks (make check)"
        if ! make check; then
            die "make check failed. Fix issues before releasing."
        fi
    fi

    version_write_file "$VERSION_FILE" "$RELEASE_VERSION"

    step "Commit version bump"
    git add VERSION
    git commit -m "chore: release v${RELEASE_VERSION}"

    step "Push branch and tag"
    git push origin HEAD
    git tag "$RELEASE_TAG"
    git push origin "$RELEASE_TAG"
    finish_after_tag_push
}

main() {
    trap on_exit EXIT

    require_command git
    require_command curl

    if [[ "${REPUSH:-}" == "1" ]]; then
        repush_release "${1:-}"
        return 0
    fi

    publish_release "${1:-}"
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
