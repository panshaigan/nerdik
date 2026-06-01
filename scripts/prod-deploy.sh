#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

COMPOSE=(docker compose -f compose.prod.yaml)

if [[ ! -f .env ]]; then
    echo "Missing .env — copy .env.production.example to .env and configure secrets." >&2
    exit 1
fi

if [[ ! -f docker/caddy/Caddyfile ]]; then
    echo "Missing docker/caddy/Caddyfile — copy docker/caddy/Caddyfile.example and set APP_DOMAIN." >&2
    exit 1
fi

"${COMPOSE[@]}" build
"${COMPOSE[@]}" up -d
"${COMPOSE[@]}" exec -T app php artisan migrate --force
"${COMPOSE[@]}" exec -T app php artisan config:cache
"${COMPOSE[@]}" exec -T app php artisan route:cache
"${COMPOSE[@]}" exec -T app php artisan view:cache

echo "Deploy complete. Verify: curl -fsS \"\${APP_URL:-https://localhost}/up\""
