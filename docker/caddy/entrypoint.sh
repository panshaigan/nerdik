#!/bin/sh
# Generate Caddyfile from container env at startup (reliable domain substitution).
set -eu

: "${APP_DOMAIN:?APP_DOMAIN is required}"
: "${STAGING_DOMAIN:?STAGING_DOMAIN is required}"
: "${ACME_EMAIL:?ACME_EMAIL is required}"

STAGING_MAILPIT_DOMAIN="${STAGING_MAILPIT_DOMAIN:-}"

cat > /etc/caddy/Caddyfile <<EOF
{
	email ${ACME_EMAIL}
}

${APP_DOMAIN} {
	encode gzip

	@maintenance file /etc/caddy/state/maintenance

	handle @maintenance {
		root * /etc/caddy/maintenance
		rewrite * /index.html
		header Cache-Control "no-store"
		header Retry-After "300"
		file_server
	}

	handle /app/* {
		reverse_proxy nerdik-prod-reverb:8080
	}

	handle {
		reverse_proxy nerdik-prod-app:80 {
			transport http {
				read_timeout 30s
				dial_timeout 5s
			}
		}
	}

	handle_errors {
		@upstream expression {http.error.status_code} >= 502 && {http.error.status_code} <= 504
		handle @upstream {
			root * /etc/caddy/maintenance
			rewrite * /index.html
			header Cache-Control "no-store"
			file_server
		}
	}
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

if [ -n "$STAGING_MAILPIT_DOMAIN" ]; then
cat >> /etc/caddy/Caddyfile <<EOF

${STAGING_MAILPIT_DOMAIN} {
	encode gzip

	reverse_proxy nerdik-staging-mailpit:8025 {
		transport http {
			read_timeout 30s
			dial_timeout 5s
		}
	}
}
EOF
fi

exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
