# Development Workflow

## Prerequisites

- Docker and Docker Compose
- PHP/Composer available inside Sail workflows
- Node/npm available through Sail

This project is expected to run through Laravel Sail commands.

## First-Time Setup

1. Install PHP dependencies:
   - `vendor/bin/sail composer install`
2. Prepare environment:
   - `cp .env.example .env` (if `.env` is missing)
   - `vendor/bin/sail artisan key:generate`
3. Start services:
   - `make up` or `vendor/bin/sail up -d`
4. Run migrations and seed:
   - `make migrate`
   - `make seed`
5. Install and build frontend assets:
   - `make npm install`
   - `make npm run build`

## Daily Commands

- Start containers: `make up`
- Stop containers: `make down`
- Restart containers: `make restart`
- Open Sail shell: `make shell`
- Run migrations: `make migrate`
- Seed database: `make seed`
- Run queue worker: `make queue`
- Run scheduler worker: `make scheduler`
- Clear app cache: `make cache`

You can also run direct Sail commands such as `vendor/bin/sail artisan ...`.

### Make passthrough (artisan, npm, composer, test)

GNU Make treats unknown `--flags` as its own options (and `foo=bar` as variable assignments), so stock `/usr/bin/make artisan … --dsn=…` fails before the Makefile runs.

Put the project wrapper first on `PATH` so those args are forwarded unchanged:

```bash
export PATH="$(pwd)/bin:$PATH"
# or permanently, from the repo root:
# echo 'export PATH="/var/www/nerdik/bin:$PATH"' >> ~/.bashrc
```

Then flags work as expected:

```bash
make artisan sentry:publish --dsn=https://…
make artisan housekeeping:prune-sessions --dry-run
make test --filter=SomeTest
make npm run build -- --mode=production
make composer require vendor/pkg --dev
```

Without `bin` on `PATH`, use either `./bin/make artisan …` or `make artisan ARGS='…'`.

## Testing

- Run selected tests:
  - `vendor/bin/sail artisan test --compact --filter testName`
- Run one test file:
  - `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`
- Run all tests (CI parity; includes `@group slow`):
  - `make test` or `vendor/bin/sail artisan test --compact`
  - CI uses `php artisan test --compact --parallel` (see `.github/workflows/ci.yml`)
- Faster local loop (excludes `@group slow`):
  - `make test-fast`
- Local Services coverage report (pcov; not a CI gate):
  - `make test-coverage-services`
  - HTML report: `storage/coverage/services/index.html`
  - Narrow with `ARGS`, e.g. `make test-coverage-services ARGS='--filter=ActivityHostingModesTest'`

For quick Makefile filtering (requires `bin` on `PATH`; see above):

- `make test --filter=SomeTest`

### Unit vs Feature

Keep two PHPUnit suites only:

| Suite | Put tests here when… |
| --- | --- |
| `tests/Unit` | No HTTP and no `Livewire::test`. Pure helpers/presenters go under `Unit/Support` (etc.). Domain logic that needs the DB and factories goes under `Unit/Services` or `Unit/Models`. |
| `tests/Feature` | The test hits HTTP (`get`/`post`/…) and/or `Livewire::test`. |

Layering: put counting, filtering, join/waitlist/proposal/slot-fit rules in Unit/Services. Feature/Livewire stays a thin smoke that the UI reflects a seeded outcome — do not duplicate the same matrix in both layers. See `.cursor/rules/ui-testing.mdc` and `.cursor/rules/no-config-value-tests.mdc`.

### PHPUnit groups

| Group | Meaning |
| --- | --- |
| `slow` | Intentionally heavy (PDF, large media pipelines, series, image forms). Excluded by `make test-fast`; always run in CI / `make test`. |
| `infra` | Static checks of scripts, nginx, Vite, Makefile, workflows. Cheap; kept in the default suite (not excluded by `test-fast`). |

Tag with `#[Group('slow')]` / `#[Group('infra')]` on the test class (or method). Latest parallel-suite speed backlog: [test-suite-speed-baseline.md](test-suite-speed-baseline.md).

### Browser E2E (Playwright)

Covers UI journeys PHPUnit cannot see well: login return URLs, saved search restore, the browse map, joining from the event plan, tabbed create forms, image crop on the event form, slot edit modal, notification bell, mobile nav drawer, waitlist join/leave, and activity propose/accept on the event plan.

Second-wave fixtures (from `e2e:prepare`) include an occupied slot and empty slot on the join event, a host-approval waitlist activity, a player draft activity, a pre-seeded pending proposal, and an unread proposal notification for the organizer.

With Sail already running:

```bash
make test-e2e
```

`make test-e2e` runs `e2e:prepare`, installs Playwright Chromium inside the Sail container when it is missing (first run only), then runs the specs.

Or step by step:

```bash
vendor/bin/sail artisan e2e:prepare
vendor/bin/sail exec -u root -e PLAYWRIGHT_BROWSERS_PATH=0 laravel.test ./scripts/ensure-playwright-chromium.sh
vendor/bin/sail exec -e PLAYWRIGHT_BROWSERS_PATH=0 laravel.test npx playwright test
```

`e2e:prepare` upserts deterministic users (`e2e.player@nerdik.test` / `e2e.organizer@nerdik.test`, password `password`), a public event with enrollment, joinable and waitlist activities, proposal fixtures, a Wrocław venue, and `storage/app/e2e-fixtures.json`. Re-running it clears the player's signup and waitlist entries so join/waitlist specs stay repeatable.

Point Playwright at a non-default origin with `PLAYWRIGHT_BASE_URL` (defaults to `http://localhost` locally so it matches Sail `APP_URL` / `SESSION_DOMAIN`, and `http://localhost:8000` in CI). Specs live in `tests/e2e/`. Keep business rules in PHPUnit; do not duplicate waitlist/lottery matrices here.

## Code Style

For PHP formatting in changed files:

- `vendor/bin/sail bin pint --dirty --format agent`

## Frontend Assets

- Development watch mode: `make npm run dev`
- Production build: `make npm run build`
- If dependencies changed after pull: run `make npm install` then build again.

## Seeded Demo Data

Seed includes:

- foundation data (places/tags),
- sample users and organizations,
- sample events, slots, activities,
- one pending activity proposal for workflow testing.

Default sample password for seeded users: `password`.

## Optional OAuth Setup

To enable social sign-in buttons:

- Google: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- Facebook: `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`, `FACEBOOK_REDIRECT_URI`

Callback routes:

- `/auth/google/callback`
- `/auth/facebook/callback`

## PostgreSQL Polish FTS Initialization

Polish full-text-search dictionaries/configuration are initialized through:

- `docker/pgsql/init-polish-fts.sql`

If using an older DB volume after pulling FTS changes:

1. Run `make migrate`, or
2. Recreate DB volume:
   - `vendor/bin/sail down -v`
   - `vendor/bin/sail up -d`
   - `make migrate`

## Production deploy

Local development uses Sail (`make up`). Server deploys use Docker Compose — see [deployment.md](deployment.md) and [deployment-plan.md](deployment-plan.md). You do not need a VPS to develop locally.
