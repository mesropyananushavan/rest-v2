# Worklog — Phase 2: Admin UI Foundation

Status: Stage 1.14 tenant translation override editing screen in progress
Branch: phase-2-stage-1.14-tenant-translation-ui

PR state: Codex may create and merge PRs after exact-head green CI; direct
pushes to `main`, force-push, history rewriting, and branch deletion remain
forbidden.

## Plan
- [x] Stage 1.11C-scale.1: baseline reconciliation and backend-only plan.
  Read `AGENTS.md`, `docs/BLUEPRINT.md`, `docs/DECISIONS.md`, and this
  worklog; run `git status`, `git log --oneline -8`, and `git fetch origin`;
  verify Part B head `d0065ae` ancestry; create
  `phase-2-stage-1.11c-menu-scale` from fresh `origin/main`; inspect the real
  Menu module, migrations, command registration, and Make targets; record that
  the prompt's "Part C not started" note is stale against current `main`, which
  already contains Stage 1.11 Part C UI/read-model work plus later
  Audit/Halls/Tables merges. Result: `git status` was clean on
  `main...origin/main`; `git fetch origin`
  succeeded; `d0065ae` is an ancestor of `origin/main`; branch
  `phase-2-stage-1.11c-menu-scale` was created from `origin/main` at
  `fb909c0`. Actual current Menu code already has `PaginateMenuCategories`,
  `PaginateMenuItems`, `SearchMenuItems`, PostgreSQL trigram JSONB expression
  indexes, and the broad `menu:seed-load` command, but it does not have the
  prompt-compatible per-demo-tenant `menu:load-test-data` command or the
  required fresh backend measurement proof for that exact slice.
- [x] Stage 1.11C-scale.2: prompt-compatible load-test command. Add a
  standalone `menu:load-test-data` command registered in `bootstrap/app.php`
  that runs only in local/testing, targets the existing two demo tenants,
  deterministically creates about 200 categories and 20000 items per tenant
  without running from `DemoSeeder` or `make fresh`, batches raw inserts with
  bounded memory, and provides an explicit purge option that removes only rows
  carrying its own generated marker while leaving DemoSeeder and human rows
  intact. Add focused command tests for guard, idempotency, purge safety,
  tenant/branch/name/money shape, and generated counts. Result: added
  nullable `load_test_key` markers and purge indexes to Menu tables; registered
  `menu:load-test-data`; added `make artisan ARGS="..."`; documented command
  usage in `README.md`; command defaults to exactly 200 category rows and
  20000 item rows per demo tenant, splits Arat rows across both demo branches,
  refuses outside local/testing, fails instead of duplicating generated rows,
  and `--purge-generated` / `--purge-only` delete only rows marked by this
  command. Verification so far: Pint pass (`211 files`), PHPStan pass
  (`[OK] No errors`), SQLite Pest pass (`163 passed / 5 skipped /
  1235 assertions`).
- [x] Stage 1.11C-scale.3: read-model proof and index decision refresh. Keep
  the existing PostgreSQL `pg_trgm` JSONB expression-index strategy unless
  measurement proves a gap; add any needed additive/reversible index migration
  for the measured query shapes; update `docs/DECISIONS.md` with a dated entry
  for the backend-scale slice; strengthen read-model/API tests for tenant and
  branch isolation in search plus bounded query count for the paginated item
  list. Result: added `BrowseMenuItems` as a coherent Application facade for
  category/default item browsing and global search, switched the API controller
  to that facade, recorded the 2026-07-24 JSONB trigram/search-index decision,
  extended search coverage with a matching tenant-B row that must not leak,
  and added a bounded-query-count assertion for `PaginateMenuItems` while
  touching each eager-loaded category. No additional search migration was
  needed before measurement. Verification: Pint pass (`212 files`), PHPStan
  pass (`[OK] No errors`), SQLite Pest pass (`164 passed / 5 skipped /
  1264 assertions`).
- [x] Stage 1.11C-scale.4: PostgreSQL load run and measurements. Run
  `make fresh`, execute the new load command in the container with purge/load
  options, capture per-tenant row counts, run `EXPLAIN (ANALYZE, BUFFERS)` for
  first page, deep page, global search hit, global search miss, and category
  panel/list queries, fix any sequential-scan slow path with query/index shape
  rather than caching, and record timings/index evidence in this worklog.
  Result: `make fresh` passed on PostgreSQL through
  `2026_07_24_010000_add_menu_category_panel_index`; final load command
  `make artisan ARGS="menu:load-test-data --purge-generated"` generated
  `menu_categories=400`, `menu_items=40000` in `9.747s`. Row counts per demo
  tenant were `arat-riverside: 200 categories / 20000 items` and
  `northstar-bistro: 200 categories / 20000 items`. The first category-panel
  measurement used the marker purge index as a tenant-leading index rather
  than the parent-panel index; to keep the intended tenant/parent/deleted/sort
  access path available at larger tenant counts, an additive
  `menu_categories_tenant_parent_deleted_sort_id_idx` migration was added and
  covered by `MenuSchemaTest`.

  Stage 1.11C-scale.4 measurements on local PostgreSQL after the final fresh
  load:

  | Query | Scale | Time | Index evidence |
  |---|---:|---:|---|
  | Category item first page (`tenant_id=1`, `branch_id=1`, `category_id=48`, `limit 25`) | 400 categories / 40000 items | `Execution Time: 2.259 ms` | `Index Scan using menu_items_tenant_branch_category_deleted_active_sort_id_idx` |
  | Category item deep page (`offset 50`) | same | `Execution Time: 0.957 ms` | `Index Scan using menu_items_tenant_branch_category_deleted_active_sort_id_idx` |
  | Global search hit (`LIKE '%1-9999%'`) | same | `Execution Time: 2.026 ms` | `Bitmap Index Scan on menu_items_translated_name_trgm_idx` |
  | Global search miss (`LIKE '%zz-no-match-zz%'`) | same | `Execution Time: 0.810 ms` | `Bitmap Index Scan on menu_items_translated_name_trgm_idx` |
  | Category panel roots (`tenant_id=1`, roots, `limit 25`) | same | `Execution Time: 0.386 ms` | `Index Scan using menu_categories_parent_id_idx`; no sequential scan |
  Superseded on 2026-07-24: the category-panel measurement above used only
  the two demo tenants and is unrepresentative for the panel-index decision.
  The representative combined-dataset measurements in
  Stage 1.11C-scale-review2 supersede it.
- [x] Stage 1.11C-scale.5: final verification and scoped commit/push. Run
  `make pint`, `make stan`, `make test`, `make fresh`, any required focused
  PostgreSQL checks, and a full branch diff review; commit each logical step
  with its worklog update and push the feature branch only if all required
  verification is green. Do not create or merge a PR. Result: final local
  gates are green: Pint pass (`213 files`), PHPStan pass (`[OK] No errors`),
  SQLite Pest pass (`164 passed / 5 skipped / 1265 assertions`), PostgreSQL
  Tenancy/RLS pass (`21 passed / 73 assertions`), and `make fresh` pass.
  The final fresh PostgreSQL load command generated `400` categories and
  `40000` items in `9.747s`; corrected per-tenant row counts were
  `arat-riverside: 200 categories / 20000 items` and
  `northstar-bistro: 200 categories / 20000 items`. EXPLAIN checks used
  indexes for category item pagination, global search hit/miss, and category
  panel roots with no sequential scans. No asset-affecting files changed, so
  `npm run build` / `make build` was not required. Full branch diff reviewed;
  branch push remains pending.
- [x] Stage 1.11C-scale-review.1: review-correction baseline and worklog plan.
  Re-read the required sources, confirm branch/worktree state, inspect the API
  and Livewire Menu read paths, inspect `menu:seed-load`, inspect marker-column
  exposure, and write this corrective plan before code. Result: branch
  `phase-2-stage-1.11c-menu-scale` is clean and tracks
  `origin/phase-2-stage-1.11c-menu-scale`; commits `672a43a`, `7bc39f6`,
  `27297e8`, `74e0220`, and `942598e` are present. API item listing uses
  `BrowseMenuItems`; Livewire `MenuIndex` still calls
  `ResolveMenuCategorySelection`, `PaginateMenuCategories`,
  `PaginateMenuItems`, and `SearchMenuItems` directly. `menu:seed-load
  --fresh` currently expresses schema recreation and is local-database guarded,
  while `--force` bypasses the top-level environment guard before the
  schema-recreation assertion; marker columns are not currently fillable,
  cast, appended, or returned by `MenuItemResource`, but need explicit tests.
- [x] Stage 1.11C-scale-review.2: real read-path query-count proof and
  Livewire characterization. Add tests proving query count is identical for
  small and large page sizes on both `BrowseMenuItems` category/search modes
  and full `MenuIndex` Livewire category/search renders. Add behavior
  characterization tests for current Livewire semantics without migrating it to
  `BrowseMenuItems`: global search ignores selected category, clearing search
  returns to selected category context, default category selection, empty
  category empty-list rendering, and superadmin-only archive controls. Record
  the current two-read-path state and deferred convergence decision in
  `docs/DECISIONS.md`. Result: added exact-count invariance tests:
  `BrowseMenuItems` category mode `5` vs `30` rows both execute `6` queries;
  `BrowseMenuItems` search mode `5` vs `30` rows both execute `3` queries;
  `MenuIndex` category render small vs full page both execute `10` queries;
  `MenuIndex` search render small vs full page both execute `13` queries.
  Added Livewire characterization coverage for search ignoring selected
  category, clearing search back to selected category context, default
  category selection, empty subcategory empty-list rendering, and existing
  superadmin-only archive behavior coverage. Recorded the split-read-path
  decision and deferred convergence target in `docs/DECISIONS.md`. Verification:
  initial `make test` exposed a stale Livewire test query-string setup issue;
  after clearing query params explicitly, `make test` passed (`169 passed /
  5 skipped / 1375 assertions`) and `make pint` passed (`213 files`).
- [x] Stage 1.11C-scale-review.3: `menu:seed-load` safety and command
  separation. Determine precisely what `--force` bypasses before changing code;
  make environment and local-database guards unconditional if needed; require
  confirmation for schema recreation unless explicitly suppressed by `--force`;
  add focused command tests; update README and `docs/DECISIONS.md` to clarify
  `menu:seed-load` versus `menu:load-test-data`. Result: before this change,
  `--force` bypassed the top-level local/testing environment guard for ordinary
  `menu:seed-load` runs, but did not bypass the schema-recreation
  local-database assertion; non-interactive `--fresh` schema recreation could
  skip confirmation because confirmation was only asked for interactive input.
  After this change, local/testing and local-database guards are unconditional
  and `--force` only suppresses the schema-recreation confirmation. Added
  command safety tests proving `--force` cannot run outside local/testing,
  schema recreation is blocked for non-local database config even with
  `--force`, and confirmation is required for local schema recreation without
  `--force`. README now distinguishes `menu:load-test-data` demo-tenant loads
  from `menu:seed-load` synthetic-tenant loads; `docs/DECISIONS.md` records the
  command separation and safety contract. Verification: `make test` passed
  (`172 passed / 5 skipped / 1382 assertions`) and `make pint` passed
  (`214 files`).
- [x] Stage 1.11C-scale-review.4: marker-column containment. Add tests proving
  `load_test_key` is not fillable, not cast, not appended, not present in API
  resources, not present in serialized model output, and not rendered in Menu
  views. Record the dev/test-tooling-only marker-column decision and exit path
  in `docs/DECISIONS.md`. Result: added `#[Hidden(['load_test_key'])]` to
  `MenuCategory` and `MenuItem` so hydrated marker columns do not leak through
  model serialization. Added marker exposure tests proving marker columns are
  not fillable, not cast, not appended, absent from serialized model output,
  absent from `MenuItemResource`/API responses, and not rendered by the Menu
  screen. Recorded the dev/test-tooling-only marker decision and exit path in
  `docs/DECISIONS.md`. Verification: `make pint` passed (`215 files`, one
  style issue fixed in the new test) and `make test` passed (`175 passed /
  5 skipped / 1400 assertions`).
- [x] Stage 1.11C-scale-review.5: realistic panel measurement and index
  decision. Use `menu:seed-load` without schema recreation to create at least
  about 200 local load tenants, run `ANALYZE`, capture the exact SQL generated
  by `PaginateMenuCategories`, measure `EXPLAIN (ANALYZE, BUFFERS)` before and
  after the panel-index decision, and either keep the unmerged composite index
  with plan evidence or remove its migration/test assertion and record why in
  `docs/DECISIONS.md`. Result: `make fresh` succeeded, then
  `menu:seed-load --mode=production-like --restaurants=200 --categories=1
  --subcategories=1 --items=1 --batch=5000` ran without `--fresh` and inserted
  200 load tenants, 200 roots, 200 subcategories, and 200 items
  (`copy_load_seconds=54.098`). Per-tenant aggregate counts were stable:
  200 load tenants, min/max roots `1/1`, subcategories `1/1`, items `1/1`.
  Captured the exact active panel SQL from `PaginateMenuCategories` via
  interactive Tinker/`DB::listen`: root count, root page select ordered by
  `sort_order`, localized `hy/ru/en` lower expression, `id`, and child eager
  load for the selected root ids. After `ANALYZE`, before the decision, root
  count used `Index Only Scan using
  menu_categories_tenant_parent_deleted_sort_id_idx` (`0.196 ms`), root page
  select used `Index Scan using
  menu_categories_tenant_parent_deleted_sort_id_idx` (`0.195 ms`), and child
  eager-load used `Index Scan using menu_categories_parent_id_idx`
  (`0.087 ms`). The composite panel index is kept. After the keep decision and
  repeat `ANALYZE`, the same plan nodes were used: root count `0.203 ms`, root
  page select `0.100 ms`, child eager-load `0.078 ms`. Recorded the
  panel-index decision in `docs/DECISIONS.md`. Superseded on 2026-07-24: this
  dataset had high tenant cardinality but only 400 category rows and 200 item
  rows, so it did not validate the panel path under many tenants, many roots
  per tenant, and a large item table simultaneously.
- [x] Stage 1.11C-scale-review.6: HTTP smoke, final gates, diff review, and
  push. Run `make pint`, `make stan`, `make test`, `make fresh`, PostgreSQL
  tenant-isolation, the multi-tenant load/counts, `ANALYZE`/EXPLAIN evidence,
  HTTP smoke for `/admin/menu` and `/api/v1/menu-items`, `git diff --check`,
  and full branch diff review. Commit the final worklog handoff and push the
  feature branch only if green; do not create or merge a PR. Result: final
  gates passed. `make pint`: `PASS 215 files`. `make stan`: `121/121`, no
  errors. `make test`: `175 passed / 5 skipped / 1400 assertions`.
  `make fresh`: migrations and `DemoSeeder` completed successfully, including
  `2026_07_24_010000_add_menu_category_panel_index`. PostgreSQL tenancy suite:
  `21 passed / 73 assertions`. Final multi-tenant load after `make fresh`:
  `menu:seed-load --mode=production-like --restaurants=200 --categories=1
  --subcategories=1 --items=1 --batch=5000`, no `--fresh`,
  `copy_load_seconds=53.008`, verified `tenants=200`, `roots=200`,
  `subcategories=200`, `menu_categories=400`, `menu_items=200`; per-load-tenant
  min/max roots `1/1`, subcategories `1/1`, items `1/1`. Final `ANALYZE` plus
  exact category-panel EXPLAIN used `Index Only Scan using
  menu_categories_tenant_parent_deleted_sort_id_idx` for root count
  (`0.100 ms`), `Index Scan using
  menu_categories_tenant_parent_deleted_sort_id_idx` for root page select
  (`0.250 ms`), and `Index Scan using menu_categories_parent_id_idx` for child
  eager-load (`0.080 ms`). Superseded on 2026-07-24: these final panel numbers
  were measured on the same undersized 400-category/200-item synthetic state
  and are not the deciding evidence for the index. HTTP smoke against
  `http://127.0.0.1:8080` passed:
  manager and owner logins returned final `200`; manager `/admin/menu`,
  category, page-forward, search-hit, search-miss, and clear-back requests all
  returned `200` with expected Armenian content markers; manager did not see
  archive controls, owner did; API category paging, global-search hit ignoring
  `category_id=3`, search miss, and clear-back category request all returned
  `200` with expected pagination/data markers. `git diff --check` passed. Full
  branch diff versus `origin/main` reviewed: 19 files, Menu scale/read actions,
  command safety, README/docs/worklog, additive migrations, and Menu tests only;
  no `docs/BLUEPRINT.md`, `template/`, frontend asset, or unrelated module
  changes.
- [x] Stage 1.11C-scale-review2.1: representative combined-dataset plan and
  loader choice. Re-read the source docs, verify the clean pushed branch at
  `9f65491`, inspect `PaginateMenuCategories`, `PaginateMenuItems`,
  `SearchMenuItems`, `menu:load-test-data`, and `menu:seed-load`, then decide
  the cheapest safe local-only dataset build that contains both high tenant
  cardinality and high item-table scale in one database state. Result: branch
  `phase-2-stage-1.11c-menu-scale` was clean and tracking origin at
  `9f65491`. No blueprint/source conflict found. The earlier 2026-07-24
  panel-index decision is accepted as needing supersession because it measured
  high item counts and high tenant cardinality on separate or undersized
  database states. Chosen build: after one `make fresh`, run
  `menu:load-test-data --purge-generated` for the two demo tenants to keep two
  tenants at about 20000 items each, then run `menu:seed-load` without
  `--fresh` for 100 synthetic load tenants with 100 roots each, one subcategory
  per root, and enough items per subcategory to make the single local database
  state reach at least 100 tenants, 10000 roots, and 200000 total menu items.
- [x] Stage 1.11C-scale-review2.2: build and count the combined local dataset.
  After one `make fresh`, run `menu:load-test-data --purge-generated` for the
  two demo tenants and run `menu:seed-load` without `--fresh` for 100 synthetic
  load tenants with at least 100 roots each and enough generated items to make
  total `menu_items >= 200000`. Record exact wall clock time, per-table counts,
  per-tenant min/max counts, and any scale ceiling or bottleneck if the target
  cannot be reached. Result: target reached without reduction on the local dev
  PostgreSQL database. `make fresh` passed first. `menu:load-test-data
  --purge-generated` generated the two demo tenants in `9.713s` command time
  (`11s` wall clock): generated rows were `menu_categories=400` and
  `menu_items=40000`, with `arat-riverside` and `northstar-bistro` each at
  `200` generated categories and `20000` generated items. Then
  `menu:seed-load --mode=production-like --restaurants=100 --categories=100
  --subcategories=1 --items=16 --batch=20000` ran without `--fresh` and
  inserted 100 load tenants, 100 branches, 100 load-manager users, 10000 roots,
  10000 subcategories, and 160000 items in `63.075s` command time (`64s` wall
  clock). Combined per-table counts in the single DB state: `tenants=102`,
  `branches=103`, `users=108`, `menu_categories=20407`, root categories
  `10042`, subcategories `10365`, and `menu_items=200007`. Per-tenant counts:
  demo tenants min/max roots `21/21`, subcategories `182/183`, items
  `20002/20005` including DemoSeeder rows; load tenants min/max roots
  `100/100`, subcategories `100/100`, items `1600/1600`. The bottleneck was
  PostgreSQL COPY plus live index maintenance during item insertion; no scale
  reduction was needed.
