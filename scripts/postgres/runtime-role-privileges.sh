#!/bin/sh
set -eu

run_psql() {
    database="$1"

    PGPASSWORD="$DB_MIGRATION_PASSWORD" docker compose exec -T \
        -e PGPASSWORD \
        -e DB_DATABASE \
        -e DB_MIGRATION_USERNAME \
        -e DB_RUNTIME_USERNAME \
        -e DB_RUNTIME_PASSWORD \
        -e PGSQL_TEST_DB \
        -e PGSQL_TEST_USER \
        -e PGSQL_TEST_PASSWORD \
        -e PGSQL_RUNTIME_TEST_DB \
        -e PGSQL_RUNTIME_TEST_USER \
        -e PGSQL_RUNTIME_TEST_PASSWORD \
        -e TARGET_DATABASE \
        -e TARGET_RUNTIME_USERNAME \
        -e TARGET_RUNTIME_PASSWORD \
        postgres psql -v ON_ERROR_STOP=1 -U "$DB_MIGRATION_USERNAME" -d "$database"
}

wait_postgres() {
    PGPASSWORD="$DB_MIGRATION_PASSWORD" docker compose exec -T \
        -e PGPASSWORD \
        -e DB_MIGRATION_USERNAME \
        postgres sh -c 'until pg_isready -U "$DB_MIGRATION_USERNAME" -d postgres >/dev/null 2>&1; do sleep 1; done'
}

env_value() {
    name="$1"

    if ! printenv "$name"; then
        printf '%s\n' "Missing required PostgreSQL role variable: $name" >&2
        exit 2
    fi
}

create_restricted_role() {
    role_env="$1"
    password_env="$2"

    TARGET_RUNTIME_USERNAME=$(env_value "$role_env")
    TARGET_RUNTIME_PASSWORD=$(env_value "$password_env")
    export TARGET_RUNTIME_USERNAME TARGET_RUNTIME_PASSWORD

    run_psql postgres <<'SQL'
\getenv target_runtime_username TARGET_RUNTIME_USERNAME
\getenv target_runtime_password TARGET_RUNTIME_PASSWORD
select format('create role %I login password %L', :'target_runtime_username', :'target_runtime_password')
where not exists (
    select 1 from pg_roles where rolname = :'target_runtime_username'
)
\gexec
alter role :"target_runtime_username" with login nosuperuser nocreatedb nocreaterole nobypassrls password :'target_runtime_password';
SQL
}

create_database_if_missing() {
    database_env="$1"

    TARGET_DATABASE=$(env_value "$database_env")
    export TARGET_DATABASE

    run_psql postgres <<'SQL'
\getenv target_database TARGET_DATABASE
\getenv migration_role DB_MIGRATION_USERNAME
select format('create database %I owner %I', :'target_database', :'migration_role')
where not exists (
    select 1 from pg_database where datname = :'target_database'
)
\gexec
SQL
}

create_extension() {
    database_env="$1"

    TARGET_DATABASE=$(env_value "$database_env")
    export TARGET_DATABASE

    run_psql "$TARGET_DATABASE" <<'SQL'
create extension if not exists pg_trgm;
SQL
}

grant_restricted_runtime_privileges() {
    database_env="$1"
    role_env="$2"

    TARGET_DATABASE=$(env_value "$database_env")
    TARGET_RUNTIME_USERNAME=$(env_value "$role_env")
    export TARGET_DATABASE TARGET_RUNTIME_USERNAME

    run_psql "$TARGET_DATABASE" <<'SQL'
\getenv target_database TARGET_DATABASE
\getenv target_runtime_username TARGET_RUNTIME_USERNAME
\getenv migration_role DB_MIGRATION_USERNAME
grant connect on database :"target_database" to :"target_runtime_username";
grant usage on schema public to :"target_runtime_username";
grant select, insert, update, delete on all tables in schema public to :"target_runtime_username";
grant usage, select on all sequences in schema public to :"target_runtime_username";
alter default privileges for role :"migration_role" in schema public
    grant select, insert, update, delete on tables to :"target_runtime_username";
alter default privileges for role :"migration_role" in schema public
    grant usage, select on sequences to :"target_runtime_username";
SQL
}

prepare_schema_owner_test_db() {
    create_restricted_role PGSQL_TEST_USER PGSQL_TEST_PASSWORD
    create_database_if_missing PGSQL_TEST_DB
    create_extension PGSQL_TEST_DB

    export TARGET_DATABASE="$PGSQL_TEST_DB"
    export TARGET_RUNTIME_USERNAME="$PGSQL_TEST_USER"

    run_psql "$TARGET_DATABASE" <<'SQL'
\getenv target_database TARGET_DATABASE
\getenv target_runtime_username TARGET_RUNTIME_USERNAME
grant connect on database :"target_database" to :"target_runtime_username";
grant usage, create on schema public to :"target_runtime_username";
alter schema public owner to :"target_runtime_username";
SQL
}

case "${1:-}" in
    wait)
        wait_postgres
        ;;
    grant-runtime)
        create_restricted_role DB_RUNTIME_USERNAME DB_RUNTIME_PASSWORD
        grant_restricted_runtime_privileges DB_DATABASE DB_RUNTIME_USERNAME
        ;;
    prepare-tenant-test-db)
        prepare_schema_owner_test_db
        ;;
    prepare-runtime-test-db)
        create_restricted_role PGSQL_RUNTIME_TEST_USER PGSQL_RUNTIME_TEST_PASSWORD
        create_database_if_missing PGSQL_RUNTIME_TEST_DB
        create_extension PGSQL_RUNTIME_TEST_DB
        ;;
    grant-runtime-test-db)
        grant_restricted_runtime_privileges PGSQL_RUNTIME_TEST_DB PGSQL_RUNTIME_TEST_USER
        ;;
    *)
        printf '%s\n' 'Usage: runtime-role-privileges.sh wait|grant-runtime|prepare-tenant-test-db|prepare-runtime-test-db|grant-runtime-test-db' >&2
        exit 2
        ;;
esac
