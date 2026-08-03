#!/bin/sh
set -eu

repo_root=$(pwd)
work_dir="tests/Scripts/.runtime-db-env-contract"
alias_dir="$work_dir/no-env"
guard_dir="$work_dir/cache-guard"
guard_file="$guard_dir/config.php"

cleanup() {
    rm -rf "$work_dir"
}

rm -rf "$work_dir"
mkdir -p "$alias_dir"
trap cleanup EXIT INT TERM HUP

cat > "$work_dir/.env" <<'EOF'
  export DB_DATABASE = 'dotenv db'
DB_MIGRATION_USERNAME=dotenv_owner
DB_MIGRATION_PASSWORD='space dollar$ backtick` backslash\ equals= hash# double"'
DB_RUNTIME_USERNAME=dotenv_app
DB_RUNTIME_PASSWORD='runtime dollar$ backtick` backslash\ equals= hash# double"'
EOF

expected_migration_password='space dollar$ backtick` backslash\ equals= hash# double"'
expected_runtime_password='runtime dollar$ backtick` backslash\ equals= hash# double"'

(
    cd "$work_dir"
    env -i \
        PATH="$PATH" \
        EXPECTED_MIGRATION_PASSWORD="$expected_migration_password" \
        EXPECTED_RUNTIME_PASSWORD="$expected_runtime_password" \
        sh "$repo_root/scripts/local-db-env.sh" sh -c '
        test "$DB_DATABASE" = "dotenv db"
        test "$DB_MIGRATION_USERNAME" = "dotenv_owner"
        test "$DB_RUNTIME_USERNAME" = "dotenv_app"
        test "$DB_MIGRATION_PASSWORD" = "$EXPECTED_MIGRATION_PASSWORD"
        test "$DB_RUNTIME_PASSWORD" = "$EXPECTED_RUNTIME_PASSWORD"
    '
)

(
    cd "$work_dir"
    env -i \
        PATH="$PATH" \
        DB_DATABASE=exported_db \
        DB_MIGRATION_USERNAME=exported_owner \
        DB_MIGRATION_PASSWORD=exported_owner_password \
        sh "$repo_root/scripts/local-db-env.sh" sh -c '
            test "$DB_DATABASE" = "exported_db"
            test "$DB_MIGRATION_USERNAME" = "exported_owner"
            test "$DB_MIGRATION_PASSWORD" = "exported_owner_password"
            test "$DB_RUNTIME_USERNAME" = "dotenv_app"
        '
)

(
    cd "$work_dir"
    env \
        DB_DATABASE=exported_db \
        DB_MIGRATION_USERNAME=exported_owner \
        make --no-print-directory \
            -f "$repo_root/Makefile" \
            LOCAL_DB_ENV="sh $repo_root/scripts/local-db-env.sh" \
            DB_DATABASE=cli_db \
            DB_MIGRATION_USERNAME=cli_owner \
            --eval 'show-db-contract: ; @$(LOCAL_DB_ENV) sh -c '\''test "$$DB_DATABASE" = "cli_db"; test "$$DB_MIGRATION_USERNAME" = "cli_owner"'\''' \
            show-db-contract
)

assert_rejected_env() {
    case_name="$1"
    line="$2"
    tmp_dir="$work_dir/reject-$case_name"

    mkdir -p "$tmp_dir"
    cat > "$tmp_dir/.env" <<EOF
DB_DATABASE=$line
EOF

    if (
        cd "$tmp_dir"
        env -i PATH="$PATH" sh "$repo_root/scripts/local-db-env.sh" true
    ) >/dev/null 2>&1; then
        printf '%s\n' "Expected unsupported .env syntax to fail: $case_name" >&2
        exit 1
    fi
}

assert_rejected_env double-quoted '"quoted_db"'
assert_rejected_env unquoted-space 'bad db'
assert_rejected_env unquoted-comment 'bad#db'
assert_rejected_env unquoted-backslash 'bad\db'
assert_rejected_env empty ''

command_marker="$work_dir/dotenv-executed"
no_exec_dir="$work_dir/no-exec"
mkdir -p "$no_exec_dir"
cat > "$no_exec_dir/.env" <<EOF
DB_DATABASE=no_exec_db
DB_MIGRATION_PASSWORD='\$(touch "$command_marker")'
DB_RUNTIME_PASSWORD='\`touch "$command_marker"\`'
EOF

(
    cd "$no_exec_dir"
    env -i PATH="$PATH" sh "$repo_root/scripts/local-db-env.sh" true
)

if [ -e "$command_marker" ]; then
    printf '%s\n' '.env content was executed.' >&2
    exit 1
fi

(
    cd "$alias_dir"
    env -i PATH="$PATH" DB_NAME=legacy_db sh "$repo_root/scripts/local-db-env.sh" sh -c '
        test "$DB_DATABASE" = "legacy_db"
    '
)

alias_env_dir="$work_dir/alias-env"
mkdir -p "$alias_env_dir"
cat > "$alias_env_dir/.env" <<'EOF'
DB_NAME=legacy_dotenv_db
EOF

(
    cd "$alias_env_dir"
    env -i PATH="$PATH" sh "$repo_root/scripts/local-db-env.sh" sh -c '
        test "$DB_DATABASE" = "legacy_dotenv_db"
    '
)