- [x] Stage 1.11C-scale-review2.3: capture real SQL and re-measure with/without
  the panel index. Use `DB::listen` around the actual Application actions on
  the combined dataset to capture panel, item pagination, and global search SQL.
  Run `ANALYZE`, then `EXPLAIN (ANALYZE, BUFFERS)` for panel root count, panel
  root page select, panel child eager-load, category item first page, category
  item deep page, global search hit, and global search miss. Drop the local
  `menu_categories_tenant_parent_deleted_sort_id_idx` only for the comparison
  measurement, recreate it immediately, and record plan nodes, estimates,
  actual rows, timings, and restored index state. Result: captured real SQL via
  interactive Tinker/`DB::listen` on the combined dataset. Panel action used
  synthetic tenant `3` and emitted root count, root page select, and child
  eager-load for root ids `408..432`. Item/search actions used Arat tenant `1`,
  branch `1`, category `56` (`54` active items), search hit `1-9999`, and
  search miss `zz-no-match-zz`; the real miss path emitted only the count query
  because Laravel skips the page select when total is zero. After `ANALYZE`,
  with the composite index present: panel root count used `Index Only Scan
  using menu_categories_tenant_parent_deleted_sort_id_idx`, estimate/actual
  `98/100`, `0.261 ms`; panel root page used `Bitmap Heap Scan` plus
  `Bitmap Index Scan on menu_categories_tenant_parent_deleted_sort_id_idx`,
  estimate/actual `98/100` before limit and returned `25`, `0.753 ms`; child
  eager-load used `Index Scan using menu_categories_parent_id_idx`,
  estimate/actual `1/25`, `0.319 ms`; category item first page used
  `Index Scan using menu_items_tenant_branch_category_deleted_sort_id_idx`,
  estimate/actual `1/54` before limit and returned `25`, `0.704 ms`; category
  item deep page used the same index, estimate/actual `1/54` before offset and
  returned `4`, `0.440 ms`; global search hit used `Bitmap Heap Scan` with
  `BitmapAnd` over `menu_items_tenant_branch_load_test_key_idx` and
  `menu_items_translated_name_trgm_idx`, estimate/actual `1/1`, `1.890 ms`;
  global search miss count used `Bitmap Heap Scan` with the same bitmap indexes,
  estimate/actual `1/0`, `1.673 ms`. Dropped the local composite index only for
  comparison, ran `ANALYZE menu_categories`, then measured panel again:
  root count used the existing `Index Only Scan using
  menu_categories_tenant_parent_deleted_active_sort_id_idx`, estimate/actual
  `98/100`, `0.141 ms`; root page used `Bitmap Heap Scan` plus
  `Bitmap Index Scan on menu_categories_tenant_parent_deleted_active_sort_id_idx`,
  estimate/actual `98/100` before limit and returned `25`, `0.674 ms`; child
  eager-load still used `menu_categories_parent_id_idx`, estimate/actual
  `1/25`, `0.267 ms`. Recreated the local
  `menu_categories_tenant_parent_deleted_sort_id_idx` immediately and confirmed
  it exists before making the branch-level decision.
- [x] Stage 1.11C-scale-review2.4: supersede the panel-index decision. Based
  only on the representative same-dataset with/without evidence, either keep
  the composite index or remove the unmerged migration and schema-test
  assertion. Add a dated `docs/DECISIONS.md` entry that explicitly supersedes
  the 2026-07-24 keep decision, explains why the earlier evidence was
  inadequate, and records the deciding plan evidence. Keep earlier worklog
  numbers but mark them as unrepresentative and superseded. Result: removed
  the unmerged `2026_07_24_010000_add_menu_category_panel_index` migration and
  the schema assertion for `menu_categories_tenant_parent_deleted_sort_id_idx`.
  Added a superseding `docs/DECISIONS.md` entry: on the representative
  single-state dataset, the new index used `0.261 ms` root count and
  `0.753 ms` root page plans, while existing
  `menu_categories_tenant_parent_deleted_active_sort_id_idx` used `0.141 ms`
  and `0.674 ms` for the same statements after dropping the new index locally.
  The new index is therefore redundant and removed from this branch.
  Verification: `make test` passed (`175 passed / 5 skipped /
  1399 assertions`); the assertion count decreased by one only because the
  owner-approved schema assertion for the removed unmerged index was deleted
  with that migration, while total test count stayed at `175`.
- [x] Stage 1.11C-scale-review2.5: corrected paging smoke. Re-run HTTP smoke
  without host PHP against `/admin/menu` and `/api/v1/menu-items` using a
  category with more than one page of items; prove page 2 is non-empty and
  contains a different item set than page 1, with real status codes and
  distinguishing rendered/API markers. Result: used Arat category `56`
  (`Թարմ ոսպ բաժին arat-riverside 1-9`) with `54` active branch-1 items.
  Curl smoke against `http://127.0.0.1:8080` used the real login form:
  `GET /login` returned `200` and manager `POST /login` returned `302`.
  `/admin/menu?category=56` returned `200`, rendered page-1 marker
  `Այգու պանիր ուտեստ arat-riverside 1-9`, and did not render page-2 marker
  `Շուկայի պանիր ուտեստ arat-riverside 1-4689`.
  `/admin/menu?category=56&item_page=2` returned `200`, rendered the page-2
  marker, and did not render the page-1 marker. `/api/v1/menu-items?
  category_id=56&per_page=25&page=1` returned `200` with `25` item ids,
  including `id=16` and excluding `id=4696`; page `2` returned `200` with
  `25` item ids, including `id=4696` and excluding `id=16`.
- [x] Stage 1.11C-scale-review2.6: final gates, branch diff review, worklog
  handoff, and push. Run `make pint`, `make stan`, `make test`, `make fresh`,
  PostgreSQL tenant-isolation, the combined dataset load/counts, final
  `ANALYZE`/EXPLAIN evidence, corrected paging smoke, `git diff --check`, and
  full branch diff review versus `origin/main`. Commit each logical step with
  its worklog update and push the feature branch only if green; do not create
  or merge a PR. Result: final local gates passed. `make pint`: `PASS
  214 files`. `make stan`: `121/121`, `[OK] No errors`. `make test`:
  `175 passed / 5 skipped / 1399 assertions`. `make fresh`: passed; migration
  output ends at `2026_07_24_000000_add_load_test_markers_to_menu_tables` and
  no longer includes the removed panel-index migration. PostgreSQL tenancy
  suite: `21 passed / 73 assertions`.

  Final combined dataset after `make fresh`: `menu:load-test-data
  --purge-generated` produced `400` generated categories and `40000` generated
  items in `10.157s` command time (`11.93s` wall clock). `menu:seed-load
  --mode=production-like --restaurants=100 --categories=100 --subcategories=1
  --items=16 --batch=20000` ran without `--fresh`, inserted 100 load tenants,
  100 branches, 100 users, 10000 roots, 10000 subcategories, and 160000 items;
  `copy_load_seconds=59.643`, wall clock `1:02.42`. Combined counts:
  `tenants=102`, `branches=103`, `users=108`, `menu_categories=20407`,
  `root_categories=10042`, `subcategories=10365`, `menu_items=200007`. Demo
  tenants min/max roots `21/21`, subcategories `182/183`, items
  `20002/20005`; load tenants min/max roots `100/100`, subcategories
  `100/100`, items `1600/1600`.

  Final `ANALYZE` plus real captured action SQL measurements:

  | Query | Without removed index | With temporary removed index | Estimate / actual |
  |---|---:|---:|---:|
  | Panel root count | `Index Only Scan using menu_categories_tenant_parent_deleted_active_sort_id_idx`, `0.188 ms` | `Index Only Scan using menu_categories_tenant_parent_deleted_sort_id_idx`, `0.136 ms` | `98 / 100` |
  | Panel root page | `Bitmap Heap Scan` + active index bitmap, `0.792 ms` | `Bitmap Heap Scan` + removed-index bitmap, `0.690 ms` | `98 / 100`, returned `25` |
  | Panel child eager-load | `Index Scan using menu_categories_parent_id_idx`, `0.245 ms` | same index, `0.259 ms` | `1 / 25` |
  | Category item first page | `Index Scan using menu_items_tenant_branch_category_deleted_sort_id_idx`, `0.707 ms` | n/a | `1 / 54`, returned `25` |
  | Category item deep page | same item index, `0.450 ms` | n/a | `1 / 54`, returned `4` |
  | Global search hit | `Bitmap Heap Scan` with `BitmapAnd` over `menu_items_tenant_branch_load_test_key_idx` and `menu_items_translated_name_trgm_idx`, `0.885 ms` | n/a | `1 / 1` |
  | Global search miss count | same bitmap indexes, `0.751 ms` | n/a | `1 / 0` |

  The temporary `menu_categories_tenant_parent_deleted_sort_id_idx` was dropped
  after measurement and `to_regclass(...)` returned null, restoring the local
  DB to the current branch state. The deciding evidence remains removal: the
  temporary index provides only about `0.052 ms` to `0.102 ms` on the root
  count/page statements and does not improve child eager-load; the existing
  active tenant/parent/deleted index already prevents sequential scans.

  Final corrected paging smoke against `http://127.0.0.1:8080`: `GET /login`
  `200`, manager `POST /login` `302`; `/admin/menu?category=56` `200` with
  page-1 marker `Այգու պանիր ուտեստ arat-riverside 1-9` and without page-2
  marker `Շուկայի պանիր ուտեստ arat-riverside 1-4689`;
  `/admin/menu?category=56&item_page=2` `200` with the page-2 marker and
  without the page-1 marker; `/api/v1/menu-items?category_id=56&per_page=25&
  page=1` `200` with `25` ids including `16` and excluding `4696`; page `2`
  `200` with `25` ids including `4696` and excluding `16`. `git diff --check`
  passed. Full branch diff versus `origin/main` reviewed: 17 files, limited to
  Menu scale/read tooling, command safety, README, decisions/worklog, Makefile
  artisan target, load-marker migration, and Menu tests; no `docs/BLUEPRINT.md`,
  `template/`, assets, or unrelated modules changed.
- [x] Stage 1.11C-converge.1: baseline, path comparison, and convergence plan.
  Verify repository state; fetch `origin`; check whether `27f5650` is merged to
  `origin/main`; create the session branch from the correct base; inspect
  `BrowseMenuItems`, `MenuIndex`, the API controller, and the read actions it
  bypasses; read the characterization and query-count tests; write the
  comparison and plan before implementation. Result: baseline is stacked
  because `27f5650` is not an ancestor of `origin/main` (`origin/main` is
  `fb909c0`); branch `phase-2-stage-1.11c-menu-read-convergence` was created
  from `origin/phase-2-stage-1.11c-menu-scale` at `27f5650`.

  Path comparison before convergence:

  | Aspect | API / `BrowseMenuItems` behavior before | Livewire `MenuIndex` behavior before | Converged target |
  |---|---|---|---|
  | Default category when none requested | If no search/category is supplied, `BrowseMenuItems` calls `ResolveMenuCategorySelection(null, active)` and paginates the resolved subcategory; if none exists it returns an empty paginator. | `MenuIndex` calls the same resolver, writes the resolved id back to URL state, and renders the selected-category item page or empty category state. | Facade returns the resolved selected category plus item page so API keeps item pagination and UI keeps URL normalization. |
  | Search ignores selected category | Non-empty `search` calls `SearchMenuItems`; `category_id` is ignored. | Non-empty `search` renders `SearchMenuItems` results even when `category` is selected; the selected category remains in component state. | Same search path through facade; selected category is retained for UI state but not applied to search. |
  | Search cleared | Stateless API: a later request without `search` falls back to `category_id` or default selection. | `clearSearch()` empties `search`, keeps `category`, resets `searchPage`, and shows category items. | UI calls facade with retained category and null search; API remains stateless. |
  | Empty category | Explicit empty subcategory produces an empty paginator with normal metadata. | Empty selected subcategory renders `menu.empty.no_items_title`, not an error. | Same empty paginator returned from facade for both adapters. |
  | Root/foreign category URL state | API `category_id` is a strict item filter; root, foreign-tenant, or foreign-branch category filters return 404 through `ResolveMenuItemListCategory`. | UI `category` is selection state; a root with selectable children normalizes to the first child, an empty root becomes unselected, and a foreign selected id falls back to the current tenant default. | Preserve both adapter contracts explicitly inside one facade: API strict item filter, UI selection-state mode. |
  | Archived visibility | API controller always passes `archiveMode='active'`; unsupported `show_archived` input is ignored by validation/request accessors. | `MenuIndex` clamps archive mode to active for non-superadmins; superadmins may use `archived` and `all`. | Archive-mode normalization moves into the facade call so both adapters rely on one read-path gate while keeping API active-only and UI superadmin-only archive visibility. |
  | Ordering | Category and search item paths order by `sort_order`, localized lower `hy/ru/en` expression, then `id`. | Uses the same underlying `PaginateMenuItems` and `SearchMenuItems` ordering. | Unchanged. |
  | Page size | API defaults to `25` and clamps `per_page` to `50`; empty facade paginator uses the requested/clamped value. | UI category/items/search pages use fixed `25`. | Unchanged adapter inputs; facade delegates to existing paginated actions. |
  | Pagination metadata | API serializes `LengthAwarePaginator` metadata through `ApiResponse::pagination`. | UI renders paginator totals/current pages through the Blade partials. | Paginator instances remain the source of metadata; API response shape stays unchanged. |

  Go/no-go: proceed. The only adapter-contract difference is strict API
  category filtering versus UI category selection-state normalization; both are
  already pinned by tests and will be preserved inside the single facade rather
  than resolved as a product behavior change.
- [x] Stage 1.11C-converge.2: extend `BrowseMenuItems` for both adapters.
  Add only the data needed for the Livewire screen: category panel paginator,
  resolved selected category, selected-category item paginator, optional global
  search paginator, normalized archive mode, and search-mode flag. Keep the
  facade as a thin Application layer over `ResolveMenuCategorySelection`,
  `ResolveMenuItemListCategory`, `PaginateMenuCategories`, `PaginateMenuItems`,
  and `SearchMenuItems`; keep API response behavior unchanged. Result: added
  `BrowseMenuItemsResult` plus `BrowseMenuItems::forMenuIndex()` and
  `BrowseMenuItems::selectedCategoryForMenuIndex()`. The API `__invoke()`
  path and response paginator remain unchanged; the new UI mode preserves the
  UI selection-state contract while centralizing archive-mode normalization in
  the facade.
- [x] Stage 1.11C-converge.3: switch `MenuIndex` to the facade. Remove direct
  read-action dependencies from `render()` and `selectCategory()`; keep the
  component as state binding, mutation dispatch, and presentation only. Preserve
  current URL state, archive visibility, selected-category behavior, and search
  clearing without markup or styling changes. Result: `MenuIndex` now obtains
  category panel data, selected category, item paginator, and global search
  paginator through `BrowseMenuItems`; `rg` confirms no direct calls to
  `ResolveMenuCategorySelection`, `PaginateMenuCategories`,
  `PaginateMenuItems`, or `SearchMenuItems` remain in the component. No Blade,
  CSS, route, or API-resource markup changed.
- [x] Stage 1.11C-converge.4: prove behavior and query-count invariance. Run
  the existing characterization tests unmodified, preserve API tests, and keep
  query-count invariance for `BrowseMenuItems` category/search and full
  `MenuIndex` category/search renders. Record before/after absolute counts and
  justify or reduce any increases. Result: characterization tests passed
  unmodified and API tests stayed green. Query-count before -> after:
  `BrowseMenuItems` category `6 -> 6`, `BrowseMenuItems` search `3 -> 3`,
  `MenuIndex` category render `10 -> 10`, `MenuIndex` search render
  `13 -> 10`. The Livewire search count decreased because the converged facade
  no longer calculates the hidden selected-category item page while global
  search results are visible. Verification: `make test` passed (`175 passed /
  5 skipped / 1399 assertions`).
- [x] Stage 1.11C-converge.5: decisions, gotchas, and final verification. Add a
  dated `docs/DECISIONS.md` entry superseding the temporary split-read-path
  entry; add the PostgreSQL measurement caveats to Gotchas; run `make pint`,
  `make stan`, `make test`, `make fresh`, `make tenant-isolation-pgsql`, HTTP
  smoke for `/admin/menu` and `/api/v1/menu-items`, `git diff --check`, and a
  full branch diff review; push the branch only if green. Result: added the
  2026-07-24 `Menu adapters use one BrowseMenuItems read path` decision,
  explicitly superseding the temporary split decision. Added Gotchas for the
  dev-only marker index appearing in local PostgreSQL search `BitmapAnd` plans
  and the 200k-row selected-category estimate drift. Final verification:
  `make pint` passed (`PASS 215 files`), `make stan` passed (`122/122`,
  `[OK] No errors`), `make test` passed (`175 passed / 5 skipped /
  1399 assertions`), `make fresh` passed with migrations through
  `2026_07_24_000000_add_load_test_markers_to_menu_tables` and `DemoSeeder`
  complete, and `make tenant-isolation-pgsql` passed (`21 passed /
  73 assertions`). For HTTP smoke, `menu:load-test-data --purge-generated`
  generated deterministic page-2 data (`400` generated categories,
  `40000` generated items, `9.885s`; demo totals including seed rows:
  `arat-riverside 204 categories / 20005 items`, `northstar-bistro
  203 categories / 20002 items`). Manager smoke: login form `200`, login
  submit `302`; `/admin/menu?category=48` `200` with page-1 marker
  `Թարմ ոսպ ուտեստ arat-riverside 1-1` and no page-2 marker; `/admin/menu?
  category=48&item_page=2` `200` with distinct page-2 marker
  `Այգու պանիր ուտեստ arat-riverside 1-4861`; `/admin/menu?category=49&
  q=4861` `200` showed the category-48 search hit, proving search ignores the
  selected category; `/admin/menu?category=49&q=zz-no-match-zz` `200` showed
  `Համընկնող դիրքեր չկան։`; `/admin/menu?category=49` `200` showed
  `Այգու ոսպ ուտեստ arat-riverside 1-2`, proving cleared search returns to
  category context; manager archive-mode filter marker was absent. API smoke:
  `/api/v1/menu-items?category_id=48&per_page=25&page=1` `200` contained
  `id=8` and `current_page=1`; page `2` `200` contained `id=4868` and
  `current_page=2`; `/api/v1/menu-items?category_id=49&search=4861` `200`
  contained `id=4868`, proving search ignores `category_id`; miss search
  returned `data=[]` and `total=0`. Owner smoke: login form `200`, login submit
  `302`, `/admin/menu?category=48` `200` with archive-mode filter marker
  present. No asset-affecting files changed, so `make build` / `npm run build`
  was not required. `git diff --check` passed; full branch diff versus
  `origin/main` reviewed as 19 files limited to Menu scale tooling/read path,
  tests, README, Makefile artisan target, and decisions/worklog.
