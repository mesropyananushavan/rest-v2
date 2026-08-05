COMPOSE := docker compose
LOCAL_DB_ENV := sh scripts/local-db-env.sh
CONFIG_CACHE_PATH ?= bootstrap/cache/config.php
CONFIG_GUARD := CONFIG_CACHE_PATH='$(CONFIG_CACHE_PATH)' sh scripts/ensure-config-uncached.sh
RUN_DB_ROLE := $(LOCAL_DB_ENV) sh scripts/docker-compose-run-db-role.sh
PG_ROLE := $(LOCAL_DB_ENV) sh scripts/postgres/runtime-role-privileges.sh
APP := $(LOCAL_DB_ENV) $(COMPOSE) run --rm php-fpm
APP_NO_DEPS := $(LOCAL_DB_ENV) $(COMPOSE) run --rm --no-deps php-fpm
APP_MIGRATION := $(RUN_DB_ROLE) migration
APP_TEST := $(COMPOSE) run --rm --no-deps \
	-e APP_ENV=testing \
	-e CACHE_STORE=array \
	-e DB_CONNECTION=sqlite \
	-e DB_DATABASE=:memory: \
	-e DB_URL= \
	-e QUEUE_CONNECTION=sync \
	-e SESSION_DRIVER=array \
	php-fpm
APP_TEST_PGSQL := $(RUN_DB_ROLE) tenant-test
APP_TEST_PGSQL_RUNTIME := $(RUN_DB_ROLE) runtime-test
APP_TEST_PGSQL_MIGRATION := $(RUN_DB_ROLE) runtime-test-migration
NODE := docker run --rm -u $$(id -u):$$(id -g) -v "$$(pwd)":/app -w /app node:24-alpine

.PHONY: up down restart shell artisan pgsql pgsql-runtime test tenant-isolation-pgsql orders-concurrency-pgsql cashboxes-concurrency-pgsql payments-concurrency-pgsql runtime-role-pgsql prepare-pgsql-test-db prepare-runtime-pgsql-test-db provision-runtime-db-role grant-runtime-db-privileges wait-postgres ensure-config-uncached config-clear stan pint fresh build smoke-menu-context tools logs logs-queue

up:
	@$(CONFIG_GUARD)
	@$(MAKE) grant-runtime-db-privileges
	$(LOCAL_DB_ENV) $(COMPOSE) up -d --build

down:
	$(COMPOSE) down

restart:
	@$(CONFIG_GUARD)
	$(COMPOSE) down
	$(MAKE) up

shell:
	@$(CONFIG_GUARD)
	$(APP) bash

artisan:
	@$(CONFIG_GUARD)
	$(APP) php artisan $(ARGS)

pgsql:
	@$(LOCAL_DB_ENV) sh -c 'docker compose exec -T postgres psql -U "$$DB_MIGRATION_USERNAME" -d "$$DB_DATABASE" "$$@"' sh $(ARGS)

pgsql-runtime:
	@$(CONFIG_GUARD)
	$(APP) php artisan db:show --connection=pgsql

test:
	@$(CONFIG_GUARD)
	$(APP_TEST) vendor/bin/pest $(ARGS)

tenant-isolation-pgsql:
	@$(CONFIG_GUARD)
	@$(MAKE) prepare-pgsql-test-db
	@$(APP_TEST_PGSQL) vendor/bin/pest tests/Feature/Tenancy

orders-concurrency-pgsql:
	@$(CONFIG_GUARD)
	@$(MAKE) prepare-pgsql-test-db
	@$(APP_TEST_PGSQL) vendor/bin/pest tests/Feature/Orders/OrderConcurrencyTest.php

cashboxes-concurrency-pgsql:
	@$(CONFIG_GUARD)
	@$(MAKE) prepare-pgsql-test-db
	@$(APP_TEST_PGSQL) vendor/bin/pest tests/Feature/Payments/CashboxConcurrencyTest.php

payments-concurrency-pgsql:
	@$(CONFIG_GUARD)
	@$(MAKE) prepare-pgsql-test-db
	@$(APP_TEST_PGSQL) vendor/bin/pest tests/Feature/Payments/CaptureCashPaymentPostgreSQLTest.php

runtime-role-pgsql:
	@$(CONFIG_GUARD)
	@$(MAKE) prepare-runtime-pgsql-test-db
	@$(APP_TEST_PGSQL_RUNTIME) vendor/bin/pest tests/Feature/PostgreSQL/RuntimeDatabaseRoleTest.php

prepare-pgsql-test-db:
	@$(CONFIG_GUARD)
	$(LOCAL_DB_ENV) $(COMPOSE) up -d postgres
	@$(PG_ROLE) prepare-tenant-test-db

wait-postgres:
	@$(CONFIG_GUARD)
	@$(LOCAL_DB_ENV) $(COMPOSE) up -d postgres
	@$(PG_ROLE) wait

provision-runtime-db-role: wait-postgres
	@$(CONFIG_GUARD)
	@$(PG_ROLE) grant-runtime

grant-runtime-db-privileges: provision-runtime-db-role
	@$(CONFIG_GUARD)

prepare-runtime-pgsql-test-db: wait-postgres
	@$(CONFIG_GUARD)
	@$(PG_ROLE) prepare-runtime-test-db
	@$(APP_TEST_PGSQL_MIGRATION) php artisan migrate:fresh --seed --force
	@$(PG_ROLE) grant-runtime-test-db

ensure-config-uncached:
	@$(CONFIG_GUARD)

config-clear:
	$(APP_NO_DEPS) php artisan config:clear

stan:
	$(APP_NO_DEPS) vendor/bin/phpstan analyse --memory-limit=1G

pint:
	$(APP_NO_DEPS) vendor/bin/pint

fresh:
	@$(CONFIG_GUARD)
	$(APP) php artisan storage:link --force
	@$(APP_MIGRATION) php artisan migrate:fresh --seed
	$(MAKE) grant-runtime-db-privileges

build:
	$(APP_NO_DEPS) composer install
	$(APP_NO_DEPS) php artisan key:generate --ansi
	$(APP_NO_DEPS) php artisan storage:link --force
	$(NODE) npm ci
	$(NODE) npm run build

smoke-menu-context:
	@$(CONFIG_GUARD)
	$(LOCAL_DB_ENV) $(COMPOSE) up -d nginx
	$(APP) php artisan smoke:menu-context

tools:
	$(COMPOSE) --profile dev up -d adminer

logs:
	$(COMPOSE) exec php-fpm sh -lc 'touch storage/logs/smartrest.json && tail -f storage/logs/smartrest.json'

logs-queue:
	$(COMPOSE) logs -f horizon
