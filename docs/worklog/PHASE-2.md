# Worklog — Phase 2: Admin UI Foundation

Status: Stage 1.11 Part C planning checkpoint; awaiting owner OK before code
Branch: phase-2-stage-1.11c-menu-ux

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
- [x] Stage 1.11.1: branch baseline and Menu UX plan. Update fresh `main`,
  verify Stage 1.10 merge is present, create
  `phase-2-stage-1.11-menu-ux`, and write the A/B/C plan before code.
  Result: `origin/main` fast-forwarded to merge commit `a7cdc36`, Stage 1.10
  head `ea82eb4` verified as an ancestor of `origin/main`, local `main`
  fast-forwarded, branch `phase-2-stage-1.11-menu-ux` created, and this
  Stage 1.11 plan written before implementation.
- [x] Stage 1.11.2 (Part A): soft-delete policy documentation and cascade
  decision. Update `AGENTS.md` Product Principles so product deletion means
  archive/soft delete, restoration is superadmin-only, and physical deletion
  is not exposed in UI; record the Menu category cascade archive/restore
  behavior in `docs/DECISIONS.md`. Run documentation/grep checks and commit.
  Result: `AGENTS.md` now defines product deletion as archive/soft delete,
  with normal manage permission for archive, superadmin-only restore, no
  physical deletion through UI, and confirm-modal archive controls;
  `docs/DECISIONS.md` records the explicit Menu category cascade marker
  policy so category restore only restores items archived by that cascade.
- [x] Stage 1.11.3 (Part A): schema, models, and actions for archive/restore.
  Add `deleted_at` to `menu_categories` and `menu_items`, convert models to
  Laravel `SoftDeletes`, replace `DeleteMenu*` behavior with archive actions,
  add restore actions, make category archive cascade to non-archived child
  items and restore only the items archived by that category cascade, and update
  composite indexes for `tenant_id`/`branch_id`/`category_id`/`deleted_at`
  filtering paths. Run focused action/schema tests and commit. Result: added
  soft-delete migration, `SoftDeletes` models, explicit
  `archived_with_category_id` marker, Archive/Restore Application actions,
  compatibility wrappers for legacy Delete actions, deleted-at-aware indexes,
  and tests proving default lists hide archived records, category restore only
  restores cascade-marked items, manual archives stay archived, and item
  restore is blocked while its category is archived. Gates green: Pint pass,
  PHPStan pass, Pest 51 passed / 2 skipped / 339 assertions.
- [x] Stage 1.11.4 (Part A): routes, controllers, UI, translations, and
  permission tests. Remove `superadmin.delete` from archive routes while
  retaining normal manage permissions, add superadmin-only restore routes,
  rename UI copy from delete to archive, add archive filters and archived
  badges/restores, ensure archived categories are not selectable in item
  forms, update `hy`/`ru`/`en` translations, and cover archive permission,
  restore `403` for normal users, hidden archived records, and tenant
  isolation. Run `make pint && make stan && make test`, commit. Result:
  archive routes now require only normal manage permissions, restore routes
  use a new `superadmin` middleware alias, Menu controllers call Archive and
  Restore actions, index has a show/hide archived filter, archived badges,
  restore controls for superadmins only, category/item action visibility
  follows permissions, archived categories are excluded from item forms and
  rejected by create, all archive/restore strings are translated in `hy`,
  `ru`, and `en`, and feature tests cover permission, restore 403, tenant
  404s, hidden archived rows, and inaccessible category controls. Gates green:
  Pint pass, PHPStan pass, Pest 53 passed / 2 skipped / 379 assertions.
- [x] Stage 1.11.5 (Part A): final verification and handoff for soft delete.
  Run `make fresh`, curl-smoke manager archive/category cascade/hidden
  archive filter plus owner restore, final `make pint && make stan &&
  make test`, push `phase-2-stage-1.11-menu-ux`, wait for both CI jobs green,
  update this worklog, and do not create or merge a PR. Result: `make fresh`
  passed on PostgreSQL with the new soft-delete migration; curl smoke passed
  by creating a temporary category/item as `manager@arat.test`, archiving the
  category, confirming default `/admin/menu` hid it, `show_archived=1` showed
  the localized archived badge, manager restore returned 403, `owner@arat.test`
  restore returned 302, and the cascade item was restored with
  `archived_with_category_id` cleared; final gates green: Pint pass, PHPStan
  pass, Pest 53 passed / 2 skipped / 379 assertions. Branch pushed at code
  head `9374d4b`; CI run 29747861501 passed both `quality` and
  `tenant-isolation-pgsql`.