- [x] Stage 1.16.1: preconditions, branch, and read-only inspection. Verify a
  clean worktree, fetch `origin/main`, confirm Stage 1.14 ancestry and
  `routes/api.php`, fast-forward `main`, create
  `phase-2-stage-1.16-audit-log`, inspect the existing Menu mutating actions,
  logging context/redaction plumbing, tenancy model/RLS patterns, and relevant
  tests, then list mutating actions and transaction boundaries before code.
  Result: started from clean `main`, fetched `origin/main` at
  `016bf5dc773ccb66aa774758118f56c6195b6f1a`, confirmed Stage 1.14 head
  `3ea0e46` is an ancestor and `routes/api.php` exists, branch
  `phase-2-stage-1.16-audit-log` was created, and inspection found Menu
  mutating actions for category/item create, update, archive, restore,
  force-delete, item activity toggle, image replace, and image remove.
  Existing category archive/restore/force-delete used `DB::transaction()`;
  the other mutating actions did not.
- [x] Stage 1.16.2: audit persistence foundation. Add the additive reversible
  `audit_logs` migration with tenant scope, indexes, PostgreSQL RLS policy,
  append-only database triggers, the tenant-scoped append-only Eloquent model,
  the `app/Support/Audit` recorder contract/implementation, and container
  binding. Result: added `audit_logs` with tenant/date/action/target/branch
  indexes, PostgreSQL `audit_logs_tenant_isolation` RLS policy, SQLite and
  PostgreSQL append-only triggers, tenant-scoped `AuditLog`, `AuditRecorder`,
  `EloquentAuditRecorder`, and the `AppServiceProvider` binding.
- [x] Stage 1.16.3: Menu audit wiring. Reuse `RecordsMenuAction` so existing
  structured INFO/WARNING logs remain intact, and wire exactly one audit row
  per Menu mutation for category/item create, update, archive, restore,
  force-delete, activity toggle, image replace, and image remove. Cascades will
  be represented in the parent target's audit JSON with descendant id/count
  metadata rather than per-row loops. Result: wired all listed Menu mutating
  actions; cascade archive/restore/force-delete records one parent-target audit
  row with category level, marker category id, and affected descendant counts
  rather than one row per descendant.
- [x] Stage 1.16.4: automated coverage. Add tests for correct audit context and
  payload, transaction rollback/commit behavior, append-only enforcement,
  redaction, Eloquent tenant scope, and PostgreSQL RLS coverage in the
  Tenancy suite. Result: added `tests/Feature/Audit/AuditLogTest.php` for
  context/payload, rollback and audit-failure atomicity, append-only model and
  DB trigger enforcement, redaction, Eloquent tenant scoping, and full Menu
  action-string coverage; extended `TenantIsolationTest` with PostgreSQL RLS
  coverage for `audit_logs`.
- [ ] Stage 1.16.5: documentation, verification, and release. Record the
  ADR-009 implementation decision in `docs/DECISIONS.md`, keep this worklog
  current, run `make pint`, `make stan`, `make test`,
  `make tenant-isolation-pgsql`, `make fresh`, perform the required audit-row
  smoke after `make fresh`, commit scoped paths, push, open a PR, and merge
  only after exact-head green CI. Result so far: `docs/DECISIONS.md` records
  the audit placement/append-only/transaction/device/action/redaction decision;
  local gates passed with Pint pass, PHPStan pass, SQLite Pest `140 passed / 3
  skipped / 979 assertions`, PostgreSQL Tenancy `19 passed / 67 assertions`,
  and `make fresh` pass. HTTP smoke after `make fresh` logged in as
  `manager@arat.test`, archived item `3`, and observed exactly one audit row
  for correlation id `audit-smoke-archive-1`:
  `menu.item.archived|menu_item|3|audit-smoke-archive-1`. Commit, push, PR,
  exact-head CI, and merge are still pending.
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
  Verification note from Block 3 on 2026-07-23: this is not implemented yet.
  The code has item status badges plus edit/archive/restore/force-delete UI,
  but no `ToggleMenuItemActivity` Application action, no Livewire toggle
  method, and no inline activity toggle button.
  Block 4.2 update on 2026-07-23: inline activity toggle is implemented by
  `ToggleMenuItemActivity` plus `MenuIndex::toggleItemActivity()`, using the
  same `menu.items.manage` permission as item edit. The active-list UX is
  intentionally consistent with archive: when `showInactive=false`, a
  deactivated item disappears from the current list after the Livewire refresh;
  users can see/reactivate it by enabling the existing inactive filter. The
  Livewire test harness does not convert the action's `ModelNotFoundException`
  into `assertStatus(404)`, so tenant-isolation coverage stays exception-level
  while the HTTP endpoint convention remains 404. The wider row-overflow
  archive-control part is still not implemented.
- [ ] Stage 1.11.10.5 (Part C): context-preserving forms and searchable
  category combobox. Replace the item form's all-options category select with
  a Livewire + Alpine server-search combobox, prefill category from
  `?category=`, preserve return context after save/cancel (`category`,
  category page, item page, global search query, inactive/archive filters),
  and keep image upload behavior from Part B intact. Add `hy`/`ru`/`en`
  translations and Pest coverage for create/edit context and searchable
  category selection. Verification note from Block 3 on 2026-07-23: this was
  not implemented in Part C. Part D owner re-scope on 2026-07-23 split the
  missing work: shared searchable combobox first, context-preserving
  save/cancel remains pending.
- [ ] Stage 1.11 Part D: finish deferred Menu UX. The shared JSON endpoint +
  shared Alpine searchable combobox for category `parent_id` and item
  `category_id` was implemented in Part C follow-up commits
  `8cca014`/`d9856a2`/`8956f77`. Remaining Part D work is listed in the
  carry-over section below, especially context-preserving save/cancel and
  final tablet polish.
- [ ] Stage 1.11.10.6 (Part C): load-data command and performance fixes. Add
  an artisan command outside `DemoSeeder` to generate about 200 categories and
  20000 items per tenant with deterministic localized names, prices, active
  distribution, sort values, and placeholder-image coverage compatible with
  Part B. Run `EXPLAIN` on category panel, selected-category page, global
  search, inactive filter, and archive paths; fix slow paths with indexes, not
  cache.
- [x] Stage 1.11.11 (Part C): final local verification, load smoke, and owner
  PR handoff. Run `make fresh`, run the load-data command, capture curl/HTTP
  timings for Menu index, category panel pagination/search, category
  switching, global item search, item pagination, create-item write latency,
  and activity toggle write latency on the loaded PostgreSQL DB, then run
  `make pint`, `make stan`, and `make test`. Result: local measurements and
  gates were recorded below; push/CI/PR are explicitly owner-owned.
- [x] Stage 1.12.1: branch baseline. Verify clean local `main`, fetch
  `origin`, confirm local `main` is not behind `origin/main`, confirm the
  target branch name is unused, then create exactly one branch
  `phase-2-stage-1.12-branch-authorization` from `main`. Result: working tree
  was clean on `main` at `33a1cec`, `git fetch origin` succeeded, local
  `main` and `origin/main` were in sync (`0 0` ahead/behind), the target branch
  name was unused, and the single authorized branch was created.
- [x] Stage 1.12.2: branch context middleware hardening. Update
  `ResolveBranch` so production ignores `X-Branch-ID`, non-production keeps
  header -> session -> first assigned branch candidate order, authenticated
  users may resolve only assigned branch ids through `UserDirectory`, stale
  session ids are forgotten with one WARNING log, unauthorized explicit header
  ids abort 404, tenant ownership remains 404, unauthenticated non-production
  header workflow still works, and assigned branch ids are resolved at most
  once per request. Add the branch policy decision to `docs/DECISIONS.md`.
  Result: `ResolveBranch` now ignores production branch headers, authorizes
  authenticated branch candidates against `UserDirectory::assignedBranchIds()`,
  forgets stale session branch ids with stable WARNING logs, preserves
  tenant-scoped `Branch` lookup before setting context/session, and the branch
  header/assignment policy is recorded in `docs/DECISIONS.md`.
- [x] Stage 1.12.3: focused branch resolution tests. Add focused Tenancy
  feature coverage for production header ignoring, authenticated authorized
  and unauthorized headers, foreign-tenant headers, stale session discard and
  fallback, session cleanup, and warning log context without changing existing
  `TenantIsolationTest` or `AdminSwitchingTest` behavior. Result: added
  `tests/Feature/Tenancy/BranchContextResolutionTest.php`; existing
  `TenantIsolationTest.php` and `AdminSwitchingTest.php` stayed unchanged.
- [x] Stage 1.12.4: verification and handoff. Run `make pint`, `make stan`,
  `make test`, then the required focused Docker Pest command for
  `tests/Feature/Tenancy` plus `tests/Feature/AdminSwitchingTest.php`; update
  this worklog with checked-off result lines, gotchas, and final next steps.
  Result: final gates green: Pint pass (`157 files`, one style issue fixed),
  PHPStan pass (`[OK] No errors`), Pest pass (`122 passed / 2 skipped /
  848 assertions`), and focused Tenancy/AdminSwitching Pest pass (`17 passed /
  2 skipped / 69 assertions`).
- [x] Stage 1.12.5 follow-up: privileged clean-database trgm migration probe.
  Using only the local Docker PostgreSQL service, create temporary database
  `smartrest_ext_probe`, confirm `pg_trgm` is absent, run migrations against
  that database as the privileged local role while using the Stage 1.13 trgm
  migration contents, verify `pg_trgm` plus both trigram indexes exist, and
  drop the probe database unconditionally. Do not modify Stage 1.13 files or
  any persistent database. Result: `smartrest_ext_probe` was created with
  `pg_trgm` absent (`0 rows`), the application migrations completed
  successfully in an ephemeral app copy using the Stage 1.13 migration file,
  `pg_trgm` plus `menu_categories_translated_name_trgm_idx` and
  `menu_items_translated_name_trgm_idx` existed afterwards, and the probe
  database was dropped and confirmed gone (`0 rows`).
- [x] Stage 1.12.6 follow-up: production branch-context regression coverage.
  Add exactly two tests to `BranchContextResolutionTest`: authenticated
  production requests ignore a valid header in favor of an existing assigned
  session branch, and authenticated production requests validate a stale
  unassigned session branch, forget it, and fall back to the first assigned
  branch. Existing tests stay unchanged. Result: added only those two tests;
  a disposable container-copy experiment with the production header guard
  removed failed the new header test because the header branch overwrote the
  session branch (`branch_id`/`session_branch_id` became `2` instead of `1`).
- [x] Stage 1.12.7 follow-up: final verification, commit, and push. Run
  `make pint`, `make stan`, and `make test`; record the Part A result, the
  reason for the two new tests, and backlog gotchas for
  `MenuSeedLoadCommand`'s `CREATE EXTENSION IF NOT EXISTS pg_trgm` assumption
  plus the `actions/checkout@v4` Node.js 20 deprecation warning; commit on
  `phase-2-stage-1.12-branch-authorization` and push the branch without force.
  Result: final gates green: Pint pass (`157 files`), PHPStan pass
  (`[OK] No errors`), and Pest pass (`124 passed / 2 skipped /
  854 assertions`), which is two tests above the Stage 1.12 baseline.
- [x] Stage 1.12.8: merge Stage 1.13 main into Stage 1.12 and verify
  PostgreSQL branch-context coverage. After Stage 1.13 was merged to
  `origin/main` at `714cb9a`, fast-forward local `main`, merge `origin/main`
  into `phase-2-stage-1.12-branch-authorization` with a normal merge commit,
  resolve documentation conflicts only, run `make pint`, `make stan`,
  `make test`, and `make tenant-isolation-pgsql`, then push and open/merge the
  Stage 1.12 PR after green CI. Result: merge conflict occurred only in
  `docs/worklog/PHASE-2.md`; `docs/DECISIONS.md` auto-merged and was reordered
  chronologically. Worklog resolution kept Stage 1.12, Stage 1.12 follow-ups,
  and Stage 1.13 entries, with one `Next steps` section. Local gates green:
  Pint pass (`157 files`), PHPStan pass (`[OK] No errors`), SQLite Pest pass
  (`124 passed / 2 skipped / 854 assertions`), and PostgreSQL Tenancy pass
  (`18 passed / 64 assertions`). The Stage 1.12 branch-context tests passed
  under the unprivileged PostgreSQL role.
- [x] Stage 1.13.1: branch baseline and failure inspection. Preserve local
  Stage 1.12 branch `phase-2-stage-1.12-branch-authorization` at `e5bace8`
  unchanged, switch to clean `main`, fetch `origin`, confirm local `main` is
  not behind `origin/main`, create exactly one branch
  `phase-2-stage-1.13-pgsql-ci-repair` from `main`, then inspect CI,
  migration, Makefile, compose/test config, and pgsql-conditional tests.
  Result: Stage 1.12 was clean at `e5bace8` and left untouched; local `main`
  at `33a1cec` was clean and in sync with `origin/main` (`0 0` ahead/behind);
  the Stage 1.13 branch was created from `main`; inspection confirmed the CI
  pgsql job runs as unprivileged `smartrest_app` but the trgm migration tries
  to create `pg_trgm`, while local `make test` forces SQLite and skips that
  migration branch plus the two PostgreSQL-only RLS tests.
- [x] Stage 1.13.2: extension provisioning and migration tolerance. Update the
  CI PostgreSQL preparation step to create `pg_trgm` with the privileged
  `smartrest` role before tests run, keep `smartrest_app` unprivileged, and
  make the existing trgm migration skip `CREATE EXTENSION` when `pg_trgm` is
  already present while preserving the same indexes and `down()` behavior.
  Record the privileged-extension provisioning decision in `docs/DECISIONS.md`.
  Result: CI now creates `pg_trgm` as privileged `smartrest`, explicitly keeps
  `smartrest_app` non-superuser/non-`BYPASSRLS`, and still runs pgsql Pest as
  `smartrest_app`; the trgm migration checks `pg_extension` before
  `CREATE EXTENSION` and leaves the two GIN index statements and `down()`
  unchanged; `docs/DECISIONS.md` records the privileged-extension policy.
- [x] Stage 1.13.3: local pgsql tenancy Make target. Add a self-contained
  Makefile target that starts local PostgreSQL if needed, creates a separate
  local test database and unprivileged `NOBYPASSRLS` test role idempotently,
  pre-provisions `pg_trgm` as the privileged local role, and runs the whole
  `tests/Feature/Tenancy` directory through Pest as that unprivileged role
  without touching the development `smartrest` database. Result: added
  `make tenant-isolation-pgsql`, using `smartrest_test_local` and
  `smartrest_app_test`; first run exposed and fixed a Makefile dollar-quoting
  bug in role creation, then the target created the role/database/extension
  idempotently and ran the Tenancy directory on PostgreSQL.
- [x] Stage 1.13.4: establish real PostgreSQL Tenancy result and CI width.
  Run the new local pgsql tenancy target, record exact pass/fail/skip counts
  and any remaining failures, correct the stale "3 known RLS/BYPASSRLS
  failures" worklog claim, and widen the CI pgsql job to
  `tests/Feature/Tenancy` only if the whole directory passes locally. Result:
  local unprivileged PostgreSQL Tenancy run passed completely (`11 passed /
  0 failed / 0 skipped / 42 assertions`), so the stale "3 known
  RLS/BYPASSRLS failures" claim is superseded and the CI pgsql job was widened
  from `tests/Feature/Tenancy/TenantIsolationTest.php` to the whole
  `tests/Feature/Tenancy` directory.
- [x] Stage 1.13.5: verification and handoff. Run `make pint`, `make stan`,
  `make test`, and the new pgsql tenancy target; update the worklog with
  checked-off result lines, gotchas, and a zero-context next action. Do not
  push; CI proof remains owner-owned. Result: final gates green: Pint pass
  (`156 files`), PHPStan pass (`[OK] No errors`), SQLite Pest pass
  (`117 passed / 2 skipped / 832 assertions`), and PostgreSQL Tenancy pass
  (`11 passed / 42 assertions`). CI itself was not verified because pushing is
  owner-owned.
- [x] Stage 1.15.1: merge backlog and codify PR autonomy policy. Verify Stage
  1.13 CI on exact head `9259bb7`, merge PR #11 to `main`, confirm new
  `main` `714cb9a` green; then merge `origin/main` into Stage 1.12, verify
  locally with `make pint`, `make stan`, `make test`, and
  `make tenant-isolation-pgsql`, push, verify CI on exact head `7744883`, and
  merge PR #12 to `main` at `65da625`. Update `AGENTS.md` and
  `docs/DECISIONS.md` so the Codex PR/merge policy matches the owner-approved
  autonomy model. Result: Stage 1.13 and Stage 1.12 are merged to `main`;
  policy documentation is updated and local gates are green: Pint pass
  (`157 files`), PHPStan pass (`[OK] No errors`), and Pest pass (`124 passed /
  2 skipped / 854 assertions`). PR #13 merged to `main` at `36478fc` after
  exact-head green CI; post-merge `main` CI was green.
- [x] Stage 1.14.1: API routing and shared JSON contract foundation. Register
  `routes/api.php`, add `/api/v1` routing with session-auth-compatible API
  middleware, tenant and branch resolution, and a conservative `throttle:60,1`
  rate limit. Implement shared success/error envelopes that include the
  existing request correlation id and locale, and map API authentication,
  authorization, not-found, validation, and Menu domain errors to the
  Blueprint section 6 JSON format. Result: added `routes/api.php`, registered
  API routing, reused session/web middleware plus `auth`, `tenant`, `branch`,
  `can:menu.items.manage`, and `throttle:60,1`, and added shared API response
  and exception rendering helpers. `AttachLogContext` is prioritized before
  auth so API 401 responses keep the supplied request id.
- [x] Stage 1.14.2: read-only Menu items API adapter. Add
  `GET /api/v1/menu-items` as a thin Menu module controller/resource path that
  validates only `page`, bounded `per_page`, optional `category_id`, and
  optional `search`, authorizes `menu.items.manage`, reuses
  `PaginateMenuItems` / `SearchMenuItems`, and serializes tenant/branch-scoped
  active non-archived items with integer money fields only. Result: added the
  Menu API request/controller/resource plus an Application category guard for
  explicit category filters; no controller Eloquent queries were added.
