# nerdik

**App URL:** [https://nerdik.app](https://nerdik.app)

nerdik is a platform for organizing and joining nerd events: RPG sessions, board game meetups, and convention-style programs. It supports public discovery, organizer-managed scheduling, activity proposals, participant rosters, and waitlists.

## Engineering Highlights

- **Real-time:** Live participation counters via Laravel Reverb + Echo; channel authorization in [`routes/channels.php`](routes/channels.php).
- **Search:** Polish full-text search (hybrid `tsvector` + trigram similarity) on a custom PostgreSQL image — [`docker/pgsql/`](docker/pgsql/).
- **Domain logic:** Proposals, waitlists, lotteries, and enrollment windows orchestrated through Actions and Services — [`app/Services/`](app/Services/), [`app/Actions/`](app/Actions/).
- **Admin:** Filament 5 CRUD panel with user impersonation — [`app/Filament/Admin/`](app/Filament/Admin/).
- **Media:** Spatie Media Library with queued WebP conversions and responsive images.
- **Security:** HIBP password checking, reCAPTCHA, HTML purify, CSP headers, Sentry in production — see [`docs/security.md`](docs/security.md).
- **Quality:** 300+ PHPUnit tests, parallel execution, Larastan + Psalm.
- **Delivery:** GitHub Actions pipeline — Gitleaks → prod-matching Postgres CI → GHCR → SSH deploy — [`docs/ci-cd.md`](docs/ci-cd.md).

## Observability, Analytics & Ops

Admin shortcuts live in the **user profile menu** (not the Filament sidebar), visible to `is_admin` users. All links are defined in [`app/Support/AdminOpsNavLinks.php`](app/Support/AdminOpsNavLinks.php); optional entries are hidden when their env URL is unset (see [`tests/Feature/NavigationMenuTest.php`](tests/Feature/NavigationMenuTest.php)).

### Platform (in-app)

- **Filament admin panel** — internal CRUD for users, events, activities, feedback, tags, and sent emails. Path: `FILAMENT_ADMIN_PATH` (default `admin`).
- **Laravel Pulse** — performance dashboard with a custom slow-Livewire recorder ([`app/Pulse/Recorders/SlowLivewireActions.php`](app/Pulse/Recorders/SlowLivewireActions.php)). Admin-gated via `viewPulse`.

### Environment switcher

- **Production / Staging / Development** — jumps to the same path and query on another origin. Livewire-aware referer handling preserves the page you are viewing.

### Monitoring (external dashboards)

- **Sentry** — error and performance tracking; SDK in [`resources/js/sentry.js`](resources/js/sentry.js) plus Laravel exception handler. Nav: `SENTRY_DASHBOARD_URL`; runtime: `SENTRY_LARAVEL_DSN`.
- **Umami** — privacy-friendly web analytics; tracker via `<x-umami-analytics />`. Nav: `UMAMI_DASHBOARD_URL`; runtime: `UMAMI_WEBSITE_ID`.
- **Google Search Console** — SEO and search performance dashboard. `GOOGLE_SEARCH_CONSOLE_URL`.
- **PageSpeed Insights** — auto-built link for the current page on the production origin.

### Email & support

- **Support mailbox** — external inbox UI (e.g. Mailpit locally). `SUPPORT_MAILBOX_URL`.
- **Brevo** — transactional email logs dashboard. `BREVO_DASHBOARD_URL`.

### Infrastructure

- **Adminer** — database admin UI with a pre-filled Postgres connection. `ADMINER_URL`.
- **Hosting manager** — VPS control panel shortcut (e.g. OVH). `HOSTING_MANAGER_URL`.

### Also available (not in admin menu)

- **Laravel Telescope** — local-only request/query debugging (`local` env, `/telescope`).
- **Mailpit** — local mail catcher (Sail compose service).

See `.env.example` for the full list of integration env vars.

## Quick Start

1. Install dependencies:
   - `vendor/bin/sail composer install`
   - `vendor/bin/sail npm install`
2. Start containers:
   - `make up` (or `vendor/bin/sail up -d`)
3. Prepare app:
   - `cp .env.example .env` (if missing)
   - `vendor/bin/sail artisan key:generate`
   - `make migrate`
   - `make seed`
4. Build assets:
   - `make npm-build` (or `make npm-dev` during frontend work)
5. Open the app:
   - `http://localhost`

## Technology Stack

- Backend: `PHP 8.5`, `Laravel 13`, `Livewire 4`, `Volt`, `Filament 5`
- Frontend: `Tailwind CSS 4`, `DaisyUI 5`, `Mary UI`, `Vite 7`
- Database & runtime: `PostgreSQL`, Laravel Sail (Docker), queues/scheduler via Sail
- Real-time: Laravel Reverb, Laravel Echo, Pusher JS
- Auth: Socialite (Google, Facebook, Discord)
- Admin: Filament 5, Filament Impersonate, Spatie Media Library plugin
- Observability: Pulse, Sentry, Telescope (local)
- Analytics: Umami
- Email: Brevo (production), Mailpit (local)
- Media/PDF: Spatie Media Library, Intervention Image, DomPDF
- Security: reCAPTCHA, HIBP password check, HTML Purify, Crawler Detect
- Quality: PHPUnit 12, Paratest, Larastan, Psalm, Pint

## Architecture & Patterns

- **Actions** (~40 invokables) for single-purpose operations — media uploads, GDPR anonymization, locale switching.
- **Services** (~60) for domain orchestration — participation, proposals, lotteries, notifications.
- **Strategy registry** for user requests (invites, join requests) — [`UserRequestHandlerRegistry`](app/Services/UserRequests/UserRequestHandlerRegistry.php).
- **Events + observers** for broadcasting and cache invalidation (`EventShowReadCache`).
- **Ownership authorization** via `canModifyEntity()` and Eloquent `visibleTo` scopes (no Policy classes).
- **18 backed enums** for domain states — participation mode, lottery triggers, proposal status.

## Core Concepts

- **Event**: top-level entity visible in browse when public.
- **Slot**: time/place capacity unit within an event that can host one activity.
- **Activity**: playable/joinable item either self-hosted or scheduled on an event slot.
- **Proposal**: request to place an activity into an event slot, then accepted/rejected by event owner.
- **Participation**: attendee roster and optional waitlist logic per activity.

## Notable Domain Features

- Activity hosting lifecycle (draft → self-hosted → proposed → scheduled).
- Slot compatibility validation on proposal acceptance.
- Waitlist promotion and host approval flows.
- Lottery draws for oversubscribed activities (scheduled job).
- User request system (org/activity invites, join requests).
- Notification preferences and periodic digest emails.
- Event/activity series with catalog pages.
- Map browse (Leaflet) with geo bounding-box filter.
- ICS calendar export and participant roster PDF.
- Bilingual UI (EN/PL) with translation parity tests.
- GDPR user anonymization.

## Key Behavior (High-Level)

- Public browse is unified under `search` and includes public events and eligible activities.
- Activities can require host approval and can switch between participant roster and waitlist.
- Proposal acceptance validates slot compatibility (activity type, duration, and capacity).
- Authorization is ownership-based (`created_by`) with admin override.
- All core entities keep audit metadata and soft-delete support.

## Testing & CI/CD

- **Tests:** 300+ feature and unit tests; parallel execution via `make test`.
- **CI:** Gitleaks secret scan, PHPUnit on prod-matching Polish FTS Postgres, `composer audit`, compose validation.
- **Release:** Docker images to GHCR on `v*` tags → GitHub Release → SSH deploy to VPS.
- **Environments:** local (Sail), staging, production — same Makefile targets, env-routed via `scripts/app-cmd.sh`.

Full pipeline details: [`docs/ci-cd.md`](docs/ci-cd.md).

## Documentation Map

- Product and feature context: [`docs/product-overview.md`](docs/product-overview.md)
- Domain rules and mechanisms: [`docs/domain-mechanics.md`](docs/domain-mechanics.md)
- Setup and development operations: [`docs/development-workflow.md`](docs/development-workflow.md)
- Production deployment checklist: [`docs/deployment.md`](docs/deployment.md)
- CI/CD (GitHub Actions, GHCR): [`docs/ci-cd.md`](docs/ci-cd.md)
- GitHub Actions deploy secrets setup: [`docs/github-deploy-setup.md`](docs/github-deploy-setup.md)
- Security policy and controls: [`docs/security.md`](docs/security.md) · [`SECURITY.md`](SECURITY.md)
- Deployment roadmap (phases): [`docs/deployment-plan.md`](docs/deployment-plan.md)
- **Environments:** local (`APP_ENV=local`, Sail), staging (`/opt/nerdik-staging`, `make deploy` / `make down`), production (`/opt/nerdik`, `make deploy` or GitHub Actions). Same Make names route via `APP_ENV`.

## Updating Docs

- Update `README.md` for quick-start, stack, and top-level project orientation.
- Update `docs/product-overview.md` for product scope and feature-level explanation.
- Update `docs/domain-mechanics.md` for business logic and flow rules.
- Update `docs/development-workflow.md` for setup/ops commands and local workflows.
- Agent codebase map: edit `.ai/guidelines/` (`nerdik-map.md`, `nerdik-conventions.md`), then run `make artisan boost:update --no-interaction` to refresh `AGENTS.md` and `.agents/skills` (shared by Cursor and Codex; `.cursor/skills` is a symlink).

## Optional Authentication Providers

Set these in `.env` to enable social login buttons:

- Google: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- Facebook: `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`, `FACEBOOK_REDIRECT_URI`
- Discord: `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_REDIRECT_URI`

Callbacks are routed under `/auth/google/callback`, `/auth/facebook/callback`, and `/auth/discord/callback`.

## License

nerdik is free software licensed under the [GNU General Public License v3.0 or later](LICENSE).

## Notes

- Datetimes are stored in UTC; UI renders in the user profile timezone.
- After pulling dependency or frontend changes, run `make npm install` and `make npm run build`.
- Polish full-text search catalog setup lives in `docker/pgsql/init-polish-fts.sql`.

## Passing Artisan options through Make

Use a double-dash separator before Artisan options so Make does not parse them itself:

```bash
make artisan feedback:migrate-uploads -- --apply
```

Without the separator, Make exits with `make: unrecognized option '--apply'` before the Makefile can forward the option to Artisan.