- [x] Stage 1.11.5.1 (Part A review): review-change plan. Continue on the
  existing `phase-2-stage-1.11-menu-ux` branch without touching `main`, read
  the required session documents, verify the working tree, and write this
  owner-review plan before code. Result: branch was already clean and tracking
  `origin/phase-2-stage-1.11-menu-ux` at Part A handoff head; owner requested
  superadmin-only archive visibility plus superadmin force delete.
- [x] Stage 1.11.5.2 (Part A review): archive visibility and policy docs.
  Update `AGENTS.md` and `docs/DECISIONS.md` so archive viewing,
  `show_archived`, badges, restore, and force delete are superadmin-only;
  record that `show_archived=1` from non-superadmins is ignored rather than
  forbidden. Commit with the worklog result. Result: product policy now states
  archive is by manage permission while archive viewing, restore, and
  permanent delete are superadmin-only; `docs/DECISIONS.md` records ignored
  `show_archived` for non-superadmins and superadmin-only force delete.
- [x] Stage 1.11.5.3 (Part A review): force-delete application and routes.
  Add superadmin-only force-delete actions/routes for categories/items,
  permanently delete archived categories with their archived items, keep tenant
  and branch isolation at 404 for foreign ids, and update tests for
  non-superadmin restore/force-delete 403 and force delete database removal.
  Run focused tests and commit. Result: added `ForceDeleteMenuCategory` and
  `ForceDeleteMenuItem`, superadmin-only force-delete routes/controllers,
  category force delete permanently removes archived child items, item force
  delete is branch-scoped and only applies to archived rows, and feature tests
  cover non-superadmin 403, foreign tenant 404, route middleware, and database
  removal. Gates green: Pint pass, PHPStan pass, Pest 54 passed / 2 skipped /
  397 assertions.
- [x] Stage 1.11.5.4 (Part A review): superadmin-only archive UI. Hide the
  `show_archived` filter, archived rows, archived badges, restore, and force
  delete controls from non-superadmins; add hard confirm-modal copy for force
  delete in `hy`/`ru`/`en`; keep normal manager archive behavior unchanged so
  archived records disappear for that user. Run `make pint && make stan &&
  make test` and commit. Result: Menu index ignores `show_archived` unless the
  authenticated user is superadmin, hides archive filters/badges/rows/actions
  from non-superadmins, renders restore and force-delete controls only for
  superadmins, adds irreversible force-delete confirm copy and flash messages
  in `hy`/`ru`/`en`, and tests prove manager archive disappearance plus
  superadmin archive controls. Gates green: Pint pass, PHPStan pass, Pest
  54 passed / 2 skipped / 411 assertions.
- [x] Stage 1.11.5.5 (Part A review): final verification and handoff. Run
  `make fresh`, curl-smoke manager archive then hidden/no archive access,
  owner archive visibility/restore/force-delete, final `make pint && make stan
  && make test`, push `phase-2-stage-1.11-menu-ux`, wait for both CI jobs
  green, update this worklog, and do not create or merge a PR. Result:
  `make fresh` passed; curl smoke passed for manager archive disappearance,
  ignored manager `show_archived`, owner archive visibility, owner restore,
  and owner force-delete category cascade; final gates green: Pint pass,
  PHPStan pass, Pest 54 passed / 2 skipped / 411 assertions. Branch pushed at
  code head `0d11d6d`; CI run 29749417502 passed both `quality` and
  `tenant-isolation-pgsql`.
- [x] Stage 1.11.6 (Part B): branch baseline, image architecture, and
  dependency decision. Verify Part A is merged to fresh `main`, create
  `phase-2-stage-1.11b-item-images`, choose the image processing dependency
  and Storage-backed path policy, record file lifecycle and dependency
  decisions in `docs/DECISIONS.md`, add `internal_image` / `public_image`
  metadata columns and config, and commit with focused schema checks. Result:
  `origin/main` fast-forwarded to merge commit `08f3321`, Part A head
  `dd4a395` verified as an ancestor, local `main` fast-forwarded, branch
  `phase-2-stage-1.11b-item-images` created, `intervention/image-laravel` 4.x
  selected for processing, Storage-backed tenant path and lifecycle policy
  recorded in `docs/DECISIONS.md`, nullable JSON metadata columns plus
  `config/menu_images.php` added, and schema/model tests updated. Gates green:
  Pint pass, PHPStan pass, Pest 55 passed / 2 skipped / 415 assertions.