- [x] Stage 1.14.3: API contract coverage and documentation. Add feature tests
  proving unauthenticated JSON 401, permission 403, success envelope,
  tenant/branch/category isolation, archive exclusion, pagination/clamping,
  validation errors, MenuDomainException JSON rendering, money shape, request
  id propagation, and unchanged Blade behavior. Record the session-auth/token
  deferral, page pagination fields, and rate limit in `docs/DECISIONS.md`.
  Result: added `tests/Feature/Menu/MenuItemsApiTest.php` with 9 API-focused
  tests and recorded the session-auth/token deferral, page pagination fields,
  and `throttle:60,1` rate limit in `docs/DECISIONS.md`.
- [x] Stage 1.14.4: verification, smoke, PR, and merge. Run `make pint`,
  `make stan`, `make test`, `make tenant-isolation-pgsql`, and `make fresh`,
  then curl-smoke authenticated and unauthenticated `/api/v1/menu-items`.
  Commit with documentation, push, open a PR, and merge only after exact-head
  CI is fully green. Result: local gates green: Pint pass (`168 files`),
  PHPStan pass (`[OK] No errors`), SQLite Pest pass (`133 passed / 2 skipped /
  944 assertions`), PostgreSQL Tenancy pass (`18 passed / 64 assertions`),
  and `make fresh` pass. Curl smoke after fresh: demo manager login returned
  `302` to `/admin`; authenticated `GET /api/v1/menu-items` returned `200`
  with top-level keys `data,meta,errors`; unauthenticated request returned
  `401` with code `auth.unauthenticated`. PR CI and merge are pending.

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
- 2026-07-23: Stage 1.12 branch authorization hardening complete locally.
  Branch `phase-2-stage-1.12-branch-authorization` was created from clean
  `main` at `33a1cec` after `origin/main` sync was verified. `ResolveBranch`
  now ignores production branch headers, requires authenticated branch
  candidates to be assigned through the Identity `UserDirectory` contract,
  discards stale unassigned session branch ids with WARNING logs, and preserves
  tenant-scoped branch ownership checks. Local gates green: Pint pass, PHPStan
  pass, Pest 122 passed / 2 skipped / 848 assertions, focused
  Tenancy/AdminSwitching Pest 17 passed / 2 skipped / 69 assertions. Nothing
  was pushed; PR remains owner-owned.
- 2026-07-23: Stage 1.12 follow-up complete locally. The clean privileged
  `pg_trgm` migration path was proven on throwaway database
  `smartrest_ext_probe` using the Stage 1.13 migration file in an ephemeral
  app copy, then the database was dropped and confirmed gone. Added
  authenticated production branch-context tests for header-ignore/session
  precedence and stale-session assignment fallback; no production code change
  was needed. A disposable container-copy experiment with the production guard
  removed failed the new header test as expected. Final gates green: Pint pass
  (`157 files`), PHPStan pass (`[OK] No errors`), and Pest pass
  (`124 passed / 2 skipped / 854 assertions`).
- 2026-07-23: Stage 1.13 PostgreSQL CI repair complete locally. Branch
  `phase-2-stage-1.13-pgsql-ci-repair` was created from clean `main` at
  `33a1cec` after preserving local Stage 1.12 branch
  `phase-2-stage-1.12-branch-authorization` at `e5bace8` unchanged. CI now
  pre-provisions `pg_trgm` as privileged `smartrest`, keeps `smartrest_app`
  non-superuser/non-`BYPASSRLS`, and runs pgsql Pest as `smartrest_app`; the
  trgm migration tolerates pre-provisioned extensions by checking
  `pg_extension`; `make tenant-isolation-pgsql` runs the whole Tenancy feature
  directory against local PostgreSQL using separate `smartrest_test_local` DB
  and unprivileged `smartrest_app_test` role. Real local pgsql Tenancy result:
  `11 passed / 0 failed / 0 skipped / 42 assertions`, so the stale "3 known
  RLS/BYPASSRLS failures" claim is corrected to zero current failures under
  the unprivileged pgsql path. Final local gates green: Pint pass, PHPStan
  pass, SQLite Pest 117 passed / 2 skipped / 832 assertions, pgsql Tenancy
  Pest 11 passed / 42 assertions. Nothing was pushed; PR remains owner-owned.
- 2026-07-23: Stage 1.12 post-Stage 1.13 merge verification complete locally.
  Stage 1.13 was already merged to `origin/main` at `714cb9a`; merging that
  main into Stage 1.12 produced only the expected worklog documentation
  conflict. Resolution kept both Stage 1.12 and Stage 1.13 histories, ordered
  Stage 1.12 and follow-ups before Stage 1.13, kept `docs/DECISIONS.md` in
  chronological order, and left one `Next steps` section. Local gates green:
  Pint pass (`157 files`), PHPStan pass (`[OK] No errors`), SQLite Pest pass
  (`124 passed / 2 skipped / 854 assertions`), and PostgreSQL Tenancy pass
  (`18 passed / 64 assertions`) under `smartrest_app_test` with
  `NOBYPASSRLS`.
- 2026-07-23: Stage 1.15 merge backlog cleared. Stage 1.13 PR #11 merged
  after exact-head CI passed for `9259bb7`, producing `main` `714cb9a` with
  green post-merge CI. Stage 1.12 was merged with that `main`, verified locally
  including PostgreSQL Tenancy (`18 passed / 64 assertions`) under the
  unprivileged `smartrest_app_test` role, then PR #12 merged after exact-head
  CI passed for `7744883`, producing `main` `65da625` with green post-merge
  CI. The policy documentation branch updated `AGENTS.md`,
  `docs/DECISIONS.md`, and this worklog; local gates are green: Pint pass
  (`157 files`), PHPStan pass (`[OK] No errors`), and Pest pass (`124 passed /
  2 skipped / 854 assertions`). PR #13 merged to `main` at `36478fc` after
  exact-head green CI, and post-merge `main` CI was green.
- 2026-07-23: Stage 1.14 API foundation complete locally. Added `/api/v1`
  routing and read-only `GET /api/v1/menu-items` for session-authenticated
  admin users, using the same tenant/branch middleware and
  `menu.items.manage` permission as the admin UI. The endpoint reuses
  `ResolveMenuCategorySelection`, `PaginateMenuItems`, and `SearchMenuItems`,
  returns the Blueprint JSON envelope, page pagination metadata, integer
  `price_minor` plus `currency`, localized names for the request locale, and
  no image storage paths. This closes the Blueprint Phase 1 DoD API item once
  the PR is merged. Local gates green: Pint pass (`168 files`), PHPStan pass
  (`[OK] No errors`), SQLite Pest pass (`133 passed / 2 skipped /
  944 assertions`), PostgreSQL Tenancy pass (`18 passed / 64 assertions`),
  `make fresh` pass, and curl smoke pass for authenticated `200` and
  unauthenticated JSON `401`.

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
- Resolved by CI maintenance: GitHub Actions previously emitted non-blocking
  Node.js 20 deprecation annotations for `actions/checkout@v4` /
  `actions/setup-node@v4`; `phase-2-ci-node24-actions` bumps both GitHub
  JavaScript actions to `@v7` with `node24` manifests.
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
- During Stage 1.11 Part C backend-scale measurements, PostgreSQL chose the
  older `menu_categories_parent_id_idx` for the tiny two-tenant root category
  panel query even after a tenant/parent/deleted/sort index was available.
  The measured path is still an index scan with no sequential scan; keep the
  tenant/parent composite index because it gives the planner a tenant-leading
  path when root categories grow across many tenants.
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
- 2026-07-23 Stage 1.13 correction: the "3 known RLS/BYPASSRLS failures"
  statement is superseded for Tenancy coverage. Running
  `tests/Feature/Tenancy` through `make tenant-isolation-pgsql` against
  local PostgreSQL as unprivileged `smartrest_app_test` produced
  `11 passed / 0 failed / 0 skipped / 42 assertions`. The remaining historical
  issue is not a Tenancy test failure; it was that local and CI paths did not
  consistently run PostgreSQL with an unprivileged role after `pg_trgm` was
  added.
- Stage 1.13 failure cause: `make test` and `phpunit.xml` force SQLite, so the
  `CREATE EXTENSION pg_trgm` migration branch and the two RLS assertions in
  `TenantIsolationTest` were not exercised locally. The GitHub Actions pgsql
  job did exercise them, but ran migrations as `smartrest_app`, which did not
  have database-level extension creation privileges.
- `tests/Feature/Menu/MenuSchemaTest.php` has two PostgreSQL-only checks that
  return early on non-pgsql drivers: category tree FK/check constraints and
  trigram expression index definitions. SQLite `make test` keeps those tests
  green but does not prove PostgreSQL constraint/index behavior.
- `app/Console/Commands/MenuSeedLoadCommand.php` also issues
  `CREATE EXTENSION IF NOT EXISTS pg_trgm` during optional trgm index rebuild.
  It is outside Stage 1.13 scope because this task is limited to CI/local
  Tenancy reproducibility and the existing migration path; revisit the load
  command if it is later run under an unprivileged role.
- The first local run of the new `make tenant-isolation-pgsql` target failed
  before tests because Makefile escaping turned a `DO $$` role-creation block
  into a shell PID. The target now uses idempotent `SELECT ... | grep ||
  CREATE ROLE` provisioning instead.
- Step B intentionally allows an inactive root category to be used as a
  subcategory parent. Parent validation is tenant-scoped and non-trashed, but
  not `active`, so disabling a root does not block maintaining the menu
  structure under it.
- Step D removed the temporary root `sort_order=100` accommodations from demo
  seed data and test fixtures. Default Menu selection is now resolved through
  the tree-aware `ResolveMenuCategorySelection` action rather than flat row
  ordering.
- Step D keeps category-panel search scoped to selectable subcategory names.
  Parent-name search is intentionally deferred so the PostgreSQL indexed
  localized-name search expression remains untouched in this step.
- Future task: add parent-name category-panel search so matching a root also
  returns its selectable subcategories. Do this carefully around
  `FiltersLocalizedNames` and PostgreSQL trgm expression-index compatibility;
  the current helper is table-name based and not alias-aware for self-joins.
- 2026-07-22 load-test follow-up: two `menu:seed-load` attempts partially
  committed parent rows but never loaded items. The 5-restaurant run left 5
  tenants, 100 roots, and 500 subcategories, then failed on the first
  `menu_items` insert with PostgreSQL bind-parameter limit
  `number of parameters must be between 0 and 65535` (`10000 rows * 15
  columns = 150000 params`). The 300-restaurant run left 300 tenants and 6000
  roots, then failed on the first subcategory insert for the same reason
  (`10000 rows * 9 columns = 90000 params`). `menu_items = 0`, so no valid
  15M performance measurement exists yet; any `trgm` vs `tenant_id` planner
  conclusion is withdrawn as unmeasured until the loader is fixed and rerun.
- 2026-07-23 curl login verification got `419 Page Expired` only when curl
  tried to use a cookie jar under `storage/`, which is owned by `www-data` in
  this Docker setup and was not writable by the host shell. The login form and
  application session flow are valid: manually carrying the `Set-Cookie`
  header from `GET /login` into `POST /login` returned `302` to `/admin`, and
  `GET /admin` returned `200` for
  `load-manager+20260723071232-1-restaurant-1@smartrest.test`.
- 2026-07-23 final verification found that root categories with no
  subcategories were invisible on `/admin/menu` because the category panel was
  paginated by selectable subcategory rows (`parent_id is not null`) and roots
  were rendered only as parents of rows on the current page. The local fix
  changes category panel pagination to root-first: `category_page` now counts
  root-category pages, not subcategory pages. This is a breaking change for
  saved `/admin/menu?category_page=N` URLs from earlier Part C builds.

## INCIDENT: 2026-07-23 Step G `--fresh` hang on dirty local DB
- Earlier `menu:seed-load --mode=production-like --restaurants=5
  --drop-rebuild-trgm --fresh` cleanup hung for 430 seconds before
  interruption.
- Cause: the dirty local DB contained 5M accumulated rows from run
  `20260722135401-1`, which had not cleaned up after itself, and
  `menu_categories.parent_id` / `menu_categories.archived_with_category_id`
  had no standalone FK indexes. Composite indexes with leading `tenant_id` did
  not cover FK checks on the referenced FK columns.
- Consequence: the interrupted PostgreSQL backend kept a relation lock for
  more than 14 minutes and blocked the next `migrate:fresh`; the blocked drop
  phase took 3m26s until the stale backend was terminated.
- Fixes: add standalone FK indexes for Menu FK columns, make production-like
  `--fresh` recreate the guarded local schema instead of doing O(n) row
  deletes, set PostgreSQL `lock_timeout = 10s` for loader sessions, and verify
  loaded counts against the current run before reporting success.
- Important: all Stage 1.11 load/performance measurements before the
  2026-07-23 clean guarded run were taken on a dirty DB and are invalid.

## Stage 1.11.11 load measurements: 2026-07-23
- Dataset check before timings: local PostgreSQL DB still contained the load
  dataset, so it was not reloaded. `tenants`: 5 rows with `seed_source='load'`
  plus 2 demo rows with `seed_source is null`; `menu_items=250007`,
  `menu_categories=607`.
- Quality gate before timings: `make pint` passed (`153 files`), `make stan`
  passed (`[OK] No errors`), and `make test` passed (`94 passed / 2 skipped /
  716 assertions`).
- Measurement method: authenticated as
  `load-manager+20260723071232-1-restaurant-1@smartrest.test` against
  `http://localhost:8080`. GET scenarios used `curl -w '%{http_code}
  %{time_total}'`. Livewire scenarios fetched a fresh component snapshot, then
  measured only the real `POST /livewire-b02a6282/update` request with
  `Content-Type: application/json` and `X-Livewire: true`. Each scenario used
  one warm-up request excluded from the median, followed by 3 measured runs;
  the recorded value is the median of those 3 runs.
- Superseded on 2026-07-23: the original Menu index/category-panel timings
  below were taken before root-first category pagination and are no longer the
  current baseline for those four scenarios. Old values: menu index
  `0.130298s`, category pagination `0.109441s`, category search `0.106006s`,
  category switching `0.120919s`.
- Root-first retest on 2026-07-23: current local DB had 5 load tenants plus
  2 demo tenants, `menu_items=250012`, `menu_categories=608` (`103` roots,
  `505` subcategories). Method stayed curl-based: one warm-up excluded, then
  3 measured runs, median recorded. Livewire POST used `_token` from
  `livewireScriptConfig`, `Content-Type: application/json`, and
  `X-Livewire: true`. The first root-first retest was invalidated because
  `category-actions.blade.php` lazily loaded each subcategory parent and added
  100 extra SQL queries on the load tenant page. The clean numbers below were
  taken after assigning each loaded subcategory's `parent` relation from its
  already-loaded root with Eloquent `setRelation()`.
- Menu index first load after root-first pagination: `GET /admin/menu`;
  warm-up `200 0.187630`; measured `200 0.193979`, `200 0.183121`,
  `200 0.185654`; median `0.185654s`.
- Category panel pagination after root-first pagination: Livewire page
  `GET /admin/menu`, payload `updates={}`, `call=nextCategoryPage[]`; warm-up
  `200 0.091634`; measured `200 0.107154`, `200 0.138955`,
  `200 0.090559`; median `0.107154s`.
- Category panel search after root-first pagination: Livewire page
  `GET /admin/menu`, payload `updates={"categorySearch":"Trout"}`, no call;
  warm-up `200 0.134520`; measured `200 0.123804`, `200 0.121593`,
  `200 0.122639`; median `0.122639s`.
- Category switching after root-first pagination: Livewire page
  `GET /admin/menu`, payload `updates={}`, `call=selectCategory[109]`;
  warm-up `200 0.155920`; measured `200 0.156799`, `200 0.158919`,
  `200 0.167151`; median `0.158919s`.
- N+1 check after root-first pagination: `PaginateMenuCategories(perPage: 25,
  page: 1)` on tenant 3 executed 3 SQL queries total (`count`, root page,
  eager-load subcategories with `where parent_id in (...)`). No per-root query
  loop was observed after the `setRelation()` fix. Current load tenant page 1
  loads 21 roots plus 100 subcategories (`121` category models). At a typical
  25 roots * 15 subcategories profile, one page could render up to 400
  category models. `CATEGORY_PAGE_SIZE=25` is left unchanged for Part C; if
  manual tablet review reports sluggishness, reduce the root page size to 15
  in Part D (`15 + 225 = 240` category models at the same profile).
- Global item search, frequent prefix: Livewire page `GET /admin/menu`,
  payload `updates={"search":"Fresh"}`, no call. SQL precheck for tenant 3
  returned 3125 matching `menu_items`. Warm-up `200 0.185770`; measured
  `200 0.191471`, `200 0.181143`, `200 0.187575`; median `0.187575s`.
- Global item search, rare string: Livewire page `GET /admin/menu`, payload
  `updates={"search":"Dish 1-1-1-499"}`, no call. SQL precheck for tenant 3
  returned 1 matching `menu_item` (`id=506`). Warm-up `200 0.138756`;
  measured `200 0.133024`, `200 0.130020`, `200 0.142413`; median
  `0.133024s`.
- Global item search, no matches: Livewire page `GET /admin/menu`, payload
  `updates={"search":"zzzz-no-match"}`, no call. SQL precheck returned 0
  matches. Warm-up `200 0.095980`; measured `200 0.097918`,
  `200 0.100064`, `200 0.095406`; median `0.097918s`.
- Item pagination inside subcategory: Livewire page
  `GET /admin/menu?category=108`, payload `updates={}`,
  `call=nextItemPage[]`; warm-up `200 0.105977`; measured `200 0.111848`,
  `200 0.117280`, `200 0.131414`; median `0.117280s`.
- Create-item write latency: Livewire page `GET /admin/menu/items/create`,
  payload sets `category_id=108`, `name_hy`, `name_ru`, `name_en`,
  empty descriptions, `price_major=1234`, `currency=AMD`,
  `sort_order=999999`, `active=true`, then `call=save[]`; warm-up
  `200 0.064592`; measured `200 0.072050`, `200 0.064232`,
  `200 0.065597`; median `0.065597s`.
- Activity-toggle write latency: Livewire page
  `GET /admin/menu?category=108&show_inactive=1`, payload `updates={}`,
  `call=toggleItemActivity[8]`; warm-up `200 0.124836`; measured
  `200 0.116878`, `200 0.119222`, `200 0.120593`; median `0.119222s`.
