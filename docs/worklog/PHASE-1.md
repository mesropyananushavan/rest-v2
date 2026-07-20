# Worklog — Phase 1: Walking Skeleton

Status: Branch pushed; PR not created; awaiting owner PR review and merge
Branch: phase-1-walking-skeleton

PR state: `phase-1-walking-skeleton` is pushed to origin. PR is NOT yet
created; only the GitHub `/pull/new` link exists. The OWNER creates and
reviews the PR himself:
https://github.com/mesropyananushavan/rest-v2/pull/new/phase-1-walking-skeleton

CI status: green after end-of-day CI fix. Run:
https://github.com/mesropyananushavan/rest-v2/actions/runs/29590252242

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
- 2026-07-17: End-of-day CI fix complete. Branch
  `phase-1-walking-skeleton` is pushed; PR is not created. Current state:
  Stage 2 checkpoint approved, tenant isolation tests extended
  (TenantIsolationTest 6 tests / 27 assertions), logging foundation done.
  GitHub Actions CI is green at run 29590252242.

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
- npm lock desync cause/fix: `package-lock.json` was missing optional
  `@emnapi/*` entries required by Rolldown/Vite under Node 24 in CI,
  so `npm ci` failed before quality checks. Fix was to regenerate/sync
  the lockfile and include the required `@emnapi/core` and
  `@emnapi/runtime` entries; local `npm ci` and CI `npm ci` now pass.
- CI Pest requires an application key. `phpunit.xml` now sets a static
  testing-only `APP_KEY` so GitHub Actions can run Feature tests without
  a local `.env`.

## Next steps
Awaiting owner: PR review and merge to main. Do NOT start Stage 3, do NOT create new branches, do NOT touch main. After the owner confirms the merge, create branch phase-1-stage-3 from fresh main and begin Stage 3 (menu vertical slice) per the Phase 1 task prompt and BLUEPRINT section 9.
