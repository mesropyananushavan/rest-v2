COMPOSE := docker compose
APP := $(COMPOSE) run --rm php-fpm
APP_NO_DEPS := $(COMPOSE) run --rm --no-deps php-fpm
APP_TEST := $(COMPOSE) run --rm --no-deps \
	-e APP_ENV=testing \
	-e CACHE_STORE=array \
	-e DB_CONNECTION=sqlite \
	-e DB_DATABASE=:memory: \
	-e DB_URL= \
	-e QUEUE_CONNECTION=sync \
	-e SESSION_DRIVER=array \
	php-fpm
PGSQL_TEST_DB := smartrest_test_local
PGSQL_TEST_USER := smartrest_app_test
PGSQL_TEST_PASSWORD := smartrest_app_test
APP_TEST_PGSQL := $(COMPOSE) run --rm --no-deps \
	-e APP_ENV=testing \
	-e CACHE_STORE=array \
	-e DB_CONNECTION=pgsql \
	-e DB_HOST=postgres \
	-e DB_PORT=5432 \
	-e DB_DATABASE=$(PGSQL_TEST_DB) \
	-e DB_USERNAME=$(PGSQL_TEST_USER) \
	-e DB_PASSWORD=$(PGSQL_TEST_PASSWORD) \
	-e DB_URL= \
	-e QUEUE_CONNECTION=sync \
	-e SESSION_DRIVER=array \
	php-fpm
NODE := docker run --rm -u $$(id -u):$$(id -g) -v "$$(pwd)":/app -w /app node:24-alpine

.PHONY: up down restart shell artisan pgsql test tenant-isolation-pgsql prepare-pgsql-test-db stan pint fresh build smoke-menu-context tools logs logs-queue

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) down
	$(COMPOSE) up -d --build

shell:
	$(APP) bash

artisan:
	$(APP) php artisan $(ARGS)

pgsql:
	$(COMPOSE) exec -T postgres psql -U smartrest -d smartrest $(ARGS)

test:
	$(APP_TEST) vendor/bin/pest

tenant-isolation-pgsql: prepare-pgsql-test-db
	$(APP_TEST_PGSQL) vendor/bin/pest tests/Feature/Tenancy

prepare-pgsql-test-db:
	$(COMPOSE) up -d postgres
	$(COMPOSE) exec -T postgres sh -lc "psql -v ON_ERROR_STOP=1 -U smartrest -d postgres -tc \"SELECT 1 FROM pg_roles WHERE rolname = '$(PGSQL_TEST_USER)'\" | grep -q 1 || psql -v ON_ERROR_STOP=1 -U smartrest -d postgres -c \"CREATE ROLE $(PGSQL_TEST_USER) LOGIN PASSWORD '$(PGSQL_TEST_PASSWORD)'\""
	$(COMPOSE) exec -T postgres sh -lc "psql -v ON_ERROR_STOP=1 -U smartrest -d postgres -c \"ALTER ROLE $(PGSQL_TEST_USER) WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS PASSWORD '$(PGSQL_TEST_PASSWORD)'\""
	$(COMPOSE) exec -T postgres sh -lc "psql -v ON_ERROR_STOP=1 -U smartrest -d postgres -tc \"SELECT 1 FROM pg_database WHERE datname = '$(PGSQL_TEST_DB)'\" | grep -q 1 || psql -v ON_ERROR_STOP=1 -U smartrest -d postgres -c \"CREATE DATABASE $(PGSQL_TEST_DB) OWNER smartrest\""
	$(COMPOSE) exec -T postgres sh -lc "psql -v ON_ERROR_STOP=1 -U smartrest -d $(PGSQL_TEST_DB) -c \"CREATE EXTENSION IF NOT EXISTS pg_trgm\""
	$(COMPOSE) exec -T postgres sh -lc "psql -v ON_ERROR_STOP=1 -U smartrest -d $(PGSQL_TEST_DB) -c \"GRANT CONNECT ON DATABASE $(PGSQL_TEST_DB) TO $(PGSQL_TEST_USER)\""
	$(COMPOSE) exec -T postgres sh -lc "psql -v ON_ERROR_STOP=1 -U smartrest -d $(PGSQL_TEST_DB) -c \"GRANT USAGE, CREATE ON SCHEMA public TO $(PGSQL_TEST_USER)\""
	$(COMPOSE) exec -T postgres sh -lc "psql -v ON_ERROR_STOP=1 -U smartrest -d $(PGSQL_TEST_DB) -c \"ALTER SCHEMA public OWNER TO $(PGSQL_TEST_USER)\""

stan:
	$(APP_NO_DEPS) vendor/bin/phpstan analyse --memory-limit=1G

pint:
	$(APP_NO_DEPS) vendor/bin/pint

fresh:
	$(APP) php artisan storage:link --force
	$(APP) php artisan migrate:fresh --seed

build:
	$(APP_NO_DEPS) composer install
	$(APP_NO_DEPS) php artisan key:generate --ansi
	$(APP_NO_DEPS) php artisan storage:link --force
	$(NODE) npm ci
	$(NODE) npm run build

smoke-menu-context:
	$(COMPOSE) up -d nginx
	$(APP) php artisan smoke:menu-context

tools:
	$(COMPOSE) --profile dev up -d adminer

logs:
	$(COMPOSE) exec php-fpm sh -lc 'touch storage/logs/smartrest.json && tail -f storage/logs/smartrest.json'

logs-queue:
	$(COMPOSE) logs -f horizon
