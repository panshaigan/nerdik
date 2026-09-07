SHELL := /bin/bash

SAIL := ./vendor/bin/sail
APP_CMD := ./scripts/app-cmd.sh

SEED_DATASET ?= minimal

.PHONY: up down restart ps logs shell migrate refresh fresh seed seed-minimal seed-standard seed-maximal \
        queue scheduler test npm-install npm-dev npm-build tinker serve composer-install composer-require \
        composer-audit cache artisan pint sail tags-recalculate tags-seed-images test-all \
        docker-config docker-pull prod-maintenance-on prod-maintenance-off prod-maintenance-status \
        deploy init docker-publish dump-schema sync-from-prod sync-from-prod-db \
        sync-from-prod-storage sync-from-prod-tables prod-to-staging-sync prod-to-staging-sync-remote \
        prod-to-staging-sync-tables prod-to-staging-sync-tables-remote ci-check \
        backup-prod backup-prod-dry-run restore-prod sail-build sail-rebuild \
        regenerate-backgrounds regenerate-brand-logo regenerate-welcome-image

# Data sync (prod → local / staging)
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

# Production restore (ARCHIVE = backup directory or .tar.gz)
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

# Day-to-day commands: Sail when APP_ENV=local, compose stack when staging/production
up:
	$(APP_CMD) up

down:
	$(APP_CMD) down

restart:
	$(APP_CMD) restart

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

queue:
	$(SAIL) artisan queue:work

scheduler:
	$(SAIL) artisan schedule:work

serve:
	$(SAIL) artisan serve

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

tags-recalculate:
	$(SAIL) artisan tags:recalculate-popularity

tags-seed-images:
	$(SAIL) artisan tags:seed-images

regenerate-backgrounds:
	$(SAIL) artisan app:generate-shell-backgrounds

regenerate-brand-logo:
	$(SAIL) artisan app:generate-brand-logo

regenerate-welcome-image:
	$(APP_CMD) regenerate-welcome-image

npm-install:
	$(SAIL) npm install

npm-dev:
	$(SAIL) npm run dev

npm-build:
	$(SAIL) npm run build

composer-install:
	$(SAIL) composer install

composer-require:
	@if [ -z "$(PACKAGE)" ]; then echo "Usage: make composer-require PACKAGE=vendor/package"; exit 1; fi
	$(SAIL) composer require $(PACKAGE)

composer-audit:
	$(SAIL) composer audit

artisan:
	$(APP_CMD) artisan $(filter-out $@,$(MAKECMDGOALS))

pint:
	$(SAIL) bin pint --dirty --format agent

# Local CI parity: gitleaks, compose, tests, composer audit, pint; FULL=1 adds Docker build
ci-check:
	FULL=$(FULL) ./scripts/ci-check.sh

# VPS Docker stack (not Sail) — env from APP_ENV in this checkout's .env
IMAGE_TAG ?=
BUILD ?=

docker-config:
	@eval "$$(./scripts/compose-env.sh)"; \
	docker compose -f compose.stack.yaml -f compose.$${DEPLOY_ENV}.yaml config

docker-pull:
	$(if $(IMAGE_TAG),IMAGE_TAG=$(IMAGE_TAG),) ./scripts/deploy.sh --pull-only

prod-maintenance-on:
	./scripts/maintenance.sh on

prod-maintenance-off:
	./scripts/maintenance.sh off

prod-maintenance-status:
	./scripts/maintenance.sh status

# Full VPS release: git pull + verify GHCR image + deploy this checkout
deploy:
	$(if $(IMAGE_TAG),IMAGE_TAG=$(IMAGE_TAG),) $(if $(BUILD),BUILD=$(BUILD),) ./scripts/vps-deploy.sh

docker-publish:
	./scripts/docker-publish.sh

sync-from-prod:
	./scripts/sync/pull-from-prod.sh $(SYNC_FLAGS)

sync-from-prod-db:
	./scripts/sync/pull-from-prod.sh --db-only $(SYNC_FLAGS)

sync-from-prod-storage:
	./scripts/sync/pull-from-prod.sh --storage-only $(SYNC_FLAGS)

sync-from-prod-tables:
	@tables="$(filter-out $@,$(MAKECMDGOALS))"; \
	if [ -z "$$tables" ]; then \
		echo "Usage: make sync-from-prod-tables TABLE [TABLE...] [YES=1] [DRY_RUN=1]" >&2; \
		exit 1; \
	fi; \
	./scripts/sync/pull-from-prod.sh --tables $$tables $(SYNC_FLAGS)

prod-to-staging-sync:
	./scripts/sync/prod-to-staging.sh $(SYNC_FLAGS)

prod-to-staging-sync-remote:
	./scripts/sync/prod-to-staging-remote.sh $(SYNC_FLAGS)

prod-to-staging-sync-tables:
	@tables="$(filter-out $@,$(MAKECMDGOALS))"; \
	if [ -z "$$tables" ]; then \
		echo "Usage: make prod-to-staging-sync-tables TABLE [TABLE...] [YES=1] [BACKUP=1] [DRY_RUN=1]" >&2; \
		exit 1; \
	fi; \
	./scripts/sync/prod-to-staging.sh --tables $$tables $(SYNC_FLAGS)

prod-to-staging-sync-tables-remote:
	@tables="$(filter-out $@,$(MAKECMDGOALS))"; \
	if [ -z "$$tables" ]; then \
		echo "Usage: make prod-to-staging-sync-tables-remote TABLE [TABLE...] [YES=1] [BACKUP=1] [DRY_RUN=1]" >&2; \
		exit 1; \
	fi; \
	./scripts/sync/prod-to-staging-remote.sh --tables $$tables $(SYNC_FLAGS)

backup-prod:
	./scripts/backup/backup-prod.sh

backup-prod-dry-run:
	DRY_RUN=1 ./scripts/backup/backup-prod.sh

restore-prod:
	@if [ -z "$(ARCHIVE)" ]; then \
		echo "Usage: make restore-prod ARCHIVE=/path/to/backup.tar.gz [YES=1] [RESTORE_BACKUP=1] [RESTORE_ENV=1] [DRY_RUN=1]" >&2; \
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
