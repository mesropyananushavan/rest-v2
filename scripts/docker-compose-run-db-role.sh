#!/bin/sh
set -eu

role="$1"
shift

app_env=local
cache_store=
db_connection=pgsql
db_database="$DB_DATABASE"
db_host=postgres
db_port=5432
db_username="$DB_RUNTIME_USERNAME"
db_password="$DB_RUNTIME_PASSWORD"
queue_connection=
session_driver=
no_deps=

case "$role" in
    migration)
        db_username="$DB_MIGRATION_USERNAME"
        db_password="$DB_MIGRATION_PASSWORD"
        ;;
    tenant-test)
        app_env=testing
        cache_store=array
        db_database="$PGSQL_TEST_DB"
        db_username="$PGSQL_TEST_USER"
        db_password="$PGSQL_TEST_PASSWORD"
        queue_connection=sync
        session_driver=array
        no_deps=--no-deps
        ;;
    runtime-test)
        app_env=testing
        cache_store=array
        db_database="$PGSQL_RUNTIME_TEST_DB"
        db_username="$PGSQL_RUNTIME_TEST_USER"
        db_password="$PGSQL_RUNTIME_TEST_PASSWORD"
        queue_connection=database
        session_driver=array
        no_deps=--no-deps
        ;;
    runtime-test-migration)
        app_env=testing
        cache_store=array
        db_database="$PGSQL_RUNTIME_TEST_DB"
        db_username="$DB_MIGRATION_USERNAME"
        db_password="$DB_MIGRATION_PASSWORD"
        queue_connection=sync
        session_driver=array
        no_deps=--no-deps
        ;;
    *)
        printf '%s\n' "Unknown database role profile: $role" >&2
        exit 2
        ;;
esac

export APP_ENV="$app_env"
export DB_CONNECTION="$db_connection"
export DB_DATABASE="$db_database"
export DB_HOST="$db_host"
export DB_PORT="$db_port"
export DB_USERNAME="$db_username"
export DB_PASSWORD="$db_password"
export DB_URL=

args="run --rm"
if [ -n "$no_deps" ]; then
    args="$args $no_deps"
fi

if [ -n "$cache_store" ]; then
    export CACHE_STORE="$cache_store"
    args="$args -e CACHE_STORE"
fi

if [ -n "$queue_connection" ]; then
    export QUEUE_CONNECTION="$queue_connection"
    args="$args -e QUEUE_CONNECTION"
fi

if [ -n "$session_driver" ]; then
    export SESSION_DRIVER="$session_driver"
    args="$args -e SESSION_DRIVER"
fi

exec docker compose $args \
    -e APP_ENV \
    -e DB_CONNECTION \
    -e DB_DATABASE \
    -e DB_HOST \
    -e DB_PORT \
    -e DB_USERNAME \
    -e DB_PASSWORD \
    -e DB_MIGRATION_USERNAME \
    -e DB_MIGRATION_PASSWORD \
    -e DB_URL \
    php-fpm "$@"