- Timing conclusion: no obvious >250k-row regression was observed in these
  local curl measurements. The slowest median was the frequent global search
  prefix `Fresh` at `0.187575s`.
- Final count note after write-latency probes: `menu_items=250012` because one
  pre-measurement Livewire save smoke plus the create-item warm-up and 3
  measured create-item writes inserted 5 measurement rows. `menu_categories`
  remained `607`.
- `menu_items` size: heap `140 MB`, indexes `121 MB`, total `261 MB`.
  `menu_items` index sizes: `menu_items_translated_name_trgm_idx=40 MB`;
  `menu_items_tenant_branch_category_deleted_active_sort_id_idx=20 MB`;
  `menu_items_tenant_branch_deleted_active_sort_id_idx=20 MB`;
  `menu_items_tenant_branch_category_deleted_sort_id_idx=16 MB`;
  `menu_items_tenant_category_deleted_sort_idx=12 MB`;
  `menu_items_pkey=5496 kB`; `menu_items_category_id_idx=2016 kB`;
  `menu_items_tenant_branch_deleted_active_idx=1680 kB`;
  `menu_items_tenant_id_index=1632 kB`;
  `menu_items_tenant_archive_marker_deleted_idx=1632 kB`;
  `menu_items_branch_id_idx=1624 kB`.
- `menu_categories` size: heap `160 kB`, indexes `440 kB`, total `632 kB`.
  `menu_categories` index sizes:
  `menu_categories_translated_name_trgm_idx=192 kB`;
  `menu_categories_tenant_parent_deleted_active_sort_id_idx=72 kB`;
  `menu_categories_tenant_deleted_sort_id_idx=64 kB`;
  `menu_categories_pkey=32 kB`;
  `menu_categories_parent_id_idx=16 kB`;
  `menu_categories_archived_with_category_id_idx=16 kB`;
  `menu_categories_tenant_id_index=16 kB`;
  `menu_categories_tenant_archive_marker_deleted_idx=16 kB`;
  `menu_categories_tenant_deleted_active_sort_idx=16 kB`.

## Open questions
- Livewire test harness does not convert the inline action's
  `ModelNotFoundException` into `assertStatus(404)`: tenant isolation for
  Livewire methods is covered at exception/action level, but not yet proven at
  HTTP endpoint level. Add a real HTTP Livewire request test or another
  endpoint-level check before relying on `assertStatus(404)` for Livewire
  methods.
- Category hierarchy UI gap found on 2026-07-23: the backend request/action
  path accepted `parent_id`, but `resources/views/modules/menu/category-form.blade.php`
  did not render a parent selector, so managers could not create subcategories
  through the visible UI. A related bug allowed a PUT request that omitted
  `parent_id` to treat an existing subcategory as root because
  `MenuCategoryRequest::parentId()` returned `null` for both "missing field"
  and "root selected". The gap was missed because tests mostly called
  Application actions directly, and the one HTTP subcategory create test
  manually injected `parent_id` into the payload instead of proving the HTML
  form rendered and submitted that field.
- Stage 1.11 Part D carry-over: context-preserving save/cancel remains
  unimplemented for Menu forms. Search/page/filter/category URL context is not
  yet preserved across create/edit/cancel flows.
- Stage 1.11 Part D carry-over: archive/restore/force-delete controls still
  render inline in the category/item cards. Move them into an overflow menu
  before polishing the final tablet UI.
- Stage 1.11 Part D carry-over: there are no automated tests for the
  `menu:seed-load` loader itself; only post-load manual/count verification has
  been recorded.
- Stage 1.11 Part D carry-over: `CATEGORY_PAGE_SIZE=25` is retained for the
  root-first panel. At a 25-root x 15-child profile this can render up to 400
  category models; reduce to 15 roots/page if tablet review complains.
- Stage 1.11 Part D carry-over: moving a category with items is currently
  blocked by the domain action. This may be too strict for real restaurant
  maintenance; owner decision required before changing the rule.
- Final cleanup note: `ListMenuCategories` had no live calls in `app/` after
  the searchable-combobox and root-first changes. Only the class itself,
  direct tests, and historical worklog mentions remained, so the dead action
  and direct test assertions were removed during finalization.
- Stage 1.12 PHPStan required an explicit `@param-out` annotation for the
  by-reference per-request assigned-branch cache in `ResolveBranch`; without
  it PHPStan flagged the nullable by-ref type as unused.
- Stage 1.12 follow-up backlog only:
  `app/Console/Commands/MenuSeedLoadCommand.php` still issues
  `CREATE EXTENSION IF NOT EXISTS pg_trgm` during optional trgm index rebuild
  and will fail if that command is later run under an unprivileged role. This
  was intentionally not changed in the Stage 1.12 follow-up.
- Stage 1.12 follow-up historical note: GitHub Actions still emitted the
  Node.js 20 deprecation warning for `actions/checkout@v4`; this was
  intentionally recorded only and not fixed in the Stage 1.12 follow-up.
  `phase-2-ci-node24-actions` resolves it with a later workflow-only bump.
- Stage 1.14 API gotcha: the API route uses the session guard, so the route
  must include session-compatible middleware. `AttachLogContext` must also run
  before auth failures; otherwise unauthenticated API 401 responses generate a
  fallback request id instead of preserving the supplied `X-Request-Id`.
- Stage 1.14 API gotcha: `menu_categories` is tenant-scoped but not
  branch-owned, while `menu_items` is branch-owned. The explicit
  `category_id` API guard therefore treats a category that only has visible
  items in another branch as 404 so the filter cannot be used to infer another
  branch's menu structure.
- Stage 1.16 audit gotcha: `MenuDemoSeeder` calls the Menu item image
  Application actions, so `make fresh` now creates seed audit rows for image
  replace/remove actions with actor null and generated correlation ids. Manual
  audit smoke must isolate the user action by a unique `X-Request-Id` instead
  of assuming the table starts empty after seeding.
- Stage 1.17 legacy hall screen findings: `template/rooms-hall.html` shows
  halls as branch operating areas grouped under future floor containers, with a
  localized hall name, a color picker, a preparation-place selector, sortable
  hall cards, and edit/archive-style destructive controls. `rooms-tables.html`
  and `rooms-hall-planning.html` use hall names and colors as board filters and
  panel backgrounds. `rooms-hall-tables.html` is table-specific and implies no
  hall columns beyond the selected hall relationship. The resulting Stage 1.17
  `halls` schema is branch-owned (`tenant_id`, `branch_id`), localized name
  JSON, `color`, `sort_order`, `active`, soft delete, timestamps, tenant/branch
  indexes, and PostgreSQL RLS. `floor_id`, floor-plan geometry, preparation
  place FK, table counts, table shapes, commission fields, and table metadata
  are intentionally deferred because the Blueprint has no current tables/floors
  schema and Stage 1.17 is Halls only.
- Stage 1.17 smoke gotcha: curl scratch files could not be written under
  `storage/framework/testing` because that directory is owned by `www-data` in
  the local stack. The HTTP smoke used a temporary repo-root `.codex-smoke/`
  directory instead and removed it after collecting evidence.

## Manual UI checks before PR
- `/admin/menu/categories/create`: create a root category; `/admin/menu`
  should show it as a root header with the empty-subcategory state.
- `/admin/menu`: click the empty root's create-subcategory link; the category
  form should open with that root preselected as `parent_id`.
- `/admin/menu/categories/create` and `/admin/menu/items/create`: type in the
  parent/category combobox; options should load server-side and hidden ids
  should change only after explicit selection/clear.

- `/admin/menu/categories/{subcategory}/edit`: save a subcategory without
  changing parent; it should stay under the same root.
- `/admin/menu`: toggle a menu item's activity inline; the row should update
  and deactivated items should disappear when inactive items are hidden.
- `/admin/menu`: search categories and items on the 250k-item load dataset;
  matching roots/subcategories/items should appear without visible slowdown.
- `/admin/menu`: paginate categories and items; category pages should count
  root groups, keep stable ordering, and not drop empty roots on later pages.
- `/admin/menu?category={foreign_id}` and direct edit/archive URLs for another
  tenant's category/item: expected HTTP result is 404.

## Stage 1.17 Halls Completed Plan
- [x] Step 1.17.1: Run Step 0 from updated `main`, verify Stage 1.16 audit
  infrastructure is present, inspect Blueprint/doc/template/Menu/audit/tenancy
  conventions, and record the hall schema rationale before implementation.
  Result: branch `phase-2-stage-1.17-halls` created from `origin/main`
  `e365ceb633ee2cb02be4ede52e04525fe01da4c0`; Stage 1.16 head
  `ab73bab5c6008cad8b32673ce5951f50f88ad89e` is contained in `origin/main`;
  `app/Support/Audit` and the audit logs migration exist.
- [x] Step 1.17.2: Add the additive `halls` migration, RLS policy, indexes,
  Tables module skeleton, and `Hall` model using tenant scope and soft deletes.
  Result: `2026_07_23_010000_create_halls_table.php` creates `halls` with
  tenant/branch indexes and `halls_tenant_isolation`; `Hall` uses
  `BelongsToTenant` and `SoftDeletes`.
- [x] Step 1.17.3: Add branch-scoped Hall Application query and lifecycle
  actions for create, update, archive, restore, permanent delete, and paginated
  list, with structured logs and same-transaction audit writes using
  `tables.hall.*` action strings.
  Result: all hall mutations run through Application actions, filter by
  resolved branch, and record `tables.hall.created`, `.updated`, `.archived`,
  `.restored`, and `.permanently_deleted` audit rows inside their transactions.
- [x] Step 1.17.4: Add admin routes, controller/request, Blade UI, sidebar
  navigation, shared confirm-modal destructive flows, translated flashes, and
  superadmin-only archive maintenance.
  Result: `/admin/tables/halls` supports list/create/edit/archive and
  superadmin-only archived view/restore/permanent delete, using existing admin
  layout/components and translated strings.
- [x] Step 1.17.5: Add `tables.halls.manage` to seeded permissions and grant it
  to owner/manager roles, then seed deterministic demo halls for both demo
  tenants.
  Result: owner/manager roles receive `tables.halls.manage`; demo halls are
  seeded for Arat Kentron, Arat Dilijan, and Northstar Downtown.
- [x] Step 1.17.6: Add architecture, Application, HTTP/UI, permission,
  branch/tenant isolation, audit, translation, and PostgreSQL RLS tests.
  Result: SQLite `make test` passed with `150 passed / 4 skipped / 1105
  assertions`; PostgreSQL Tenancy passed with `20 passed / 70 assertions`;
  architecture now includes `Tables` and proves module internals stay isolated.
- [x] Step 1.17.7: Record the hall schema decision and pending Blueprint
  amendment, run required Makefile verification and HTTP smoke, and leave a
  zero-context handoff for the follow-up Tables stage.
  Result: `docs/DECISIONS.md` records the Halls schema and required Blueprint
  amendment; `make pint`, `make stan`, `make test`, `make tenant-isolation-pgsql`,
  and `make fresh` all passed locally. HTTP smoke request id
  `STAGE-1.17-HALLS-SMOKE-20260723180536` created, updated, archived, and
  restored hall id `8`; audit rows were written for created/updated/archived/
  restored with that correlation id. Next stage after Stage 1.17 is merged:
  implement the actual Tables schema/UI against the `halls` relationship after
  the owner-approved Blueprint amendment; no `tables` table is created in
  Stage 1.17.

Historical Stage 1.11 Part C subcategory implementation order:
- [x] Step A: add schema/model foundation for `menu_categories.parent_id` and
  `menu_categories.archived_with_category_id`, self-FK/check/indexes, model
  relations/casts, and PostgreSQL schema tests. Result: review-ready on
  2026-07-22; focused PostgreSQL `MenuSchemaTest` passed (`8 passed / 54
  assertions`).
- [x] Step B: enforce depth=2 and item-only-under-subcategory in Application
  actions/requests/tests. Scope: explicit tenant-scoped parent/category lookups,
  parent_id request validation, no moving non-empty category nodes, and focused
  PostgreSQL tests. Result: review-ready on 2026-07-22; PostgreSQL
  `tests/Feature/Menu` passed (`43 passed / 405 assertions`), and committed as
  `1f416b8`. No cascade changes, no DemoSeeder update yet, and no new
  seed-load command.
- [x] Step C: implement root/subcategory archive/restore/force-delete cascade
  semantics with cascade markers. Batch archive updates must only touch
  currently non-trashed rows with `archived_with_category_id is null`, so
  independently archived descendants keep their marker/state. DB cascade
  operations must run inside `DB::transaction()`, while storage cleanup for
  force-deleted item images runs only after successful commit. No
  `MenuCategory`/`MenuItem` observers or lifecycle event hooks were found, so
  batch updates do not bypass current domain side effects. Update demo seed
  data to root+subcategory structure before running full `make fresh`. Result:
  review-ready on 2026-07-22; focused PostgreSQL `tests/Feature/Menu` passed
  (`47 passed / 462 assertions`), then force-delete root edge coverage was
  extended for independently archived subcategories and PostgreSQL
  `MenuActionsTest` passed (`17 passed / 169 assertions`). No DemoSeeder
  update yet, no Step D UI/query changes, and no seed-load command.
- [x] Step D: adapt Menu query actions and Livewire master-detail to the
  root -> subcategory -> item tree, reusing existing paginated actions where
  possible and collapsing duplicated archive container logic. Result:
  review-ready on 2026-07-22; added tree-aware selection via
  `ResolveMenuCategorySelection`, made roots non-clickable group headers,
  selectable nodes subcategories only, removed direct Livewire Eloquent
  selection queries, removed all root `sort_order=100` accommodations, and
  verified PostgreSQL `tests/Feature/Menu` passed (`50 passed / 488
  assertions`), `make fresh` passed, and at the time `TenantIsolationTest`
  was still believed to have 3 known RLS/BYPASSRLS failures. Superseded by
  Stage 1.13: unprivileged pgsql Tenancy now passes `11 passed / 0 failed /
  0 skipped / 42 assertions`.
- [x] Step C.1: convert demo seed data and raw test fixtures to the root ->
  subcategory -> item structure before full `make fresh`. Scope:
  `MenuDemoSeeder`, `MenuDemoSeederTest`, and raw fixtures in dashboard,
  schema, and tenant-isolation tests. Do not change RLS expectations in
  `TenantIsolationTest`; the then-known `BYPASSRLS` role failure was accepted
  security debt. Result: review-ready on 2026-07-22; `make fresh` passed,
  focused PostgreSQL DemoSeeder/Login/dashboard/schema tests passed
  (`20 passed / 172 assertions`), and PostgreSQL `TenantIsolationTest` was
  still believed to have exactly the 3 known RLS/BYPASSRLS failures with no
  new structure failures. Superseded by Stage 1.13: unprivileged pgsql
  Tenancy now passes fully. Committed as `69a37fc`.
- [x] Step E: implement `menu:seed-load` last, after parent_id schema and UI
  paths are final. Support production-like and giant-menu modes with raw batch
  insert/COPY and optional drop/rebuild trgm index flow. Scope: standalone
  Artisan command only, not `make fresh` and not `DemoSeeder`; generate
  diverse deterministic localized names, stream inserts in bounded batches,
  preserve root -> subcategory -> item invariants, optionally drop/rebuild
  PostgreSQL trgm indexes, and guard non-local/testing environments behind
  `--force`. Result: review-ready on 2026-07-22; added standalone
  `menu:seed-load` command with production-like and giant-menu modes,
  deterministic diverse hy/ru/en localized names, bounded raw insert batches,
  real-ID parent lookup after each parent stage, and optional PostgreSQL trgm
  drop/rebuild. Safe checks passed: Artisan help renders and focused Pint
  passed for changed PHP files. No actual load was run. Full `make stan` still
  fails only on pre-existing Step B/C/D PHPStan issues outside the new command.
  Committed as `ae0436b`.
- [x] Step F: clean up pre-existing Step B/C/D PHPStan typing errors without
  changing runtime behavior. Scope: typed subcategory id collection in
  archive/restore/force-delete cascade actions, builder callback annotations
  for tree archive filtering, safe nullable parent-id comparison in
  `UpdateMenuCategory`, and `MenuCategoryRequest` rule PHPDoc. Result:
  committed as `835fda0`; `make stan` passed with no errors, focused
  PostgreSQL `MenuActionsTest`, `MenuQueryActionsTest`, and `MenuSchemaTest`
  passed (`31 passed / 259 assertions`), and full SQLite `make test` passed
  (`88 passed / 2 skipped / 693 assertions`).
- [x] Step G: fix `menu:seed-load` PostgreSQL bulk loading to use CSV `COPY`
  instead of bind-heavy multi-row inserts. Scope: install PHP `pgsql`
  extension in the php-fpm image, stream PostgreSQL rows through chunked
  `COPY ... FROM STDIN WITH (FORMAT csv, NULL '\N')`, keep committed parent
  id lookup before child stages, add load-manager users/roles/permissions and
  branch assignments per generated tenant, add `tenants.seed_source` and make
  `--fresh` cleanup scoped only to `seed_source = 'load'`, retain safe dynamic
  INSERT fallback for non-PostgreSQL drivers, and rerun only safe
  command/static checks before owner-run small load. Result: completed on
  2026-07-23; php-fpm was rebuilt and `pgsql` was loaded, `--fresh` for
  production-like mode now uses guarded `migrate:fresh --seed` instead of
  row-by-row cleanup, fallback DELETE cleanup remains scoped to
  `seed_source = 'load'`, PostgreSQL loader sessions set
  `lock_timeout = 10s`, and final count verification is scoped to the current
  `run_id`. The verified command
  `menu:seed-load --mode=production-like --restaurants=5
  --drop-rebuild-trgm --fresh --no-interaction` completed with
  `cleanup_seconds=2.688`, `copy_load_seconds=33.066`,
  `trgm_rebuild_seconds=11.222`, wall-clock `51.55s`, `menu_items=250000`,
  `menu_categories=600`, `pg_total_relation_size('menu_items') = 261 MB`, and
  `pg_total_relation_size('menu_categories') = 632 kB`. Generated load manager
  login was verified for
  `load-manager+20260723071232-1-restaurant-1@smartrest.test`: `POST /login`
  returned `302` to `/admin`, then `GET /admin` returned `200`.

Historical note: Stage 1.16 Audit and Stage 1.17 Halls were merged before
Stage 1.18 began. Current follow-up is recorded in the final `Next steps`
section at the end of this file.

## Stage 1.18 Tables Vertical Slice

Step 0 state:
- `git status --short --branch`: clean `main...origin/main` before branch
  creation.
- `origin/main`: `28c2c237e296766839406192f5ce6f31398a4a1c`.
- Stage 1.17 head `a75a367` is an ancestor of `origin/main`.
- `app/Modules/Tables/` and
  `database/migrations/2026_07_23_010000_create_halls_table.php` exist.