- [x] Stage 1.11.7 (Part B): image processing service and lifecycle actions.
  Install/configure the image library, implement tenant-scoped upload
  processing through Laravel Storage with resized originals and thumbnails,
  add replace/remove helpers for both image slots, delete old files on
  replacement/removal, delete image files during superadmin force delete, keep
  archive/restore file-preserving, and cover upload/replace/remove/force-delete
  behavior with `Storage::fake` tests. Run `make pint && make stan &&
  make test`, then commit. Result: installed `intervention/image-laravel`
  4.1.0 (`intervention/image` 4.2.0), added `MenuItemImageSlot`, Storage-backed
  processing service, replace/remove Application actions, old-file cleanup on
  replacement/removal, archive-preserving behavior, and item/category
  force-delete file cleanup. The PHP Docker image now installs GD with
  jpeg/png/webp support because the previous runtime lacked any image driver.
  Tests cover both image slots, validation for unsupported type/size, tenant
  isolation on id tampering, archive preserving files, and force delete removing
  files. Gates green: Pint pass, PHPStan pass, Pest 59 passed / 2 skipped /
  464 assertions.
- [x] Stage 1.11.8 (Part B): Livewire upload UI, placeholders, translations,
  and demo fixtures. Convert the menu item form to a thin Livewire adapter for
  two optional image upload zones with current preview, replace, and remove
  controls; render thumbnails with the shared default placeholder in the item
  list; add `hy`/`ru`/`en` translations; add deterministic demo image fixtures
  for a few seeded items while leaving other items empty. Run focused UI tests,
  `npm run build`, full gates, and commit. Result: item create/edit now renders
  a Livewire form with staff/internal and guest/public upload zones, previews,
  replace/remove controls, Livewire validation for jpeg/png/webp and max size,
  and translated labels/help in `hy`/`ru`/`en`; the item list renders an
  internal staff thumbnail with the shared SVG placeholder fallback; demo
  seeding uses two small PNG fixtures through the same image processing action
  while other items remain image-empty. Verified `make pint`, `make stan`,
  `make test` (Pest 63 passed / 2 skipped / 502 assertions), and `make build`.
- [x] Stage 1.11.9 (Part B): final verification, push, and CI handoff. Run
  `make fresh`, curl/HTTP smoke for Livewire upload, thumbnail rendering, and
  placeholder fallback, then final `make pint && make stan && make test`.
  Push `phase-2-stage-1.11b-item-images`, wait for both GitHub Actions jobs
  green, update this worklog with local/CI results, and do not create or merge
  a PR. Result: `make fresh` passed after adding `storage:link` to the Make
  target; curl smoke passed for manager login, create form Livewire upload
  fields, real Livewire `_startUpload` -> multipart temporary upload ->
  `_finishUpload` -> `save`, item list visibility, thumbnail `200 image/png`,
  and placeholder `200 image/svg+xml`; final local gates green: Pint pass,
  PHPStan pass, Pest 64 passed / 2 skipped / 503 assertions. Branch pushed at
  implementation code head `d0065ae`; GitHub Actions run 29753190555 passed
  both `quality` and `tenant-isolation-pgsql`. PR is not created by Codex.
- [x] Stage 1.11.10.1 (Part C): branch baseline, plan, and decisions. Verify
  Part A and Part B are merged to `origin/main`, create/switch to
  `phase-2-stage-1.11c-menu-ux` from fresh `origin/main`, confirm `git status`
  and `git log --oneline -8`, decide the JSONB item-search indexing strategy
  and category searchable-select approach, record both in `docs/DECISIONS.md`,
  and stop for owner OK before code because the indexing choice affects
  migrations. Result: Part A merge `08f3321` and Part B merge `5b72b93` are on
  `origin/main`; Part B head `278c4b5` is an ancestor of `origin/main`; branch
  `phase-2-stage-1.11c-menu-ux` was created from `origin/main`; worktree was
  clean; decisions recorded for `pg_trgm` GIN expression search over JSONB
  names and a Livewire + Alpine category combobox with no new UI library.
