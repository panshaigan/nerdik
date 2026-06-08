# GitHub Actions Deploy Setup

Step-by-step guide to enable remote deploy from GitHub Actions. Deploy secrets are **not stored in the repository** — they are configured in GitHub Settings. For the overall CI/CD pipeline, see [ci-cd.md](ci-cd.md). For VPS operations (including manual staging), see [deployment.md](deployment.md).

## Overview

The [Deploy workflow](../.github/workflows/deploy.yml) SSHes into your production VPS and runs [`scripts/vps-deploy.sh`](../scripts/vps-deploy.sh) with an explicit image SHA. Staging is deployed manually on the VPS (`make staging-deploy` / `make staging-down`). Until secrets are configured, the workflow prints a skip message and exits successfully.

## A. One-time VPS preparation

1. Install Docker Engine and the Compose plugin on the VPS.
2. Create a `deploy` Linux user with Docker group membership:
   ```bash
   sudo adduser deploy
   sudo usermod -aG docker deploy
   ```
3. Clone this repo as the deploy user (e.g. `/opt/nerdik`).
4. Copy [`.env.production.example`](../.env.production.example) to `.env` and fill secrets. Set `GITHUB_OWNER` to your GitHub username or org.
5. Set `STAGING_DOMAIN=staging.nerdik.app` (or your staging subdomain) for prod Caddy routing.
6. Copy Caddy config: `cp docker/caddy/Caddyfile.example docker/caddy/Caddyfile`.
7. Log in to GHCR on the VPS: `docker login ghcr.io` (PAT with `read:packages`).

## B. SSH key for GitHub Actions

Generate a deploy key pair **on your local machine** (not on the VPS):

```bash
ssh-keygen -t ed25519 -C "github-actions-nerdik-deploy" -f nerdik-deploy -N ""
```

Add the public key to the VPS:

```bash
# On the VPS, as root or via sudo:
sudo mkdir -p /home/deploy/.ssh
sudo tee -a /home/deploy/.ssh/authorized_keys < nerdik-deploy.pub
sudo chown -R deploy:deploy /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
sudo chmod 600 /home/deploy/.ssh/authorized_keys
```

Verify SSH access from your machine:

```bash
ssh -i nerdik-deploy deploy@<vps-ip> 'cd /opt/nerdik && git status'
```

Keep `nerdik-deploy` (private key) secure — you will paste its contents into GitHub.

## C. GitHub repository secrets

Go to **GitHub → your repo → Settings → Secrets and variables → Actions → Repository secrets**.

| Secret | Value |
|--------|-------|
| `DEPLOY_SSH_KEY` | Full contents of the private key file `nerdik-deploy` |
| `DEPLOY_USER` | `deploy` |
| `DEPLOY_HOST_PROD` | Production VPS hostname or IP |

## D. GitHub repository variables (optional)

Go to **Settings → Secrets and variables → Actions → Variables**.

| Variable | Example |
|----------|---------|
| `PROD_APP_URL` | `https://nerdik.app` |

When set, the Deploy workflow runs a smoke check (`GET /up`) after deploy.

## E. GitHub environments

Go to **Settings → Environments** and create:

- **`production`** — used by the prod deploy job. Add **required reviewers** here for a manual approval gate before production deploys.

## F. Verify remote deploy

1. Push to `main` and wait for CI and Docker workflows to finish.
2. Note the published image tag (full git SHA) from Actions → Docker workflow or GitHub Packages.
3. Go to **Actions → Deploy → Run workflow**.
4. Enter the image SHA (or leave empty to use the workflow run's commit SHA).
5. Approve if the `production` environment requires reviewers.
6. Confirm the SSH step succeeds and the smoke check passes.

## G. VPS manual deploy (alternative)

You can deploy directly on the VPS without GitHub Actions:

```bash
cd /opt/nerdik
make vps-deploy
```

This pulls the latest `main`, verifies the GHCR image for that SHA exists, and runs `make prod-deploy`.

Staging is manual only — see [deployment.md](deployment.md#staging-on-the-same-vps).

## Troubleshooting

| Symptom | Likely cause |
|---------|----------------|
| "Deploy skipped" in Actions | Repository secrets not configured (workflow exits green by design) |
| SSH authentication failure | Wrong `DEPLOY_SSH_KEY`, `DEPLOY_USER`, or missing `authorized_keys` entry |
| Image pull failure on VPS | `docker login ghcr.io` not done, or wrong `GITHUB_OWNER` in `.env` |
| "GHCR image not found" | Docker workflow on `main` has not finished publishing that SHA yet |
| Smoke check fails | `PROD_APP_URL` wrong, or app not healthy after deploy |

## Related

- [ci-cd.md](ci-cd.md) — pipeline overview
- [deployment.md](deployment.md) — VPS checklist and updates
- [`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml) — workflow definition