- Local `main` fast-forwarded with `git merge --ff-only origin/main`; already
  up to date.
- Created branch `phase-2-stage-1.18-tables` from `main`.

Legacy table screen findings:
- `template/rooms-hall-tables.html` is the table settings screen under one
  selected hall (`hallId` hidden field). It shows a table `Անվանում` field
  (`table_name`) with examples like `Սեղան 10`, numeric and string labels in
  rows (`1`, `10`, `VIP`), `ՀԴՄ բաժին`, table shape
  (`planning_table_form`: circle/square/rect), commission type/value,
  delivery flag, edit/archive controls, DataTables-style pagination, and
  search.
- `template/rooms-tables.html` is the operational table board, not this stage.
  It groups cards by hall, displays hall colors, table labels (`1`, `VIP`,
  `T1`, `V1`), order status colors, waiter/customer/current money/discount/time
  data, and table move/order entry behavior. Those order/board fields are
  deferred.
- `template/rooms-hall.html` shows halls as configurable containers and exposes
  a `Սեղանների տիպեր` modal with localized type names (`Սեղան`, `Table`,
  `Стол`, `VIP`). A separate table-types entity remains deferred by task D8.
- `template/rooms-hall-planning.html` shows the future floor-plan layout with
  table shapes and coordinates, but geometry/coordinates are explicitly
  deferred. The only table-planning field kept now is the simple constrained
  shape value.
- `template/rooms-table-order.html` confirms a table label plus hall name in
  order context and shows order-time data such as client count, order type,
  subtables, payments, waiter changes, discounts, and moving tables/items. All
  of that belongs to later Orders/Table Board stages.

Stage 1.18 plan:
- [x] Step 1.18.1: Add the additive `tables` schema and model. Columns:
  `tenant_id`, `branch_id`, `hall_id`, `archived_with_hall_id`,
  localized `translated_name`, constrained `shape`, `sort_order`, `active`,
  soft deletes, timestamps, FK/index coverage for tenant/branch/hall/archive
  lookup paths, and PostgreSQL `tables_tenant_isolation` RLS. Document the
  schema decision in `docs/DECISIONS.md` and leave `docs/BLUEPRINT.md`
  untouched.
  Result: added `2026_07_23_020000_create_tables_table.php`, tenant-scoped
  `Table`, hall relations, explicit tenant/branch/hall/archive indexes, shape
  and type PostgreSQL checks, and `tables_tenant_isolation` RLS. The schema
  decision is recorded in `docs/DECISIONS.md`; `docs/BLUEPRINT.md` is
  intentionally unchanged pending owner approval.
- [x] Step 1.18.2: Add table Application actions for create, update, archive,
  restore, permanent delete, and paginated tenant/branch/hall-scoped listing.
  Each mutation writes one `tables.table.*` audit row inside its transaction
  and uses the existing `RecordsTableAction` structured logging pattern.
  Result: added `CreateTable`, `UpdateTable`, `ArchiveTable`, `RestoreTable`,
  `ForceDeleteTable`, `FindTable`, and `PaginateTables`; all mutation actions
  branch-scope by resolved context and audit inside the mutation transaction.
- [x] Step 1.18.3: Modify Stage 1.17 Hall cascade actions. Update
  `ArchiveHall` to archive currently non-archived child tables with
  `archived_with_hall_id = hall_id`; update `RestoreHall` to restore only
  tables carrying that marker and clear it; update `ForceDeleteHall` to
  permanently delete archived child tables before deleting the archived hall.
  These changes are required by D5 and will record table counts on the hall
  audit row rather than per-table cascade audit rows.
  Result: modified `ArchiveHall`, `RestoreHall`, and `ForceDeleteHall` because
  D5 makes hall archive semantics own child table cascade membership. The
  cascade is set-based, transaction-wrapped, marker-driven, and records counts
  on the hall audit row without per-table cascade audit rows.
- [x] Step 1.18.4: Add admin UI/routes/controllers/requests for tables nested
  under a selected hall, using existing admin layout and `x-` components,
  confirm-modal archive/permanent-delete flows, translated flashes, and
  superadmin-only archive visibility/restore/permanent-delete rendering and
  server-side enforcement.
  Result: added nested `/admin/tables/halls/{hall}/tables` routes,
  `TableController`, `TableRequest`, table index/form Blade views, and a Halls
  list link to manage a hall's tables. Archive controls render only as allowed,
  and restore/permanent-delete routes require the `superadmin` middleware.
- [x] Step 1.18.5: Add `tables.tables.manage` to the seeded permission catalog,
  grant it to the same owner/manager roles as `tables.halls.manage`, and seed
  deterministic demo tables for both demo tenants inside the demo halls.
  Result: seeded `tables.tables.manage` for owner and manager roles and added
  deterministic demo tables for Arat Riverside and Northstar Bistro halls.
- [x] Step 1.18.6: Add and extend automated tests for schema/RLS, table
  actions, hall cascade matrix, HTTP CRUD, permission denial, superadmin-only
  archive behavior, tenant/branch/hall scoping and 404 isolation, audit rows,
  translations, demo seed visibility, and architecture boundaries.
  Result: added `TableSchemaTest`, `TableActionsTest`, `TableBladeTest`, demo
  seeder coverage, and PostgreSQL `tables` RLS coverage in Tenancy. Full local
  SQLite and PostgreSQL suites passed with the counts recorded below.
- [x] Step 1.18.7: Run required Makefile verification (`make pint`,
  `make stan`, `make test`, `make tenant-isolation-pgsql`, `make fresh`),
  perform the required HTTP/database smoke with a unique `X-Request-Id`,
  update this worklog with results and handoff H1-H6, commit scoped paths,
  push, open a PR, and merge only after exact-head green CI.
  Result: local `make pint`, `make stan`, `make test`,
  `make tenant-isolation-pgsql`, and `make fresh` passed. HTTP smoke request id
  `STAGE-1.18-TABLES-SMOKE-20260723-001` created table id `17`, updated it,
  archived seeded table id `2` independently, archived hall id `1`, then
  restored hall id `1` as superadmin. Final database evidence: table `2`
  remained archived with `archived_with_hall_id = null`; table `17` was active
  with `archived_with_hall_id = null`; audit rows were
  `tables.table.created`, `tables.table.updated`, `tables.table.archived`,
  `tables.hall.archived` with `cascade.archived_table_count = 3`, and
  `tables.hall.restored` with `cascade.restored_table_count = 3`. Commit, push,
  PR, exact-head CI, and merge are release steps after this local handoff.

Stage 1.18 verification:
- `make pint`: passed; Pint reported `PASS` over `208 files`.
- `make stan`: passed; PHPStan analyzed `119/119` files and reported
  `[OK] No errors`.
- `make test`: passed; SQLite Pest reported `160 passed / 5 skipped / 1206
  assertions`.
- `make tenant-isolation-pgsql`: passed; PostgreSQL Tenancy reported
  `21 passed / 73 assertions`.
- `make fresh`: passed; migrations ran through
  `2026_07_23_020000_create_tables_table`, and `Database\Seeders\DemoSeeder`
  completed.

Stage 1.18 gotchas:
- `make stan` initially failed because `TablesDemoSeeder::tablesForHall()` was
  declared as returning a list but `array_map()` preserved literal numeric keys.
  The method now wraps the mapped rows in `array_values()`.
- The first `make test` run failed only because the demo seeder test expected
  the English shape label `Square` while the Arat manager locale renders
  Armenian `Քառակուսի`; the assertion now matches the seeded user's locale.
- The first HTTP smoke attempt returned `502 Bad Gateway` because nginx held an
  old php-fpm upstream IP after `make up` recreated php-fpm. `make restart`
  refreshed nginx, after which the smoke passed. No smoke mutations completed
  during the failed 502 attempt.
- A committed worklog cannot contain the future GitHub merge commit SHA created
  by merging that same commit without a forbidden direct `main` follow-up or
  history rewrite. This file records verified local baselines; the final report
  must record the exact PR, exact-head CI, merge commit, new `main` SHA, and
  post-merge CI conclusion.

Stage 1.18 durable handoff:

Current state:
- Merged `main` SHA and post-merge CI conclusion: pending release flow. The
  final report must record the exact merge commit SHA and GitHub CI conclusion
  after the PR is merged. The branch starts from `origin/main`
  `28c2c237e296766839406192f5ce6f31398a4a1c`.
- Verification baselines future sessions must beat: SQLite Pest `160 passed /
  5 skipped / 1206 assertions`; PostgreSQL Tenancy `21 passed / 73 assertions`;
  Pint `208 files`.

What exists now:
- Tenancy: tenant and branch context resolution, branch assignments, Eloquent
  tenant scopes, and PostgreSQL RLS coverage for current tenant-owned tables.
- Identity: session login/logout, roles, permissions, branch assignments, and
  deterministic demo users for both seeded tenants.
- Menu: admin root/subcategory/item CRUD, archive/restore/force-delete
  semantics with cascade markers, images, search, pagination, and demo data.
- Menu API: `/api/v1/menu-items` read slice with session auth, branch scoping,
  permission gating, throttling, and pagination metadata.
- Audit logs: append-only `audit_logs`, transaction-bound mutation audit writes,
  redaction, structured logging context, and PostgreSQL RLS coverage.
- Halls: Tables module hall CRUD, branch-scoped lists, archive maintenance, and
  hall audit strings; hall archive now cascades to child tables.
- Tables: additive `tables` schema, tenant/branch/hall scoped model/actions,
  nested admin UI, permissions, deterministic demo tables, and hall cascade
  marker semantics.

Prioritized remaining work:
- Table board (Livewire) — next product stage, depends on tables.
- Menu public contracts (`MenuCatalog`, `PriceResolver`) — `app/Modules/Menu/
  Contracts/` currently holds only `.gitkeep`; Orders cannot consume Menu
  without them and cannot bypass module boundaries.
- Orders module — depends on the table board and Menu contracts.
- Domain events and the outbox (ADR-008) — every `app/Modules/*/Events/`
  directory is still empty; nothing emits or consumes domain events.
- `docs/BLUEPRINT.md` section 4 amendment for `halls` and `tables` — text
  prepared, awaiting owner approval, must not be applied unilaterally.
- Runtime PostgreSQL role separation — the 2026-07-23 decision gates this
  before the first real tenant is onboarded; `docker-compose.yml`,
  `.env.example`, and `config/database.php` still point runtime traffic at the
  privileged `smartrest` role.
- `app/Console/Commands/MenuSeedLoadCommand.php` issues
  `CREATE EXTENSION IF NOT EXISTS pg_trgm` and will fail under an unprivileged
  role; it is also about 1500 lines with no automated tests.
- `tests/Feature/Menu/MenuSchemaTest.php` early-returns on non-pgsql drivers,
  so it silently passes without asserting anything, and it is not included in
  the `tenant-isolation-pgsql` job.
- During the Stage 1.11C review-correction HTTP smoke, the first curl script
  forced `-X POST` while following redirects, so curl preserved POST across the
  login redirect and reported a final `405`; it also included a host-PHP helper
  to format an unused marker, which violated the project workflow and was
  discarded. The corrected smoke used normal form POST redirect handling, no
  host PHP, and passed with explicit manager/owner/UI/API status and content
  markers.
- Stage 1.11C local PostgreSQL search measurements include the dev/test-only
  `menu_items_tenant_branch_load_test_key_idx` marker index in a `BitmapAnd`
  on the generated load dataset. That marker index is for purge tooling, so it
  must not be treated as production search-plan evidence.
- Stage 1.11C representative item-path measurements showed PostgreSQL
  materially underestimating selected-category item rows at 200k-row scale
  (`estimate 1` versus `actual 54`). Future Menu item-path index reviews must
  check estimates as well as whether the chosen node is an index scan.
- Menu UX carry-over from Stage 1.11 Part D: context-preserving save/cancel,
  and moving archive/restore/force-delete controls into a row overflow menu.
- No admin UI or API for reading audit logs.
- CI Node.js 20 action-runtime deprecation is being handled by the
  `phase-2-ci-node24-actions` maintenance branch; final status is recorded in
  the CI maintenance section below.
- Every branch push triggers duplicate CI runs (`push` and `pull_request`),
  doubling CI minutes.
- Branch protection on `main` requiring `quality` and
  `tenant-isolation-pgsql` is not enabled; the "never merge red" rule currently
  rests on task instructions rather than on GitHub enforcement.
- Roughly eleven stale local branches from completed stages remain undeleted;
  branch deletion is forbidden to the agent, so this is an owner-only cleanup.

Design implications:
- `audit_logs` uses `restrictOnDelete` on `tenant_id`, `branch_id`, and
  `actor_id`, so a tenant, branch, or user can no longer be deleted once audit
  rows exist. Any future admin deletion feature must be designed around this.

Blueprint amendment text awaiting owner approval:

```markdown
Halls & Tables:

| Entity | Key fields | Relationships/indexes |
|---|---|---|
| `halls` | tenant_id, branch_id, translated_name, color, sort_order, active, deleted_at | belongs to branch; indexes on tenant, branch, and tenant+branch+deleted_at+active+sort_order+id; PostgreSQL `halls_tenant_isolation` RLS |
| `tables` | tenant_id, branch_id, hall_id, archived_with_hall_id nullable, translated_name, type, shape, hdm_department nullable, is_delivery, sort_order, active, deleted_at | belongs to hall; branch filtering remains explicit; indexes on tenant, branch, hall, archive marker, and tenant+branch+hall+deleted_at+active+sort_order+id; PostgreSQL `tables_tenant_isolation` RLS |

Tables are managed under a selected hall. `translated_name` stores the
human-facing table label/name as a localized value object because legacy
screens show numeric, text, and VIP labels. `type` is a constrained simple
column for the current `standard`/`vip` distinction; a dedicated table-types
entity remains deferred. `shape` is a constrained simple planning hint
(`circle`, `square`, `rectangle`); floor-plan coordinates, geometry,
commission/pricing metadata, subtables, the table board, and Orders remain
deferred. Archiving a hall archives only currently active child tables and
marks them with `archived_with_hall_id`; restoring the hall restores only those
marked tables; independently archived tables remain archived. Permanent hall
deletion is superadmin-only maintenance and permanently deletes archived child
tables before deleting the archived hall.
```

Decisions awaiting the owner:
- Should the Blueprint section 4 Halls & Tables amendment above be approved as
  written, revised before approval, or deferred until after the Table Board
  stage?
- Should runtime PostgreSQL role separation be scheduled before any more
  product slices, before first real-tenant onboarding, or deferred with an
  explicit pre-production risk acceptance?
- Should the next Table Board stage remain a read/interaction board without
  Orders writes, or should it wait until Menu public contracts are added first?

## CI maintenance: Node 24 action runtime

Status: complete; final pushed-head CI evidence is reported in the session
final response
Branch: `phase-2-ci-node24-actions`
Base: `origin/main` at `c3e1f13`

Read-only inspection:
- Required sources read: `AGENTS.md`, `docs/DECISIONS.md`, and this worklog.
- Starting git state: `main...origin/main` clean at `c3e1f13`; latest log
  entries were `c3e1f13`, `b60a195`, `ad16570`, `6a3c333`, and `8695c35`.
- `git fetch origin` succeeded; local `main` fast-forward check reported
  `Already up to date`; branch `phase-2-ci-node24-actions` was created from
  fresh `origin/main`.
- Workflow files: `.github/workflows/ci.yml` only.
- Pinning style: major tags (`@v4`, `@v2`); this task keeps major-tag pinning.

Action inventory:

| Workflow | Job | Action | Version before | Runtime before | Newest major verified | Runtime at newest major | Version after |
|---|---|---|---|---|---|---|---|
| `.github/workflows/ci.yml` | `quality` | `actions/checkout` | `v4` | `node20` | `v7` | `node24` | `v7` |
| `.github/workflows/ci.yml` | `quality` | `shivammathur/setup-php` | `v2` | `node24` | `v2` | `node24` | `v2` |
| `.github/workflows/ci.yml` | `quality` | `actions/setup-node` | `v4` | `node20` | `v7` | `node24` | `v7` |
| `.github/workflows/ci.yml` | `tenant-isolation-pgsql` | `actions/checkout` | `v4` | `node20` | `v7` | `node24` | `v7` |
| `.github/workflows/ci.yml` | `tenant-isolation-pgsql` | `shivammathur/setup-php` | `v2` | `node24` | `v2` | `node24` | `v2` |

Verification sources:
- `git ls-remote --tags` against the upstream action repositories verified the
  newest major tags available on 2026-07-24: `actions/checkout` `v7`,
  `actions/setup-node` `v7`, and `shivammathur/setup-php` `v2`.
- `action.yml` from `https://raw.githubusercontent.com/actions/checkout/v4/`
  declares `runs.using: node20`; `v7` declares `runs.using: node24`.
- `action.yml` from `https://raw.githubusercontent.com/actions/setup-node/v4/`
  declares `runs.using: node20`; `v7` declares `runs.using: node24`.
- `action.yml` from `https://raw.githubusercontent.com/shivammathur/setup-php/v2/`
  declares `runs.using: node24`; no newer major tag exists, so it remains
  unchanged.
- Breaking-input review: the workflow uses no `actions/checkout` inputs and
  uses only `node-version` plus explicit `cache: npm` for `actions/setup-node`;
  those inputs exist at the verified `v7` manifests, so no step adaptation is
  planned.

Plan:
- [x] CI-node24.1: bump only `actions/checkout@v4` to `@v7` in both jobs and
  `actions/setup-node@v4` to `@v7` in `quality`; leave
  `shivammathur/setup-php@v2` unchanged because it already declares `node24`.
  Result: `.github/workflows/ci.yml` changed only those three `uses:` lines;
  no inputs, jobs, runner images, verification commands, or suppression
  variables were added.
- [x] CI-node24.2: run local verification exactly as required:
  `make pint`, `make stan`, `make test`, `git diff --check`, and a full diff
  review versus `origin/main`.
  Result: `make pint` passed (`PASS 215 files`); `make stan` analyzed
  `122/122` files and reported `[OK] No errors`; `make test` passed
  (`175 passed / 5 skipped / 1399 assertions`); `git diff --check` passed;
  full diff review versus `origin/main` showed only
  `.github/workflows/ci.yml` and this worklog changed. The skipped Pest tests
  are the pre-existing SQLite-suite skips for PostgreSQL RLS coverage, not
  caused by this workflow-only change.
