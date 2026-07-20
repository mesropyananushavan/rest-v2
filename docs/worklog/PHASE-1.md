# Worklog — Phase 1: Walking Skeleton

Status: Stage 3.2 Menu CRUD complete; awaiting owner PR
Branch: phase-1-stage-3.2-menu

PR state: owner creates and merges PRs; Codex does not create PRs.

CI status: green on pushed Stage 3.1 branch. Run:
https://github.com/mesropyananushavan/rest-v2/actions/runs/29725976561
Docker permission fix CI: green. Run:
https://github.com/mesropyananushavan/rest-v2/actions/runs/29727485150

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
- [x] Stage 3.1.1: minimal Identity login/logout routes and controller.
  Add hand-written Laravel session auth endpoints in Identity Http, no
  Breeze/Jetstream/Fortify, no registration/password reset/2FA/remember me.
  Add translated validation/flash text and focused feature tests, run
  `make pint && make stan && make test`, commit. Result: implemented as part
  of login slice; gates green with Stage 3.1.1-3.1.4 combined.
- [x] Stage 3.1.2: minimal login Blade UI. Build the `/login` page with
  email and password only, using existing Bootstrap and `tokens.css`, with
  all user-facing text in `lang/en`, `lang/hy`, and `lang/ru`. Add smoke
  coverage for seeded demo users where practical, run `make pint &&
  make stan && make test`, commit. Result: translated Blade form added with
  demo seeder login smoke test; gates green with Stage 3.1.1-3.1.4 combined.
- [x] Stage 3.1.3: auth middleware and tenant isolation through real login.
  Prove guest redirects to `/login`, authenticated users are redirected away
  from `/login`, and a user from tenant A receives 404 for tenant B branch via
  `POST /login -> session -> GET /admin/branches/{id}` rather than
  `actingAs`. Run `make pint && make stan && make test`, commit. Result:
  tenant and branch context resolve from authenticated user middleware and
  foreign tenant branch returns 404 through real session login; gates green
  with Stage 3.1.1-3.1.4 combined.
- [x] Stage 3.1.4: login rate limiting. Apply standard Laravel throttle to
  the login endpoint and add a regression test. Run `make pint && make stan
  && make test`, commit. Result: `/login` POST uses `throttle:5,1`; regression
  test covers 429 after five failed attempts; gates green with Stage 3.1.1-
  3.1.4 combined.
- [x] Stage 3.1.5: final verification and handoff. Run full local gates,
  push `phase-1-stage-3-login`, wait for both CI jobs green, update this
  worklog with CI links/results, no PR creation. Result: local `make fresh`,
  Pint, PHPStan, and Pest green; branch pushed at code head `bf13432`; CI run
  29725976561 passed both `quality` and `tenant-isolation-pgsql`.
- [x] Stage 3.1.6: Docker storage/bootstrap cache permissions. Add a
  container startup permission repair for `storage` and `bootstrap/cache` so
  www-data can write compiled views and logs after rebuild/fresh checkout.
  Verify with `make down && make build && make up && make fresh`, then curl
  `/` and `/login` for HTTP 200, run `make pint && make stan && make test`,
  commit, push, and wait for CI. Result: entrypoint repair added; compose web
  runtime now forces PostgreSQL/Redis service env; `make test` forces isolated
  testing/sqlite env. Local rebuild/fresh/curl/gates green; pushed at
  `8b607d9`; CI run 29727485150 passed both `quality` and
  `tenant-isolation-pgsql`.
- [x] Stage 3.2.1: menu schema, RLS, models, and contracts. Add
  `menu_categories` and `menu_items` with `tenant_id` indexes, branch
  ownership for items, PostgreSQL RLS policies matching existing tenant-owned
  tables, tenant-scoped Eloquent models using `BelongsToTenant`/`TenantScoped`,
  Money value-object usage for prices, and focused migration/model/RLS tests.
  Run `make pint && make stan && make test`, commit. Result: added
  tenant-owned Menu schema, pgsql RLS policies, tenant-scoped Menu models, a
  minimal Money value object for integer minor-unit prices, sqlite tenant-scope
  tests, and pgsql menu RLS coverage in the tenant isolation suite. Gates green:
  Pint pass, PHPStan pass, Pest 27 passed / 2 skipped / 172 assertions.