- [x] Stage 1.11.10.2 (Part C): scalable query foundation. Add PostgreSQL
  migration(s) for `pg_trgm`, Menu item localized-name expression GIN index,
  category localized-name expression GIN index if the category panel search
  needs it, and composite btree indexes for selected-category list paths:
  `tenant_id`, `branch_id`, `category_id`, `deleted_at`, optional `active`,
  `sort_order`, and `id`. Replace full-collection list actions with paginated
  query actions for category panel, selected-category items, and global item
  search, preserving tenant and branch isolation. Add focused schema/action
  tests and run the relevant checks before commit. Result: added a PostgreSQL
  `pg_trgm` migration with localized-name GIN expression indexes for
  `menu_categories` and `menu_items`, added btree indexes for category panel,
  selected-category item pages, global item pages, inactive filtering, and
  archive-aware paths, and introduced paginated Application query actions for
  category search, selected-category items, and global multilingual item
  search. Empty global search returns an empty paginator instead of scanning
  all items. Legacy collection actions remain temporarily for the pre-redesign
  Blade screen/forms and will be removed from the hot path in Stage 1.11.10.3
  / 1.11.10.5. Gates green: Pint pass, PHPStan pass, Pest 70 passed /
  2 skipped / 530 assertions.
- [ ] Stage 1.11.10.3 (Part C): Livewire master-detail index. Replace the
  current two-table Menu index with one thin Livewire adapter that renders a
  tablet-first master-detail screen: prominent global item search, left
  category panel search with page-size-limited results and "show more",
  selected `?category=` URL state with first-category default, right-side
  paginated item list, category-visible search results, item thumbnails with
  placeholder fallback, empty states, and responsive collapse behavior on
  narrow screens. Add Pest Livewire tests for global search, category panel
  search, category URL selection, and empty states.
  Micro-plan for WIP reconciliation after owner review: remove the stale
  full-collection query path from `MenuIndexController`; keep the current WIP
  Livewire view structure without adding new category "show more" or mobile
  collapse UX in this pass; check whether existing `PaginateMenuCategories`
  / `ListMenuCategories` cover selected-category fallback before introducing
  any new Application query action; add Livewire coverage for global search,
  category search, category URL/fallback state, empty states, and archive /
  permission visibility; update only translation keys and markup-coupled
  assertions needed by the current WIP. Migration fix micro-plan: replace the
  PostgreSQL `concat_ws` localized-name expressions in the Stage 1.11.10.2
  trigram indexes and matching Menu search/order SQL with immutable-safe
  `coalesce(...) || ' ' || ...` expressions, then stop for owner-run checks.
  Archive-mode micro-plan: replace `showArchived` boolean with URL-backed
  `archive_mode=active|archived|all`; make `archived` use `onlyTrashed()`,
  `all` use `withTrashed()`, and force non-superadmins back to `active`;
  update `PaginateMenuCategories`, `PaginateMenuItems`, `SearchMenuItems`,
  Livewire UI, redirects, translations, and focused tests; stop for review
  before commit.
- [ ] Stage 1.11.10.4 (Part C): item row operations and archive controls.
  Add an Application action for toggling item activity, wire an inline
  Livewire row toggle without full-page reload, keep title click -> edit, move
  archive into the row overflow menu, and preserve Part A superadmin-only
  archive visibility/restore/force-delete behavior with confirm-modal usage.
  Cover toggle, item pagination, inactive filter, archived filter visibility,
  restore/force-delete visibility, and permission/tenant-isolation regressions.
- [ ] Stage 1.11.10.5 (Part C): context-preserving forms and searchable
  category combobox. Replace the item form's all-options category select with
  a Livewire + Alpine server-search combobox, prefill category from
  `?category=`, preserve return context after save/cancel (`category`,
  category page, item page, global search query, inactive/archive filters),
  and keep image upload behavior from Part B intact. Add `hy`/`ru`/`en`
  translations and Pest coverage for create/edit context and searchable
  category selection.
- [ ] Stage 1.11.10.6 (Part C): load-data command and performance fixes. Add
  an artisan command outside `DemoSeeder` to generate about 200 categories and
  20000 items per tenant with deterministic localized names, prices, active
  distribution, sort values, and placeholder-image coverage compatible with
  Part B. Run `EXPLAIN` on category panel, selected-category page, global
  search, inactive filter, and archive paths; fix slow paths with indexes, not
  cache.
