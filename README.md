# Nerdik

Nerdik is a platform for organizing and joining nerd events: RPG sessions, board game meetups, and convention-style programs. It supports public discovery, organizer-managed scheduling, activity proposals, participant rosters, and waitlists.

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
- Integrations: `Laravel Reverb`, `Laravel Echo`, `Socialite`, `Spatie Media Library`

## Core Concepts

- **Event**: top-level entity visible in browse when public.
- **Slot**: time/place capacity unit within an event that can host one activity.
- **Activity**: playable/joinable item either self-hosted or scheduled on an event slot.
- **Proposal**: request to place an activity into an event slot, then accepted/rejected by event owner.
- **Participation**: attendee roster and optional waitlist logic per activity.

## Key Behavior (High-Level)

- Public browse is unified under `search` and includes public events and eligible activities.
- Activities can require host approval and can switch between participant roster and waitlist.
- Proposal acceptance validates slot compatibility (activity type, duration, and capacity).
- Authorization is ownership-based (`created_by`) with admin override.
- All core entities keep audit metadata and soft-delete support.

## Documentation Map

- Product and feature context: [`docs/product-overview.md`](docs/product-overview.md)
- Domain rules and mechanisms: [`docs/domain-mechanics.md`](docs/domain-mechanics.md)
- Setup and development operations: [`docs/development-workflow.md`](docs/development-workflow.md)
- Production deployment checklist: [`docs/deployment.md`](docs/deployment.md)
- CI/CD (GitHub Actions, GHCR): [`docs/ci-cd.md`](docs/ci-cd.md)
- GitHub Actions deploy secrets setup: [`docs/github-deploy-setup.md`](docs/github-deploy-setup.md)
- Security policy and controls: [`docs/security.md`](docs/security.md) · [`SECURITY.md`](SECURITY.md)
- Deployment roadmap (phases): [`docs/deployment-plan.md`](docs/deployment-plan.md)
- **Environments:** local dev (`make up` via Sail), staging on the same VPS (`make vps-staging-deploy` / `make staging-down`), production (`make vps-deploy` or GitHub Actions).

## Updating Docs

- Update `README.md` for quick-start, stack, and top-level project orientation.
- Update `docs/product-overview.md` for product scope and feature-level explanation.
- Update `docs/domain-mechanics.md` for business logic and flow rules.
- Update `docs/development-workflow.md` for setup/ops commands and local workflows.

## Optional Authentication Providers

Set these in `.env` to enable social login buttons:

- Google: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- Facebook: `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`, `FACEBOOK_REDIRECT_URI`

Callbacks are routed under `/auth/google/callback` and `/auth/facebook/callback`.

## License

Nerdik is free software licensed under the [GNU General Public License v3.0 or later](LICENSE).

## Notes

- Datetimes are stored in UTC; UI renders in the user profile timezone.
- After pulling dependency or frontend changes, run `make npm-install` and `make npm-build`.
- Polish full-text search catalog setup lives in `docker/pgsql/init-polish-fts.sql`.
