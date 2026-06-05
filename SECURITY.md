# Security Policy

## Reporting a vulnerability

If you believe you have found a security vulnerability in Nerdik, please report it **privately**. Do not open a public issue, pull request, or discussion thread that describes the flaw.

**Preferred channel:** [GitHub private security advisories](https://github.com/panshaigan/nerdik/security/advisories/new) on this repository.

1. Open the repository on GitHub.
2. Go to **Security → Advisories**.
3. Choose **Report a vulnerability**.

If private advisories are unavailable, contact the repository maintainers through the channel listed in the repository profile or organization settings.

## What to include

- A clear description of the issue and the impact you expect.
- Steps to reproduce, including URLs, request samples, or test accounts if needed.
- Affected version or commit SHA (if known).
- Any proof-of-concept you are comfortable sharing.

Please give us reasonable time to investigate and patch before public disclosure.

## Response expectations

- We aim to acknowledge reports within **5 business days**.
- We will work on a fix on `main` and coordinate disclosure timing with you when possible.
- We may ask for clarification; we appreciate responsible disclosure and will credit reporters when they wish (unless anonymity is preferred).

## Supported versions

Only the current `main` branch and deployments built from recent commits on `main` receive security updates. See [docs/security.md](docs/security.md#supported-versions) for details.

## Security documentation

Application controls, production hardening, CI checks, and report scope are documented in [docs/security.md](docs/security.md).
