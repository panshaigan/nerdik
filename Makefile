SHELL := /bin/bash

SAIL := ./vendor/bin/sail

.PHONY: up down restart ps logs shell migrate refresh fresh seed queue scheduler test \
        npm-install npm-dev npm-build tinker serve composer-install composer-require \
        dump cache artisan pint sail tags-recalculate tags-seed-images test-all \
        docker-config docker-pull staging-deploy staging-down staging-ps dev-deploy prod-deploy deploy vps-deploy docker-publish

up:
	$(SAIL) up -d
	nohup $(SAIL) artisan schedule:work > storage/logs/scheduler.log 2>&1 &
	nohup $(SAIL) artisan queue:work > storage/logs/queue.log 2>&1 &

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

refresh:
	$(SAIL) artisan migrate:refresh --seed
	$(SAIL) artisan tags:recalculate-popularity

seed:
	$(SAIL) artisan db:seed

dump:
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

# New dedicated command for recalculating tags popularity
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
	$(STAGING_DC) down

staging-ps:
	$(STAGING_DC) ps

dev-deploy:
	@echo "dev-deploy is deprecated; use: make staging-deploy" >&2
	$(MAKE) staging-deploy IMAGE_TAG=$(IMAGE_TAG) BUILD=$(BUILD)

prod-deploy:
	$(MAKE) deploy DEPLOY_ENV=prod IMAGE_TAG=$(IMAGE_TAG) BUILD=$(BUILD)

vps-deploy:
	./scripts/vps-deploy.sh

deploy:
	$(DEPLOY_IMAGE_ENV) ./scripts/deploy.sh $(DEPLOY_ENV) $(DEPLOY_BUILD_FLAG)

docker-publish:
	./scripts/docker-publish.sh