- [x] CI-node24.3: commit the workflow and worklog update as one CI
  maintenance step, push the branch, retrieve the GitHub Actions run, record
  both job statuses and any remaining runner warnings, then stop without PR or
  merge.
  Result so far: committed `b44cb82` (`ci: move actions to node24 runtime`) and
  pushed `phase-2-ci-node24-actions`. GitHub Actions run `30079876185` on head
  `b44cb823b2daca092e31efca9b196daa702d876b` passed: `quality` succeeded in
  `47s` and `tenant-isolation-pgsql` succeeded in `38s`. Check-run API
  reported `annotations_count=0` for both jobs. Log searches found no
  `Node.js 20 is deprecated`, `forced to run on Node.js 24`, `node20`, or
  forbidden suppression-variable text. Remaining non-Node warning-like output
  is pre-existing: Git's default initial branch hint during checkout, Pest
  warning summaries (`quality`: `176 warnings`; `tenant-isolation-pgsql`:
  `21 warnings`) with `file_get_contents(...)` warning snippets, and the
  PostgreSQL service log line `initdb: warning: enabling "trust"
  authentication for local connections`. This worklog evidence update is
  docs-only and triggered one additional push CI run. A final docs-only
  handoff update marks the worklog complete; the session final response records
  the exact latest pushed-head CI run to avoid an endless loop where recording a
  run id creates a newer run.

## Menu context + overflow follow-up

Status: Stage 1.11D owner review-correction pass in progress
Branch: `phase-2-stage-1.11d-menu-context-overflow`
Base: `origin/main` at `cc46b95`

Scope:
- UI-only Menu admin follow-up.
- Preserve Menu screen context across item/category create/edit save and cancel.
- Move rare destructive row actions into a compact Alpine overflow.
- No read-action, API, response-shape, schema, migration, npm package,
  `docs/BLUEPRINT.md`, `template/`, Audit, Halls, Tables, or unrelated module
  changes.

Read-only inspection result:
- `git fetch origin` and `git merge --ff-only origin/main` on local `main`
  reported `Already up to date`; new branch
  `phase-2-stage-1.11d-menu-context-overflow` was created from fresh
  `origin/main`.
- Starting git state was clean. `git log --oneline -5` showed `cc46b95`
  merge of `phase-2-ci-node24-actions`, then the node24 action commits, then
  `c3e1f13` merge of Menu read convergence.
- No root `.smoke-tmp` or related smoke-temp artifact exists; `git ls-files`
  for those patterns returned no tracked paths.
- Preconditions are present on this base: `.github/workflows/ci.yml` uses
  `actions/checkout@v7` and `actions/setup-node@v7`; `MenuIndex` obtains read
  data through `BrowseMenuItems::forMenuIndex()`, and no longer directly calls
  `ResolveMenuCategorySelection`, `PaginateMenuCategories`,
  `PaginateMenuItems`, or `SearchMenuItems` from `render()`.
- Current Menu index URL/component context contract:
  `category` -> `MenuIndex::$category`;
  `q` -> `MenuIndex::$search`;
  `category_page` -> `MenuIndex::$categoryPage`;
  `item_page` -> `MenuIndex::$itemPage`;
  `search_page` -> `MenuIndex::$searchPage`;
  `show_inactive` -> `MenuIndex::$showInactive`;
  `archive_mode` -> `MenuIndex::$archiveMode`.
  `categorySearch` is component-only and is not bookmarkable today.
- The context requested for this follow-up maps to `category`, `item_page`,
  `q`, and `archive_mode`; `search_page` remains existing search-pagination
  state but is not part of the prompt's required return-context set.
- Current create/edit forms reset to `route('admin.menu.index')` on save and
  cancel. Category forms are plain Blade/controller forms; item forms are a
  plain Blade wrapper with a Livewire `MenuItemForm` component.
- Current destructive controls are visible row buttons/forms in
  `category-actions.blade.php` and `item-list.blade.php`; archive uses the
  shared `x-confirm-modal`, restore is a plain POST form button, and
  force-delete uses `x-confirm-modal` with irreversible copy.
- Current routes preserve archive authorization correctly: archive routes have
  normal manage permission; restore and force-delete routes also require
  `superadmin`.
- Characterization tests pin default category selection, search ignoring
  selected category, clearing search returning to category context, empty
  root/category rendering, superadmin-only archive controls, and Livewire
  query-count invariance. Query-count tests currently pin
  `BrowseMenuItems` category `6`, `BrowseMenuItems` search `3`,
  `MenuIndex` category render `10`, and `MenuIndex` search render `10`.

Plan:
- [x] Stage 1.11D.1: preconditions, branch, and read-only inspection. Read the
  required sources; fast-forward local `main`; verify Menu read convergence and
  node24 actions; confirm no tracked or untracked smoke-temp artifacts; create
  the feature branch; inspect Menu index/form/controller/shared-component
  surfaces, routes, translations, and pinned tests; record the current URL
  contract and plan before code. Result: completed as recorded above.
- [x] Stage 1.11D.2: context carrier and form return path. Add a small
  Menu HTTP context helper/request DTO that accepts only bookmarkable Menu
  context parameters, sanitizes them through `BrowseMenuItems` selection
  behavior without leaking invalid ids, renders them into create/edit links
  and form hidden fields, and redirects successful item/category create/edit
  plus cancel/back to the sanitized Menu URL with flash preserved.
  Result: added `MenuIndexContext` using a nested `context[...]` carrier on
  form/action URLs to avoid conflicting with route parameters such as
  `/categories/{category}` while still returning to the existing `/admin/menu`
  query keys. Category Blade forms now render hidden context fields and
  context-aware back/cancel URLs; `MenuItemForm` receives sanitized context,
  preselects the context category on create, and redirects successful Livewire
  saves back to the sanitized Menu URL. Raw item/category POST/PUT routes and
  archive/restore/force-delete redirects also preserve context when supplied.
  Existing pinned Menu index characterization stayed unchanged; one initial
  regression on the empty-root create-subcategory URL was fixed by not
  emitting implicit default category context.
- [x] Stage 1.11D.3: context preservation tests. Add tests for item/category
  create and edit save/cancel returning to category, item page, search term,
  and archive mode; validation failures retaining context; direct/bookmarked
  form URLs carrying context after reload; and foreign-tenant, archived, and
  nonexistent context category ids degrading to the default selected category
  without error or disclosure.
  Result: added `tests/Feature/Menu/MenuContextReturnTest.php` covering
  category create/edit save and cancel/back, item create/edit Livewire save and
  cancel/back, validation failure re-render with context intact, directly
  loaded/bookmarked form URLs, and foreign-tenant/archived/nonexistent context
  category degradation to the default category without rendering the invalid
  id. Focused gates after this slice: `make test` passed (`179 passed /
  5 skipped / 1443 assertions`), `make pint` passed after fixing two style
  issues (`217 files`), and `make stan` passed (`123/123`, `[OK] No errors`).
- [x] Stage 1.11D.4: shared row overflow. Add a reusable Alpine-only row
  overflow component for compact per-row rare actions with one-open-at-a-time
  coordination, focusable keyboard trigger, Escape close with focus return,
  outside-click close, and tablet-safe positioning. Move archive, restore, and
  force-delete for Menu items/categories into that overflow while keeping edit
  visible and keeping destructive actions on shared confirm-modal flow.
  Result: added reusable `x-row-overflow` with Alpine-only one-open-at-a-time
  coordination, focusable trigger, Escape close/focus return, outside-click
  close, and first-item focus on open. Menu item/category archive, restore,
  and force-delete controls now render inside per-row overflow menus while
  edit remains visible; destructive archive/force-delete controls continue to
  use the shared `x-confirm-modal`, now with an optional trigger class for
  menu placement.
- [x] Stage 1.11D.5: overflow tests and translations. Add `hy`/`ru`/`en`
  strings for the overflow trigger/label; prove managers do not see archived
  rows, archive-mode controls, restore, or force-delete inside overflow
  content; prove archive/restore/force-delete moved out of visible row actions
  and force-delete irreversible copy remains rendered only through confirm
  modal content.
  Result: added the `menu.actions.more` translation in all three locales and
  `tests/Feature/Menu/MenuOverflowTest.php` covering active archive actions
  inside category/item overflow menus, superadmin archived restore and
  force-delete content inside overflow menus with irreversible copy preserved,
  non-superadmin absence of archived row maintenance content inside overflow,
  and Alpine accessibility hooks for trigger, Escape, outside click, focus
  return, and one-open-at-a-time dispatch. Verification after this slice:
  `make test` passed (`182 passed / 5 skipped / 1501 assertions`),
  `make pint` passed (`218 files`), and `make stan` passed (`123/123`,
  `[OK] No errors`).
- [x] Stage 1.11D.6: verification, smoke, commit, push, and handoff. Run
  focused tests after each implementation step, then final `make pint`,
  `make stan`, `make test`, `make fresh`, `make tenant-isolation-pgsql`,
  `npm run build` or `make build`, HTTP smoke for save/cancel context on
  `/admin/menu`, `git status`, `git diff --check`, and full diff review
  versus `origin/main`. Commit logical steps with this worklog updated, push
  the branch, record CI evidence in the final response only, and stop without
  PR or merge.
  Result: final local verification passed after commits: `make pint` (`PASS`,
  `218 files`), `make stan` (`[OK] No errors`, `123/123`), `make test`
  (`182 passed / 5 skipped / 1501 assertions`), `make fresh` (migrations and
  `Database\Seeders\DemoSeeder` completed), `make tenant-isolation-pgsql`
  (`21 passed / 73 assertions` with PostgreSQL RLS tests active), and
  `make build` (composer install, key generation, storage link, `npm ci`, and
  Vite build completed). HTTP smoke on `/admin/menu` passed through in-container
  Artisan/Tinker using the seeded `manager@arat.test` user and real Armenian
  markers: index/edit/save/cancel all returned to
  `/admin/menu?category=2&q=%D4%BC%D5%B8%D5%BC%D5%AB&item_page=2`, with save
  returning `302` and the landing page showing `Դիրքը թարմացվեց։`. `git
  diff --check` passed and full branch diff review versus `origin/main` showed
  only Menu/UI/test/translation/worklog files in scope. CI evidence belongs in
  the final response only after the branch push.

Gotchas:
- The `make test` target does not forward trailing file arguments, so the first
  overflow-test command intentionally ended up running the full suite.
- The final HTTP smoke was run through `make artisan ARGS="tinker --execute=..."`
  to avoid host PHP; inline PHP required Make-safe dollar escaping, and the
  successful save request used the real CSRF token parsed from the rendered
  edit form.
- `make build` completed successfully but emitted local tool notices: Composer
  reported Git dubious ownership inside `/var/www/html`, and npm reported a
  newer major npm version. Neither stopped the build.
- Review correction smoke gotchas: category mode must use `item_page`, while
  global search mode must use `search_page`; using `item_page` with `q` only
  proves URL round-trip, not the operator's search landing. The durable smoke
  target checks the HTML-escaped cancel URL because Blade renders `&` as
  `&amp;` inside href attributes.
- Server-side Blade/Livewire tests and curl smokes that post directly to the
  Livewire endpoint cannot detect broken client-side expressions. Translation
  strings, user-entered text, and other PHP values must never be interpolated
  raw into Alpine directives, inline handlers, or `wire:click` expressions;
  pass encoded identifiers or resolve values server-side.

Owner review-correction plan:
- [x] Stage 1.11D-review.1: hostile context redirect proof. Re-read the
  required sources; verify the clean branch at `154d944`; inspect how
  `context[...]` becomes a return URL; add focused tests for absolute external
  URLs, protocol-relative values, other admin paths, newline/encoded separator
  smuggling, unexpected context keys, and scalar/array type confusion. If any
  case escapes the named Menu index route, fix the defect without changing the
  legitimate context contract. Result: `MenuIndexContext` rebuilds every
  return target with `route('admin.menu.index', $this->toQuery())` after
  whitelisting known keys and type-normalizing values; it never consumes a raw
  return URL. Added `MenuContextRedirectSecurityTest` covering all requested
  hostile cases against both rendered cancel/back URLs and save redirects. No
  redirect escape or code defect was found. Verification: initial `make test`
  failed because the new test incorrectly forbade legitimate search text inside
  the encoded Menu URL; after narrowing that assertion to target/path safety,
  `make test` passed (`183 passed / 5 skipped / 1572 assertions`) and
  `make pint` passed (`219 files`).
- [x] Stage 1.11D-review.2: durable Menu context HTTP smoke. Add a repeatable
  Make target that runs inside the PHP container without host PHP or temporary
  files, authenticates through the real login/session/CSRF flow, uses
  `menu:load-test-data` data, and proves category-mode and search-mode
  save/cancel landings with rendered Armenian markers. Document the target in
  `README.md`; do not add smoke-only routes, controllers, middleware bypasses,
  packages, or a general smoke framework. Result: added
  `smoke:menu-context` and `make smoke-menu-context`; the Make target starts
  Nginx if needed and runs the command inside the PHP container. The smoke uses
  a Guzzle cookie jar through Laravel's HTTP client, parses CSRF tokens from
  the real login/edit forms, submits the existing authenticated item update
  route with method spoofing, and does not disable middleware or add any
  smoke-only route/controller/middleware. README documents the target beside
  the other local commands. Verification so far: `make pint` passed
  (`220 files` after one style fix), `make stan` passed (`124/124`,
  `[OK] No errors`), and `make smoke-menu-context` passed after fixing the
  command's own HTML-escaped cancel-link assertion.
- [x] Stage 1.11D-review.3: execute smoke data and record outcome. Run
  `make fresh`, load deterministic multi-page Menu data with
  `menu:load-test-data`, record the resulting counts/category selected for the
  smoke, run the new smoke target, and record gotchas including the category
  mode `item_page` versus search mode `search_page` distinction. Result:
  `make fresh` passed through migrations and `DemoSeeder`; `make artisan
  ARGS="menu:load-test-data --purge-generated"` purged `0` generated rows,
  loaded `menu_categories=400`, `menu_items=40000`, and reported
  `tenant=arat-riverside menu_categories=200 menu_items=20000` plus
  `tenant=northstar-bistro menu_categories=200 menu_items=20000`
  (`elapsed_seconds=9.867`). The final `make smoke-menu-context` selected
  category `48` with `53` active rendered items and search term
  `arat-riverside 1-` with `9474` active results. Smoke HTTP statuses:
  login form `200`, login submit `302`; category page 1 `200` marker
  `Թարմ ոսպ ուտեստ arat-riverside 1-1`; category page 2 `200`, edit page
  `200`, save landing `200`, and cancel landing `200` all used/kept marker
  `Այգու պանիր ուտեստ arat-riverside 1-4861` and excluded the page-1 marker.
  Search page 2 `200`, edit page `200`, and save landing `200` used/kept marker
  `Շուկայի սունկ ուտեստ arat-riverside 1-27` and excluded reset/page-1 marker
  `Թարմ ոսպ ուտեստ arat-riverside 1-1`.
- [x] Stage 1.11D-review.4: required gates, diff review, commit, push, and CI
  handoff. Run `make pint`, `make stan`, `make test`, `make fresh`, the
  load/count command, the new smoke target, `make tenant-isolation-pgsql`,
  `make build`, `git diff --check`, `git status`, and full branch diff review
  versus `origin/main`. Commit scoped logical steps with worklog updates, push
  this branch only, collect CI run id and both job statuses, then stop without
  creating or merging a PR. Result: final local gates passed from committed
  code. `make pint`: `PASS 220 files`. `make stan`: `124/124`, `[OK] No
  errors`. `make test`: `183 passed / 5 skipped / 1572 assertions`. `make
  fresh`: migrations and `DemoSeeder` completed successfully. Final
  `menu:load-test-data --purge-generated`: purged `0` generated rows and loaded
  `menu_categories=400`, `menu_items=40000` in `9.698s`, with each demo tenant
  at `200` generated categories and `20000` generated items. Final
  `make smoke-menu-context`: selected category `48` with `53` active rendered
  items and search term `arat-riverside 1-` with `9474` active results; login
  form `200`, login submit `302`; category page 1/page 2/edit/save
  landing/cancel landing all returned `200` and proved page-2 marker
  `Այգու պանիր ուտեստ arat-riverside 1-4861` present while page-1 marker
  `Թարմ ոսպ ուտեստ arat-riverside 1-1` was absent after save/cancel; search
  page 2/edit/save landing all returned `200` and proved marker
  `Շուկայի սունկ ուտեստ arat-riverside 1-27` present while reset/page-1 marker
  `Թարմ ոսպ ուտեստ arat-riverside 1-1` was absent. PostgreSQL tenant-isolation:
  `21 passed / 73 assertions`. `make build`: composer install, key generation,
  storage link, `npm ci`, and Vite build completed; known local warnings were
  Composer Git dubious ownership and npm major-version notices. `git diff
  --check` passed. Full branch diff versus `origin/main` reviewed: 24 files,
  limited to accepted Menu context/overflow files plus the new smoke target,
  README/Makefile/bootstrap registration, translations, tests, and this
  worklog; no `docs/BLUEPRINT.md`, `template/`, schema/migration, API
  response-shape, npm package, or unrelated product-module changes. This final
  worklog handoff commit is the remaining commit to push; CI run id and job
  statuses belong in the final response only.

Tenant translation override read-side plan:
- [x] Stage 1.12.1: documentation and decision update. Amend only the
  i18n/cross-cutting blueprint wording for tenant-level UI translation
  overrides; add one dated decision covering DB storage, tenant-only scope,
  resolution order, translator hook choice, caching, and the non-overridable
  safety rule. Result: amended only the Cross-Cutting Concerns i18n paragraph
  in `docs/BLUEPRINT.md`; added the 2026-07-24 decision entry documenting
  tenant-only DB storage, five-step resolution order, translator-subclass hook
  choice over loader replacement, tenant/locale cache key shape, and
  non-overridable safety/auth/destructive keys.
- [x] Stage 1.12.2: tenant-owned override schema and model. Add the additive
  reversible `tenant_translation_overrides` migration with tenant/locale/key
  uniqueness, tenant-leading read indexes, PostgreSQL RLS policy guarded by
  driver, and an Eloquent model using the established tenant scoping traits.
  Result: added `tenant_translation_overrides` with `tenant_id`, `locale`,
  `translation_key`, `override_value`, timestamps, tenant/locale read index,
  tenant/locale/key uniqueness, PostgreSQL RLS policy, and
  `TenantTranslationOverride` using `BelongsToTenant`; added SQLite schema and
  uniqueness tests plus PostgreSQL tenant-isolation coverage. Verification:
  `make test` passed (`185 passed / 6 skipped / 1579 assertions`) and
  `make pint` passed after fixing one style issue (`223 files`).
- [x] Stage 1.12.3: resolution layer and non-overridable registry. Add the
  central non-overridable key registry plus the tenant override repository,
  cache key builder, and custom translator integration without changing
  `LocalizedText` or rewriting translation call sites. Result: added
  `TenantAwareTranslator` as the application translator binding, central
  `NonOverridableTranslationKeys`, tenant/locale cache key helpers, cached
  tenant override reads, lazy tenant-default fallback lookup, request-local
  caches, and model/tenant hooks that keep override cache keys straightforward
  for the future write path. `LocalizedText` and menu localized name storage
  were not changed.
