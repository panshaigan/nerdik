# Deployment

Checklist for production and staging environments. For CI/CD (GitHub Actions, GHCR, automated deploy), see [ci-cd.md](ci-cd.md). For GitHub Actions deploy secrets and SSH setup, see [github-deploy-setup.md](github-deploy-setup.md). For the multi-phase roadmap (Docker, CI/CD, K8s), see [deployment-plan.md](deployment-plan.md). For local development, see [development-workflow.md](development-workflow.md).

## Before you have a server

You can stay on local Sail only. When you are ready:

1. Push the project to a git remote (GitHub recommended for bundled Actions/GHCR).
2. Wait for CI to publish `ghcr.io/<github-owner>/nerdik:<git-sha>` on `main`.
3. Provision a VPS, then follow **First-time setup** below.

Do not use `NERDIK_IMAGE=...:main` unless you tagged that image yourself; CI publishes **commit SHAs**, not a `main` tag. Prefer:

```bash
IMAGE_TAG=<full-git-sha-from-ci> make deploy
```

## Docker production (VPS)

Production uses a shared stack plus prod overlay: [`compose.stack.yaml`](../compose.stack.yaml) + [`compose.prod.yaml`](../compose.prod.yaml) (not Sail [`compose.yaml`](../compose.yaml)).

### Prerequisites

- Docker Engine and Compose plugin on the server
- DNS `A`/`AAAA` for `APP_DOMAIN` pointing at the VPS (for Caddy automatic HTTPS)
- Ports `80` and `443` open
- GHCR package read access configured on the server (`docker login ghcr.io`)

### First-time setup

1. Clone this repo on the server from your git remote (e.g. `git clone … /opt/nerdik`).
2. Copy [`.env.production.example`](../.env.production.example) to `.env` and fill secrets (`APP_KEY`, `DB_PASSWORD`, Reverb keys, mail, OAuth).
3. Set `APP_DOMAIN`, `STAGING_DOMAIN` (e.g. `staging.nerdik.app`), `ACME_EMAIL`, and `GITHUB_OWNER`. Set `NERDIK_IMAGE` to a published SHA, or leave it unset and deploy with `IMAGE_TAG=<sha>` (see below).
4. Set `APP_URL` to `https://<APP_DOMAIN>`.
5. Set browser Reverb vars in server `.env`: `VITE_REVERB_HOST=<APP_DOMAIN>`, `VITE_REVERB_PORT=443`, `VITE_REVERB_SCHEME=https`. These drive **runtime** Echo config injected into pages (same image can serve prod and staging). Docker build args are compile-time fallbacks only.
6. Set `STAGING_DOMAIN=staging.nerdik.app` in `.env`. Caddy config is generated at container start from `APP_DOMAIN`, `STAGING_DOMAIN`, and `ACME_EMAIL` (see `docker/caddy/entrypoint.sh`).
7. Deploy (use the SHA or semver from GHCR after your first `v*` tag, e.g. `v1.0.0`). The first run builds the local PostgreSQL image (`nerdik-pgsql:local` from `docker/pgsql`); only the app image is pulled from GHCR.

```bash
make deploy
```

Pin a specific immutable image tag:

```bash
IMAGE_TAG=<git-sha> make deploy
```

Fallback if you must build on the server:

```bash
make deploy BUILD=1
```

Generate secrets on the **server host** before the first deploy (Compose injects `.env` as environment variables; there is no `.env` file inside the app container):

```bash
# On the VPS, in /opt/nerdik — add these to .env (do not commit .env)
echo "APP_KEY=base64:$(openssl rand -base64 32)"
openssl rand -hex 16   # REVERB_APP_KEY
openssl rand -hex 32   # REVERB_APP_SECRET
```

Or print an `APP_KEY` without writing a file:

```bash
docker compose -f compose.stack.yaml -f compose.prod.yaml run --rm --no-deps app php artisan key:generate --show
```

After editing `.env`, recreate containers so new values load: `docker compose … up -d --force-recreate`, then clear config cache before re-caching (see **Updates**).

Initialize an empty production database (base catalog seed, tag/listing images, first admin). This is interactive and needs a TTY:

```bash
make init
```

Do **not** use `make refresh` for first-time production: it seeds sample users (`alice@nerdik.test`, etc.). To add another admin later without wiping, run `make artisan user:create-admin`.

### Staging on the same VPS

Staging runs on the **same VPS** as production, in a separate directory and Docker Compose project. Prod Caddy owns ports `80`/`443` and routes `STAGING_DOMAIN` to staging containers over the shared `nerdik-edge` network. Staging has **no local Caddy** and can be started or stopped without affecting prod.

