# Worklog — Phase 2: Admin UI Foundation

Status: Stage 1.10 UI stack migration complete; awaiting owner PR
Branch: phase-2-stage-1.10-ui-stack

PR state: owner creates and merges PRs; Codex does not create PRs.

## Plan
- [x] Stage 1.1: session setup and branch baseline. Create this Phase 2
  worklog, branch from fresh `main`, confirm Phase 1 Menu CRUD is present on
  `main`, and run the starting status checks. Commit only documentation for
  the worklog/bootstrap state. Result: branch created from fresh
  `origin/main` at `531d82f`, Phase 1 Menu CRUD confirmed on `main`, and
  worklog bootstrap committed at `25a8bdf`.
- [x] Stage 1.2: admin shell, navigation, and dashboard. Replace the skeletal
  admin layout with SmartRest branding, responsive Bootstrap 5 sidebar/topbar,
  user/tenant/branch display, logout, flash messages, `/admin` dashboard with
  current-tenant menu counters, and login redirect to `/admin`. Add
  `hy`/`ru`/`en` translations and focused feature tests. Run `make pint &&
  make stan && make test`, commit. Result: added SmartRest admin shell,
  `/admin` dashboard, tenant/branch/user topbar context, sidebar navigation,
  login redirect to `/admin`, admin translations for all three locales, and
  dashboard/login regression tests. Gates green: Pint pass, PHPStan pass,
  Pest 37 passed / 2 skipped / 250 assertions.
- [x] Stage 1.3: branch and locale switching. Add topbar branch switch using
  the Identity `UserDirectory` assignments contract without changing tenant,
  persist selected branch in session, reject foreign/unassigned branches with
  404/403, add locale switch stored in session, apply locale through
  middleware with tenant default fallback via `TenantSettingsReader`, and add
  tests for both switches. Run `make pint && make stan && make test`, commit.
  Result: extended Identity/Tenancy contracts for assigned branch IDs and
  tenant-owned branch summaries, added topbar branch/locale forms, persisted
  branch and locale in session, applied locale from session with tenant
  default fallback, and covered assigned/unassigned branch switching plus
  locale switching. Gates green: Pint pass, PHPStan pass, Pest 40 passed /
  2 skipped / 267 assertions.
- [x] Stage 1.4: Blade UI component foundation. Add reusable Blade components
  for page header, card, table, buttons, form input/select/toggle, status
  badge, confirm-modal delete flow, and flash messages. Keep Bootstrap 5 +
  existing `tokens.css`; no new JS framework or packages. Add component smoke
  coverage where practical. Run `make pint && make stan && make test`, commit.
  Result: added anonymous Blade components for page headers, cards, dense
  tables, buttons, form input/select/toggle controls, status badges,
  confirm-modal delete flow, and flash messages; dashboard/layout now consume
  shared components and component smoke coverage renders the set. Gates green:
  Pint pass, PHPStan pass, Pest 41 passed / 2 skipped / 275 assertions.
- [x] Stage 1.5: Menu pages as the UI reference implementation. Rewrite the
  existing Menu CRUD pages to use the new admin layout and x-components,
  replace raw delete buttons with confirm-modal, ensure every action returns
  success/error flash messages, preserve thin controllers and Application
  action placement, and keep tenant isolation tests green. Run `make pint &&
  make stan && make test`, commit. Result: Menu index/forms now use shared
  page header, card, table, button, form, status badge, and confirm-modal
  components; delete actions render Bootstrap confirm modals while direct
  action routes and existing flash messages remain unchanged. Gates green:
  Pint pass, PHPStan pass, Pest 41 passed / 2 skipped / 279 assertions.
