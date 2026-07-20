# Worklog — Phase 2: Admin UI Foundation

Status: Stage 1.4 complete; Stage 1.5 ready
Branch: phase-2-stage-1-admin-ui

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
- [ ] Stage 1.5: Menu pages as the UI reference implementation. Rewrite the
  existing Menu CRUD pages to use the new admin layout and x-components,
  replace raw delete buttons with confirm-modal, ensure every action returns
  success/error flash messages, preserve thin controllers and Application
  action placement, and keep tenant isolation tests green. Run `make pint &&
  make stan && make test`, commit. Result: pending.
- [ ] Stage 1.6: money presentation and major-unit forms. Add
  `App\Support\Money` formatting helpers that render minor-unit values as
  locale/currency-aware major units, update Menu prices to display and accept
  major units while storing minor units, and add unit/feature tests for AMD
  and decimal currencies. Run `make pint && make stan && make test`, commit.
  Result: pending.
- [ ] Stage 1.7: admin error pages and UI Definition of Done. Add translated
  403/404/500 pages styled with the admin visual system, then update
  `AGENTS.md` with the requested "UI Definition of Done" rule for future
  stages. Run `make pint && make stan && make test`, commit. Result: pending.
- [ ] Stage 1.8: final verification, push, and CI handoff. Run `make fresh`,
  curl-smoke login -> `/admin` -> `/admin/menu` -> locale switch -> branch
  switch, then run full `make pint && make stan && make test`, push
  `phase-2-stage-1-admin-ui`, wait for both GitHub Actions jobs green, update
  this worklog with local/CI results, and do not create or merge a PR. Result:
  pending.

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

## Gotchas / known issues
- Host PHP is outdated; use Make targets only, never raw host PHP.
- `template/` remains read-only reference material and must not be modified.
- Phase 2 Stage 1 is an admin UI foundation slice, not Phase 2 domain work
  for halls/tables/orders. Any blueprint-level change requires owner approval
  and a separate commit.
- `main` now includes the Phase 1 Menu CRUD merge, so Menu pages are the
  correct reference target for component migration.

## Next steps
Implement Stage 1.5 by rewriting Menu CRUD pages to use the new admin
components, replacing raw delete buttons with confirm-modal, and preserving
flash messages plus tenant isolation coverage.
