#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fixture_dir="$(mktemp -d)"
trap 'rm -rf -- "$fixture_dir"' EXIT
mkdir -p "$fixture_dir/docs" "$fixture_dir/.github/workflows"

# Generate a nonfunctional token-shaped canary at runtime; never commit a credential.
canary="ghp_$(openssl rand -hex 18)"
for relative_path in docs/security-example.md .env.production.example .github/workflows/ci.yml; do
    printf 'token = "%s"\n' "$canary" > "$fixture_dir/$relative_path"
done

status=0
gitleaks dir "$fixture_dir" --config "$repository_root/.gitleaks.toml" \
    --redact --no-banner --log-level error --exit-code 42 \
    --report-format json --report-path "$fixture_dir/report.json" || status=$?

if [[ "$status" != 42 ]]; then
    echo "Gitleaks self-test failed: expected canary detection (42), got $status." >&2
    exit 1
fi

[[ "$(grep -c '"RuleID"' "$fixture_dir/report.json")" == 3 ]]
for relative_path in docs/security-example.md .env.production.example .github/workflows/ci.yml; do
    grep -F "$relative_path\"" "$fixture_dir/report.json" >/dev/null
done

echo "Gitleaks self-test passed: all three canaries detected."