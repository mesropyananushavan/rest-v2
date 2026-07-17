# Worklog — Phase 1: Walking Skeleton

Status: Stage 2 checkpoint complete; pre-Stage-3 isolation hardening complete
Branch: phase-1-walking-skeleton

## Plan
- [x] Stage 1: Laravel 13 scaffold, module skeletons (Tenancy/Identity/Menu),
  arch test, Docker Compose, CI — DONE, checkpoint approved
- [x] Pre-Stage-2: switch MariaDB → PostgreSQL 17 (compose, config, CI,
  pdo_pgsql), update BLUEPRINT ADR-001/§8 (approved edit)
- [x] Pre-Stage-2: Makefile (up/down/shell/test/stan/pint/fresh/build/tools)
- [x] Pre-Stage-2: Adminer under compose profile "dev"
- [x] Pre-Stage-2: logging foundation per AGENTS.md (JSON channel,
  request_id middleware, shared log context, queue context propagation,
  redaction helper, make logs/logs-queue)
- [x] Pre-Stage-2: AGENTS.md, DECISIONS.md, README rewrite (provided by owner)
- [x] Stage 2: tenancy + identity (migrations, middleware, scopes, auth,
  permissions, seeders, isolation tests) — STOP checkpoint after
- [x] Pre-Stage-3: extend tenant isolation tests for write/create/HTTP 404
  invariants and add standing AGENTS rule for every new resourceful route
- [ ] Stage 3: menu vertical slice (actions, Blade UI, API, i18n, audit,
  tests, demo seeders)

## Done log
- 2026-07-17: Stage 1 complete. All gates green (composer validate, Pint,
  PHPStan, Pest 6 passed, Vite build).
- 2026-07-17: PostgreSQL 17 switch, Makefile, Adminer dev profile,
  Horizon placeholder TODOs, and approved BLUEPRINT edit completed.
- 2026-07-17: Stage 2 complete. Tenant isolation checkpoint output:
  TenantIsolationTest 3 passed / 14 assertions; full Pest 9 passed.
- 2026-07-17: DemoSeeder foundation added. Deterministic two-tenant
  tenancy/identity demo data seeded; menu demo seeder deferred to Stage 3.
- 2026-07-17: Logging foundation added before Stage 3. JSON channel,
  request ID response header, shared context, queue payload propagation,
  redactor, and Makefile log targets implemented. Gates green: Pint pass,
  PHPStan pass, Pest 12 passed / 74 assertions.
- 2026-07-17: Tenant isolation coverage hardened before Stage 3. Tests now
  cover read isolation with order-independent assertions, write/delete zero-row
  effects, create tenant_id override prevention, and HTTP 404 for foreign
  resource ids. Gates green: Pint pass, PHPStan pass, focused
  TenantIsolationTest 6 passed / 27 assertions, full Pest 15 passed /
  87 assertions.

## Gotchas / known issues
- Host PHP is 8.1 — never run PHP on host, docker/make only.
- Horizon has no Laravel 13-compatible release yet: `horizon` compose
  service runs `queue:work` as placeholder. Revisit when Horizon updates.
- Pest Laravel plugin incompatible with Laravel 13 (stable targets 11/12);
  using Pest core 4.7.2 + pinned PHPUnit 12.5.28.
- Larastan stable is 3.x (no 4.x); one legacy config key removed from
  phpstan.neon for PHPStan 2.
- Logging context is shared centrally. Application actions in Stage 3+
  must log stable English messages and use Redactor for input summaries;
  do not manually pass request_id/tenant_id/branch_id/user_id/module.
- Composer platform is pinned to PHP 8.3.32. Without this, Composer run
  under newer images can select Symfony 8.1 packages requiring PHP 8.4.1,
  breaking the PHP 8.3 app image.
- Make quality targets use `docker compose run --no-deps` to avoid binding
  PostgreSQL/Redis ports during Pint/PHPStan/Pest; `make fresh` still starts
  the app service dependencies.
- Tenant isolation tests must cover reads, writes, creates, deletes, and
  HTTP resource access. Every new resourceful route in Stage 3+ needs a
  tenant isolation test proving foreign tenant IDs return 404.

## Next steps
Push `phase-1-walking-skeleton` for PR review; Stage 3 starts only after
merge approval.