- [ ] Stage 1.11.11 (Part C): final verification, load smoke, push, and CI
  handoff. Run `make fresh`, run the load-data command, capture curl/HTTP
  timings for Menu index, category panel pagination/search, category switching,
  global item search, item pagination, create-item write latency, and activity
  toggle write latency on the filled table so GIN index write overhead is
  visible. If writes noticeably regress, record it in this worklog for owner
  discussion. Then run final `make pint && make stan && make test`, push
  `phase-2-stage-1.11c-menu-ux`, wait for both GitHub Actions jobs green,
  record measurements/CI/handoff in this worklog, and do not create or merge a
  PR.

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
- 2026-07-20: Stage 1.11 started from fresh `main` after owner merged Stage
  1.10. Stage 1.10 merge commit `a7cdc36` includes Stage 1.10 head `ea82eb4`;
  branch `phase-2-stage-1.11-menu-ux` created. Stage is intentionally split
  into independently reviewable parts: A soft delete, B images, C Menu UX
  redesign and load measurements.
- 2026-07-20: Stage 1.11.2 Part A documentation complete. Product deletion
  now means archive in `AGENTS.md`, restore is superadmin-only, and
  `docs/DECISIONS.md` records explicit Menu category cascade restore
  semantics.
- 2026-07-20: Stage 1.11.3 Part A schema/action layer complete. Menu
  categories/items now use `deleted_at`, item cascade membership is tracked
  by `archived_with_category_id`, archive/restore Application actions cover
  item/category behavior, and focused schema/action tests plus full Pest,
  Pint, and PHPStan are green.
- 2026-07-20: Stage 1.11.4 Part A HTTP/UI layer complete. Delete routes now
  archive by normal manage permission, restore routes are superadmin-only,
  Menu index shows archived rows only via filter with translated badges and
  restore controls, archived categories are unavailable in item forms, and
  permission/tenant-isolation feature coverage is updated.
- 2026-07-20: Stage 1.11.5 Part A final verification complete. Local
  `make fresh`, curl smoke, Pint, PHPStan, and Pest are green. Branch
  `phase-2-stage-1.11-menu-ux` pushed at code head `9374d4b`; GitHub Actions
  run 29747861501 passed both `quality` and `tenant-isolation-pgsql`. PR is
  not created by Codex.
- 2026-07-20: Stage 1.11 Part A owner review opened on the existing
  `phase-2-stage-1.11-menu-ux` branch. Scope is limited to superadmin-only
  archive visibility and superadmin force delete; `main` is not touched.
- 2026-07-20: Stage 1.11.5.2 Part A review documentation complete.
  `AGENTS.md` and `docs/DECISIONS.md` now make archive visibility, restore,
  and permanent delete superadmin-only, while normal managers may still
  archive by permission.
- 2026-07-20: Stage 1.11.5.3 Part A review backend complete. Force-delete
  Application actions and superadmin routes are implemented for archived Menu
  categories/items, with cascade physical deletion and tenant/branch isolation
  coverage.
- 2026-07-20: Stage 1.11.5.4 Part A review UI complete. Archive visibility is
  now superadmin-only in the Menu index; managers can archive but cannot see
  archive filters, archived rows, badges, restore, or force-delete controls.
- 2026-07-20: Stage 1.11.5.5 Part A review final verification complete.
  Local `make fresh`, curl smoke, Pint, PHPStan, and Pest are green. Branch
  `phase-2-stage-1.11-menu-ux` pushed at code head `0d11d6d`; GitHub Actions
  run 29749417502 passed both `quality` and `tenant-isolation-pgsql`. PR is
  not created by Codex.

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
- Stage 1.11 is too large for one safe review chunk. Work it as A -> B -> C,
  with a push/CI handoff after each part and owner-created PRs only.
- During Stage 1.11 Part B, running host Composer updated `composer.json` and
  `composer.lock` but failed package discovery because host PHP is 8.1.2. The
  fix was to complete install/discovery through Docker-backed `make build`.
  Do not use host Composer again in this repo.
- The pre-existing PHP Docker image had no `gd` extension, so Laravel fake
  image generation and Intervention's default GD driver failed. Stage 1.11.7
  added GD with jpeg/png/webp libraries to `docker/php/Dockerfile` and rebuilt
  services with `make up`; `php -m` now lists `gd`.
- During Stage 1.11 Part B final smoke, generated local thumbnails existed in
  `storage/app/public` but nginx returned 403 until the standard Laravel
  `public/storage` link was created. `make fresh` and `make build` now run
  `php artisan storage:link --force`; the local public disk defaults to the
  relative `FILESYSTEM_PUBLIC_URL=/storage` so Docker port changes do not
  produce broken `APP_URL`-based image URLs.
