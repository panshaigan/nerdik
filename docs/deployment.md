# Deployment

Checklist for production and staging environments. For the multi-phase roadmap (Docker, CI/CD, K8s), see [deployment-plan.md](deployment-plan.md). For local development, see [development-workflow.md](development-workflow.md).

## Docker production (VPS)

Production runs via [`compose.prod.yaml`](../compose.prod.yaml) (not Sail [`compose.yaml`](../compose.yaml)).

### Prerequisites

- Docker Engine and Compose plugin on the server
- DNS `A`/`AAAA` for `APP_DOMAIN` pointing at the VPS (for Caddy automatic HTTPS)
- Ports `80` and `443` open

### First-time setup

1. Copy [`.env.production.example`](../.env.production.example) to `.env` and fill secrets (`APP_KEY`, `DB_PASSWORD`, Reverb keys, mail, OAuth).
2. Set `APP_DOMAIN` and `ACME_EMAIL` (used by the Caddy container).
3. Set `APP_URL` to `https://<APP_DOMAIN>`.
4. Set browser Reverb vars: `VITE_REVERB_HOST=<APP_DOMAIN>`, `VITE_REVERB_PORT=443`, `VITE_REVERB_SCHEME=https` (must match what you pass at **image build** — see below).
5. Copy Caddy config: `cp docker/caddy/Caddyfile.example docker/caddy/Caddyfile` and ensure `APP_DOMAIN` in `.env` matches the site block.
6. Build and start:

```bash
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml up -d
```

Or use [`scripts/prod-deploy.sh`](../scripts/prod-deploy.sh) after steps 1–5 (runs migrate and config caches).

7. Generate `APP_KEY` if needed: `docker compose -f compose.prod.yaml exec app php artisan key:generate`
8. Migrate and cache (if not using the deploy script):

```bash
docker compose -f compose.prod.yaml exec app php artisan migrate --force
docker compose -f compose.prod.yaml exec app php artisan config:cache
docker compose -f compose.prod.yaml exec app php artisan route:cache
docker compose -f compose.prod.yaml exec app php artisan view:cache
```

### Image build and Vite

Frontend assets are baked into the image (`npm run build` in [`docker/production/Dockerfile`](../docker/production/Dockerfile)). `VITE_REVERB_*` and `VITE_APP_NAME` are taken from build args (defaults from `.env` via Compose). If you change the public Reverb URL, rebuild the `app` image.

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

Persistent volumes: `nerdik_storage` (uploads/media), `nerdik_pgsql_data` (if using container Postgres).

### Updates

```bash
git pull
docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml exec app php artisan migrate --force
docker compose -f compose.prod.yaml exec app php artisan config:cache
docker compose -f compose.prod.yaml exec app php artisan route:cache
docker compose -f compose.prod.yaml exec app php artisan view:cache
```

Restart workers after deploy: `docker compose -f compose.prod.yaml restart worker scheduler reverb`

## Environment

1. Copy [`.env.production.example`](../.env.production.example) to `.env` on the server.
2. Run `php artisan key:generate` if `APP_KEY` is empty.
3. Set production values: `APP_URL` (HTTPS), database credentials, mail, OAuth/reCAPTCHA, Reverb keys.
4. Set `TRUSTED_PROXIES` when TLS terminates at a reverse proxy (`*` or specific proxy IPs).
5. Keep `APP_DEBUG=false`, `TELESCOPE_ENABLED=false`, and `PULSE_ENABLED=false` unless you explicitly need Pulse (admins only via `viewPulse` gate).

## Database

1. `php artisan migrate --force`
2. Polish full-text search: new PostgreSQL volumes pick up [`docker/pgsql/init-polish-fts.sql`](../docker/pgsql/init-polish-fts.sql) automatically. On managed Postgres, apply that script manually once per database.

## Application setup

1. `php artisan storage:link`
2. Build frontend assets before or during image build: `npm ci && npm run build` (committed `public/build` is not in git).
3. In production, cache configuration after deploy:
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