| | Production | Staging |
|---|------------|---------|
| Directory | `/opt/nerdik` | `/opt/nerdik-staging` |
| Compose overlay | `compose.prod.yaml` | `compose.staging.yaml` |
| Domain | `nerdik.app` | `staging.nerdik.app` |
| Deploy | `cd /opt/nerdik && make deploy` | `cd /opt/nerdik-staging && make deploy` |
| Stop | always on | `cd /opt/nerdik-staging && make down` |
| Env signal | `APP_ENV=production` in `.env` | `APP_ENV=staging` in `.env` |

Stack: [`compose.stack.yaml`](../compose.stack.yaml) + [`compose.staging.yaml`](../compose.staging.yaml).

#### One-time prod update (existing installs)

After pulling this layout, update production once so Caddy creates `nerdik-edge` and serves the staging domain:

```bash
cd /opt/nerdik
git pull --ff-only
# Ensure .env has APP_DOMAIN=nerdik.app, STAGING_DOMAIN=staging.nerdik.app, ACME_EMAIL=...
make deploy
```

Add DNS: `A` record `staging.nerdik.app` → same VPS IP as production.

Verify:

```bash
curl -fsS https://nerdik.app/up
curl -fsS https://staging.nerdik.app/up   # 503 until staging is started
```

#### One-time staging directory setup

Use a **second clone** so `.env` files stay separate:

```bash
sudo -u deploy git clone <your-repo-url> /opt/nerdik-staging
cd /opt/nerdik-staging
cp .env.staging.example .env
```

Fill staging `.env`:

- `APP_DOMAIN=staging.nerdik.app`, `APP_URL=https://staging.nerdik.app`
- Unique `APP_KEY`, `DB_PASSWORD`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` (do not reuse prod)
- `GITHUB_OWNER=<your-github-owner>`
- `VITE_REVERB_HOST=staging.nerdik.app`

No `docker/caddy/Caddyfile` is required in the staging directory.

#### Activate staging

Production must have been deployed at least once (so `nerdik-edge` exists).

```bash
cd /opt/nerdik-staging
make deploy
```

That runs [`scripts/vps-deploy.sh`](../scripts/vps-deploy.sh): `git pull --ff-only`, resolves HEAD SHA, verifies the GHCR image exists, then deploys this checkout's stack (`APP_ENV=staging`). Pin a specific SHA with `IMAGE_TAG=<sha> make deploy` or `IMAGE_TAG=<sha> ./scripts/vps-deploy.sh --no-pull`.

First run only — seed the empty database if needed:

```bash
docker compose -f compose.stack.yaml -f compose.staging.yaml exec -T app php artisan db:seed --force
```

Verify:

```bash
curl -fsS https://staging.nerdik.app/up
make ps
```

#### Deactivate staging

```bash
cd /opt/nerdik-staging
make down
```

Prod keeps running. Staging data remains in `nerdik_staging_*` volumes until you remove them explicitly.

Staging volumes are isolated (`nerdik_staging_storage`, `nerdik_staging_pgsql_data`, etc.).

#### Staging mail (Mailpit)

Staging never sends real email. The app and worker deliver mail to a **Mailpit** container on the internal Docker network; messages are viewable in the Mailpit UI only. Laravel forces Mailpit when `APP_ENV=staging`, even if `.env` is misconfigured.

**One-time DNS:** add an `A` record `mail.staging.nerdik.app` → same VPS IP as `staging.nerdik.app`.

**Production `.env`** (`/opt/nerdik`) — Caddy routes the Mailpit UI subdomain:

```env
STAGING_MAILPIT_DOMAIN=mail.staging.nerdik.app
```

Redeploy production so Caddy picks up the new site block:

```bash
cd /opt/nerdik
make deploy
```

**Staging `.env`** (`/opt/nerdik-staging`) — mail transport and UI credentials:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@staging.nerdik.app"

# username:bcrypt — generate with:
# docker run --rm axllent/mailpit:latest mailpit bcrypt 'your-secret'
MAILPIT_UI_AUTH='nerdik:$2a$12$...'
```

Use **single quotes** around the value in `.env` so `$` in the bcrypt hash is not expanded when deploy/sync scripts load the file.

Deploy staging after setting `MAILPIT_UI_AUTH` (deploy fails if it is missing):

```bash
cd /opt/nerdik-staging
make deploy
```

**Verify:**