- Stage 1.11 Part C owner checkpoint approved the `pg_trgm` JSONB localized
  name expression index and Livewire + Alpine category combobox decisions on
  2026-07-21. Final load measurements must include write latency for creating
  a menu item and toggling activity on the filled table, not only read paths.
- During Stage 1.11.10.3 WIP reconciliation, existing `PaginateMenuCategories`
  covers category panel pagination/search and first-page lookup, but no
  existing Application action fetches one selected category by id without
  loading a full collection. Do not add a new selected-category query action
  unless the owner approves it; `MenuIndex` still has the pre-existing direct
  Eloquent selected-category lookup in the WIP.
- `make fresh` failed on
  `2026_07_21_000000_add_menu_search_and_pagination_indexes.php` because
  PostgreSQL rejects `concat_ws` in expression indexes as not immutable. The
  WIP now uses the same immutable-safe `coalesce(...) || ' ' || ...`
  localized-name expression in both trigram indexes and PostgreSQL
  search/order SQL, but owner-run `make fresh` is still pending.
- Archive mode WIP intentionally treats `archive_mode=archived` category
  panel rows as archived categories plus active categories that contain
  archived items. Item lists and global item search still use `onlyTrashed()`,
  so active items are not mixed into archived item results while individually
  archived items remain discoverable under their active category container.
- Archive-mode filtering currently duplicates the category container
  interpretation in `PaginateMenuCategories` and `MenuIndex` selected-category
  lookup. Leave it for this review slice; revisit when subcategory introduces
  a proper selected-node query/action.
- Technical debt for subcategory: archive-mode container filtering is
  duplicated between Livewire selected-category lookup and the paginated query
  action. Collapse it into one Application query path when the category tree
  becomes a first-class parent/child selection model.
- Menu search/index coverage must run against PostgreSQL for trgm/GIN behavior.
  SQLite feature tests are still useful for fast behavior checks, but they do
  not prove PostgreSQL expression indexes, `pg_trgm`, or planner behavior.
- 2026-07-22 PostgreSQL-only diagnostics found pre-Step-A failures hidden by
  the default SQLite test target: localized Menu search currently fails on
  PostgreSQL with `SQLSTATE[HY093]` in `FiltersLocalizedNames` when a non-empty
  LIKE search is bound, and RLS expectations fail because the local/test
  `smartrest` database role is a superuser with `BYPASSRLS`.

## Next steps
Stage 1.11 Part C subcategory implementation order after owner-approved
`docs/DECISIONS.md` entry:
- [x] Step A: add schema/model foundation for `menu_categories.parent_id` and
  `menu_categories.archived_with_category_id`, self-FK/check/indexes, model
  relations/casts, and PostgreSQL schema tests. Result: review-ready on
  2026-07-22; focused PostgreSQL `MenuSchemaTest` passed (`8 passed / 54
  assertions`).
- [ ] Step B: enforce depth=2 and item-only-under-subcategory in Application
  actions/requests/tests. No new seed-load command.
- [ ] Step C: implement root/subcategory archive/restore/force-delete cascade
  semantics with cascade markers. Batch archive updates must only touch
  currently non-trashed rows with `archived_with_category_id is null`, so
  independently archived descendants keep their marker/state. Update demo
  seed data to root+subcategory structure before running full `make fresh`.
- [ ] Step D: adapt Menu query actions and Livewire master-detail to the
  root -> subcategory -> item tree, reusing existing paginated actions where
  possible and collapsing duplicated archive container logic.
- [ ] Step E: implement `menu:seed-load` last, after parent_id schema and UI
  paths are final. Support production-like and giant-menu modes with raw batch
  insert/COPY and optional drop/rebuild trgm index flow.

Next action: await owner direction on documenting/fixing the RLS runtime-role
security debt, then proceed to Step B only after explicit approval. Do not edit
`docs/BLUEPRINT.md`, do not create PRs/merges/pushes, and do not commit without
explicit owner approval.

Immediate fix before subcategory Step B: repair PostgreSQL localized LIKE
binding in `FiltersLocalizedNames` without changing the indexed
`lower(coalesce(...) || ...)` expression, then run the four failing PostgreSQL
Menu search tests. Do not touch RLS implementation until the owner decides on
the production DB role.
Result: review-ready on 2026-07-22; the PostgreSQL escape literal now uses
`ESCAPE E'\\'` only for pgsql while other drivers keep the existing escape
clause, and the four previously failing PostgreSQL Menu search tests passed
(`4 passed / 26 assertions`). RLS code remains untouched.
