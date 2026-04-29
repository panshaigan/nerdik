SHELL := /bin/bash

SAIL := ./vendor/bin/sail

.PHONY: up down restart ps logs shell migrate refresh fresh seed queue scheduler test npm-install npm-dev npm-build tinker serve composer-install composer-require dump cache artisan pint sail
up:
	$(SAIL) up -d

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

test:
	$(SAIL) artisan test

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

