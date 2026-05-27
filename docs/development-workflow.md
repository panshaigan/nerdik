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
   - `make npm-install`
   - `make npm-build`

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

## Testing

- Run selected tests:
  - `vendor/bin/sail artisan test --compact --filter testName`
- Run one test file:
  - `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`
- Run all tests:
  - `vendor/bin/sail artisan test --compact`

For quick Makefile filtering:

- `make test --filter SomeTest`

## Code Style

For PHP formatting in changed files:

- `vendor/bin/sail bin pint --dirty --format agent`

## Frontend Assets

- Development watch mode: `make npm-dev`
- Production build: `make npm-build`
- If dependencies changed after pull: run `make npm-install` then build again.

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