1. Open `https://mail.staging.nerdik.app` — Mailpit login (use the username and password from `MAILPIT_UI_AUTH`).
2. On staging, trigger forgot-password or any notification.
3. Refresh Mailpit — the message appears; nothing is delivered externally.

After **prod → staging sync**, Mailpit still captures all mail — real user addresses in the database do not receive email.

**Deploy order:** DNS → set prod `STAGING_MAILPIT_DOMAIN` and redeploy prod → set staging `MAILPIT_UI_AUTH` and redeploy staging.

#### Routing / TLS troubleshooting

Prod and staging must use **fixed container names** (`nerdik-prod-app`, `nerdik-staging-app`, etc.) so Caddy never confuses the two on the shared `nerdik-edge` network. Caddy config is generated by `docker/caddy/entrypoint.sh` from `.env` — you do not maintain `docker/caddy/Caddyfile` on the server.

If `nerdik.app` shows staging when staging is up, or `staging.nerdik.app` returns `ERR_SSL_PROTOCOL_ERROR`:

```bash
cd /opt/nerdik
git pull --ff-only
# .env must include:
#   APP_DOMAIN=nerdik.app
#   STAGING_DOMAIN=staging.nerdik.app
#   STAGING_MAILPIT_DOMAIN=mail.staging.nerdik.app
#   ACME_EMAIL=your@email
make deploy

cd /opt/nerdik-staging
git pull --ff-only
make deploy

# Verify Caddy generated both site blocks
docker logs nerdik-prod-caddy-1 2>&1 | tail -30

# DNS must point staging to the same VPS IP as prod
dig +short staging.nerdik.app
dig +short nerdik.app
```

### Image build and publish

Composer and frontend dependencies are installed **inside the Docker image** during CI, not on the VPS at deploy time. The [`docker/production/Dockerfile`](../docker/production/Dockerfile) runs `composer install --no-dev`, `npm ci`, and `npm run build`; [`scripts/deploy.sh`](../scripts/deploy.sh) only pulls that image and runs containers. After you push a `v*` tag, wait for the Docker workflow to publish `ghcr.io/<owner>/nerdik:<sha>` (and the semver tags), then deploy with that tag.

Frontend assets are baked into the image (`npm run build` in the Dockerfile). `VITE_REVERB_*` build args in CI are compile-time fallbacks only; browsers read **runtime** Echo config from each server's `.env` via `window.__nerdikEchoConfig` (see `resources/js/echo.js`).

Publish from a machine authenticated to GHCR with write permissions:

```bash
GITHUB_OWNER=your-github-owner ./scripts/docker-publish.sh
```

Optional private Composer packages: pass `COMPOSER_AUTH` or a BuildKit secret for `auth.json` when building.

### Stack layout

| Service | Role |
|---------|------|
| `caddy` (prod only) | TLS for prod + staging domains; prod → `prod-app`/`prod-reverb`; staging → `staging-app`/`staging-reverb` on `nerdik-edge` |
| `app` | Nginx + PHP-FPM (Laravel) |
| `worker` | `queue:work database` |
| `scheduler` | `schedule:work` |
| `reverb` | `reverb:start` (requires PHP `pcntl` in the image — see [`docker/production/Dockerfile`](../docker/production/Dockerfile)) |
| `pulse` | `pulse:check` (Servers card / host metrics when `PULSE_ENABLED=true`) |
| `pgsql` | PostgreSQL with Polish FTS init (or use external DB: set `DB_HOST` and remove `pgsql` service) |

Persistent volumes in prod: `nerdik_storage`, `nerdik_pgsql_data`.

### Updates

The server needs a clone of your git remote (not only a copied folder). After pushing a release tag (`v*`) and waiting for CI + Docker workflows to publish the image:

```bash
cd /opt/nerdik
make deploy
```

That runs [`scripts/vps-deploy.sh`](../scripts/vps-deploy.sh): `git pull --ff-only`, resolves the new commit SHA, verifies the GHCR image exists, then deploys this checkout's stack (`APP_ENV=production`).

To pin a specific SHA manually:

```bash
IMAGE_TAG=<git-sha> make deploy
```

Omit `IMAGE_TAG` only if `NERDIK_IMAGE` in `.env` already points at the image you want.

### Maintenance mode

Production serves a branded static page from Caddy when maintenance is enabled. This covers deploy downtime (app container restarting) and manual maintenance windows.

| Command | Action |
|---------|--------|
| `make maintenance on` | Show maintenance page immediately |
| `make maintenance off` | Return to normal traffic |
| `make maintenance status` | Print `ON` or `OFF` |

