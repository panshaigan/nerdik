# Deployment

Checklist for production and staging environments. For CI/CD (GitHub Actions, GHCR, automated deploy), see [ci-cd.md](ci-cd.md). For the multi-phase roadmap (Docker, CI/CD, K8s), see [deployment-plan.md](deployment-plan.md). For local development, see [development-workflow.md](development-workflow.md).

## Before you have a server

You can stay on local Sail only. When you are ready:

1. Push the project to a git remote (GitHub recommended for bundled Actions/GHCR).
2. Wait for CI to publish `ghcr.io/<github-owner>/nerdik:<git-sha>` on `main`.
3. Provision a VPS, then follow **First-time setup** below.

Do not use `NERDIK_IMAGE=...:main` unless you tagged that image yourself; CI publishes **commit SHAs**, not a `main` tag. Prefer:

```bash
IMAGE_TAG=<full-git-sha-from-ci> make prod-deploy
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
3. Set `APP_DOMAIN`, `ACME_EMAIL`, and `GITHUB_OWNER`. Set `NERDIK_IMAGE` to a published SHA, or leave it unset and deploy with `IMAGE_TAG=<sha>` (see below).
4. Set `APP_URL` to `https://<APP_DOMAIN>`.
5. Set browser Reverb vars: `VITE_REVERB_HOST=<APP_DOMAIN>`, `VITE_REVERB_PORT=443`, `VITE_REVERB_SCHEME=https` (must match the image build).
6. Copy Caddy config: `cp docker/caddy/Caddyfile.example docker/caddy/Caddyfile` and ensure `APP_DOMAIN` in `.env` matches the site block.
7. Deploy (use the SHA from CI after your first push to `main`). The first run builds the local PostgreSQL image (`nerdik-pgsql:local` from `docker/pgsql`); only the app image is pulled from GHCR.

```bash
make prod-deploy
```

Pin a specific immutable image tag:

```bash
IMAGE_TAG=<git-sha> make prod-deploy
```

Fallback if you must build on the server:

```bash
make prod-deploy BUILD=1
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

### Staging / dev VPS

Staging uses the same image with a different env and overlay: [`compose.stack.yaml`](../compose.stack.yaml) + [`compose.dev.yaml`](../compose.dev.yaml).

1. Copy [`.env.staging.example`](../.env.staging.example) to `.env` on the staging host.
2. Set staging domain and secrets.
3. Copy Caddy config: `cp docker/caddy/Caddyfile.example docker/caddy/Caddyfile`.
4. Deploy:

```bash
make dev-deploy
```

Pin staging to a specific SHA:

```bash
IMAGE_TAG=<git-sha> make dev-deploy
```

Staging volumes are isolated (`nerdik_dev_storage`, `nerdik_dev_pgsql_data`, etc.).

### Image build and publish

Frontend assets are baked into the image (`npm run build` in [`docker/production/Dockerfile`](../docker/production/Dockerfile)). `VITE_REVERB_*` and `VITE_APP_NAME` are taken from build args.

Publish from a machine authenticated to GHCR with write permissions:

```bash
make docker-publish
```

Optional private Composer packages: pass `COMPOSER_AUTH` or a BuildKit secret for `auth.json` when building.

### Stack layout

| Service | Role |
|---------|------|
| `caddy` | TLS, HTTP → `app:80`, WebSocket `/app/*` → `reverb:8080` |
| `app` | Nginx + PHP-FPM (Laravel) |
| `worker` | `queue:work database` |
| `scheduler` | `schedule:work` |
| `reverb` | `reverb:start` (requires PHP `pcntl` in the image — see [`docker/production/Dockerfile`](../docker/production/Dockerfile)) |
| `pgsql` | PostgreSQL with Polish FTS init (or use external DB: set `DB_HOST` and remove `pgsql` service) |

Persistent volumes in prod: `nerdik_storage`, `nerdik_pgsql_data`.

### Updates

The server needs a clone of your git remote (not only a copied folder). After pushing changes:

```bash
git pull --ff-only
IMAGE_TAG=<git-sha> make prod-deploy
```

Omit `IMAGE_TAG` only if `NERDIK_IMAGE` in `.env` already points at the image you want.

### Promote the same SHA from staging to production

```bash
IMAGE_TAG=<git-sha> make dev-deploy
IMAGE_TAG=<git-sha> make prod-deploy
```

## Environment

1. Copy [`.env.production.example`](../.env.production.example) to `.env` on production (or [`.env.staging.example`](../.env.staging.example) on staging).
2. Set `APP_KEY` in the server `.env` before deploy (see **First-time setup**). Do not run `key:generate` inside the app container without `--show` — it has no `.env` file to write.
3. Set `APP_URL`, DB credentials, mail, OAuth/reCAPTCHA, Reverb keys, and `GITHUB_OWNER`. Set `NERDIK_IMAGE` to a CI-published SHA, or deploy with `IMAGE_TAG=<sha>` (recommended).
4. Set `TRUSTED_PROXIES` when TLS terminates at a reverse proxy (`*` or specific proxy IPs).
5. Keep `APP_DEBUG=false`, `TELESCOPE_ENABLED=false`, and `PULSE_ENABLED=false` unless you explicitly need Pulse (admins only via `viewPulse` gate).
6. Logging: use `LOG_LEVEL=error` and `LOG_STACK=daily` (see env templates). Deploy prunes log files older than `LOG_DAILY_DAYS` (default 14). Application logs redact passwords, CSRF tokens, and session payloads before they are written.

## Database

1. `php artisan migrate --force`
2. Polish full-text search: new PostgreSQL volumes pick up [`docker/pgsql/init-polish-fts.sql`](../docker/pgsql/init-polish-fts.sql) automatically. On managed Postgres, apply that script manually once per database.

## Application setup

1. `php artisan storage:link`
2. Build frontend assets during image build (`npm ci && npm run build`).
3. Cache configuration after deploy:
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`

## Long-running processes

Run these in addition to the web server:

| Process | Command |
|---------|---------|
| Queue worker | `php artisan queue:work database --sleep=1 --tries=3` |
| Scheduler | `php artisan schedule:work` or cron: `* * * * * php artisan schedule:run` |
| Reverb | `php artisan reverb:start` |

Reverb is required for live participation counters and roster refresh on activity/event pages. Set `VITE_REVERB_*` to the public WebSocket endpoint (host, port, `https`/`wss` scheme) that browsers reach.

Do not expose Sail-only tools (Adminer, Mailpit) in production.

## Post-deploy verification

- `GET /up` returns healthy
- Login and registration (and OAuth if enabled)
- Media upload (avatars, listing images)
- Queue: media conversions complete
- Reverb: open activity show in two logged-in browsers; join/leave in one updates counters in the other
- Outbound mail (password reset or notification)

## Backups

- PostgreSQL (daily minimum)
- `storage/app` (private and public media)

## Broadcast channels

Private channel `activity.{id}` is intentionally available to **any authenticated user** when the activity exists, so visitors see live capacity/roster changes before joining. Only `activityId` is broadcast; full roster data is loaded over HTTP/Livewire.
