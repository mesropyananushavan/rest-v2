# Worklog — Phase 1: Walking Skeleton

Status: Stage 2.5 hardening in progress
Branch: phase-1-stage-2.5-hardening

PR state: owner creates and merges PRs; Codex does not create PRs.

Previous CI status: green after end-of-day CI fix. Run:
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
- [x] Stage 2.5.1: tenant header policy hardening. Implement tenant resolve
  order `authenticated user -> session -> X-Tenant-ID`, accept header only
  outside production, ensure header never overrides authenticated user tenant,
  add tests, record DECISIONS.md entry, run `make pint && make stan &&
  make test`, commit.
- [x] Stage 2.5.2: queue context propagation hardening. Restore
  TenantResolver and BranchContext inside queued jobs from payload, add a test
  proving tenant-scoped queries inside a job see only that job tenant, run
  `make pint && make stan && make test`, commit.
- [x] Stage 2.5.3: PostgreSQL tenant isolation CI job. Add separate GitHub
  Actions job using PostgreSQL 17 service that runs tenant isolation tests on
  real pgsql, including no-`smartrest.tenant_id` RLS visibility coverage, run
  `make pint && make stan && make test`, commit.
- [x] Stage 2.5.4: strict types sweep. Add `declare(strict_types=1)` to all
  PHP files missing it, run `make pint && make stan && make test`, commit.
- [x] Stage 2.5.5: scaffold cleanup. Remove Laravel ExampleTests, fix
  `UserFactory` hardcoded `tenant_id => 1` via factory/state, replace welcome
  page with minimal translated placeholder, run `make pint && make stan &&
  make test`, commit.
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
- 2026-07-20: Stage 2.5.1 tenant header policy hardening complete. Tenant
  resolution is authenticated user, session, then dev/test-only
  `X-Tenant-ID`; header cannot override authenticated user tenant. Decision
  recorded in DECISIONS.md. Gates green: Pint pass, PHPStan pass, Pest
  17 passed / 91 assertions.
- 2026-07-20: Stage 2.5.2 queue context propagation hardening complete.
  Queue listeners restore TenantResolver and BranchContext from
  `smartrest_context`, then clear runtime context after job processing. Added
  database-queue regression test proving tenant-scoped queries inside a job
  only see that job tenant. Gates green: Pint pass, PHPStan pass, Pest
  18 passed / 98 assertions.
- 2026-07-20: Stage 2.5.3 PostgreSQL tenant isolation CI job complete. Added
  separate `tenant-isolation-pgsql` GitHub Actions job with PostgreSQL 17
  service and focused tenancy suite; added pgsql-only raw SQL RLS regression
  proving no `smartrest.tenant_id` sees no branch rows and each tenant setting
  sees only its own branch. Local gates green: Pint pass, PHPStan pass, Pest
  18 passed / 1 skipped / 96 assertions. CI confirmation pending until branch
  push.
- 2026-07-20: Stage 2.5.4 strict types sweep complete. All tracked PHP files
  now include `declare(strict_types=1)`; ignored generated cache/view files
  were left untouched. Gates green: Pint pass, PHPStan pass, Pest 18 passed /
  1 skipped / 96 assertions.
- 2026-07-20: Stage 2.5.5 scaffold cleanup complete. Removed Laravel
  ExampleTests, replaced `UserFactory` hardcoded `tenant_id => 1` with
  TenantFactory/default state plus `forTenant()` state, replaced Laravel
  welcome page with minimal translated placeholder and `hy`/`ru`/`en`
  translations, and added a focused welcome placeholder test. Gates green:
  Pint pass, PHPStan pass, Pest 17 passed / 1 skipped / 103 assertions.

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
- PostgreSQL service users created by the GitHub Actions postgres image can
  bypass RLS. The dedicated pgsql tenant isolation job creates and uses a
  separate non-superuser `smartrest_app` role so FORCE RLS is actually tested.
- The minimal welcome placeholder must not call `@vite` directly before the
  CI build step creates `public/build/manifest.json`; otherwise the Pest step
  fails before Vite Build runs.

## Next steps
Push `phase-1-stage-2.5-hardening`, wait for GitHub Actions, record CI result
in this worklog, then hand off to owner for PR creation/merge. Do NOT create a
PR and do NOT start Stage 3.