- [x] Stage 3.2.2: Menu Application actions and permissions. Add thin
  Application actions for list/create/update/delete categories and items,
  stable structured action logging, domain validation where needed, new menu
  permission codes via Identity contracts, Identity demo seeder permission
  assignment, and tests proving a user without permission receives 403. Run
  `make pint && make stan && make test`, commit. Result: added Menu CRUD
  Application actions, `LocalizedText` value object for JSON translations,
  branch-context enforcement for branch-owned item actions, structured action
  logging, `menu.categories.manage` demo permission alongside
  `menu.items.manage`, and action/permission tests including 403 denial.
  Gates green: Pint pass, PHPStan pass, Pest 31 passed / 2 skipped /
  191 assertions.
- [x] Stage 3.2.3: Blade Menu CRUD routes/controllers/views/i18n. Add
  authenticated `/admin/menu/...` Blade-only list/create/edit/delete flows for
  categories and items, controllers that only validate/authorize/call actions,
  Bootstrap/tokens.css-compatible views with no new design system, and
  `hy`/`ru`/`en` translation keys for every user-facing string. Include tenant
  isolation tests proving foreign tenant resource IDs return 404. Run
  `make pint && make stan && make test`, commit. Result: added authenticated
  Blade-only Menu CRUD routes, thin controllers/FormRequests, minimal
  Bootstrap/tokens admin layout and Menu views, translations for all three
  locales, HTTP CRUD feature tests, 403 coverage, and foreign-tenant 404
  coverage for category/item ids. Gates green: Pint pass, PHPStan pass, Pest
  34 passed / 2 skipped / 222 assertions.
- [x] Stage 3.2.4: deterministic menu demo seed data. Add `MenuDemoSeeder`
  for both tenants, connect it to `DemoSeeder`, ensure data is visible after
  `make fresh`, and update tests if seeder assumptions change. Run `make pint
  && make stan && make test`, commit. Result: added deterministic
  `MenuDemoSeeder` for both tenants and all demo branches, wired it into
  `DemoSeeder`, added real-login demo visibility coverage for both managers,
  and verified `make fresh` runs migrations plus demo seeds successfully.
  Gates green: `make fresh` pass, Pint pass, PHPStan pass, Pest 35 passed /
  2 skipped / 236 assertions.
- [x] Stage 3.2.5: final verification, push, and CI handoff. Run `make fresh`,
  curl-smoke the primary menu pages, run full `make pint && make stan &&
  make test`, push `phase-1-stage-3.2-menu`, wait for both GitHub Actions jobs
  green, update this worklog with final local/CI results, and do not create or
  merge a PR. Result: final `make fresh` pass; curl smoke pass after demo
  login (`POST /login` 302 to `/`, `GET /admin/menu` 200,
  `GET /admin/menu/categories/create` 200, `GET /admin/menu/items/create`
  200, seeded menu content present); Pint pass; PHPStan pass; Pest 35 passed /
  2 skipped / 237 assertions. Branch pushed at code head `db4e587`; CI run
  29735218745 passed both `quality` and `tenant-isolation-pgsql`.

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
- 2026-07-20: Stage 2.5 CI confirmed green after CI hardening fix. Branch
  `phase-1-stage-2.5-hardening` pushed to origin at head `f82ce2f`; GitHub
  Actions run 29724811580 passed both `quality` and
  `tenant-isolation-pgsql`. Earlier failed run 29724577844 exposed two CI-only
  issues: welcome placeholder depended on Vite manifest before build, and the
  pgsql service user bypassed RLS. Fix: remove direct `@vite` from the
  placeholder and test pgsql RLS through non-superuser `smartrest_app`.
- 2026-07-20: Stage 3.1 login slice implemented locally. Added minimal
  Identity session login/logout, translated `/login` Blade form, logout/context
  cleanup, tenant directory contract for scoped authentication, branch context
  fallback from authenticated user's first assignment via Identity contract,
  login throttle, real-session tenant isolation regression, guest/auth
  redirects, demo user login smoke test, and explicit tenant keys in Identity
  demo seeder rows. Demo verification green: `make fresh` pass. Gates green:
  Pint pass, PHPStan pass, Pest 25 passed / 1 skipped / 160 assertions.
