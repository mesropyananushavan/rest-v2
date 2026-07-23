# SmartRest v2

SmartRest v2 is a Laravel 13 modular monolith for the walking skeleton defined in `docs/BLUEPRINT.md`.

## Local Development

Use Docker through the Makefile; host PHP is not required.

- `make up` starts the app stack.
- `make down` stops the stack.
- `make restart` rebuilds and restarts the stack.
- `make shell` opens a PHP container shell.
- `make test` runs Pest.
- `make tenant-isolation-pgsql` runs the Tenancy feature suite on PostgreSQL
  against a separate local test database with an unprivileged RLS-enforced role.
- `make stan` runs PHPStan/Larastan.
- `make pint` formats with Pint.
- `make fresh` creates the public storage link and runs `migrate:fresh --seed`.
- `make build` installs dependencies, creates the app key and public storage link, and builds Vite assets.
- `make tools` starts dev-profile tools, currently Adminer at `http://localhost:8081`.

## Horizon

TODO: the `horizon` Compose service currently runs `php artisan queue:work` as a placeholder. Replace it with `php artisan horizon` when a Laravel 13-compatible Horizon release is available.

## Demo Data

`make fresh` creates the public storage link and runs guarded deterministic demo seeders in non-production environments only.

Dev-only password for all demo users: `password`.

Tenant `Arat Riverside Restaurants`, locale `hy`, currency `AMD`:

- `arat-owner` / `owner@arat.test`
- `arat-manager` / `manager@arat.test`
- `arat-cashier` / `cashier@arat.test`
- `arat-waiter` / `waiter@arat.test`

Tenant `Northstar Bistro Group`, locale `en`, currency `USD`:

- `northstar-owner` / `owner@northstar.test`
- `northstar-manager` / `manager@northstar.test`
- `northstar-cashier` / `cashier@northstar.test`
- `northstar-waiter` / `waiter@northstar.test`

Menu demo data is added in Stage 3, when the menu schema is introduced.
