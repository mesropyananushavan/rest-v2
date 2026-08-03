# SmartRest v2

SmartRest v2 is a Laravel 13 modular monolith for the walking skeleton defined in `docs/BLUEPRINT.md`.

## Local Development

Use Docker through the Makefile; host PHP is not required.

- `make up` provisions the local runtime PostgreSQL role, refreshes existing-table grants, and starts the app stack.
- `make down` stops the stack.
- `make restart` rebuilds and restarts the stack.
- `make shell` opens a PHP container shell.
- `make artisan ARGS="..."` runs an ordinary Artisan command inside the PHP container with runtime database credentials.
- `make pgsql ARGS="..."` runs privileged `psql` against the local PostgreSQL service.
- `make runtime-role-pgsql` prepares a fresh PostgreSQL test schema with privileged credentials, grants runtime access, and verifies runtime RLS role behavior.
- `make test` runs Pest.
- `make tenant-isolation-pgsql` runs the Tenancy feature suite on PostgreSQL
  against a separate local test database with an unprivileged RLS-enforced role.
- `make stan` runs PHPStan/Larastan.
- `make pint` formats with Pint.
- `make fresh` creates the public storage link, runs `migrate:fresh --seed` with privileged local credentials, then grants runtime database access.
- `make build` installs dependencies, creates the app key and public storage link, and builds Vite assets.
- `make smoke-menu-context` runs the Menu context-preservation HTTP smoke inside Docker after demo Menu load data has been generated.
- `make tools` starts dev-profile tools, currently Adminer at `http://localhost:8081`.

Local PostgreSQL credentials are split by responsibility. `DB_DATABASE` is the
canonical local database name for both Make and Docker Compose. `DB_NAME` is
only a compatibility alias when `DB_DATABASE` is omitted; if both are supplied
with different values, Make-managed setup stops with an error. Make and Compose
credential precedence is explicit `make VARIABLE=value`, then exported
environment values, then the project `.env`, then repository defaults. The
Make-managed `.env` reader intentionally supports only a safe subset for local
database variables: optional leading/trailing whitespace, optional `export`,
unquoted values without whitespace, quotes, backticks, backslashes, or `#`, and
single-quoted literal values without embedded single quotes. Inline comments
are supported only as full-line comments, not after a database variable. Use
exported environment variables or `make VARIABLE=value` for any value outside
that subset.
`DB_MIGRATION_USERNAME` and `DB_MIGRATION_PASSWORD` are used by Make targets
that run migrations, seeders, role bootstrap, and runtime grants.
`DB_RUNTIME_USERNAME` and `DB_RUNTIME_PASSWORD` are mapped by Docker Compose
into Laravel's `DB_USERNAME` and `DB_PASSWORD` for `php-fpm`, the queue worker,
and the scheduler.

Role-sensitive Make targets guard themselves before their first database,
Docker, grant, migration, seeding, or runtime-start command while
`bootstrap/cache/config.php` exists, because cached Laravel configuration can
preserve credentials from the wrong role. Run `make config-clear` before
`make up`, `make fresh`, PostgreSQL role verification, or ordinary runtime
Artisan commands if a local config cache exists. Build production config cache
only with runtime credentials, then restart php-fpm, queue workers, and the
scheduler after any credential or config-cache change. Runtime role bootstrap
passes passwords through process environment into stdin-fed `psql` scripts and
relies on PostgreSQL/psql quoting; passwords are not embedded in Make recipe
command strings.

## Scheduler

The Docker stack includes a dedicated `scheduler` process running
`php artisan schedule:work`. Laravel scheduling includes
`tenancy:subscriptions:auto-suspend`, which runs hourly in the platform billing
timezone and only suspends eligible active tenants at or after the configured
quiet hour. Inspect local scheduling with `make artisan ARGS="schedule:list"`;
run one local scheduler tick with `make artisan ARGS="schedule:run"`.

## Horizon

TODO: the `horizon` Compose service currently runs `php artisan queue:work` as a placeholder. Replace it with `php artisan horizon` when a Laravel 13-compatible Horizon release is available.

## Demo Data

`make fresh` creates the public storage link and runs guarded deterministic demo seeders in non-production environments only. Migrations and seeders use the privileged local PostgreSQL owner role; normal HTTP, Livewire, queue, scheduler, and ordinary Artisan commands use the restricted `smartrest_app` runtime role.

Dev-only password for all demo users: `password`.

Tenant `Arat Riverside Restaurants`, locale `hy`, currency `AMD`:

- Login tenant slug: `arat-riverside`
- `arat-owner` / `owner@arat.test`
- `arat-manager` / `manager@arat.test`
- `arat-cashier` / `cashier@arat.test`
- `arat-waiter` / `waiter@arat.test`

Tenant `Northstar Bistro Group`, locale `en`, currency `USD`:

- Login tenant slug: `northstar-bistro`
- `northstar-owner` / `owner@northstar.test`
- `northstar-manager` / `manager@northstar.test`
- `northstar-cashier` / `cashier@northstar.test`
- `northstar-waiter` / `waiter@northstar.test`

Menu demo data is seeded for both demo tenants by `make fresh`.

For repeatable Menu scale testing against the demo tenants after `make fresh`,
run `make artisan ARGS="menu:load-test-data --purge-generated"`. The command is
guarded to local/testing environments, targets only the demo tenants, and its
purge option removes only rows generated by that command.

For broad local PostgreSQL load experiments with synthetic tenants, use
`make artisan ARGS="menu:seed-load --mode=production-like --restaurants=200 --categories=1 --subcategories=1 --items=1"`.
`menu:seed-load` creates synthetic load tenants (`seed_source = load`) and is
separate from demo-tenant load data. Its production-like `--fresh` path can
recreate only the guarded local SmartRest database, asks for confirmation, and
`--force` only suppresses that confirmation; it does not allow production or
non-local database runs.
