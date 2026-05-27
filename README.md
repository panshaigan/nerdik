# Nerdik

A system for organizing and participating in nerd events (RPG sessions, board game meetings, lectures, conventions). Built with Laravel, Livewire, **Mary**, **DaisyUI**, and **Vite**.

## Development

- **Stack:** Laravel 13, Sail (Docker), PostgreSQL, Mailpit, Adminer
- **Frontend:** Tailwind CSS v4 (via `@tailwindcss/vite`), DaisyUI v5, Mary UI (Livewire components). Source CSS: `resources/css/app.css` (`@import "tailwindcss"`, `@plugin "daisyui"`, `@source` globs for views, JS, Mary, pagination, and Filament Blade under `vendor/filament`).
- **Start:** `make up` then open http://localhost (or your Sail URL)
- **Artisan:** run via Sail, e.g. `./vendor/bin/sail artisan migrate` or use Makefile: `make migrate`, `make seed`, `make test`, `make queue`
- **Assets:** after `git pull` or dependency changes, run `make npm-install` then `make npm-build` (or `make npm-dev` while developing). Production builds use `npm run build` (Vite); the admin panel (Filament) ships its own compiled CSS separately from this bundle.
- **Polish full-text search:** On first Postgres volume init, Sail runs [`docker/pgsql/init-polish-fts.sql`](docker/pgsql/init-polish-fts.sql) (dictionaries and `polish` text search config). Migrations also apply the same catalog idempotently (needed for the `testing` database and existing volumes). After pulling this setup on an old volume, run `make migrate`, or reset the DB volume with `./vendor/bin/sail down -v`, `./vendor/bin/sail up -d`, and `make migrate`.

## Seeding sample data

To load foundations (places, tags) plus sample users, organizations, events, instances, slots, activities, and a proposal for testing:

```bash
make migrate   # if needed
make seed     # or: ./vendor/bin/sail artisan db:seed
```

To reset the database and seed from scratch:

```bash
make migrate-fresh
make seed
```

**Sample users** (password for all: `password`):

| Email               | Nickname | Role in sample data |
|---------------------|----------|----------------------|
| alice@nerdik.test   | alice    | Owns Nerdik Club, Monthly RPG Night, Convention 2026; hosts D&D activity |
| bob@nerdik.test     | bob      | Owns Wrocław Gamers, Board Game Evening; hosts Forbidden Lands activity |
| charlie@nerdik.test | charlie   | Hosts Talisman board game activity |
| diana@nerdik.test   | diana    | Hosts Call of Cthulhu activity; has a **pending proposal** for Convention 2026 |

**Sample entities:**

- **Organizations:** Nerdik Club (owner: alice), Wrocław Gamers (owner: bob).
- **Events:** Monthly RPG Night (alice, Nerdik Club), Convention 2026 (alice, no org), Board Game Evening (bob, Wrocław Gamers).
- **Event instances:** One instance per event (next Friday 18:00–23:00 for Monthly RPG; a weekend in 2 months for Convention; next Wednesday 17:00–22:00 for Board Game Evening). Each has 3 slots (Table #01–#03); #02 requires approval.
- **Activities:** D&D 5e one-shot (host alice) and Forbidden Lands (host bob) on Monthly RPG slots; Talisman (host charlie) on Board Game Evening; Call of Cthulhu (host diana) proposed to Convention 2026 (pending).
- **Proposal:** Diana’s Call of Cthulhu activity is proposed to Convention 2026 (pending). Log in as **alice** → Event instances → Convention 2026 → accept or reject in “Pending proposals”.

All seeded entities have `created_by` set so ownership and audit are consistent. Use **alice** or **bob** to manage events and accept proposals; **diana** to see proposal status; all four to test browse, wishlist, join/leave, and waitlist.

**Optional: Google login** – Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` (e.g. `${APP_URL}/auth/google/callback`) in `.env` to show “Log in with Google” on the login and register pages. Create OAuth 2.0 credentials in [Google Cloud Console](https://console.cloud.google.com/apis/credentials).

## Data & conventions

- **Timezones:** All datetimes are stored in **UTC** in the database. Users can set a timezone in Profile; dates and times are then shown in that timezone. Form inputs (e.g. event instance start/end) are interpreted in the user’s timezone and saved as UTC.
- **Ownership & audit:** Main entities (events, event instances, organizations, slots, places, tags, activities, activity proposals) have `created_by` and `updated_by` (user IDs). The `HasMetaColumns` trait sets these from the authenticated user. These entities also support soft deletes (`deleted_at`, `deleted_by`).

## GitHub

This repo is ready to push to GitHub. After creating a new repository on GitHub:

```bash
git remote add origin https://github.com/YOUR_USERNAME/nerdik.git
git branch -M main   # optional: use main instead of master
git push -u origin main
```

Do not commit `.env` (it’s in `.gitignore`). Commit `.env.example` if you have one so others can copy it for local setup.
