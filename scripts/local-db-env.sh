#!/bin/sh
set -eu

dotenv_value() {
    key="$1"

    [ -f .env ] || return 1

    awk -v key="$key" '
        function trim(value) {
            sub(/^[[:space:]]+/, "", value)
            sub(/[[:space:]]+$/, "", value)
            return value
        }
        BEGIN { found = 0 }
        /^[[:space:]]*#/ || /^[[:space:]]*$/ { next }
        {
            line = $0
            sub(/\r$/, "", line)
            sub(/^[[:space:]]*export[[:space:]]+/, "", line)
            separator = index(line, "=")
            if (separator == 0) {
                next
            }

            name = trim(substr(line, 1, separator - 1))
            if (name == key) {
                print substr(line, separator + 1)
                found = 1
                exit
            }
        }
        END {
            if (! found) {
                exit 1
            }
        }
    ' .env
}

trim_dotenv_value() {
    printf '%s' "$1" | awk '
        {
            sub(/^[[:space:]]+/, "")
            sub(/[[:space:]]+$/, "")
            print
        }
    '
}

fail_dotenv_value() {
    name="$1"
    reason="$2"

    printf '%s\n' "Unsupported .env syntax for $name: $reason. Make-managed database variables support unquoted values without whitespace, quotes, backticks, backslashes, or #; or single-quoted literal values without embedded single quotes. Inline comments are not supported on database variable lines." >&2
    exit 2
}

parse_dotenv_value() {
    name="$1"
    value=$(trim_dotenv_value "$2")

    case "$value" in
        '')
            fail_dotenv_value "$name" 'empty assignment'
            ;;
        \'*\')
            case "$value" in
                *\')
                    value=${value#\'}
                    value=${value%\'}
                    ;;
                *)
                    fail_dotenv_value "$name" 'unterminated single-quoted value'
                    ;;
            esac

            case "$value" in
                *\'*)
                    fail_dotenv_value "$name" 'embedded single quotes are not supported'
                    ;;
            esac
            ;;
        \"*)
            fail_dotenv_value "$name" 'double-quoted values are not supported'
            ;;
        *\"* | *\'* | *\`*)
            fail_dotenv_value "$name" 'unquoted quote or backtick'
            ;;
        *\\*)
            fail_dotenv_value "$name" 'unquoted backslash'
            ;;
        *'#'*)
            fail_dotenv_value "$name" 'inline comments and unquoted # characters are not supported'
            ;;
        *)
            if printf '%s' "$value" | grep '[[:space:]]' >/dev/null 2>&1; then
                fail_dotenv_value "$name" 'unquoted whitespace'
            fi
            ;;
    esac

    printf '%s' "$value"
}

is_set() {
    printenv "$1" >/dev/null 2>&1
}

load_database_name() {
    database_set=0
    db_name_set=0
    database_value=
    db_name_value=

    if is_set DB_DATABASE; then
        database_value=$(printenv DB_DATABASE)
        database_set=1
    elif raw_value=$(dotenv_value DB_DATABASE); then
        database_value=$(parse_dotenv_value DB_DATABASE "$raw_value")
        database_set=1
    fi

    if is_set DB_NAME; then
        db_name_value=$(printenv DB_NAME)
        db_name_set=1
    elif raw_value=$(dotenv_value DB_NAME); then
        db_name_value=$(parse_dotenv_value DB_NAME "$raw_value")
        db_name_set=1
    fi

    if [ "$database_set" -eq 1 ] && [ "$db_name_set" -eq 1 ] && [ "$database_value" != "$db_name_value" ]; then
        printf '%s\n' "DB_NAME conflicts with DB_DATABASE; use DB_DATABASE as the canonical local database variable." >&2
        exit 2
    fi

    if [ "$database_set" -eq 1 ]; then
        export DB_DATABASE="$database_value"
        return
    fi

    if [ "$db_name_set" -eq 1 ]; then
        export DB_DATABASE="$db_name_value"
        return
    fi

    export DB_DATABASE=smartrest
}

load_var() {
    name="$1"
    default="$2"

    if ! is_set "$name"; then
        if value=$(dotenv_value "$name"); then
            value=$(parse_dotenv_value "$name" "$value")
            export "$name=$value"
        fi
    fi

    if ! is_set "$name"; then
        export "$name=$default"
    fi
}

require_non_empty() {
    name="$1"
    value=$(printenv "$name")

    if [ -z "$value" ]; then
        printf '%s\n' "Missing required local database variable: $name" >&2
        exit 2
    fi
}

load_database_name
load_var DB_MIGRATION_USERNAME smartrest
load_var DB_MIGRATION_PASSWORD smartrest
load_var DB_RUNTIME_USERNAME smartrest_app
load_var DB_RUNTIME_PASSWORD smartrest_app
load_var PGSQL_TEST_DB smartrest_test_local
load_var PGSQL_TEST_USER smartrest_app_test
load_var PGSQL_TEST_PASSWORD smartrest_app_test
load_var PGSQL_RUNTIME_TEST_DB smartrest_runtime_test_local
load_var PGSQL_RUNTIME_TEST_USER smartrest_app_runtime_test
load_var PGSQL_RUNTIME_TEST_PASSWORD smartrest_app_runtime_test

for name in \
    DB_DATABASE \
    DB_MIGRATION_USERNAME \
    DB_MIGRATION_PASSWORD \
    DB_RUNTIME_USERNAME \
    DB_RUNTIME_PASSWORD \
    PGSQL_TEST_DB \
    PGSQL_TEST_USER \
    PGSQL_TEST_PASSWORD \
    PGSQL_RUNTIME_TEST_DB \
    PGSQL_RUNTIME_TEST_USER \
    PGSQL_RUNTIME_TEST_PASSWORD
do
    require_non_empty "$name"
done

exec "$@"