- [x] Stage 1.6: money presentation and major-unit forms. Add
  `App\Support\Money` formatting helpers that render minor-unit values as
  locale/currency-aware major units, update Menu prices to display and accept
  major units while storing minor units, and add unit/feature tests for AMD
  and decimal currencies. Run `make pint && make stan && make test`, commit.
  Result: added `MoneyFormatter` with float-free major/minor conversion and
  locale-aware symbols, rendered Menu index prices as `2200 ֏` / `$14.99`,
  changed Menu forms to accept `price_major` while storing `price_minor`, and
  covered AMD plus decimal currencies. Gates green: Pint pass, PHPStan pass,
  Pest 44 passed / 2 skipped / 289 assertions.
- [x] Stage 1.7: admin error pages and UI Definition of Done. Add translated
  403/404/500 pages styled with the admin visual system, then update
  `AGENTS.md` with the requested "UI Definition of Done" rule for future
  stages. Run `make pint && make stan && make test`, commit. Result: added
  translated 403/404/500 pages using the admin layout/components, made the
  admin shell guest-safe for error rendering, covered all error pages, and
  added the requested UI Definition of Done to `AGENTS.md`. Gates green: Pint
  pass, PHPStan pass, Pest 47 passed / 2 skipped / 298 assertions.
- [x] Stage 1.8: final verification, push, and CI handoff. Run `make fresh`,
  curl-smoke login -> `/admin` -> `/admin/menu` -> locale switch -> branch
  switch, then run full `make pint && make stan && make test`, push
  `phase-2-stage-1-admin-ui`, wait for both GitHub Actions jobs green, update
  this worklog with local/CI results, and do not create or merge a PR. Result:
  final `make fresh` pass after temporarily stopping unrelated `app-redis`
  that occupied host port 6379; curl smoke pass (`POST /login` 302 to
  `/admin`, `GET /admin` 200, `GET /admin/menu` 200 with `Լոռի ձվածեղ` and
  `2200 ֏`, `POST /admin/locale` 302, `POST /admin/branch` 302, Dilijan branch
  menu showed `Դիլիջանյան նախաճաշ` and hid Kentron item); final Pint pass,
  PHPStan pass, Pest 47 passed / 2 skipped / 298 assertions. Branch pushed at
  code head `e392736`; CI run 29738507952 passed both `quality` and
  `tenant-isolation-pgsql`.
- [x] Stage 1.9.1: branch baseline and worklog plan. Update fresh `main`,
  verify Stage 1 merge is present, create
  `phase-2-stage-1.9-principles-superadmin`, and write this plan before code.
  Result: `main` fast-forwarded to merge commit `9425cdf`, Stage 1 head
  `e392736` verified as an ancestor of `origin/main`, branch created from
  fresh `main`, and this Stage 1.9 plan written before implementation.
- [x] Stage 1.9.2: Product Principles documentation. Add the mandatory
  `Product Principles` section to `AGENTS.md` covering restaurant-worker
  simplicity, superadmin-only destructive actions, and scale-from-day-one
  constraints. Commit documentation with the worklog result. Result:
  `AGENTS.md` now makes UI simplicity, superadmin-only deletes, and
  scale-from-day-one query/list/concurrency rules mandatory for all future
  stages.
- [x] Stage 1.9.3: superadmin-only delete enforcement. Add the user
  `is_superadmin` flag, seed deterministic demo superadmins, enforce
  superadmin authorization on current destructive admin routes, hide Menu
  delete UI for non-superadmins, and cover allowed/denied behavior with
  feature tests. Run `make pint && make stan && make test`, commit with the
  worklog result. Result: added `users.is_superadmin`, model/factory casts,
  deterministic owner superadmins, `superadmin.delete` middleware on all
  current `DELETE` routes, hidden Menu delete controls for non-superadmins,
  and tests proving superadmin delete succeeds, normal manager delete is
  `403`, foreign resource delete remains `404`, demo flags are seeded, and
  every delete route carries the middleware. Gates green: Pint pass, PHPStan
  pass, Pest 49 passed / 2 skipped / 313 assertions.
