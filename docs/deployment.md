# Deployment

Checklist for production and staging environments. For the multi-phase roadmap (Docker, CI/CD, K8s), see [deployment-plan.md](deployment-plan.md). For local development, see [development-workflow.md](development-workflow.md).

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
