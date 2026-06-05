# Security

Security practices, controls, and operational guidance for Nerdik. For how to report a vulnerability, see [SECURITY.md](../SECURITY.md) at the repository root.

## Reporting vulnerabilities

Do **not** open a public GitHub issue for security problems.

Use [GitHub private security advisories](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing-information-about-vulnerabilities/privately-reporting-a-security-vulnerability) on this repository (**Security → Advisories → Report a vulnerability**), or follow the process in [SECURITY.md](../SECURITY.md).

## Supported versions

Security fixes are applied to the active `main` branch and released through normal deployment. Older commits and tags are not maintained unless explicitly stated in a release note.

| Version | Supported |
|---------|-----------|
| `main` (latest deploy) | Yes |
| Older SHAs / tags | No |

## Application controls

### Authentication and accounts

- Session-based web authentication with CSRF protection on state-changing requests.
- Password rules (non-testing): minimum 12 characters with mixed case, numbers, symbols, and [uncompromised](https://haveibeenpwned.com/Passwords) checking (`AppServiceProvider`).
- Optional OAuth via Google and Facebook (credentials in environment only).
- Optional Google reCAPTCHA v2 on registration and password reset when `RECAPTCHA_ENABLED=true` (recommended in production).
- Email verification and signed verification links with rate limiting.
- Login and password-reset flows are rate limited per IP.

### Authorization

- Resource access is ownership-based (`created_by`) with admin override (`is_admin`).
- Filament admin panel requires an authenticated admin (`AdminOnly` middleware).
- Laravel Pulse dashboard is gated to admins (`viewPulse` gate).
- Laravel Telescope is registered only in the `local` environment.

### HTTP and transport

- Security headers on web responses: `X-Frame-Options: SAMEORIGIN` and `Content-Security-Policy: frame-ancestors 'self'` ([`SecurityHeaders`](../app/Http/Middleware/SecurityHeaders.php)).
- HTTPS URL forcing in production when `APP_URL` uses `https://`.
- Trusted proxy configuration via `TRUSTED_PROXIES` when TLS terminates at Caddy or another reverse proxy.
- Rate limits on selected public endpoints (browse, geocoding, map tiles) and auth routes.

### Real-time (Reverb)

- Private user channel `App.Models.User.{id}`: only the matching authenticated user may subscribe.
- Activity channel `activity.{activityId}`: any **authenticated** user may subscribe when the activity exists. This is intentional so visitors see live capacity counters before joining. Broadcast payloads contain only the activity id; roster details load over HTTP/Livewire. See [`routes/channels.php`](../routes/channels.php) and [deployment.md](deployment.md#broadcast-channels).

### Data handling

- Passwords, CSRF tokens, and session payloads are excluded from exception flash data.
- Application logs redact sensitive fields before write ([`RedactsSensitiveData`](../app/Support/Logging/RedactsSensitiveData.php)).
- Soft deletes and audit columns (`created_by`, `updated_by`, etc.) on core entities.

## Production hardening

Follow [deployment.md](deployment.md) before exposing a server to the internet.

| Setting | Production expectation |
|---------|------------------------|
| `APP_DEBUG` | `false` |
| `APP_KEY` | Unique, secret, never committed |
| `TELESCOPE_ENABLED` | `false` |
| `PULSE_ENABLED` | `false` unless needed (admins only) |
| `LOG_LEVEL` | `error` (or stricter) |
| Secrets (DB, Reverb, OAuth, mail) | Server `.env` or secret manager only |
| Sail-only tools | Do not expose Adminer, Mailpit, or Vite dev server |

Additional checklist:

- Copy [`.env.production.example`](../.env.production.example); never commit `.env` or `auth.json`.
- Run `php artisan config:cache`, `route:cache`, and `view:cache` after deploy.
- Keep PostgreSQL and `storage/app` backed up.
- Use immutable image tags (`IMAGE_TAG=<git-sha>`) from CI; see [ci-cd.md](ci-cd.md).
- Change default seeded credentials on any publicly reachable staging instance.

## CI and dependency security

Continuous integration ([`.github/workflows/ci.yml`](../.github/workflows/ci.yml)) includes:

- **Gitleaks** — secret scanning on git history (`.gitleaks.toml`).
- **Composer audit** — known vulnerable PHP dependencies.
- Full test suite against PostgreSQL matching production FTS setup.

Dependabot is configured for GitHub Actions updates ([`.github/dependabot.yml`](../.github/dependabot.yml)).

Before making the repository public, run a manual secret scan on history and review internal planning folders; see [deployment-plan.md](deployment-plan.md#phase-5-public-repository).

## Scope for vulnerability reports

**In scope**

- Authentication, authorization, or session bypass.
- Cross-site scripting, CSRF, or injection in Nerdik application code.
- Insecure direct object reference on events, activities, slots, or user data.
- Server misconfiguration documented in this repo that leads to exposure when following our deployment guides.
- Dependency issues reproducible in the application with a clear impact.

**Out of scope**

- Denial-of-service against public endpoints without a fixable application defect.
- Issues in third-party services (Google, Facebook, hosting provider) outside this codebase.
- Missing security headers or TLS settings on deployments that ignore [deployment.md](deployment.md).
- Vulnerabilities in outdated, unsupported commits with no path to upgrade on `main`.
- Social engineering or physical access attacks.

## Related documentation

- [deployment.md](deployment.md) — production checklist and post-deploy verification
- [ci-cd.md](ci-cd.md) — automated testing and image publishing
- [deployment-plan.md](deployment-plan.md) — roadmap including public-repo considerations