- [x] Stage 1.12.4: focused read-path tests. Prove the five-step resolution
  order, non-overridable override rejection, at-most-one override read per
  tenant/locale request, zero translation DB queries with no tenant context,
  and tenant A/B isolation across sequential resolutions in one process.
  Result: added focused i18n read-path tests for all five fallback steps,
  non-overridable safety/auth keys, replacement parameters, pluralization,
  cold-cache loading, one override read for many translation calls with
  overrides, zero override reads for a zero-override tenant, zero DB queries
  without tenant context, and sequential tenant A/B isolation. Verification:
  `make pint` passed (`229 files`), `make stan` passed (`[OK] No errors`),
  and `make test` passed (`193 passed / 6 skipped / 2098 assertions`).
- [x] Stage 1.12.5: required gates, diff review, commit, push, and CI handoff.
  Run `make pint`, `make stan`, `make test`, `make fresh`,
  `make tenant-isolation-pgsql`, `make build`, `git diff --check`, full branch
  diff review versus `origin/main`, push only this branch, collect CI run id
  and both job statuses, then stop without PR or merge. Result: final local
  gates passed. `make pint`: `PASS 229 files`. `make stan`: `130/130`, no
  errors. `make test`: `193 passed / 6 skipped / 2098 assertions`.
  `make fresh`: storage link, all PostgreSQL migrations including
  `2026_07_24_020000_create_tenant_translation_overrides_table`, and
  `DemoSeeder` completed successfully. `make tenant-isolation-pgsql`:
  `22 passed / 76 assertions`, including translation override RLS coverage.
  `make build`: composer install, key generation, storage link, `npm ci`, and
  Vite build completed; known local warnings were Composer Git dubious
  ownership and npm major-version notices. `git diff --check` passed. Full
  branch diff versus `origin/main` reviewed: 15 files limited to the approved
  i18n blueprint paragraph, one dated decision, tenant translation override
  migration/model, translator/cache support layer, focused i18n and RLS tests,
  and this worklog; no `template/`, UI, write action, permission, seeder,
  Menu/Halls/Tables/Audit feature changes, or unrelated module changes.

Tenant translation override write-side plan:
- [x] Stage 1.13.1: preconditions, cache-shape inspection, and plan. Read the
  required sources; fetch and fast-forward local `main`; confirm `origin/main`
  is at least `175c189` and contains `TenantAwareTranslator`,
  `NonOverridableTranslationKeys`, and the
  `tenant_translation_overrides` migration; create/verify
  `phase-2-stage-1.13-tenant-translation-write` from fresh `origin/main`;
  inspect translator/cache/model/migration, permission seeding/checking,
  audit/domain-error patterns, tenant/no-tenant behavior, locale handling, and
  raw translation rendering sites; write this plan before code. Cache facts
  recorded for the write path: the override map key is
  `tenant:{tenant_id}:translation_overrides:{locale}:v1`, the presence marker
  key is `tenant:{tenant_id}:translation_overrides:locales:v1`, both are
  stored forever, request cache key is `{tenant_id}:{locale}` for one
  container/request, reads use the presence marker to skip DB for empty
  tenants/locales, model `saved` currently marks presence plus forgets the map,
  and model `deleted` currently forgets presence plus the map. Result:
  `git fetch origin` succeeded; local `main` fast-forwarded and is exactly
  `175c189`; branch `phase-2-stage-1.13-tenant-translation-write` is clean and
  based on `origin/main`; required read-side artifacts are present; blueprint
  and decision docs do not conflict with the write-side prompt; raw rendering
  inspection found reachable title-section translation output in
  `resources/views/layouts/admin.blade.php` that must be escaped in the
  output-safety step.
- [x] Stage 1.13.2: permission and demo grants. Add the dedicated tenant
  translation override manage permission using the existing Identity
  permission/role convention; seed it for demo roles that should remain usable;
  add the settled active-superadmin bypass through the existing authorizer
  rather than a parallel policy path; cover denied, explicit-grant allowed, and
  superadmin-without-grant allowed behavior. Result: added
  `tenancy.translation_overrides.manage` as the central permission code, seeded
  it for demo owner and manager roles, added the active-superadmin bypass in
  `EloquentAuthorizer`, and covered deny/grant/superadmin plus demo-manager
  grants in `TenantTranslationOverridePermissionTest`. Verification: `make
  test` passed (`197 passed / 6 skipped / 2106 assertions`) and `make pint`
  passed (`231 files`).
- [x] Stage 1.13.3: write actions and validation. Add Application actions for
  setting and resetting tenant translation overrides; validate supported
  locale, language-file key existence, central non-overridable key rejection
  with stable domain error code, max value length, and same-tenant actor
  scope; emit structured success/failure logs and append-only audit records
  with before/after values; update `docs/DECISIONS.md` for the key-existence
  write rule. Result: added `SetTenantTranslationOverride` and
  `ResetTenantTranslationOverride` Application actions, stable
  `admin.translation_overrides.errors.*` domain codes with `hy`/`ru`/`en`
  translations, the `LanguageFileTranslationKeys` language-file-only
  existence checker, tenant-same-actor authorization, max value length, and
  set/reset audit records including before/after payloads. `docs/DECISIONS.md`
  now records the language-file key-existence rule. Verification: first `make
  test` failed because `LanguageFileTranslationKeys` needed an explicit
  binding to Laravel's `translation.loader`; after binding it, `make test`
  passed (`203 passed / 6 skipped / 2144 assertions`), `make stan` passed
  (`137/137`, `[OK] No errors`), and `make pint` passed (`238 files`).
- [x] Stage 1.13.4: centralized cache invalidation. Refactor override cache
  invalidation into one public tenant/locale write invalidation entry point
  that always refreshes/invalidates both the map layer and presence marker
  together; wire set/reset and model-event defence-in-depth through it; update
  `docs/DECISIONS.md` with the two-layer cache invariant; add immediate
  translation-helper tests for first-ever override, adding another override,
  editing an override, resetting to the language file, last-override reset,
  locale isolation, and tenant isolation. Result: replaced separate map and
  presence marker calls with
  `TenantTranslationOverrides::invalidateTenantLocaleAfterWrite()`, which
  clears the request-local tenant/locale map, forgets
  `tenant:{tenant_id}:translation_overrides:{locale}:v1`, and refreshes
  `tenant:{tenant_id}:translation_overrides:locales:v1` from the affected
  tenant's current override locales. Model saved/deleted events call the same
  entry point for defence-in-depth. Cache tests prove first-ever override,
  adding another override, editing, reset, last reset to empty marker,
  locale isolation, tenant isolation, and absence of the old public one-layer
  invalidators. `docs/DECISIONS.md` now records the two-layer cache invariant.
  Verification: `make pint` passed (`239 files`), `make stan` passed
  (`137/137`, `[OK] No errors`), and `make test` passed (`211 passed /
  6 skipped / 2168 assertions`).
- [x] Stage 1.13.5: output-safety proof. Inspect raw translation rendering
  paths; make any reachable raw path safe without changing translation
  resolution semantics; prove a stored override containing markup renders
  escaped through an ordinary rendering path. Result: raw translation rendering
  inspection found the reachable admin title path in
  `resources/views/layouts/admin.blade.php`; other translation render paths
  inspected were escaped Blade output or JSON encoding. The layout now escapes
  the default translated title and emits Blade shorthand title-section content
  without double escaping; those title sections are already escaped by Blade.
  `TenantTranslationOverrideOutputSafetyTest` stores a markup/script override
  for `admin.dashboard.title` and proves the real admin dashboard response
  contains escaped entities, not raw markup. Verification: focused output
  safety test passed (`1 passed / 3 assertions`), `make pint` passed (`240
  files`), `make stan` passed (`137/137`, `[OK] No errors`), and `make test`
  passed (`212 passed / 6 skipped / 2171 assertions`).
- [x] Stage 1.13.6: required gates, diff review, commit, push, and CI handoff.
  Run `make pint`, `make stan`, `make test`, `make fresh`,
  `make tenant-isolation-pgsql`, `make build`, `git diff --check`, `git
  status`, and full branch diff review versus `origin/main`; commit logical
  steps with matching worklog updates, push this branch only, collect CI run id
  and both job statuses, then stop without creating or merging a PR. Result:
  local final gates passed: `make pint` (`PASS 240 files`), `make stan`
  (`137/137`, `[OK] No errors`), `make test` (`212 passed / 6 skipped / 2171
  assertions`), `make fresh`, `make tenant-isolation-pgsql` (`22 passed / 76
  assertions`), `make build`, `git diff --check`, clean `git status`, and full
  branch diff review versus `origin/main` (`22 files changed, 1285
  insertions(+), 39 deletions(-)`) with no `docs/BLUEPRINT.md` or `template/`
  changes. Push and CI run id/job statuses are intentionally kept in the final
  report, not the worklog.

Tenant translation override editing-screen plan:
- [x] Stage 1.14.1: preconditions, inspection, and plan. Fetch/prune origin,
  fast-forward `main`, confirm `origin/main` is at least `9ed704e` and
  contains the read/write-side translation override services and permission,
  create `phase-2-stage-1.14-tenant-translation-ui` from fresh `origin/main`,
  inspect write actions/error codes, language-file key resolution, Menu admin
  route/Livewire/view conventions, permission gates, pagination, flash
  messages, and confirm modal usage, then write this plan before code. Blocked
  key presentation choice: hide non-overridable keys entirely because the
  operator is here to edit copy; showing safety/auth/destructive strings as
  read-only would add noise and imply they are candidates for change. Result:
  `git fetch origin --prune` succeeded; local `main` is exactly
  `9ed704e`; branch `phase-2-stage-1.14-tenant-translation-ui` was created
  from `origin/main`; required artifacts are present:
  `TenantAwareTranslator`, `NonOverridableTranslationKeys`,
  `LanguageFileTranslationKeys`, `TenantTranslationOverrides`,
  `tenancy.translation_overrides.manage`, `SetTenantTranslationOverride`, and
  `ResetTenantTranslationOverride`. Inspection confirmed the screen must call
  the existing set/reset actions, `LanguageFileTranslationKeys` currently only
  exposes existence checks through Laravel's loader/resolver, Menu admin uses
  route-level `can:*`, a thin controller, URL-backed Livewire state, shared
  Blade components, flash messages, pagination, and confirm modals, and no
  blueprint conflict was found.
- [x] Stage 1.14.2: authorization decision documentation. Add one dated
  `docs/DECISIONS.md` entry for the application-wide active-superadmin
  authorizer bypass: what it does, why it exists, blast radius, and that it
  deliberately does not bypass tenant scoping or branch/data visibility.
  Result: added the 2026-07-24 active superadmin bypass decision, documenting
  that the central Identity authorizer allows active `is_superadmin` users for
  dotted permission checks, while authentication, inactive-user denial,
  tenant-scoped models, PostgreSQL RLS, branch assignment, route-model
  isolation, and explicit same-tenant action validation still apply.
- [x] Stage 1.14.3: catalogue/read model. Add an Application/read-side service
  that flattens committed language-file string leaves for each supported
  locale, caches the per-locale catalogue with a deployment-aware version key,
  excludes `NonOverridableTranslationKeys`, overlays current tenant overrides
  through `TenantTranslationOverrides`, matches search case-insensitively by
  effective visible value plus key fragment, and returns a paginator/read model
  with effective value, key, overridden state, and all supported locale values.
  Result: added `LanguageFileTranslationCatalogue` with per-locale cache keys
  shaped as
  `app:language_file_translation_catalogue:{locale}:{fingerprint}:v1`, where
  the fingerprint uses locale language-file names, mtimes, and sizes so a
  deployment changing language files moves to a fresh cache key. Added
  `SearchTenantTranslationOverrides` and `TenantTranslationOverrideRow` to
  build editable rows from cached language catalogues plus the existing
  tenant/locale override cache layer, excluding non-overridable keys and
  matching the edited locale's effective value or the key fragment with
  Unicode-aware lowercase comparisons. Verification: focused catalogue test
  passed (`3 passed / 11 assertions`) and `make pint` passed (`244 files`, one
  provider style issue fixed).
- [x] Stage 1.14.4: admin route, navigation, Livewire adapter, and Blade UI.
  Add a permission-gated admin route and navigation link, a thin controller,
  URL-backed Livewire state for search/locale/page/editing row/value, shared
  `x-` components and confirm modal reset, translated flash messages, readable
  action validation errors, and redirects/state preservation after set/reset.
  The component must call `SetTenantTranslationOverride` and
  `ResetTenantTranslationOverride`; it must not write to
  `tenant_translation_overrides` directly. Result: added
  `/admin/translation-overrides` with `can:tenancy.translation_overrides.manage`,
  a thin controller, gated admin navigation, the `TranslationOverridesEditor`
  Livewire adapter, Blade UI using the admin layout and shared components, URL
  state for `q`, `locale`, and `page`, inline edit/reset flows that call the
  existing set/reset actions, readable component-local success/error messages,
  `hy`/`ru`/`en` strings, and non-overridable protection for this editor's own
  keys. Reset is presented as a guarded Livewire action rather than the shared
  destructive confirm modal because it removes only an override row and returns
  to a safe language-file default; archive/force-delete destructive semantics
  do not apply. Verification: focused editor test passed (`8 passed / 46
  assertions`) and `make pint` passed (`247 files`).
- [x] Stage 1.14.5: safety and behavior tests. Cover permission-gated view and
  write operations, visible-text search in Armenian/Russian/English, secondary
  key-fragment search, row content, edit/reset state preservation, crafted
  blocked-key write rejection, crafted cross-tenant write rejection, this
  screen's own strings being non-overridable, escaped output retention, and a
  bounded query-count render test independent of page result count. Result:
  extended `TenantTranslationOverrideEditorTest` to cover the permission-gated
  route/navigation, visible-text search in `hy`/`ru`/`en`, key-fragment search,
  effective value/key/overridden/all-locale row content, edit/reset through the
  existing actions while preserving `q`/`locale`/`page`, crafted blocked-key
  write rejection, crafted self-editor string write rejection, crafted
  cross-tenant write rejection through the action's same-tenant check,
  missing-permission route and write denial, and bounded render query count.
  Query evidence from the measured test path: narrow result render and full
  page render both used `3` total queries and `3` `tenant_translation_overrides`
  reads under the same cold-cache state. Verification: focused editor test
  passed (`9 passed / 55 assertions`).
- [x] Stage 1.14.6: local verification and HTTP smoke. Run focused tests,
  `make pint`, `make stan`, `make test`, `make fresh`,
  `make tenant-isolation-pgsql`, `make build`, and a no-host-PHP HTTP smoke
  that searches for a real visible string, edits it, confirms the changed text
  appears where that key is used, resets it, and confirms the language-file
  text returns. Result: local gates passed: `make pint` (`PASS 247 files`),
  `make stan` (`142/142`, `[OK] No errors`), `make test` (`224 passed / 6
  skipped / 2237 assertions`), `make fresh`, `make tenant-isolation-pgsql`
  (`22 passed / 76 assertions`), and `make build`. HTTP smoke used
  `northstar-manager` through real `curl` login, loaded
  `/admin/translation-overrides?locale=en&q=dashboard`, found
  `admin.dashboard.title`, saved `Smoke Dashboard Title` through the Livewire
  update endpoint, confirmed `/admin` rendered
  `<title>Smoke Dashboard Title</title>`, reset the override through the
  Livewire update endpoint, confirmed `/admin` rendered
  `<title>Dashboard</title>`, and removed the temporary smoke directory.
- [x] Stage 1.14.7: final diff review, push, and CI handoff. Run `git status`,
  `git diff --check`, full branch diff review versus `origin/main`, confirm no
  `template/` or unapproved `docs/BLUEPRINT.md` changes, push only
  `phase-2-stage-1.14-tenant-translation-ui`, collect CI run id and both job
  statuses for the final report, then stop without creating or merging a PR.
  Result: final pre-push review passed: `git status` clean, `git diff --check`
  clean, branch diff versus `origin/main` is `18 files changed, 1435
  insertions(+), 12 deletions(-)`, and no `docs/BLUEPRINT.md` or `template/`
  changes are present. Push and CI details are intentionally kept in the final
  report, not the worklog.
- [x] Stage 1.14.8: Alpine/Livewire expression escaping correction. Reproduce
  the owner-reported translation editor click failure by inspecting rendered
  JavaScript-evaluated attributes; identify the exact unsafe expression and a
  concrete key/value that break it; change the editor so click handlers carry
  only framework-encoded identifiers and resolve human-readable values
  server-side; audit the same defect class across Alpine, inline, and
  `wire:click` attributes including `x-row-overflow` and Menu; add regression
  coverage for apostrophe, quote, backslash, newline, non-ASCII, and
  HTML-looking values; record the client-expression gotcha in this worklog and
  AGENTS UI DoD; rerun required gates, rebuild the bundle, smoke the editor,
  push this branch only, and stop for owner browser re-check. Result: fixed
  the unsafe editor expression
  `wire:click='startEditing(@json($row->key), @json($row->effectiveValue))'`
  by passing only `Js::from($row->key)` and resolving the effective value
  server-side through `SearchTenantTranslationOverrides::rowForKey()`. A
  rendered value such as `admin.brand.tagline` = `Chef's dashboard` broke the
  old single-quoted JavaScript expression because the apostrophe terminated
  the HTML attribute; regression coverage now renders apostrophe, double
  quote, backslash, newline, non-ASCII, and HTML-looking values and asserts
  they are escaped in markup but absent from JavaScript-evaluated attributes.
  The same defect class was audited across `x-row-overflow`, searchable
  select, Menu archive mode, Menu integer handlers, and pagination handlers;
  searchable-select field ids and Menu archive-mode values now use `@js`,
  while integer IDs and fixed method-name variables were verified safe. Local
  verification passed: `make pint` (`PASS 247 files`), `make stan`
  (`142/142`, `[OK] No errors`), `make test` (`225 passed / 6 skipped /
  2269 assertions`), `make fresh`, `make tenant-isolation-pgsql` (`22 passed
  / 76 assertions`), and `make build` with bundles
  `public/build/assets/app-B3tq4fLr.css` and
  `public/build/assets/app-Blopmbua.js`. HTTP smoke passed through real curl
  login and Livewire update calls: found `admin.dashboard.title`, saved
  `<title>Smoke Dashboard Title</title>`, reset, and confirmed
  `<title>Dashboard</title>`.

## Next steps
Owner manual browser re-check of the translation editor edit click is the next
gate before merge authorization. Do not open a PR or merge until explicitly
authorized.
