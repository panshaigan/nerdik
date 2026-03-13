SHELL := /bin/bash

SAIL := ./vendor/bin/sail

.PHONY: up down restart ps logs shell migrate migrate-fresh seed queue scheduler test npm-install npm-dev npm-build tinker serve

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

migrate-fresh:
	$(SAIL) artisan migrate:fresh

seed:
	$(SAIL) artisan db:seed

queue:
	$(SAIL) artisan queue:work

scheduler:
	$(SAIL) artisan schedule:work

serve:
	$(SAIL) artisan serve

test:
	$(SAIL) artisan test

npm-install:
	$(SAIL) npm install

npm-dev:
	$(SAIL) npm run dev

npm-build:
	$(SAIL) npm run build