- [x] Stage 1.9.4: final verification and handoff. Run final
  `make pint && make stan && make test`, push the branch, check GitHub
  Actions status for both jobs if available, update this worklog, and do not
  create or merge a PR. Result: final Pint pass, PHPStan pass, Pest 49 passed
  / 2 skipped / 313 assertions; `make fresh` pass; curl-smoke pass
  (`manager@arat.test` login 302 to `/admin`, `/admin` 200, `/admin/menu` 200
  with `Լոռի ձվածեղ` and no delete controls; `owner@arat.test` `/admin/menu`
  200 with delete controls; manager direct `DELETE /admin/menu/items/1`
  returned 403). Branch pushed at code head `b65af49`; CI run 29740192312
  passed both `quality` and `tenant-isolation-pgsql`.
- [x] Stage 1.10.1: branch baseline and UI stack plan. Update fresh `main`,
  verify Stage 1.9 merge is present, create
  `phase-2-stage-1.10-ui-stack`, and write this plan before code. Commit only
  documentation for the Stage 1.10 baseline. Result: `main` fast-forwarded to
  merge commit `fe26e7e`, Stage 1.9 head `b65af49` verified through the merge
  history, branch `phase-2-stage-1.10-ui-stack` created from fresh `main`, and
  this Stage 1.10 plan written before implementation.
- [x] Stage 1.10.2: Tailwind foundation and ADR. Install the latest stable
  Tailwind CSS through Vite, remove Bootstrap from the CSS/JS entry points,
  move `resources/css/smartrest/tokens.css` values into a SmartRest Tailwind
  theme, and record the Tailwind decision in `docs/DECISIONS.md`. Run focused
  asset/build checks and commit. Result: installed `tailwindcss` 4.3.3 and
  `@tailwindcss/vite` 4.3.3, added SmartRest Tailwind theme tokens in
  `tailwind.config.js`, replaced the Bootstrap CSS import with Tailwind,
  removed the Bootstrap JS import, recorded the Tailwind decision, and verified
  `npm run build` passes. Bootstrap packages remain until Stage 1.10.5 after
  Blade views no longer depend on Bootstrap classes.
- [x] Stage 1.10.3: Livewire + Alpine foundation and proof component. Install
  the latest stable Livewire version compatible with Laravel 13 plus Alpine.js,
  wire Blade/Vite/Livewire assets, convert dashboard counters into a simple
  Livewire component served over normal HTTP, add/adjust tests for the proof,
  and record the Livewire/Alpine decision in `docs/DECISIONS.md`. Run focused
  checks and commit. Result: installed `livewire/livewire` 4.3.3, started
  Livewire through the Vite ESM bundle with its Alpine runtime, added
  `App\Livewire\Admin\DashboardCounters` and a Menu Application metric action,
  rendered dashboard counters as a Livewire component over the admin HTTP
  route, recorded the Livewire/Alpine decision, and verified `npm run build`,
  `make test` (Pest 50 passed / 2 skipped / 318 assertions), `make pint`, and
  `make stan`.
- [x] Stage 1.10.4: Tailwind admin shell and shared components. Rewrite
  `resources/views/layouts/admin.blade.php`, login, error pages, dashboard,
  and all existing `x-` Blade components to Tailwind while preserving current
  behavior, translations, tablet responsiveness, flash messages, branch/locale
  switching, and superadmin-only destructive controls. Replace Bootstrap
  modals/collapse behavior with Alpine. Run focused feature/component tests
  and commit. Result: admin layout, login, dashboard counters, and all shared
  `x-` components now render Tailwind classes; mobile sidebar and confirm
  modal use Alpine instead of Bootstrap collapse/modal JS; error pages inherit
  the Tailwind component system; markup-coupled component/delete assertions
  were updated. Verified `npm run build`, `make test` (Pest 50 passed /
  2 skipped / 318 assertions), `make pint`, and `make stan`.