if (
    cd "$alias_dir"
    env -i PATH="$PATH" DB_NAME=legacy_db DB_DATABASE=canonical_db sh "$repo_root/scripts/local-db-env.sh" true
) >/dev/null 2>&1; then
    printf '%s\n' 'Expected DB_NAME/DB_DATABASE conflict to fail.' >&2
    exit 1
fi

conflict_env_dir="$work_dir/conflict-env"
mkdir -p "$conflict_env_dir"
cat > "$conflict_env_dir/.env" <<'EOF'
DB_DATABASE=canonical_dotenv_db
DB_NAME=legacy_dotenv_db
EOF

if (
    cd "$conflict_env_dir"
    env -i PATH="$PATH" sh "$repo_root/scripts/local-db-env.sh" true
) >/dev/null 2>&1; then
    printf '%s\n' 'Expected .env DB_NAME/DB_DATABASE conflict to fail.' >&2
    exit 1
fi

if (
    cd "$alias_env_dir"
    env -i PATH="$PATH" DB_DATABASE=exported_db sh "$repo_root/scripts/local-db-env.sh" true
) >/dev/null 2>&1; then
    printf '%s\n' 'Expected exported DB_DATABASE and .env DB_NAME conflict to fail.' >&2
    exit 1
fi

mkdir -p "$guard_dir"

CONFIG_CACHE_PATH="$guard_file" sh "$repo_root/scripts/ensure-config-uncached.sh" true

printf '%s\n' '<?php return [];' > "$guard_file"

if CONFIG_CACHE_PATH="$guard_file" sh "$repo_root/scripts/ensure-config-uncached.sh" true >/dev/null 2>&1; then
    printf '%s\n' 'Expected role-sensitive config-cache guard to fail.' >&2
    exit 1
fi

if ! CONFIG_CACHE_PATH="$guard_file" sh "$repo_root/scripts/ensure-config-uncached.sh" true 2>&1 | grep 'make config-clear' >/dev/null 2>&1; then
    printf '%s\n' 'Expected config-cache guard recovery instruction.' >&2
    exit 1
fi

first_dry_run_command() {
    target="$1"

    make --no-print-directory -n \
        CONFIG_CACHE_PATH="$guard_file" \
        DB_DATABASE=safe_db \
        DB_MIGRATION_USERNAME=safe_owner \
        DB_MIGRATION_PASSWORD=safe_owner_password \
        DB_RUNTIME_USERNAME=safe_app \
        DB_RUNTIME_PASSWORD=safe_app_password \
        PGSQL_TEST_DB=safe_test_db \
        PGSQL_TEST_USER=safe_test_owner \
        PGSQL_TEST_PASSWORD=safe_test_password \
        PGSQL_RUNTIME_TEST_DB=safe_runtime_test_db \
        PGSQL_RUNTIME_TEST_USER=safe_runtime_app \
        PGSQL_RUNTIME_TEST_PASSWORD=safe_runtime_password \
        "$target" 2>/dev/null | sed '/^[[:space:]]*$/d' | sed -n '1p'
}

assert_guard_first() {
    target="$1"
    first=$(first_dry_run_command "$target")

    case "$first" in
        *'scripts/ensure-config-uncached.sh'*)
            ;;
        *)
            printf '%s\n' "Expected first dry-run command for $target to be the config-cache guard." >&2
            exit 1
            ;;
    esac
}

for target in \
    up \
    restart \
    shell \
    artisan \
    pgsql-runtime \
    test \
    tenant-isolation-pgsql \
    orders-concurrency-pgsql \
    runtime-role-pgsql \
    prepare-pgsql-test-db \
    wait-postgres \
    provision-runtime-db-role \
    grant-runtime-db-privileges \
    prepare-runtime-pgsql-test-db \
    fresh \
    smoke-menu-context
do
    assert_guard_first "$target"
done

parallel_first=$(make --no-print-directory -n -j4 \
    CONFIG_CACHE_PATH="$guard_file" \
    DB_DATABASE=safe_db \
    DB_MIGRATION_USERNAME=safe_owner \
    DB_MIGRATION_PASSWORD=safe_owner_password \
    DB_RUNTIME_USERNAME=safe_app \
    DB_RUNTIME_PASSWORD=safe_app_password \
    up 2>/dev/null | sed '/^[[:space:]]*$/d' | sed -n '1p')

case "$parallel_first" in
    *'scripts/ensure-config-uncached.sh'*)
        ;;
    *)
        printf '%s\n' 'Expected parallel make up dry-run to start with the config-cache guard.' >&2
        exit 1
        ;;
esac

if make --no-print-directory -n CONFIG_CACHE_PATH="$guard_file" config-clear 2>/dev/null | grep 'scripts/ensure-config-uncached.sh' >/dev/null 2>&1; then
    printf '%s\n' 'config-clear must not be blocked by the config-cache guard.' >&2
    exit 1
fi

rm -f "$guard_file"

if ! CONFIG_CACHE_PATH="$guard_file" sh "$repo_root/scripts/ensure-config-uncached.sh" true >/dev/null 2>&1; then
    printf '%s\n' 'Expected absent config-cache guard fixture to pass.' >&2
    exit 1
fi