- 2026-07-20: Stage 3.1 CI confirmed green. Branch
  `phase-1-stage-3-login` pushed at code head `bf13432`; GitHub Actions run
  29725976561 passed both `quality` and `tenant-isolation-pgsql`. PR is not
  created by Codex.
- 2026-07-20: Stage 3.1.6 Docker permission/runtime fix implemented locally.
  Added php entrypoint startup repair for `storage` and `bootstrap/cache`,
  forced compose web/worker runtime to PostgreSQL/Redis service env, and made
  `make test` explicitly run with testing/sqlite overrides so compose local
  runtime env does not leak into Pest. Verification green: `make down`,
  `make build`, `make up`, `make fresh`, curl `/` 200, curl `/login` 200,
  curl demo login `manager@arat.test` / `password` redirects to `/`, Pint pass,
  PHPStan pass, Pest 25 passed / 1 skipped / 160 assertions.
- 2026-07-20: Stage 3.1.6 CI confirmed green. Branch
  `phase-1-stage-3-login` pushed at head `8b607d9`; GitHub Actions run
  29727485150 passed both `quality` and `tenant-isolation-pgsql`.
- 2026-07-20: PR #3 merged to `main` at merge commit `0a0529b` after owner
  explicitly requested Codex PR creation/merge for this mini-fix. Local
  `main` fast-forwarded to `origin/main`.
- 2026-07-20: Stage 3.2 Menu CRUD complete locally and pushed. Implemented
  tenant-owned Menu schema/RLS/models, Menu Application CRUD actions with
  structured logs, Blade-only authenticated CRUD UI, menu permissions, demo
  seed data for both tenants, and tenant/RLS/403/404/CRUD tests. Final local
  verification green: `make fresh` pass, curl-smoke Menu pages pass, Pint pass,
  PHPStan pass, Pest 35 passed / 2 skipped / 237 assertions. Branch
  `phase-1-stage-3.2-menu` pushed at code head `db4e587`; GitHub Actions run
  29735218745 passed both `quality` and `tenant-isolation-pgsql`. PR is not
  created by Codex.

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
- `/login` conditionally loads Vite assets only when `public/build/manifest.json`
  exists, matching the welcome placeholder CI gotcha while still using
  Bootstrap/tokens after frontend build.
- Because users are tenant-scoped, login cannot query `users` before selecting
  a tenant. Stage 3.1 uses the Tenancy `TenantDirectory` contract to attempt
  credentials inside active tenant scopes. Duplicate emails across tenants are
  not disambiguated yet; tenant-domain routing or a tenant selector belongs to
  later login polish.
- Demo identity seeders must set `tenant_id` explicitly in `updateOrCreate`
  lookup attributes for tenant-owned rows. Relying only on the creating hook
  failed during `make fresh` for permissions.
- Bind-mounted Laravel writable paths need runtime repair, not only image
  build-time `chown`, because fresh checkouts and host-owned files are mounted
  over image paths.
- Compose web/worker runtime must set `DB_CONNECTION=pgsql` and service
  credentials explicitly. Otherwise a local `.env` with sqlite makes browser
  sessions write to `database/database.sqlite`, which can be readonly for
  `www-data`.
- `make test` must explicitly override compose runtime env back to
  testing/sqlite; otherwise local PostgreSQL service env leaks into no-deps
  Pest runs and breaks sqlite/RLS expectations.
- Real browser/curl login needs `tenant_id` stored in session immediately
  after successful authentication. Otherwise the next request cannot rehydrate
  the tenant-scoped `User` before `ResolveTenant` chooses a tenant; Laravel's
  feature-test guard can mask this unless guards are forgotten between
  requests.
- Protected web routes that use tenant-scoped auth must run `ResolveTenant`
  before Laravel auth middleware. Middleware priority now enforces
  `ResolveTenant -> ResolveBranch -> auth`; route-level protected groups also
  list `tenant`, `branch`, then `auth` explicitly for readability.
- After `docker compose up --build` recreates `php-fpm`, an already-running
  nginx container can briefly keep the old upstream IP and return 502. Restart
  nginx before curl-smoke if this appears; it is a runtime DNS/cache issue, not
  an app error.

## Next steps
Owner creates and merges the PR for `phase-1-stage-3.2-menu`. Codex must not
create or merge the PR. After owner merge, resume from `main` for the next
approved phase/stage prompt.