- [x] Stage 1.10.5: Tailwind Menu views and Bootstrap removal audit. Rewrite
  existing Menu CRUD views to the Tailwind component system without starting
  the future Menu UX redesign, remove Bootstrap dependencies from
  `package.json` / lockfile, audit views/assets/tests for leftover
  Bootstrap-only classes or JS hooks, and update only markup-coupled tests.
  Run focused Menu/admin tests and commit. Result: Menu index/category/item
  forms and localized-name partial now use Tailwind layout utilities while
  preserving existing CRUD behavior and superadmin-only delete rendering;
  removed `bootstrap` and `@popperjs/core` from npm dependencies; deleted the
  unused legacy `resources/css/smartrest/tokens.css`; grep audit found no
  Bootstrap/Popper imports or `data-bs-*` usage. Verified `npm run build`,
  `make test` (Pest 50 passed / 2 skipped / 318 assertions), `make pint`, and
  `make stan`.
- [x] Stage 1.10.6: AGENTS UI stack update. Update `AGENTS.md` UI Definition
  of Done to declare Blade + Livewire + Alpine + Tailwind as the admin UI
  base, forbid SPA frameworks, and document allowed criteria for focused
  npm/Vite UI widget libraries with mandatory `DECISIONS.md` entries. Run
  documentation/grep checks and commit. Result: `AGENTS.md` now names Blade +
  Livewire + Alpine + Tailwind as the UI base, forbids SPA frameworks for
  admin screens, and documents criteria for focused npm/Vite UI widget
  libraries plus mandatory `DECISIONS.md` entries.
- [x] Stage 1.10.7: final verification, push, and CI handoff. Run
  `make fresh`, curl-smoke login -> `/admin` -> `/admin/menu` -> create/edit
  category and item -> locale switch -> branch switch -> 403/404 pages, audit
  markup/assets for no Bootstrap remnants, run `make pint && make stan &&
  make test`, push `phase-2-stage-1.10-ui-stack`, wait for both GitHub
  Actions jobs green, update this worklog, and do not create or merge a PR.
  Result: local `make fresh` passed; full curl-smoke passed (`GET /login`
  200, owner login 302, `/admin` 200 with Livewire `wire:snapshot`,
  `/admin/menu` 200, category create/edit/update 302/200/302, item
  create/edit/update 302/200/302, locale switch 302, branch switch to Arat
  Dilijan Terrace 302, 404 page 404, manager delete 403 page 403); final audit
  found no Bootstrap/Popper imports or `data-bs-*` usage; final gates green:
  Pint pass, PHPStan pass, Pest 50 passed / 2 skipped / 318 assertions.
  First CI run 29744439073 passed `tenant-isolation-pgsql` but failed
  `quality` at `npm ci` because npm 11.16.0 required root lockfile package
  entries for optional `@emnapi/core` / `@emnapi/runtime` dependencies used by
  Rolldown's wasm binding. Added the missing lockfile entries and verified
  local `npm ci` plus `npm run build`; retry pushed at code head `7ad9506`,
  and CI run 29744773070 passed both `quality` and
  `tenant-isolation-pgsql`.

## Done log
- 2026-07-20: Phase 2 Stage 1 opened from fresh `origin/main` on branch
  `phase-2-stage-1-admin-ui`; Stage 1.1 worklog/bootstrap complete.
- 2026-07-20: Stage 1.2 admin shell/dashboard complete locally. SmartRest
  branded responsive layout, `/admin` dashboard counters, login redirect to
  `/admin`, and translated admin shell strings implemented. Gates green:
  Pint pass, PHPStan pass, Pest 37 passed / 2 skipped / 250 assertions.
- 2026-07-20: Stage 1.3 branch/locale switching complete locally. Branch
  switch uses `UserDirectory` assignment IDs and `TenantDirectory` branch
  summaries, rejects unassigned branches with 404, keeps tenant session
  unchanged, and locale switching uses session override with tenant settings
  default fallback. Gates green: Pint pass, PHPStan pass, Pest 40 passed /
  2 skipped / 267 assertions.
- 2026-07-20: Stage 1.4 Blade component foundation complete locally. Added
  reusable anonymous components for admin page structure, tables, buttons,
  forms, status badges, confirm-delete modal, and flash messages; dashboard
  and layout consume the first shared components. Gates green: Pint pass,
  PHPStan pass, Pest 41 passed / 2 skipped / 275 assertions.
