SHELL := /bin/bash

SAIL := ./vendor/bin/sail
APP_CMD := ./scripts/app-cmd.sh

SEED_DATASET ?= minimal

.PHONY: up down restart ps logs shell migrate refresh fresh seed seed-minimal seed-standard seed-maximal \
        test npm composer tinker serve cache artisan pint sail regenerate-tags test-all \
        maintenance deploy init dump-schema sync-from-prod sync-to-staging check \
        backup-prod backup-prod-dry-run restore-prod sail-build sail-rebuild \
        regenerate-backgrounds regenerate-brand-logo regenerate-welcome-image boost

# Data sync flags
SYNC_FLAGS :=
ifeq ($(YES),1)
SYNC_FLAGS += --yes
endif
ifeq ($(BACKUP),1)
SYNC_FLAGS += --backup
endif
ifeq ($(DRY_RUN),1)
SYNC_FLAGS += --dry-run
endif
ifeq ($(DB),1)
SYNC_FLAGS += --db-only
endif
ifeq ($(STORAGE),1)
SYNC_FLAGS += --storage-only
endif

# Production restore on VPS (/opt/nerdik): ARCHIVE = backup dir or .tar.gz
# Recommended: make restore-prod ARCHIVE=... YES=1 RESTORE_BACKUP=1
# Optional: DRY_RUN=1, RESTORE_ENV=1, DB_ONLY=1, STORAGE_ONLY=1
RESTORE_FLAGS :=
ifeq ($(YES),1)
RESTORE_FLAGS += --yes
endif
ifeq ($(RESTORE_BACKUP),1)
RESTORE_FLAGS += --backup
endif
ifeq ($(RESTORE_ENV),1)
RESTORE_FLAGS += --restore-env
endif
ifeq ($(DRY_RUN),1)
RESTORE_FLAGS += --dry-run
endif
ifeq ($(DB_ONLY),1)
RESTORE_FLAGS += --db-only
endif
ifeq ($(STORAGE_ONLY),1)
RESTORE_FLAGS += --storage-only
endif

# Day-to-day commands: Sail when APP_ENV=local, compose stack when staging/production.
# Local `up` also runs boost:update and verifies Boost MCP (see scripts/lib/boost.sh).
up:
	$(APP_CMD) up

down:
	$(APP_CMD) down

restart:
	$(APP_CMD) restart

# Refresh Laravel Boost guidelines/skills and verify MCP (Sail / local only)
boost:
	$(APP_CMD) boost

ps:
	$(APP_CMD) ps

logs:
	$(APP_CMD) logs

shell:
	$(APP_CMD) shell

tinker:
	$(APP_CMD) tinker

migrate:
	$(APP_CMD) migrate

init:
	$(APP_CMD) init

fresh:
	$(APP_CMD) fresh

refresh:
	SEED_DATASET=$(SEED_DATASET) $(APP_CMD) refresh

seed:
	SEED_DATASET=$(SEED_DATASET) $(APP_CMD) seed

seed-minimal:
	@$(MAKE) seed SEED_DATASET=minimal

seed-standard:
	@$(MAKE) seed SEED_DATASET=standard

seed-maximal:
	@$(MAKE) seed SEED_DATASET=maximal

dump-schema:
	$(SAIL) artisan schema:dump --prune

cache:
	$(APP_CMD) cache

# Run tests — you can now pass arguments
# Examples:
#   make test
#   make test --filter ActivityBadgeGroupBuilderTest
#   make test --filter ActivityBadgeGroupBuilderTest::test_something
test:
	$(SAIL) artisan test --filter $(filter-out $@,$(MAKECMDGOALS))

test-all:
	$(SAIL) artisan test --parallel

regenerate-tags:
	$(SAIL) artisan tags:recalculate-popularity

regenerate-backgrounds:
	$(SAIL) artisan app:generate-shell-backgrounds

regenerate-brand-logo:
	$(SAIL) artisan app:generate-brand-logo

regenerate-welcome-image:
	$(APP_CMD) regenerate-welcome-image

# Sail-only: make npm install | make npm run build | …
npm:
	@./scripts/lib/runtime.sh --print | grep -q '^RUNTIME=sail$$' || { echo "make npm is Sail-only (APP_ENV=local)." >&2; exit 1; }
	$(SAIL) npm $(filter-out $@,$(MAKECMDGOALS))

# Sail-only: make composer install | make composer require vendor/pkg | …
composer:
	@./scripts/lib/runtime.sh --print | grep -q '^RUNTIME=sail$$' || { echo "make composer is Sail-only (APP_ENV=local)." >&2; exit 1; }
	$(SAIL) composer $(filter-out $@,$(MAKECMDGOALS))

artisan:
	$(APP_CMD) artisan $(filter-out $@,$(MAKECMDGOALS))

pint:
	$(SAIL) bin pint --dirty --format agent

# Local CI parity: gitleaks, compose, tests, composer audit, pint; FULL=1 adds Docker build
check:
	FULL=$(FULL) ./scripts/ci-check.sh

# VPS Docker stack (not Sail) — env from APP_ENV in this checkout's .env
IMAGE_TAG ?=
BUILD ?=

# Production Caddy maintenance: make maintenance on|off|status
maintenance:
	./scripts/maintenance.sh $(filter-out $@,$(MAKECMDGOALS))

# Full VPS release: git pull + verify GHCR image + deploy this checkout
deploy:
	$(if $(IMAGE_TAG),IMAGE_TAG=$(IMAGE_TAG),) $(if $(BUILD),BUILD=$(BUILD),) ./scripts/vps-deploy.sh

# Prod → this checkout (local Sail or VPS staging). Blocked on production.
# Examples: make sync-from-prod YES=1
#           make sync-from-prod DB=1
#           make sync-from-prod tables users activities
sync-from-prod:
	./scripts/sync/sync-from-prod.sh $(SYNC_FLAGS) $(filter-out $@,$(MAKECMDGOALS))

# Local Sail → VPS staging (APP_ENV=local only)
sync-to-staging:
	./scripts/sync/sync-to-staging.sh $(SYNC_FLAGS) $(filter-out $@,$(MAKECMDGOALS))

backup-prod:
	./scripts/backup/backup-prod.sh

backup-prod-dry-run:
	DRY_RUN=1 ./scripts/backup/backup-prod.sh

# VPS: restore prod DB + storage/app from a backup folder or .tar.gz (see docs/deployment.md).
restore-prod:
	@if [ -z "$(ARCHIVE)" ]; then \
		echo "Usage: make restore-prod ARCHIVE=/path/to/backup_dir_or.tar.gz [YES=1] [RESTORE_BACKUP=1] [RESTORE_ENV=1] [DRY_RUN=1] [DB_ONLY=1] [STORAGE_ONLY=1]" >&2; \
		echo "Run on VPS from /opt/nerdik. Prefer RESTORE_BACKUP=1 to snapshot current prod to /tmp first." >&2; \
		exit 1; \
	fi
	./scripts/backup/restore-prod.sh "$(ARCHIVE)" $(RESTORE_FLAGS)

NO_CACHE ?=

sail-build:
	$(SAIL) build $(if $(NO_CACHE),--no-cache,)

sail-rebuild: down
	$(SAIL) build --no-cache
	$(SAIL) up -d

%:
	@:
