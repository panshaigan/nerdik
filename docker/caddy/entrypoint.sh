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

	# root / is required so the absolute flag path is not joined to a site root.
	@maintenance file /etc/caddy/state/maintenance {
		root /
	}

	handle @maintenance {
		error 503
	}

	handle /app/* {
		reverse_proxy nerdik-prod-reverb:8080
	}

	handle {
		# Retry brief upstream dial/connect blips before surfacing the maintenance page.
		# dial_timeout must stay below lb_try_duration so more than one attempt fits.
		reverse_proxy nerdik-prod-app:80 {
			lb_try_duration 3s
			lb_try_interval 250ms
			transport http {
				read_timeout 30s
				dial_timeout 1s
			}
		}
	}

	# Flag mode (error 503) and upstream 502–504 share this page.
	# Recovery refresh is handled by JS in the page (always reloads window.top).
	handle_errors {
		@upstream expression {http.error.status_code} >= 502 && {http.error.status_code} <= 504
		handle @upstream {
			root * /etc/caddy/maintenance
			rewrite * /index.html
			header Cache-Control "no-store"
			header Retry-After "300"
			file_server {
				status 503
			}
		}
	}
}

www.${APP_DOMAIN} {
	redir https://${APP_DOMAIN}{uri} permanent
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