- 2026-07-20: Stage 1.5 Menu component rewrite complete locally. Menu CRUD is
  now the reference implementation for the shared admin components, including
  confirm-modal delete UI and continued flash/tenant-isolation behavior. Gates
  green: Pint pass, PHPStan pass, Pest 41 passed / 2 skipped / 279 assertions.
- 2026-07-20: Stage 1.6 money presentation complete locally. Menu prices now
  display major units through `MoneyFormatter`, forms accept major-unit
  strings and convert to stored integer minor units, and unit/feature coverage
  proves AMD and USD behavior. Gates green: Pint pass, PHPStan pass, Pest
  44 passed / 2 skipped / 289 assertions.
- 2026-07-20: Stage 1.7 admin error pages and UI Definition of Done complete
  locally. Added translated admin-styled 403/404/500 views, regression tests,
  guest-safe admin layout behavior for error rendering, and the requested
  `AGENTS.md` UI rules. Gates green: Pint pass, PHPStan pass, Pest 47 passed /
  2 skipped / 298 assertions.
- 2026-07-20: Stage 1.8 final verification and CI handoff complete. Local
  `make fresh` passed; curl smoke passed for login, `/admin`, `/admin/menu`,
  locale switch, and explicit branch switch to Dilijan with branch-scoped menu
  content. Final gates green: Pint pass, PHPStan pass, Pest 47 passed /
  2 skipped / 298 assertions. Branch `phase-2-stage-1-admin-ui` pushed at
  code head `e392736`; GitHub Actions run 29738507952 passed both `quality`
  and `tenant-isolation-pgsql`. PR is not created by Codex.
- 2026-07-20: Stage 1.9 started from fresh `main` after owner merged Stage 1.
  Stage 1 merge commit `9425cdf` includes Stage 1 head `e392736`; branch
  `phase-2-stage-1.9-principles-superadmin` created and implementation plan
  written before code.
- 2026-07-20: Stage 1.9.2 Product Principles documentation complete.
  `AGENTS.md` now records mandatory simplicity, superadmin-only delete, and
  scale-from-day-one rules for current and future modules.
- 2026-07-20: Stage 1.9.3 superadmin-only delete enforcement complete
  locally. Added `users.is_superadmin`, deterministic demo owner superadmins,
  route middleware enforcement for current admin destructive routes, hidden
  Menu delete controls for non-superadmins, and regression tests. Gates green:
  Pint pass, PHPStan pass, Pest 49 passed / 2 skipped / 313 assertions.
- 2026-07-20: Stage 1.9.4 final verification and CI handoff complete. Final
  local gates green: Pint pass, PHPStan pass, Pest 49 passed / 2 skipped /
  313 assertions. `make fresh` passed with the new `users.is_superadmin`
  migration and `DemoSeeder`. Curl smoke passed for manager login, `/admin`,
  `/admin/menu`, manager hidden delete controls, owner visible delete controls,
  and manager direct delete returning 403. Branch
  `phase-2-stage-1.9-principles-superadmin` pushed at code head `b65af49`;
  GitHub Actions run 29740192312 passed both `quality` and
  `tenant-isolation-pgsql`. PR is not created by Codex.
- 2026-07-20: Stage 1.10 started from fresh `main` after owner merged Stage
  1.9. Stage 1.9 merge commit `fe26e7e` includes Stage 1.9 head `b65af49`;
  branch `phase-2-stage-1.10-ui-stack` created and implementation plan
  written before code.
- 2026-07-20: Stage 1.10.2 Tailwind foundation complete. Installed Tailwind
  CSS 4.3.3 and the official Vite plugin, moved SmartRest token values into
  `tailwind.config.js`, removed Bootstrap from CSS/JS entry imports, recorded
  the Tailwind decision, and verified `npm run build`.