`make deploy` on production **enables maintenance automatically** as the first deploy step (before image pull/build) and disables it after a successful deploy. If deploy fails, maintenance stays on so visitors see the page instead of errors.

Emergency bypass (skip auto maintenance during deploy):

```bash
SKIP_MAINTENANCE=1 make deploy
```

The flag file lives at `docker/caddy/state/maintenance` on the VPS host. Caddy reads it per request — no reload needed when toggling manually.

After pulling Caddy entrypoint or maintenance-page changes, recreate Caddy so the generated Caddyfile and static assets reload:

```bash
./scripts/compose-exec.sh up -d --force-recreate caddy
```

After pulling the maintenance feature for the first time, run `make deploy` once so Caddy is recreated with the maintenance volume mounts.

### Promote the same SHA from staging to production

```bash
# After verifying on staging.nerdik.app (both dirs on same SHA after pull)
cd /opt/nerdik-staging
make deploy

cd /opt/nerdik
make deploy
```

## Environment

1. Copy [`.env.production.example`](../.env.production.example) to `.env` on production (or [`.env.staging.example`](../.env.staging.example) on staging).
2. Set `APP_KEY` in the server `.env` before deploy (see **First-time setup**). Do not run `key:generate` inside the app container without `--show` — it has no `.env` file to write.
3. Set `APP_URL`, DB credentials, mail, OAuth/reCAPTCHA, Reverb keys, and `GITHUB_OWNER`. Set `NERDIK_IMAGE` to a CI-published SHA, or deploy with `IMAGE_TAG=<sha>` (recommended).
4. Set `TRUSTED_PROXIES` when TLS terminates at a reverse proxy (`*` or specific proxy IPs).
5. Keep `APP_DEBUG=false` and `TELESCOPE_ENABLED=false`. Enable Pulse in production (`PULSE_ENABLED=true`; admins only via `viewPulse` gate) and set `SENTRY_LARAVEL_DSN` plus UptimeRobot heartbeat URLs (see **Monitoring** below).
6. Logging: use `LOG_LEVEL=error` and `LOG_STACK=daily` (see env templates). Deploy and the daily scheduler prune log files older than `LOG_DAILY_DAYS` (default 14). See **Housekeeping** below. Application logs redact passwords, CSRF tokens, and session payloads before they are written.

## Monitoring

Free production monitoring stack (Sentry + UptimeRobot + Laravel Pulse):

| Concern | Tool | What to configure |
|---------|------|-------------------|
| Backend + browser errors | Sentry | `SENTRY_LARAVEL_DSN`; browser SDK boots from the same DSN via layouts |
| Site / DB / cache up | UptimeRobot HTTP | Monitor `GET /up` (checks app boot + DB + cache) |
| Scheduler / queue alive | UptimeRobot heartbeats | `MONITORING_SCHEDULER_HEARTBEAT_URL`, `MONITORING_WORKER_HEARTBEAT_URL` |
| App performance | Pulse (`/pulse`) | `PULSE_ENABLED=true`; `pulse` compose service runs `pulse:check` |

Telescope stays local-only. After deploy, containers `worker`, `scheduler`, `reverb`, and `pulse` are restarted and `pulse:restart` is signaled.

## Housekeeping

The `scheduler` container runs `schedule:work` and executes automated cleanup so prod/staging disks and database tables do not grow without bound. Retention defaults live in [`config/housekeeping.php`](../config/housekeeping.php) and can be overridden via env (see [`.env.production.example`](../.env.production.example)).

### Scheduled tasks

| When | Command | Purpose |
|------|---------|---------|
| Daily 03:30 | `auth:clear-resets` | Delete expired password reset tokens |
| Daily 03:30 | `queue:prune-failed` | Remove failed jobs older than 7 days |
| Daily 03:30 | `queue:prune-batches` | Remove finished job batches older than 2 days |
| Daily 03:30 | `housekeeping:prune-sessions` | Delete expired database session rows |
| Daily 03:30 | `housekeeping:prune-cache` | Delete expired `cache` / `cache_locks` rows |
| Daily 03:30 | `housekeeping:prune-livewire-uploads` | Remove abandoned Livewire temp uploads |
| Daily 03:30 | `housekeeping:prune-logs` | Delete `storage/logs/*.log` older than `LOG_DAILY_DAYS` |
| Daily 03:30 | `housekeeping:prune-sent-emails` | Delete sent email rows and stored bodies older than `HOUSEKEEPING_SENT_EMAILS_DAYS` |
| Weekly Sun 04:00 | `media-library:clean --delete-orphaned --force` | Orphan media, stale conversions, orphan disk dirs |
| Every minute (if configured) | `monitoring:heartbeat scheduler` | UptimeRobot scheduler heartbeat |
| Every minute (if configured) | `SendWorkerMonitoringHeartbeatJob` | UptimeRobot worker heartbeat (via queue) |
| Daily (if enabled) | `telescope:prune` | Telescope data (off in prod) |

