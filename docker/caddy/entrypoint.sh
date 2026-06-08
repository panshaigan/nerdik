#!/bin/sh
# Generate Caddyfile from container env at startup (reliable domain substitution).
set -eu

: "${APP_DOMAIN:?APP_DOMAIN is required}"
: "${STAGING_DOMAIN:?STAGING_DOMAIN is required}"
: "${ACME_EMAIL:?ACME_EMAIL is required}"

cat > /etc/caddy/Caddyfile <<EOF
{
	email ${ACME_EMAIL}
}

${APP_DOMAIN} {
	encode gzip

	handle /app/* {
		reverse_proxy nerdik-prod-reverb:8080
	}

	reverse_proxy nerdik-prod-app:80
}

${STAGING_DOMAIN} {
	encode gzip

	handle /app/* {
		reverse_proxy nerdik-staging-reverb:8080 {
			transport http {
				read_timeout 5s
				dial_timeout 2s
			}
		}
	}

	handle {
		reverse_proxy nerdik-staging-app:80 {
			transport http {
				read_timeout 5s
				dial_timeout 2s
			}
		}
	}

	handle_errors {
		respond "Staging is offline" 503
	}
}
EOF

exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