- 2026-07-20: Stage 1.10.3 Livewire/Alpine foundation complete. Installed
  Livewire 4.3.3, started Livewire through Vite ESM with its Alpine runtime,
  converted dashboard counters to a Livewire component, added coverage, and
  verified `npm run build`, `make test`, `make pint`, and `make stan`.
- 2026-07-20: Stage 1.10.4 Tailwind admin shell/components complete. Admin
  layout, login, dashboard counters, and all shared `x-` components now use
  Tailwind; sidebar and confirm modal use Alpine instead of Bootstrap JS.
  Gates green: build, Pest 50 passed / 2 skipped / 318 assertions, Pint,
  PHPStan.
- 2026-07-20: Stage 1.10.5 Menu Tailwind rewrite and Bootstrap removal
  complete. Menu CRUD views use Tailwind without starting the future Menu UX
  redesign; Bootstrap and Popper npm dependencies were removed; legacy
  `resources/css/smartrest/tokens.css` was deleted after token migration.
  Gates green: build, Pest 50 passed / 2 skipped / 318 assertions, Pint,
  PHPStan.
- 2026-07-20: Stage 1.10.6 AGENTS UI stack update complete. UI DoD now names
  Blade + Livewire + Alpine + Tailwind as the base, forbids SPA frameworks for
  admin screens, and documents criteria for focused npm/Vite UI widget
  libraries.
- 2026-07-20: Stage 1.10.7 final local verification complete. `make fresh`
  passed; curl-smoke passed for login, `/admin`, `/admin/menu`, category/item
  create/edit/update, locale switch, branch switch, 404 page, and manager 403
  page; final gates green: Pint pass, PHPStan pass, Pest 50 passed / 2 skipped
  / 318 assertions. Branch pushed at `80dd575`; first CI run 29744439073
  passed `tenant-isolation-pgsql` but failed `quality` at `npm ci`. Added the
  missing optional `@emnapi/core` / `@emnapi/runtime` lockfile entries required
  by npm 11.16.0 and verified local `npm ci` plus `npm run build`; retry CI
  run 29744773070 passed both `quality` and `tenant-isolation-pgsql` at code
  head `7ad9506`. PR is not created by Codex.

## Gotchas / known issues
- Host PHP is outdated; use Make targets only, never raw host PHP.
- `template/` remains read-only reference material and must not be modified.
- Phase 2 Stage 1 is an admin UI foundation slice, not Phase 2 domain work
  for halls/tables/orders. Any blueprint-level change requires owner approval
  and a separate commit.
- `main` now includes the Phase 1 Menu CRUD merge, so Menu pages are the
  correct reference target for component migration.
- Final local `make fresh` initially failed because unrelated Docker container
  `app-redis` occupied host port 6379. Owner-approved remediation was to
  temporarily stop `app-redis`, run verification, then stop this project's
  containers and restart `app-redis` after curl smoke.
- Stage 1.9 intentionally treats delete as an additional superadmin gate on
  top of normal permissions, not as a replacement for existing
  create/read/update permission checks.
- GitHub Actions emitted non-blocking Node.js 20 deprecation annotations for
  `actions/checkout@v4` / `actions/setup-node@v4` while the jobs still passed.
- `docs/BLUEPRINT.md` ADR-004 still names Bootstrap 5 in the original v1.0
  frontend decision. Stage 1.10 is intentionally superseding that via
  `docs/DECISIONS.md`; do not edit `docs/BLUEPRINT.md` without explicit owner
  approval and a separate commit.
- After `make up` rebuilt/recreated `php-fpm`, nginx temporarily returned 502
  because it held the old Docker upstream IP. `make restart` recreated nginx
  and resolved the smoke-test issue.
- CI npm 11.16.0 is stricter than the local npm 11.6.2 used during initial
  verification: it rejected `package-lock.json` until optional
  `@emnapi/core` / `@emnapi/runtime` package entries for Rolldown's wasm
  binding were present at the lockfile root.

## Next steps
Owner creates the PR for `phase-2-stage-1.10-ui-stack`; Codex must not create
or merge a PR.
