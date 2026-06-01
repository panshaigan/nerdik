# Deployment

Checklist for production and staging environments. For CI/CD (GitHub Actions, GHCR, automated deploy), see [ci-cd.md](ci-cd.md). For the multi-phase roadmap (Docker, CI/CD, K8s), see [deployment-plan.md](deployment-plan.md). For local development, see [development-workflow.md](development-workflow.md).

## Docker production (VPS)

Production uses a shared stack plus prod overlay: [`compose.stack.yaml`](../compose.stack.yaml) + [`compose.prod.yaml`](../compose.prod.yaml) (not Sail [`compose.yaml`](../compose.yaml)).

### Prerequisites

- Docker Engine and Compose plugin on the server
- DNS `A`/`AAAA` for `APP_DOMAIN` pointing at the VPS (for Caddy automatic HTTPS)
- Ports `80` and `443` open
- GHCR package read access configured on the server (`docker login ghcr.io`)

### First-time setup

1. Copy [`.env.production.example`](../.env.production.example) to `.env` and fill secrets (`APP_KEY`, `DB_PASSWORD`, Reverb keys, mail, OAuth).
2. Set `APP_DOMAIN`, `ACME_EMAIL`, `GITHUB_OWNER`, and `NERDIK_IMAGE`.
3. Set `APP_URL` to `https://<APP_DOMAIN>`.
4. Set browser Reverb vars: `VITE_REVERB_HOST=<APP_DOMAIN>`, `VITE_REVERB_PORT=443`, `VITE_REVERB_SCHEME=https` (must match the image build).
5. Copy Caddy config: `cp docker/caddy/Caddyfile.example docker/caddy/Caddyfile` and ensure `APP_DOMAIN` in `.env` matches the site block.
6. Deploy:

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

Generate `APP_KEY` if needed:

```bash
docker compose -f compose.stack.yaml -f compose.prod.yaml exec app php artisan key:generate
```

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
| `reverb` | `reverb:start` |
| `pgsql` | PostgreSQL with Polish FTS init (or use external DB: set `DB_HOST` and remove `pgsql` service) |

Persistent volumes in prod: `nerdik_storage`, `nerdik_pgsql_data`.

### Updates

```bash
git pull
make prod-deploy
```

### Promote the same SHA from staging to production

```bash
IMAGE_TAG=<git-sha> make dev-deploy
IMAGE_TAG=<git-sha> make prod-deploy
```

## Environment

1. Copy [`.env.production.example`](../.env.production.example) to `.env` on production (or [`.env.staging.example`](../.env.staging.example) on staging).
2. Run `php artisan key:generate` if `APP_KEY` is empty.
3. Set `APP_URL`, DB credentials, mail, OAuth/reCAPTCHA, Reverb keys, and `NERDIK_IMAGE`.
4. Set `TRUSTED_PROXIES` when TLS terminates at a reverse proxy (`*` or specific proxy IPs).
5. Keep `APP_DEBUG=false`, `TELESCOPE_ENABLED=false`, and `PULSE_ENABLED=false` unless you explicitly need Pulse (admins only via `viewPulse` gate).

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
