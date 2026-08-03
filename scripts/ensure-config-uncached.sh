#!/bin/sh
set -eu

cache_file="${CONFIG_CACHE_PATH:-bootstrap/cache/config.php}"

if [ -f "$cache_file" ]; then
    printf '%s\n' "Refusing role-sensitive database command because $cache_file exists. Run \`make config-clear\`, then rerun the command. Build production config cache only with runtime credentials and restart workers/scheduler after cache or credential changes." >&2
    exit 1
fi
