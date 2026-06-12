SHELL := /bin/bash

SAIL := ./vendor/bin/sail

.PHONY: up down restart ps logs shell migrate refresh fresh seed queue scheduler test \
        npm-install npm-dev npm-build tinker serve composer-install composer-require \
        dump cache artisan pint sail tags-recalculate tags-seed-images test-all \
        docker-config docker-pull staging-deploy staging-down staging-ps staging-refresh \
        staging-artisan dev-deploy prod-deploy prod-refresh prod-artisan deploy vps-deploy \
        vps-staging-deploy docker-publish dump-schema sync-from-prod sync-from-prod-db \
        sync-from-prod-storage prod-to-staging-sync prod-to-staging-sync-remote ci-check \
        sail-build sail-rebuild

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

up:
	$(SAIL) up -d
#	nohup $(SAIL) artisan schedule:work > storage/logs/scheduler.log 2>&1 &
#	nohup $(SAIL) artisan queue:work > storage/logs/queue.log 2>&1 &

down:
	$(SAIL) down

restart:
	$(SAIL) down && $(SAIL) up -d

ps:
	$(SAIL) ps

logs:
	$(SAIL) logs -f

shell:
	$(SAIL) shell

tinker:
	$(SAIL) tinker

migrate:
	$(SAIL) artisan migrate

fresh:
	$(SAIL) artisan migrate:fresh
	$(SAIL) artisan tags:recalculate-popularity

refresh:
	$(SAIL) artisan migrate:refresh --seed
	$(SAIL) artisan tags:recalculate-popularity

seed:
	$(SAIL) artisan db:seed

dump-schema:
	$(SAIL) artisan schema:dump --prune

queue:
	$(SAIL) artisan queue:work

scheduler:
	$(SAIL) artisan schedule:work

serve:
	$(SAIL) artisan serve

cache:
	$(SAIL) artisan optimize:clear

# Run tests — you can now pass arguments
# Examples:
#   make test
#   make test --filter ActivityBadgeGroupBuilderTest
#   make test --filter ActivityBadgeGroupBuilderTest::test_something
test:
	$(SAIL) artisan test --filter $(filter-out $@,$(MAKECMDGOALS))

test-all:
	$(SAIL) artisan test

tags-recalculate:
	$(SAIL) artisan tags:recalculate-popularity

tags-seed-images:
	$(SAIL) artisan tags:seed-images

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

artisan:
	$(SAIL) artisan $(filter-out $@,$(MAKECMDGOALS))

pint:
	$(SAIL) bin pint --dirty --format agent

ci-check:
	FULL=$(FULL) ./scripts/ci-check.sh

# VPS Docker stack (not Sail)
DEPLOY_ENV ?= prod
IMAGE_TAG ?=
BUILD ?=
DEPLOY_BUILD_FLAG := $(if $(BUILD),--build,)
DEPLOY_IMAGE_ENV := $(if $(IMAGE_TAG),IMAGE_TAG=$(IMAGE_TAG),)
DC := docker compose -f compose.stack.yaml -f compose.$(DEPLOY_ENV).yaml
STAGING_DC := docker compose -f compose.stack.yaml -f compose.staging.yaml

docker-config:
	$(DC) config

docker-pull:
	$(DEPLOY_IMAGE_ENV) ./scripts/deploy.sh $(DEPLOY_ENV) --pull-only

staging-deploy:
	$(MAKE) deploy DEPLOY_ENV=staging IMAGE_TAG=$(IMAGE_TAG) BUILD=$(BUILD)

staging-down:
	./scripts/compose-exec.sh staging down

staging-ps:
	./scripts/compose-exec.sh staging ps

dev-deploy:
	@echo "dev-deploy is deprecated; use: make staging-deploy" >&2
	$(MAKE) staging-deploy IMAGE_TAG=$(IMAGE_TAG) BUILD=$(BUILD)

prod-deploy:
	$(MAKE) deploy DEPLOY_ENV=prod IMAGE_TAG=$(IMAGE_TAG) BUILD=$(BUILD)

prod-artisan:
	./scripts/compose-exec.sh prod exec -T app php artisan $(filter-out $@,$(MAKECMDGOALS))

prod-refresh:
	./scripts/compose-exec.sh prod exec -T app php artisan migrate:refresh --seed --force
	./scripts/compose-exec.sh prod exec -T app php artisan tags:recalculate-popularity

staging-artisan:
	./scripts/compose-exec.sh staging exec -T app php artisan $(filter-out $@,$(MAKECMDGOALS))

staging-refresh:
	./scripts/compose-exec.sh staging exec -T app php artisan migrate:refresh --seed --force
	./scripts/compose-exec.sh staging exec -T app php artisan tags:recalculate-popularity

vps-deploy:
	./scripts/vps-deploy.sh prod

vps-staging-deploy:
	./scripts/vps-deploy.sh staging

deploy:
	$(DEPLOY_IMAGE_ENV) ./scripts/deploy.sh $(DEPLOY_ENV) $(DEPLOY_BUILD_FLAG)

docker-publish:
	./scripts/docker-publish.sh

sync-from-prod:
	./scripts/sync/pull-from-prod.sh $(SYNC_FLAGS)

sync-from-prod-db:
	./scripts/sync/pull-from-prod.sh --db-only $(SYNC_FLAGS)

sync-from-prod-storage:
	./scripts/sync/pull-from-prod.sh --storage-only $(SYNC_FLAGS)

prod-to-staging-sync:
	./scripts/sync/prod-to-staging.sh $(SYNC_FLAGS)

prod-to-staging-sync-remote:
	./scripts/sync/prod-to-staging-remote.sh $(SYNC_FLAGS)

NO_CACHE ?=

sail-build:
	$(SAIL) build $(if $(NO_CACHE),--no-cache,)

sail-rebuild: down
	$(SAIL) build --no-cache
	$(SAIL) up -d