**Not scheduled by design:** database notifications (retained indefinitely), `media:prune-orphans` (manual alternative), media on soft-deleted models (parent row still exists).

### Retention env vars

| Variable | Default | Notes |
|----------|---------|-------|
| `LOG_DAILY_DAYS` | `14` | Monolog daily driver + log file prune |
| `HOUSEKEEPING_FAILED_JOBS_HOURS` | `168` | 7 days |
| `HOUSEKEEPING_JOB_BATCHES_HOURS` | `48` | 2 days |
| `HOUSEKEEPING_SESSIONS_GRACE_DAYS` | `7` | Beyond `SESSION_LIFETIME` |
| `HOUSEKEEPING_LIVEWIRE_TMP_HOURS` | `24` | Abandoned upload files |
| `HOUSEKEEPING_TMP_BACKUP_RETENTION_DAYS` | `7` | Host `/tmp/nerdik-*-backup-*` from sync |
| `HOUSEKEEPING_SENT_EMAILS_DAYS` | `90` | Sent email metadata + stored HTML/text bodies |

### Verify on VPS

Commands with `--flags` need the project Make wrapper on `PATH` (see [development-workflow.md](development-workflow.md#make-passthrough-artisan-npm-composer-test)), or use `./bin/make …` / `ARGS='…'`.

```bash
cd /opt/nerdik
export PATH="$(pwd)/bin:$PATH"
make artisan schedule:list
make artisan housekeeping:prune-sessions --dry-run
make artisan media-library:clean --delete-orphaned --dry-run --force
```

### Docker container logs

Compose sets `json-file` log rotation (`max-size: 10m`, `max-file: 5`) on app, worker, scheduler, reverb, pgsql, caddy, and mailpit. Recreate containers after pulling compose changes: `make deploy`.

### Sync temp backups

Imports with `BACKUP=1` write snapshots under `/tmp/nerdik-prod-backup-*` or `/tmp/nerdik-staging-backup-*`. [`scripts/sync/import-to-env.sh`](../scripts/sync/import-to-env.sh) prunes those directories older than `HOUSEKEEPING_TMP_BACKUP_RETENTION_DAYS` after each successful import.

## Database

VPS Artisan commands use [`scripts/compose-exec.sh`](../scripts/compose-exec.sh), which resolves the deployed image automatically from the running `app` container (or `.nerdik-image` written on each deploy). You do **not** need `NERDIK_IMAGE` in `.env`.

**First-time / empty production database** (base data only, tag images, first admin — interactive TTY):

```bash
cd /opt/nerdik
make init
# confirms by requiring you to type: production
```

Same command works on staging (`cd /opt/nerdik-staging && make init`) and local Sail (`make init`). Day-to-day Make targets route via `APP_ENV`: Sail when `local`, compose stack when `staging`/`production`.

Non-interactive (no TTY):

```bash
YES=1 make artisan app:init --force --no-interaction --email=you@example.com --nickname=you --password='…'
# or: make init YES=1 … (still needs admin options / interactive prompts as applicable)
```

**Refresh database (wipes all data and seeds sample users):**

On production, `make refresh` / `make fresh` / `make seed` / `make init` (and destructive `make artisan …` such as `migrate:fresh`, `db:seed`) require typing `production` to continue, or `YES=1` for non-interactive confirmation. Prefer `make backup-prod` first. Do **not** use refresh/seed for routine production work.

```bash
cd /opt/nerdik
make refresh
# or: make refresh YES=1
```

**Run any Artisan command** (same name locally or on the VPS):

```bash
make artisan migrate --force
make artisan db:seed --force   # production: confirm or YES=1
```

On staging: `cd /opt/nerdik-staging && make refresh` / `make artisan …` (no production confirm gate).

After each deploy, `.nerdik-image` is updated automatically. Pull the latest code once so these helpers are available on the server.

### Backfill media conversions / listing-card thumbnails

Listing cards use Spatie responsive derivatives. If media was attached before conversions ran, cards fall back to full-size originals. Backfill missing conversions and responsive images (includes `--force` required in production):

```bash
cd /opt/nerdik
./scripts/compose-exec.sh prod exec app php artisan media:backfill-thumbnails
```

Equivalent Spatie command:

```bash
./scripts/compose-exec.sh prod exec app php artisan media-library:regenerate --only-missing --with-responsive-images --force --no-interaction
```

After changing `media.conversion_qualities` (or other conversion settings), existing files are not updated by `--only-missing`. Re-encode everything:

```bash
./scripts/compose-exec.sh prod exec app php artisan media:backfill-thumbnails --reencode
```

After deploying a change that **removes** a conversion format (e.g. dropping JPEG while keeping AVIF + WebP), purge deprecated derivative files and DB keys. Dry-run first:

```bash
./scripts/compose-exec.sh prod exec app php artisan media-library:clean --delete-orphaned --dry-run --force
./scripts/compose-exec.sh prod exec app php artisan media-library:clean --delete-orphaned --force
```

Do not re-encode remaining formats for a format drop — existing AVIF/WebP derivatives stay valid. The weekly schedule already runs `media-library:clean`; run it once manually after deploy so disk is freed immediately.

### Convert / shrink media originals to WebP

Prefer copying the existing capped `conversions/*-webp.webp` over the Spatie original when it is smaller (typical after AVIF/WebP derivatives exist). PNG masters without a conversion file are re-encoded. Updates `file_name` / `mime_type` / `size` / dimensions, deletes old originals and orphan `media_library_original` responsives. Dry-run first:

```bash
./scripts/compose-exec.sh prod exec app php artisan media:convert-png-originals-to-webp --dry-run
./scripts/compose-exec.sh prod exec app php artisan media:convert-png-originals-to-webp
```

Optional: `--limit=20` or `--id=1 --id=2` for staged runs. Existing AVIF/WebP conversion derivatives do not need regenerating.

Ensure the queue worker is running afterward so new uploads keep getting derivatives.

1. `php artisan migrate --force`
2. Polish full-text search: new PostgreSQL volumes pick up [`docker/pgsql/init-polish-fts.sql`](../docker/pgsql/init-polish-fts.sql) automatically. On managed Postgres, apply that script manually once per database.

## Application setup

1. `php artisan storage:link`
2. Build frontend assets during image build (`npm ci && npm run build`).
3. Cache application and Filament artifacts after deploy:
   - `php artisan optimize`
   - `php artisan filament:optimize`

## Long-running processes

Run these in addition to the web server:

| Process | Command |
|---------|---------|
| Queue worker | `php artisan queue:work database --sleep=1 --tries=3` |
| Scheduler | `php artisan schedule:work` or cron: `* * * * * php artisan schedule:run` |
| Reverb | `php artisan reverb:start` |
| Pulse check | `php artisan pulse:check` |

Reverb is required for live participation counters and roster refresh on activity/event pages. Set `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, and `VITE_REVERB_SCHEME` in each environment's `.env` to the public WebSocket endpoint browsers reach (`wss` on port `443` behind Caddy). `REVERB_APP_KEY` must match between server `.env` and the key injected into pages.

Do not expose Sail-only tools (Adminer, Mailpit) in **production**. Staging Mailpit is served at `STAGING_MAILPIT_DOMAIN` with `MAILPIT_UI_AUTH` — never expose Mailpit without authentication.

## Post-deploy verification

- `GET /up` returns healthy
- Login and registration (and OAuth if enabled)
- Media upload (avatars, listing images)
- Queue: media conversions complete
- Reverb: open activity show in two logged-in browsers; join/leave in one updates counters in the other
- Outbound mail (password reset or notification); on staging, confirm the message in Mailpit at `https://mail.staging.nerdik.app`

## Backups

- PostgreSQL (daily minimum)
- `storage/app` (private and public media)

## Data sync (prod → local / staging, and local → staging)

Scripts under [`scripts/sync/`](../scripts/sync/) copy **PostgreSQL** and **`storage/app`** between environments. They do **not** copy Redis, built frontend assets, Caddy TLS data, or per-environment `.env` secrets.

**What is copied:** users, events, activities, Spatie `media` rows/files, avatars, sent email log rows and stored HTML/text bodies, FTS search vectors.

**Post-import cleanup (automatic):** truncates `jobs`, `job_batches`, `failed_jobs`, `sessions`, `cache`, `cache_locks`; runs `storage:link`, `optimize:clear`, and `tags:recalculate-popularity`.

**Warning:** sync overwrites the target database and storage. Production data may contain real user PII — handle exports carefully.

**`.env.sync` is local-only** (SSH flows from your laptop). On the VPS staging checkout, `make sync-from-prod` uses sibling path defaults (`/opt/nerdik` → current staging) and does not need `.env.sync`.

### `make sync-from-prod` (routes by `APP_ENV`)

| Where | Effect |
|-------|--------|
| Local (`APP_ENV=local`) | SSH pull production into Sail |
| Staging (`/opt/nerdik-staging`) | On VPS: export production into this staging checkout |
| Production | **Blocked** |

Local setup: copy [`.env.sync.example`](../.env.sync.example) to `.env.sync`, set `SYNC_SSH_HOST` / `SYNC_SSH_KEY`, add the key to `deploy@VPS` `authorized_keys`, then `make up`.

```bash
# Laptop (prod → local)
make sync-from-prod              # interactive confirm
make sync-from-prod YES=1        # skip prompt
make sync-from-prod DB=1         # database only
make sync-from-prod STORAGE=1    # storage only
make sync-from-prod DRY_RUN=1    # print steps only
make sync-from-prod tables users activities media
make sync-from-prod tables users activities YES=1

# VPS staging (prod → staging)
cd /opt/nerdik-staging
make sync-from-prod
make sync-from-prod BACKUP=1 YES=1
make sync-from-prod tables users activities BACKUP=1 YES=1
```

**Selective table sync** overwrites only the named tables (still truncates volatile queue/cache/session tables afterward). Include related tables when foreign keys require them.

From a laptop you can also SSH-trigger staging sync without Make twins:

```bash
./scripts/sync/prod-to-staging-remote.sh BACKUP=1
./scripts/sync/prod-to-staging-remote.sh --tables tags --yes
```

**WSL + PuTTY keys:** OpenSSH cannot use `.ppk` files or keys stored on `/mnt/c/...` (Windows permissions cannot be tightened). Convert and keep the key in the WSL filesystem:

```bash
sudo apt install putty-tools   # if needed
puttygen /var/www/putty.ppk -O private-openssh -o ~/.ssh/nerdik-sync
chmod 600 ~/.ssh/nerdik-sync
# set SYNC_SSH_KEY=~/.ssh/nerdik-sync in .env.sync
```

### `make sync-to-staging` (local → VPS staging)

Push your current Sail database/storage to staging so staging looks like local:

```bash
make sync-to-staging
make sync-to-staging YES=1 BACKUP=1
make sync-to-staging tables users activities
```

Requires `APP_ENV=local` and `.env.sync` (`SYNC_SSH_HOST`, etc.). Staging path defaults to `/opt/nerdik-staging` (`SYNC_STAGING_PATH`).

## Backups

Production backups run on the **VPS host** (not inside the app container). They export the database and `storage/app`, optionally encrypt `.env`, store artifacts locally with retention, and optionally upload to off-site object storage.

Scripts: [`scripts/backup/backup-prod.sh`](../scripts/backup/backup-prod.sh), [`scripts/backup/common.sh`](../scripts/backup/common.sh). Config template: [`.env.backup.example`](../.env.backup.example).

### Local-only (default)

Works without external storage or `.env.backup`:

1. Ensure `gpg` is installed on the host.
2. Optional: create `echo 'your-passphrase' > /opt/nerdik/.backup-gpg-passphrase` (`chmod 600`, owned by `deploy`) to include encrypted `.env` in each backup. Without it, DB + storage backups still run.
3. Run manually:

```bash
cd /opt/nerdik
make backup-prod-dry-run   # print steps only
make backup-prod           # write backup under BACKUP_LOCAL_PATH
```

Default local path: `/home/deploy/backups/nerdik/prod/YYYY-MM-DD-HHMMSS/` containing `db.sql.gz`, `storage-app.tar.gz`, optional `env.tar.gz.gpg`, and `manifest.json`. Backups older than 30 days are pruned automatically.

Schedule nightly cron (as `deploy`):

```bash
cd /opt/nerdik
./scripts/backup/install-cron.sh
```

Or install manually:

```cron
0 3 * * * cd /opt/nerdik && ./scripts/backup/backup-prod.sh >> /home/deploy/logs/nerdik-backup.log 2>&1 # nerdik-backup-prod
```

Create the log directory once: `mkdir -p /home/deploy/logs`.

Rotate the backup cron log on the host (e.g. `/etc/logrotate.d/nerdik-backup`):

```
/home/deploy/logs/nerdik-backup.log {
    weekly
    rotate 4
    compress
    missingok
    notifempty
    copytruncate
}
```

**Failure emails:** sent only when a backup fails, to `LEGAL_CONTACT_EMAIL` via prod `MAIL_*` settings (`php artisan backup:notify-failure`). No email on success.

Local backups share the VPS disk — better than nothing, but add off-site storage when ready.

### Off-site upload (optional)

When you are ready for off-site copies:

1. Create an OVH Object Storage bucket (or other S3-compatible storage).
2. Install `rclone` on the VPS and configure a remote (e.g. `ovh-nerdik`).
3. Copy [`.env.backup.example`](../.env.backup.example) to `/opt/nerdik/.env.backup` and set:

```bash
BACKUP_REMOTE_NAME=ovh-nerdik
BACKUP_REMOTE_PATH=nerdik-backups/prod
```

The next `make backup-prod` uploads to the remote **in addition to** the local copy. If `BACKUP_REMOTE_NAME` is unset, upload is skipped (not a failure).

### Restore production

Run on the **VPS** as `deploy` from `/opt/nerdik`. Entry point: `make restore-prod` → [`scripts/backup/restore-prod.sh`](../scripts/backup/restore-prod.sh) → [`scripts/sync/import-to-env.sh`](../scripts/sync/import-to-env.sh) `prod`.

**Source:** a backup **directory** under `/home/deploy/backups/nerdik/prod/YYYY-MM-DD-HHMMSS/` (or a `.tar.gz` of that folder — flat or single top-level directory). Expected artifacts: `db.sql.gz`, `storage-app.tar.gz`, optional `env.tar.gz.gpg`, and `manifest.json`. If using off-site storage, download the backup to the VPS first (e.g. via `rclone`).

**Restores:** PostgreSQL and `storage/app` (and optionally `.env`). Does **not** restore Redis, Caddy TLS data, or built frontend assets.

**Workflow:**

1. SSH to the VPS and `cd /opt/nerdik`.
2. Choose a backup folder (or download/unpack an off-site archive).
3. Dry-run to print steps without changing data.
4. Restore with `RESTORE_BACKUP=1` so current prod is snapshotted to `/tmp/nerdik-prod-backup-*` before overwrite.
5. Post-import (automatic): truncates volatile tables (`jobs`, `sessions`, `cache`, …), then `storage:link`, `optimize:clear`, and `tags:recalculate-popularity`.

```bash
cd /opt/nerdik

# 1) Dry-run (print steps only)
make restore-prod ARCHIVE=/home/deploy/backups/nerdik/prod/2026-06-12-030001 DRY_RUN=1

# 2) Recommended: restore DB + storage; snapshot current prod to /tmp first
make restore-prod ARCHIVE=/home/deploy/backups/nerdik/prod/2026-06-12-030001 YES=1 RESTORE_BACKUP=1

# Interactive confirm (omit YES=1)
make restore-prod ARCHIVE=/home/deploy/backups/nerdik/prod/2026-06-12-030001 RESTORE_BACKUP=1

# Also restore encrypted .env (requires /opt/nerdik/.backup-gpg-passphrase)
make restore-prod ARCHIVE=/path/to/backup YES=1 RESTORE_BACKUP=1 RESTORE_ENV=1

# Partial restore
make restore-prod ARCHIVE=/path/to/backup YES=1 RESTORE_BACKUP=1 DB_ONLY=1
make restore-prod ARCHIVE=/path/to/backup YES=1 RESTORE_BACKUP=1 STORAGE_ONLY=1
```

| Flag | Effect |
|------|--------|
| `ARCHIVE` | Backup directory or `.tar.gz` file (required) |
| `YES=1` | Skip confirmation prompts |
| `RESTORE_BACKUP=1` | Dump current prod to `/tmp/nerdik-prod-backup-*` before overwriting |
| `RESTORE_ENV=1` | Decrypt and restore `env.tar.gz.gpg` into `.env` |
| `DRY_RUN=1` | Print steps only |
| `DB_ONLY=1` / `STORAGE_ONLY=1` | Partial restore |

Portable archive from a local backup folder:

```bash
tar czf nerdik-backup.tar.gz -C /home/deploy/backups/nerdik/prod 2026-06-12-030001
make restore-prod ARCHIVE=/path/to/nerdik-backup.tar.gz YES=1 RESTORE_BACKUP=1
```

Run a restore drill monthly (e.g. onto staging via `import-to-env.sh staging`) to verify backups are usable.

## Broadcast channels

Private channel `activity.{id}` is intentionally available to **any authenticated user** when the activity exists, so visitors see live capacity/roster changes before joining. Only `activityId` is broadcast; full roster data is loaded over HTTP/Livewire.
