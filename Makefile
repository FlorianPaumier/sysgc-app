# SysGC — Docker-based task runner (see docs/makefile.md)
# All commands target the FrankenPHP/Caddy Docker stack. Run from app/.

DOCKER_COMP = docker compose
PHP_CONT    = $(DOCKER_COMP) exec php
PHP         = $(PHP_CONT) php
COMPOSER     = $(PHP_CONT) composer
SYMFONY      = $(PHP) bin/console

.DEFAULT_GOAL = help
.PHONY: help build up start down stop logs sh composer sf cc db migrate test stan stan-baseline rector infection e2e install

## —— Help —————————————————————————————————————————————————————————————————————
help: ## Show this help
	@grep -hE '(^[a-zA-Z0-9_-]+:.*?## .*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-18s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker ————————————————————————————————————————————————————————————————————
build: ## Build the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

up: ## Start containers (detached) and wait for healthchecks
	@$(DOCKER_COMP) up --detach --wait

start: build up ## Build then start

down: ## Stop and remove containers
	@$(DOCKER_COMP) down --remove-orphans

stop: ## Stop containers
	@$(DOCKER_COMP) stop

logs: ## Tail container logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Shell into the PHP container
	@$(PHP_CONT) sh

## —— Symfony / Composer ————————————————————————————————————————————————————————
composer: ## Run composer: make composer c="require pkg"
	@$(eval c ?=)
	@$(COMPOSER) $(c)

sf: ## Run a console command: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: ## Clear the cache
	@$(SYMFONY) cache:clear

## —— Database ——————————————————————————————————————————————————————————————————
db: ## Create the database (if missing)
	@$(SYMFONY) doctrine:database:create --if-not-exists

migrate: ## Run pending migrations + ensure Messenger queue table
	@$(SYMFONY) doctrine:migrations:migrate --no-interaction --all-or-nothing
	@$(SYMFONY) messenger:setup-transports --no-interaction || true

## —— Quality gate ——————————————————————————————————————————————————————————————
test: ## Run PHPUnit (APP_ENV=test)
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit

stan: ## Run PHPStan at max level
	@$(DOCKER_COMP) exec php vendor/bin/phpstan analyse --memory-limit=256M

stan-baseline: ## (Re)generate the PHPStan baseline
	@$(DOCKER_COMP) exec php vendor/bin/phpstan analyse --memory-limit=256M --generate-baseline

rector: ## Run Rector (dry-run; drop --dry-run to apply)
	@$(DOCKER_COMP) exec php vendor/bin/rector process src/ tests/ --dry-run

infection: ## Run Infection mutation testing
	@$(DOCKER_COMP) exec -e APP_ENV=test php vendor/bin/infection --min-msi=70 --min-covered-msi=80

## —— E2E ———————————————————————————————————————————————————————————————————————
e2e: ## Run Playwright E2E (npx playwright install first; app on https://localhost)
	npx playwright test

## —— Setup —————————————————————————————————————————————————————————————————————
install: ## First-time setup: build, up, install deps, create DB, migrate
	@$(DOCKER_COMP) build
	@$(DOCKER_COMP) up --detach --wait
	@$(COMPOSER) install --prefer-dist --no-progress --no-interaction
	@$(SYMFONY) doctrine:database:create --if-not-exists
	@$(SYMFONY) doctrine:migrations:migrate --no-interaction --all-or-nothing
	@$(SYMFONY) messenger:setup-transports --no-interaction || true
	@echo "✅ Setup complete — app at https://localhost, Mailpit at http://localhost:8025"
