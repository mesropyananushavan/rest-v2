# Decisions Log

Post-v1.0 decisions. Architecture lives in BLUEPRINT.md; this file records
operational/tooling decisions and blueprint amendments with their reasons.

## 2026-07-17 — PostgreSQL 17 instead of MariaDB
Decision: primary database is PostgreSQL 17.
Reason: JSONB + GIN indexes fit the JSON translation value objects;
Row-Level Security planned as a second enforcement layer for tenant
isolation; stronger analytics capabilities for reporting phases.
Rejected: MariaDB (team's operational familiarity) — outweighed by the
above for a greenfield multi-tenant SaaS.
Blueprint amended: ADR-001, ADR-003 note, section 8.

## 2026-07-17 — Horizon placeholder
Decision: `horizon` compose service runs `php artisan queue:work` until
Laravel Horizon ships a Laravel 13-compatible release.
Reason: no installable compatible Horizon version exists in Composer
metadata at this date. Revisit before Phase 3 (fiscal/print queues).

## 2026-07-17 — Structured JSON logging foundation
Decision: all application logs use the dedicated `json` channel by default,
with request correlation and tenant/branch/user/module context shared once
through middleware and restored for queued jobs via queue payload context.
Reason: every user action must be traceable across web requests, domain
events, and background jobs without each log call manually duplicating
context fields.
Rejected: ad-hoc context arrays in individual log calls — too easy to omit
or leak sensitive values; plain text logs — harder to query and correlate.

## 2026-07-17 — Composer platform pinned to PHP 8.3
Decision: Composer `config.platform.php` is pinned to `8.3.32`.
Reason: the runtime contract is PHP 8.3, but Composer executed under newer
PHP images can resolve Symfony 8.1 packages that require PHP 8.4.1. Pinning
the platform keeps the lock file installable in the project PHP-FPM image
and CI runtime.
Rejected: relying on the Composer image PHP version — it is unstable over
time and can silently drift beyond the application runtime.

## 2026-07-20 — Tenant header policy limited to dev/test
Decision: tenant resolution order is authenticated user, then session, then
`X-Tenant-ID`; the header is accepted only outside production and never
overrides an authenticated user's tenant.
Reason: local and test workflows need an easy tenant selector before the
login-first UI exists, but production isolation must be bound to trusted
authentication/session state.
Rejected: allowing `X-Tenant-ID` in production or giving it precedence over
authenticated users — both would make tenant spoofing possible.

## 2026-07-20 — Tailwind CSS as admin UI foundation
Decision: admin UI styling moves from Bootstrap to Tailwind CSS through the
official Vite plugin, with SmartRest design tokens maintained in the Tailwind
theme.
Reason: Tailwind fits the Livewire/Alpine ecosystem, gives SmartRest a more
custom product look than Bootstrap defaults, and keeps colors, radius,
spacing, and shadow decisions centralized in a tokens model.
Rejected: continuing Bootstrap 5 — fast for scaffolding but pushes the admin
UI toward generic layouts and couples interactive components to Bootstrap JS.

## 2026-07-20 — Livewire and Alpine as admin interaction layer
Decision: admin UI interactions use Livewire 4 with its Vite-bundled Alpine
runtime; `@livewireScriptConfig` is rendered by the admin layout and the
single JavaScript entry starts Livewire.
Reason: Livewire keeps server-rendered Blade as the primary UI model while
adding real HTTP-driven interactive components, and Alpine is the right
lightweight layer for local disclosure/modal behavior without a SPA framework.
Rejected: React/Vue/Angular SPA foundations — unnecessary for current
restaurant admin workflows and heavier than the server-rendered product
principles require; Bootstrap JS — tied to the outgoing Bootstrap UI stack.

## 2026-07-20 — Menu archive and restore cascade policy
Decision: menu category and item deletion in the product is archive
(`deleted_at`) rather than physical deletion for normal workflows. Users with
the relevant Menu manage permission may archive categories/items. Viewing
archived records, using the `show_archived` list state, restoring archived
records, and permanently deleting archived records are superadmin-only. If a
non-superadmin requests `show_archived=1`, the parameter is ignored and the
normal non-archived list is rendered. Archiving a category also archives its
currently non-archived child items and marks those items as archived by that
category cascade. Restoring the category restores only items carrying that
cascade marker; items archived independently before the category archive
remain archived. An item cannot be restored while its category is still
archived. Force deleting an archived category permanently deletes that
category and its archived child items.
Reason: Menu catalog records can be referenced by historical orders later, so
retaining rows preserves history and tenant isolation while still removing
records from normal workflows. Archive visibility is limited to superadmins to
avoid operational users working around the archive state or seeing maintenance
data. Ignoring `show_archived` for non-superadmins keeps the normal Menu index
accessible by manage permission while preventing archive disclosure. The
explicit cascade marker avoids relying on timestamp equality and prevents
accidental restoration of items that a manager had intentionally archived
before the category was archived.
Rejected: physical deletion from admin UI — unsafe for catalog history;
restoring every item in a category — would revive independently archived
items; inferring cascade membership from matching `deleted_at` timestamps —
fragile and hard to reason about under retries or concurrent requests;
returning 403 for `show_archived=1` from non-superadmins — more disruptive
than needed because the same index page is otherwise valid for their normal
workflow.

## 2026-07-20 — Menu item image storage and processing
Decision: menu items have two optional image slots: `internal_image` for staff
UI/POS usage and `public_image` for future guest QR menu usage. Each slot
stores nullable JSON metadata on `menu_items` (`path`, `thumbnail_path`,
dimensions, MIME type, and byte size), while files are stored through Laravel
Storage using the configured `MENU_IMAGES_DISK` and tenant-scoped
`MENU_IMAGES_PATH_TEMPLATE` (`tenants/{tenant_id}/menu/items/{item_id}/{slot}`
locally on the `public` disk). The local public disk uses a relative
`FILESYSTEM_PUBLIC_URL=/storage` by default so Docker ports do not leak into
stored/rendered URLs; deployments may set an absolute CDN/object-storage URL
without changing application code. UI code resolves URLs through Storage only and
uses one shared placeholder asset when a slot is empty. Replacing or removing
an image deletes the old original and thumbnail files; archiving/restoring a
menu item keeps files; superadmin force delete removes the files with the
record. Image processing uses `intervention/image-laravel` 4.x with bounded
synchronous resizing for the current admin upload flow: originals are resized
to configured maximum dimensions and list thumbnails are generated at upload
time. Queue-based thumbnail generation remains an implementation option if
measured HTTP latency becomes noticeable.
Reason: JSON metadata avoids adding query-only columns because images are not
filtered or sorted, while keeping enough information to render and clean up
files safely. Configured disk/path access keeps local development on the
`public` disk but allows moving to S3-compatible storage without changing
application code. Intervention Image is popular, maintained, supports PHP 8.3
and Laravel 13 through the Laravel integration package, and works with GD now
while allowing Imagick/libvips later. The PHP runtime image installs GD with
jpeg/png/webp support so local tests, uploads, and resizing use the same
driver.
Rejected: storing public URLs in the database — ties records to a specific
host/disk and makes S3 migration harder; storing binary images in PostgreSQL —
unnecessary database bloat and slower backup/restore paths; custom GD-only
processing — more brittle than a maintained image library; queuing every
thumbnail immediately — extra operational complexity before this bounded admin
upload path shows measurable latency.

## 2026-07-21 — Menu global search indexing
Decision: Menu item global live search will use PostgreSQL `pg_trgm` with a
GIN expression index over a normalized lower-case concatenation of the
supported localized JSONB name values (`hy`, `ru`, `en`). Queries stay
server-side and tenant/branch-scoped, combine the trigram search predicate with
composite btree indexes for `tenant_id`, `branch_id`, `category_id`,
`deleted_at`, `active`, and `sort_order`, and remain paginated. Existing
`translated_name` JSONB remains the source of truth; no separate mutable search
column is introduced.
Reason: the critical user workflow is contains-style search across about
20000 items per tenant on tablets. A trigram expression index supports fast
`LIKE`/`ILIKE` matching without loading all rows or duplicating localized names
into an application-maintained column. Keeping JSONB as the only stored name
avoids sync bugs and preserves the existing localized value-object model.
Data migration: no row backfill is required for existing menu items because
the index is computed from current JSONB values when the migration creates it.
Rejected: querying JSONB names with unindexed `ILIKE` scans — unacceptable at
1000 tenants and millions of rows; a manually maintained normalized
`search_text` column — faster to query but adds write-path coupling and
backfill/sync failure modes before measured need; full-text search only —
less suitable for short substrings, partial dish names, and multilingual
operator input.

## 2026-07-21 — Menu category searchable combobox (revised 2026-07-23)
Decision: Menu category selection uses one shared Alpine combobox backed by a
shared JSON endpoint with server-side, debounced, paginated category lookup. It
applies to both category parent selection (`parent_id` on the plain Blade
category form) and item category selection (`category_id` inside the Livewire
Menu item form). This replaces the earlier item-form-only wording that named a
Livewire-specific combobox; Livewire remains an adapter around the same Alpine
widget and JSON result contract, not a separate selection implementation. No
third-party UI library is added for this stage.
Scope: `menu_categories` has no `branch_id`, so category lookup is scoped by
tenant and archive state only. There is no branch-level database filter for
category options. Item creation/update remains branch-owned through the item
Application actions and existing branch context.
Reason: the expected category scale is about 200 categories per tenant, which
does not justify adding an npm widget dependency. One JSON endpoint plus one
Alpine widget keeps behavior identical across plain Blade and Livewire forms,
keeps filtering server-side, and avoids two diverging adapters for the same
selection UX.
Rejected: rendering all categories in a native select — poor tablet UX and
violates the Part C scope; installing Tom Select or another widget now —
unnecessary dependency surface until a measured accessibility or behavior gap
appears that Alpine cannot cover cleanly; separate Blade and Livewire
combobox implementations — duplicate behavior and create avoidable drift risk.

## 2026-07-22 — Menu category hierarchy uses parent_id subcategories
Decision: Menu hierarchy is strictly three levels: root category -> subcategory
-> item. `menu_categories` will use a self-referencing nullable `parent_id`
adjacency list. Root categories have `parent_id = null`; subcategories have
`parent_id` pointing to a root category. `menu_items.category_id` must reference
a subcategory row, not a root category. Items cannot be attached directly to
root categories.
Reason: a single `menu_categories` table preserves the existing localized name,
active/archive, sort, tenant scope, search, and permission model for both
categories and subcategories. It avoids duplicating CRUD, archive/restore
cascade behavior, trgm search indexes, translations, and UI code across separate
category and subcategory tables. Requiring items to live only under
subcategories keeps the master-detail UI and query paths unambiguous at scale.
Rejected: a separate `menu_subcategories` table, because it duplicates category
behavior and complicates search, archive cascade, restore, and item forms
without adding enough value. Rejected allowing items directly under root
categories, because it creates ambiguous UI sections and more complex
query/index paths.

Depth invariant: category depth is exactly two category levels: root and
subcategory. A row with `parent_id !== null` cannot have children, so creating a
subcategory under another subcategory is forbidden. This rule lives in
Application actions and tests because PostgreSQL CHECK constraints cannot
validate parent-of-parent state without a trigger. The database enforces only
the self-referencing FK `menu_categories.parent_id -> menu_categories.id` and a
CHECK preventing `parent_id = id`.

Archive invariants:
- Root cascade: archiving a root category archives its non-archived
  subcategories and those subcategories' currently non-archived items, marking
  descendants with the root-category cascade marker. Restoring that root
  category restores only descendants carrying that cascade marker; subcategories
  or items archived independently before the root archive remain archived.
- Subcategory cascade: archiving one subcategory without archiving its root
  archives only that subcategory's currently non-archived items, marking those
  items with that subcategory's cascade marker. Restoring that subcategory
  restores only items carrying that subcategory cascade marker and does not
  affect sibling subcategories or their items.
- Force-delete: force-deleting an archived root category permanently deletes the
  entire archived subtree under it, including archived subcategories and
  archived items. Force-delete remains superadmin-only and irreversible.

Query/index notes: query and index work must cover `tenant_id`, `parent_id`,
`deleted_at`, `active`, `sort_order`, selected-root category paths,
selected-subcategory item paths, and global item search. Menu search/index tests
for PostgreSQL trgm/GIN behavior must run on PostgreSQL, not only SQLite.

Blueprint amendment required: `docs/BLUEPRINT.md` currently documents `Category
-> Item` and `menu_categories` without `parent_id`; update the blueprint
separately with explicit owner approval before or with the schema change.

## 2026-07-22 — Runtime database role must not bypass RLS
Decision: runtime application traffic uses a dedicated unprivileged role
`smartrest_app` with no `SUPERUSER` and no `BYPASSRLS`; privileged role
`smartrest` is used only for migrations and admin database operations.
Implementation is deferred until the gate below.
Reason: tenant isolation is a security boundary, not only an ORM convention.
PostgreSQL row-level security policies and `FORCE ROW LEVEL SECURITY` must be
exercised by the same role that serves application traffic. Using a superuser or
`BYPASSRLS` role for runtime traffic bypasses those policies for raw SQL.
Gate: RLS role separation MUST be implemented before the first real restaurant
or tenant is onboarded onto v2 in production. Until then, tenant isolation
relies solely on Eloquent global scopes, which is acceptable only while there
are no live tenants.
Operational note: `smartrest_app` was mentioned earlier in the Phase 1 worklog,
but current migrations and configuration do not create or use it. Current
`docker-compose.yml`, `.env.example`, and `config/database.php` point
application runtime traffic at `smartrest`.

## 2026-07-23 — Branch header policy requires assignment authorization
Decision: branch resolution ignores `X-Branch-ID` in production. Outside
production, branch candidates are considered in header, session, then first
assigned-branch order, but an authenticated user may resolve only branch ids
returned by the Identity `UserDirectory` assignment contract. Explicit
unauthorized header candidates return 404; stale unauthorized session
candidates are forgotten with a warning and fall back to the first assigned
branch if one exists.

## 2026-07-23 — Halls and Tables schema staged before Blueprint section 4 amendment
Decision: the existing Tables module owns both `halls` and `tables`. Halls are
branch-owned operating areas with `tenant_id`, `branch_id`, localized
`translated_name`, `color`, `sort_order`, `active`, soft delete, tenant/branch
indexes, and PostgreSQL RLS. Tables are branch-owned service locations nested
under a hall with direct `tenant_id`, `branch_id`, `hall_id`, localized
`translated_name`, simple constrained `type` (`standard`, `vip`), constrained
`shape` (`circle`, `square`, `rectangle`), nullable `hdm_department`,
`is_delivery`, `sort_order`, `active`, soft delete, PostgreSQL RLS, and an
explicit `archived_with_hall_id` cascade marker. Archiving a hall archives only
its currently non-archived tables and marks them with `archived_with_hall_id`;
restoring a hall restores only tables carrying that marker; independently
archived tables remain archived; force-deleting an archived hall permanently
deletes its archived tables. Hall cascade audit rows record affected table
counts on the hall audit row and do not emit per-table audit rows.
Reason: Blueprint v1.0 names Halls & Tables in the module map, ER diagram, and
endpoint groups, but section 4 currently has no `halls` or `tables` row. The
legacy settings screens show hall-contained table management with labels such
as `1`, `VIP`, `T1`, a table name input, shape selector, HDM department,
delivery flag, table type concepts, commission fields, and floor-plan geometry.
The current stage needs stable table identity and hall membership for later
table-board/orders work, while preserving tenant/branch isolation and archive
cascade semantics. Localized table names follow the existing JSON value-object
convention because table labels are human-facing and examples are not purely
numeric.
Rejected: creating a separate module, because the Blueprint module map already
places halls and tables together; deriving `tenant_id`/`branch_id` only through
the hall, because branch filtering must remain explicit like Menu items;
inferring hall cascade membership from timestamps, because Menu already proved
an explicit marker is safer; adding `table_types`, floor-plan coordinates,
commission/pricing metadata, subtables, orders, or table-board state now,
because those are later Blueprint stages. Blueprint amendment pending owner
approval: section 4 should add Halls and Tables rows matching the schema above;
`docs/BLUEPRINT.md` was intentionally not edited in this stage.

## 2026-07-23 — ADR-009 audit writes are cross-cutting append-only records
Decision: audit writes live in `app/Support/Audit`, not a new module. Blueprint
section 4 lists `audit_logs` under Reporting/Admin because that future module
will own audit reads, filtering, reporting, and exports. Writes remain
cross-cutting because every mutable Application action across modules must be
able to record its own audit row without importing another module's internals.
Reason: audit recording is infrastructure like structured logging: it must be
available from Menu now and Halls/Tables, Orders, Payments, and Administration
later while preserving module-boundary tests.
Append-only enforcement: `audit_logs` has no `deleted_at`, the model does not
use `SoftDeletes`, model update/delete events throw, and database triggers
reject `UPDATE` and `DELETE`. Audit foreign keys use restrictive delete
behavior rather than cascades or null-on-delete because audit rows must never
be mutated by related-record cleanup.
Transaction rule: an audit insert is part of the same database transaction as
the mutation it records. If the mutation rolls back, its audit row rolls back;
if audit recording fails, the mutation rolls back too. Database inserts are not
external effects, so this does not conflict with ADR-008.
Device id: omitted for now even though ADR-009 mentions it, because the
Administration module and device registry do not exist yet. The field should
be added with that module when device identity is defined.
Action naming: audit actions are stable dotted lowercase past-tense strings,
module-prefixed and singular by target. Current Menu actions are
`menu.category.created`, `menu.category.updated`, `menu.category.archived`,
`menu.category.restored`, `menu.category.permanently_deleted`,
`menu.item.created`, `menu.item.updated`, `menu.item.archived`,
`menu.item.restored`, `menu.item.permanently_deleted`,
`menu.item.activity_toggled`, `menu.item.image_replaced`, and
`menu.item.image_removed`.
Redaction rule: `before_json` and `after_json` always pass through the shared
`Redactor` before storage. Passwords, tokens, secrets, credentials, and card
values must not be stored. Menu item images store only existing metadata such
as path, thumbnail path, MIME type, dimensions, and byte size; binary image
content is never stored in audit JSON.
Reason: branch context filters branch-owned operational data, so a request
header must not let an authenticated user switch into an unassigned branch
inside the same tenant. Local and test workflows still need unauthenticated
header-based context before login, while production must trust only
authentication/session state and branch assignments.
Rejected: allowing `X-Branch-ID` in production or trusting same-tenant branch
ids without assignment checks — both would leak branch-scoped data; returning
403 for unassigned header branches — inconsistent with existing tenant/branch
isolation and the branch-switch controller's 404 behavior; aborting on stale
session branch ids — too disruptive for users whose assignments changed after
their session was created.

## 2026-07-23 — PostgreSQL extensions are privileged provisioning
Decision: PostgreSQL extensions such as `pg_trgm` are provisioned by a
privileged database role before unprivileged runtime/test traffic runs
migrations. Migrations that depend on an extension must tolerate that
pre-provisioned state by checking `pg_extension` before attempting
`CREATE EXTENSION`, while still creating the extension when a privileged local
migration role runs against a fresh database.
Reason: the PostgreSQL tenant-isolation CI job exists to exercise RLS with a
non-superuser, non-`BYPASSRLS` app role. Database-level extension creation is a
privileged operation and must not be smuggled into that runtime role just to
make migrations pass.
Rejected: granting `CREATE ON DATABASE` to `smartrest_app` — it weakens the
runtime role and hides privilege drift; switching the pgsql CI job back to the
privileged `smartrest` role — it bypasses the RLS condition the job is meant
to prove; removing the trigram indexes from the migration — it changes the
Menu search schema rather than fixing provisioning.

## 2026-07-23 — Codex may manage PR merge flow before launch
Decision: Codex may create feature branches from updated `main`, push feature
branches to `origin`, create pull requests against `main`, and merge pull
requests into `main` with merge commits when the task authorizes that release
flow. Codex still must not force-push, rewrite history, delete branches, push
directly to `main`, squash/rebase merge, bypass required checks, merge a pull
request before CI is green on the exact head SHA, deploy, tag, or touch
credentials/production systems.
Reason: SmartRest v2 is pre-production, has one developer/owner, no live
tenants, and CI gates now cover quality plus PostgreSQL tenant isolation.
Owner-only merges were adding latency while the active risk is better
controlled by exact-SHA CI and merge commits.
Conditions: repository-root workspace boundaries remain enforced; all feature
work still goes through pull requests; every merge requires fully green CI on
the exact head SHA being merged; irreversible operations remain forbidden.
Rejected: owner-only PR creation and merge for every stage, because merge
latency became the bottleneck for low-risk pre-production changes; full
autonomy including force-push, history rewriting, branch deletion, and direct
`main` pushes, because irreversible operations can destroy reviewability and
recovery.

## 2026-07-23 — Admin API starts with session auth and page pagination
Decision: the first `/api/v1` endpoint uses the existing Laravel session guard
plus the same tenant, branch, and permission middleware as the admin UI. Token
authentication is deferred and is required before any write endpoint, third
party client, guest QR client, or display client is added. `GET
/api/v1/menu-items` uses page pagination with
`meta.pagination.current_page`, `per_page`, `total`, `last_page`, `from`, `to`,
and `has_more_pages`; cursor pagination remains deferred for live feeds and
large append-only logs. The API route uses `throttle:60,1`.
Reason: this slice is read-only, session-authenticated, and exists to close the
Phase 1 walking-skeleton API proof for the same branch manager who already uses
the admin UI. Reusing session auth and the hardened Stage 1.12 tenant/branch
middleware avoids introducing a token package before the first write or
external-client API actually needs it. Page pagination matches the current
admin resource-list behavior, while the 60 requests/minute limit is
conservative for a human-operated admin list.
Rejected: installing Sanctum now, because token auth is not needed for this
GET-only admin slice and would add package/configuration surface before write
or external clients exist; cursor pagination for this resource list, because
the existing Menu Application actions and admin tables are page-based; leaving
the API unthrottled, because even read-only endpoints should have a default
abuse guard.

## 2026-07-23 — Halls schema for Stage 1.17
Decision: the Halls vertical slice creates a new `Tables` module directory and
a branch-owned `halls` table with `tenant_id`, `branch_id`,
`translated_name`, `color`, `sort_order`, `active`, soft delete timestamps,
and normal timestamps. The table has tenant and branch indexes plus composite
tenant/branch/archive/sort lookup indexes, uses the tenant Eloquent global
scope, filters every Application query by the resolved branch, and has the
standard PostgreSQL `halls_tenant_isolation` RLS policy. Mutations are audited
with `tables.hall.created`, `tables.hall.updated`,
`tables.hall.archived`, `tables.hall.restored`, and
`tables.hall.permanently_deleted`.
Reason: Blueprint section 4 omits a concrete Halls/Tables data model but does
state that Halls belong to Branches and that tenant-owned tables use global
columns and soft delete where restoration is valid. Legacy
`template/rooms-hall.html` shows halls with localized names, colors, sort
order, edit/archive controls, and a future preparation-place selector;
`rooms-tables.html` and `rooms-hall-planning.html` use hall names and colors
for operational board filters and visual grouping. These sources justify
`translated_name`, `color`, `sort_order`, `active`, and `deleted_at` now.
Deferred fields: floor containers, floor-plan geometry, preparation-place
foreign keys, table counts, table shapes, commission metadata, and table
relationships are not included because Stage 1.17 is Halls only and no
`tables`, floors, preparation-place, or Orders schema exists yet. The owner
must approve a separate Blueprint section 4 amendment matching this schema;
`docs/BLUEPRINT.md` is intentionally not edited in this task.
Rejected: creating a tenant-wide hall table, because the ER diagram says
Branches have Halls; adding a `tables` table or floor-plan geometry now,
because that violates the Halls-only scope; storing preparation place as free
text, because it would likely conflict with the future kitchen/printing
domain; creating a new Reporting/Admin audit module, because audit writes are
already cross-cutting and Reads remain future Reports & Analytics work.

## 2026-07-24 — Menu scale backend proof keeps JSONB trigram search
Decision: the Menu backend scale slice keeps `translated_name` JSON/JSONB as
the source of truth and uses the existing PostgreSQL `pg_trgm` GIN expression
indexes over the lower-case concatenation of `hy`, `ru`, and `en` localized
name values for contains-style item/category search. Runtime read paths go
through paginated Application queries, with `BrowseMenuItems` as the coherent
item-list facade for category context, global search, active filtering, archive
mode, stable ordering, and page pagination. Load-test rows are marked with
nullable `load_test_key` columns and narrow purge indexes so local scale data
can be regenerated without touching DemoSeeder or human rows.
Reason: measured Menu search must support multilingual partial operator input
around tens of thousands of rows per tenant without a denormalized mutable
search column or in-memory filtering. The current trigram expression strategy
matches that workflow, stays compatible with the existing localized value
object model, and lets SQLite tests keep a driver-aware LIKE fallback while
PostgreSQL proves real index usage.
Rejected: unindexed JSONB `ILIKE` scans, because they do not scale at the
target tenant/row counts; a generated or application-maintained `search_text`
column, because it adds write-path synchronization and backfill risk before
measurements require it; full-text search alone, because short substrings and
partial multilingual dish names are a better fit for trigrams; caching the Menu
list/search paths, because this slice is intended to prove query and index
shape rather than hide slow scans.

## 2026-07-24 — Menu item read paths temporarily remain split
Decision: the JSON API item list now goes through `BrowseMenuItems`, while the
existing Livewire `MenuIndex` screen remains on its current direct calls to
`ResolveMenuCategorySelection`, `PaginateMenuCategories`, `PaginateMenuItems`,
and `SearchMenuItems` for this correction session. Convergence is deferred; the
planned end state is one `BrowseMenuItems` read facade used by both API and
Livewire adapters.
Reason: this session is proving that the previous backend-scale refactor did
not change user-visible behavior. Moving the Livewire adapter to the new facade
in the same proof session would invalidate that evidence by changing the path
being characterized. The new tests named `characterizes current search and
category context semantics`, `keeps menu index category render query count
independent of rendered result size`, and `keeps menu index search render query
count independent of rendered result size` pin the current Livewire behavior so
a later convergence change has a safety net.
Rejected: migrating Livewire to `BrowseMenuItems` now, because it would combine
behavior proof with adapter convergence; leaving the divergence undocumented,
because future reviewers would not know whether the split is intentional or an
accidental regression.

## 2026-07-24 — Menu adapters use one BrowseMenuItems read path
Decision: supersede the temporary split-read-path decision above. The JSON API
item list and Livewire `MenuIndex` now both obtain Menu read data through
`BrowseMenuItems`. The facade keeps the API's strict `category_id` item-filter
contract and the UI's category selection-state normalization as separate
adapter modes over the same Application query actions.
Reason: one facade removes duplicated archive-mode gating and read-path
orchestration while preserving the already-characterized API and UI behavior.
The Livewire characterization tests from the temporary split session remain the
safety net: they passed unchanged after convergence, and query-count invariance
still holds for both adapters.
Rejected: moving query orchestration back into Livewire, because adapters must
stay thin; forcing the API and UI to share one category-id semantic, because
strict API filters and UI selection-state fallback are different public
contracts; changing API response shape or Menu markup, because this session is
a behavior-preserving refactor.

## 2026-07-24 — Menu load commands have separate safety contracts
Decision: `menu:load-test-data` is the repeatable demo-tenant scale-data command;
it only targets existing demo tenants and purges rows marked by its own
`load_test_key`. `menu:seed-load` is the broader local PostgreSQL synthetic-load
command; it creates load tenants with `seed_source = load` and may recreate only
the guarded local SmartRest database when `--fresh` is used in
`production-like` mode. `--force` no longer bypasses the local/testing
environment guard or local-database assertion; it only suppresses the
schema-recreation confirmation.
Reason: the two commands serve different measurement needs. Demo-tenant data is
for repeatable UI/API scale checks after `make fresh`, while synthetic tenants
are for planner/cardinality experiments such as 200-tenant category-panel
measurements. Schema recreation must remain an explicit local-dev operation and
must never become available through an environment-bypass flag.
Rejected: deleting `menu:seed-load`, because it remains useful for
multi-tenant planner evidence; letting `--force` bypass environment or database
guards, because that could run load tooling against the wrong database; merging
both commands, because demo-row idempotency and synthetic-tenant generation have
different cleanup semantics.

## 2026-07-24 — Menu load-test markers are dev/test tooling only
Decision: `menu_categories.load_test_key` and `menu_items.load_test_key` are
dev/test-tooling columns used only by `menu:load-test-data` to make generated
rows idempotent and purgeable without touching DemoSeeder or human data. The
columns stay on the tenant-owned Menu tables because the purge boundary must
follow the rows being generated and deleted, but they are hidden from Eloquent
serialization, excluded from mass assignment, not cast, not appended, and not
returned by Menu API resources or rendered views.
Reason: marker columns keep local scale data deterministic and safely removable
without introducing a second tracking table whose lifecycle could drift from
tenant-owned Menu rows. Treating the markers as tooling metadata preserves the
runtime API/UI contract while still giving the load generator an auditable
cleanup key.
Exit path: if generated-row metadata becomes broader than local dev/test
tooling, move it to a dedicated internal metadata table keyed by table name,
row id, tenant id, and generator name, then backfill/purge the marker columns in
a separate owner-approved migration.
Rejected: exposing marker values in resources or model serialization, because
they are not product data; removing or renaming the columns in this correction
session, because the review explicitly keeps them and asks only to contain
their exposure.

## 2026-07-24 — Keep the Menu category panel tenant-leading index
Decision: keep the unmerged `menu_categories_tenant_parent_deleted_sort_id_idx`
index from the Menu scale branch. On a local PostgreSQL dataset with 200
synthetic load tenants, each with one active root category, one subcategory, and
one item, the exact `PaginateMenuCategories` active panel query used the
tenant-leading index for the root count and root page select. The eager-loaded
child query continued to use the existing `menu_categories_parent_id_idx`.
Evidence before the keep decision, after `ANALYZE`: root count used
`Index Only Scan using menu_categories_tenant_parent_deleted_sort_id_idx`
with execution time `0.196 ms`; root page select used
`Index Scan using menu_categories_tenant_parent_deleted_sort_id_idx` with
execution time `0.195 ms`; child eager-load used
`Index Scan using menu_categories_parent_id_idx` with execution time
`0.087 ms`. Evidence after the keep decision and a repeat `ANALYZE`: root
count used the same `Index Only Scan` with execution time `0.203 ms`; root
page select used the same `Index Scan` with execution time `0.100 ms`; child
eager-load used `menu_categories_parent_id_idx` with execution time `0.078 ms`.
Reason: the prior two-tenant measurement was too small to validate the
tenant-leading path. At realistic multi-tenant cardinality, the planner chooses
the composite index for the root panel access pattern, so removing it would
discard measured protection against tenant-wide category scans.
Rejected: removing the migration, because the intended index is chosen by the
real read-model SQL at 200 tenants; adding another panel index, because the
current root and child panel statements already use indexes and no sequential
scan remains on the measured path.

## 2026-07-24 — Supersede and remove the Menu category panel tenant-leading index
Decision: supersede the earlier 2026-07-24 keep decision and remove the
unmerged `menu_categories_tenant_parent_deleted_sort_id_idx` migration from
this branch. The representative combined dataset reached `102` tenants,
`10042` root categories, `20407` category rows, and `200007` item rows in one
local PostgreSQL database state. On that data, dropping the new index did not
hurt the real `PaginateMenuCategories` panel path because the existing
`menu_categories_tenant_parent_deleted_active_sort_id_idx` served the same
active root predicates.
Evidence: with the new index present, panel root count used
`Index Only Scan using menu_categories_tenant_parent_deleted_sort_id_idx`,
estimate/actual `98/100`, execution `0.261 ms`; root page used a
`Bitmap Heap Scan` with `Bitmap Index Scan on
menu_categories_tenant_parent_deleted_sort_id_idx`, estimate/actual `98/100`
before limit, execution `0.753 ms`; child eager-load used
`menu_categories_parent_id_idx`, estimate/actual `1/25`, execution `0.319 ms`.
With only existing indexes, root count used `Index Only Scan using
menu_categories_tenant_parent_deleted_active_sort_id_idx`, estimate/actual
`98/100`, execution `0.141 ms`; root page used a `Bitmap Heap Scan` with
`Bitmap Index Scan on menu_categories_tenant_parent_deleted_active_sort_id_idx`,
estimate/actual `98/100` before limit, execution `0.674 ms`; child eager-load
again used `menu_categories_parent_id_idx`, estimate/actual `1/25`, execution
`0.267 ms`.
Reason: the earlier keep decision was based on two unrepresentative states:
one state had high per-tenant item rows but only two tenants, while the later
state had 200 tenants but only 400 category rows and 200 item rows. Those
measurements did not prove the panel path under many tenants, many roots per
tenant, and a large item table at the same time. The representative same-data
comparison shows the new index is redundant and no material improvement exists.
Rejected: keeping both tenant-leading category indexes, because the measured
path already has equivalent active-panel coverage through the existing index;
removing the existing active index, because it predates this branch and also
covers active-state category paths beyond the narrow panel comparison.
