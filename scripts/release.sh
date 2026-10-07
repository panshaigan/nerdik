#!/usr/bin/env bash
# Bump VERSION, run local checks, push tag, watch GitHub Actions, smoke-check production.
#
# Usage:
#   ./scripts/release.sh [minor|feature|major|MAJOR.MINOR.PATCH]
#   make release feature
#
# Env:
#   VERSION       Override first positional arg when set and argv empty
#   DRY_RUN=1     Print planned steps without commit/push/tag/watch
#   SKIP_WATCH=1  Push and tag only; skip GitHub polling
#   SKIP_SMOKE=1  Skip final https://nerdik.app/up check
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/version.sh
source "${ROOT}/scripts/lib/version.sh"

VERSION_FILE="${ROOT}/VERSION"
POLL_INTERVAL=120
WATCH_TIMEOUT=7200
APP_URL="https://nerdik.app"
HEALTH_URL="${APP_URL}/up"
DEFAULT_GITHUB_REPO="panshaigan/nerdik"

RELEASE_VERSION=""
RELEASE_SHA=""
RELEASE_TAG=""
GITHUB_REPO=""
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

require_gh_auth() {
    if ! gh auth status >/dev/null 2>&1; then
        die "GitHub CLI is not authenticated. Run: gh auth login"
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

run_json_query() {
    local query="$1"
    local json="$2"
    jq -r "$query" <<<"$json"
}

workflow_latest_json() {
    local workflow="$1"
    shift
    gh run list \
        --repo "$GITHUB_REPO" \
        --workflow "$workflow" \
        "$@" \
        --limit 5 \
        --json databaseId,status,conclusion,url,displayTitle,headSha 2>/dev/null || echo '[]'
}

workflow_status_line() {
    local name="$1"
    local json="$2"

    local count status conclusion url
    count="$(run_json_query 'length' "$json")"
    if [[ "$count" == "0" ]]; then
        echo "${name}: not started"
        return 0
    fi

    status="$(run_json_query '.[0].status' "$json")"
    conclusion="$(run_json_query '.[0].conclusion // "pending"' "$json")"
    url="$(run_json_query '.[0].url' "$json")"
    echo "${name}: ${status} (${conclusion}) ${url}"
}

workflow_failed() {
    local json="$1"
    local count failure_count in_progress

    count="$(run_json_query 'length' "$json")"
    if [[ "$count" == "0" ]]; then
        return 1
    fi

    failure_count="$(run_json_query '[.[] | select(.status == "completed" and .conclusion != "success" and .conclusion != null)] | length' "$json")"
    in_progress="$(run_json_query '[.[] | select(.status != "completed")] | length' "$json")"

    if [[ "$failure_count" -gt 0 && "$in_progress" -eq 0 ]]; then
        return 0
    fi

    return 1
}

workflow_succeeded() {
    local json="$1"
    run_json_query '.[0] | select(.status == "completed" and .conclusion == "success") | .databaseId' "$json"
}

print_workflow_failure() {
    local name="$1"
    local json="$2"
    local id conclusion url

    id="$(run_json_query '[.[] | select(.status == "completed" and .conclusion != "success")] | .[0].databaseId // empty' "$json")"
    conclusion="$(run_json_query '[.[] | select(.status == "completed" and .conclusion != "success")] | .[0].conclusion // empty' "$json")"
    url="$(run_json_query '[.[] | select(.status == "completed" and .conclusion != "success")] | .[0].url // empty' "$json")"

    echo "${name} failed (conclusion=${conclusion}) ${url}" >&2
    if [[ -n "$id" ]]; then
        gh run view "$id" --repo "$GITHUB_REPO" --log-failed 2>/dev/null || true
    fi
}

watch_cicd_chain() {
    local deadline=$((SECONDS + WATCH_TIMEOUT))
    local ci_json docker_json release_json release_id

    step "Watching CI/CD (CI + Docker + Release) — poll every ${POLL_INTERVAL}s"

    while (( SECONDS < deadline )); do
        ci_json="$(workflow_latest_json ci.yml --commit "$RELEASE_SHA")"
        docker_json="$(workflow_latest_json docker.yml --commit "$RELEASE_SHA")"
        release_json="$(workflow_latest_json release.yml --branch "$RELEASE_TAG")"

        workflow_status_line "CI" "$ci_json"
        workflow_status_line "Docker" "$docker_json"
        workflow_status_line "Release" "$release_json"

        if workflow_failed "$ci_json"; then
            print_workflow_failure "CI" "$ci_json"
            die "CI/CD chain failed."
        fi

        if workflow_failed "$docker_json"; then
            print_workflow_failure "Docker" "$docker_json"
            die "CI/CD chain failed."
        fi

        if workflow_failed "$release_json"; then
            print_workflow_failure "Release" "$release_json"
            die "CI/CD chain failed."
        fi

        release_id="$(workflow_succeeded "$release_json")"
        if [[ -n "$release_id" ]]; then
            local release_url
            release_url="$(run_json_query '.[0].url' "$release_json")"
            echo ""
            echo "CI/CD complete — GitHub Release ${RELEASE_TAG} published (${release_url})"
            return 0
        fi

        sleep "$POLL_INTERVAL"
    done

    die "Timed out after ${WATCH_TIMEOUT}s waiting for CI/CD chain."
}

deploy_runs_for_sha() {
    local all_json
    all_json="$(workflow_latest_json deploy.yml)"
    jq --arg sha "$RELEASE_SHA" '[.[] | select(.headSha == $sha)]' <<<"$all_json"
}

watch_deploy() {
    local deadline=$((SECONDS + WATCH_TIMEOUT))
    local deploy_json deploy_id status conclusion url count

    step "Watching Deploy — poll every ${POLL_INTERVAL}s"

    while (( SECONDS < deadline )); do
        deploy_json="$(deploy_runs_for_sha)"
        count="$(run_json_query 'length' "$deploy_json")"

        if [[ "$count" == "0" ]]; then
            echo "Deploy: not started"
            sleep "$POLL_INTERVAL"
            continue
        fi

        status="$(run_json_query '.[0].status' "$deploy_json")"
        conclusion="$(run_json_query '.[0].conclusion // "pending"' "$deploy_json")"
        url="$(run_json_query '.[0].url' "$deploy_json")"
        deploy_id="$(run_json_query '.[0].databaseId' "$deploy_json")"

        echo "Deploy: ${status} (${conclusion}) ${url}"

        if [[ "$status" == "completed" ]]; then
            if [[ "$conclusion" == "success" ]]; then
                if gh run view "$deploy_id" --repo "$GITHUB_REPO" --log 2>/dev/null | grep -q "Deploy skipped"; then
                    echo ""
                    echo "Deployment skipped (deploy secrets not configured)."
                    return 0
                fi

                echo ""
                echo "Deployment complete (${url})"
                return 0
            fi

            echo "Deploy failed (conclusion=${conclusion}) ${url}" >&2
            gh run view "$deploy_id" --repo "$GITHUB_REPO" --log-failed 2>/dev/null || true
            die "Deployment failed."
        fi

        sleep "$POLL_INTERVAL"
    done

    die "Timed out after ${WATCH_TIMEOUT}s waiting for Deploy."
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
    local mode current next tag

    trap on_exit EXIT

    mode="$(resolve_version_mode "${1:-}")"

    require_command git
    require_command curl

    if [[ "${DRY_RUN:-}" != "1" ]]; then
        require_command gh
        require_gh_auth

        if [[ "${SKIP_WATCH:-}" != "1" ]]; then
            require_command jq
        fi
    fi

    if [[ "${DRY_RUN:-}" != "1" ]]; then
        require_clean_tree
        require_main_branch
    fi

    current="$(version_read_file "$VERSION_FILE")"
    next="$(version_resolve "$mode" "$current")"
    RELEASE_VERSION="$next"
    RELEASE_TAG="v${RELEASE_VERSION}"
    GITHUB_REPO="$(resolve_github_repo)"

    step "Release ${RELEASE_VERSION} (from ${current}, mode=${mode:-minor})"

    if [[ "${DRY_RUN:-}" == "1" ]]; then
        echo "DRY_RUN: would write VERSION=${RELEASE_VERSION}"
        echo "DRY_RUN: would commit, run make check, push, tag ${RELEASE_TAG}, watch ${GITHUB_REPO}"
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
        echo "SKIP_WATCH=1 — skipping GitHub Actions polling."
        return 0
    fi

    watch_cicd_chain
    watch_deploy
}

main "$@"
