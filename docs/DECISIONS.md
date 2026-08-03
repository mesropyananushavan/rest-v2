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

## 2026-07-24 — Tenant UI translation overrides read layer
Decision: UI translation strings resolve through a tenant-level database table
layered over the existing Laravel language files. Resolution order is active
locale tenant override, active locale language file, tenant default locale
tenant override, tenant default locale language file, then English language
file. Overrides are stored at tenant level only; there is no branch column.
Cache keys follow the established tenant-leading convention as
`tenant:{tenant_id}:translation_overrides:{locale}:v1`, so the future write path
can invalidate one tenant/locale directly after a change.
Reason: restaurant operators need tenant-specific wording without redeploying
code or editing container files. Database storage keeps overrides auditable,
tenant-scoped, cacheable, and editable by a future permission-gated UI. Tenant
level matches the settled product decision: branch-specific wording would
increase operational complexity and cache cardinality without a current need.
Hook choice: extend Laravel's translator rather than replacing the translation
loader. A translator subclass can preserve ordinary `__()`/`trans()` call sites,
replacement parameters, pluralization through the existing message selector, and
the separate `LocalizedText` JSON value-object behavior while inserting tenant
override checks into string resolution. Replacing the loader was rejected
because loaders operate per locale/group, do not naturally know the requested
flat key or tenant fallback sequence, and would either merge unsafe overrides
into loaded groups or require more invasive call-site changes for pluralization
and fallback behavior.
Non-overridable rule: authentication/login strings, authorization and
permission-denied messages, destructive confirmation copy and labels for
archive/restore/force-delete, safety-warning wording, and the tenant
translation override editor's own labels/actions/help text are deliberately not
overridable. Their wording carries security, permission, irreversible-action, or
self-recovery meaning; allowing tenants to soften or hide it would weaken safe
operation, and allowing the editor to override itself could make the reset path
unreadable. The read resolver centrally ignores database rows for those keys,
and write actions reject them, so the edit UI cannot make them affect
rendering.
Write rule: set/reset actions reject any translation key that does not resolve
to a string in the committed `hy`/`ru`/`en` language files. Junk keys are not
stored "for later" because they cannot be reviewed in context, cannot be safely
classified against the non-overridable registry, and would make future editing
UI behave like a free-form key/value store instead of a controlled UI wording
override.
Cache invariant: the read side uses two cache layers. The override map
`tenant:{tenant_id}:translation_overrides:{locale}:v1` holds the tenant/locale
key-value rows, while the presence marker
`tenant:{tenant_id}:translation_overrides:locales:v1` stores which locales have
any overrides so zero-override tenants/locales avoid an added database read.
Every successful write invalidation for a tenant/locale must refresh both
layers together through the single cache service entry point; invalidating only
the map can leave a first-ever override hidden until the stale empty presence
marker expires, and invalidating only the presence marker can leave stale
override values visible.

## 2026-07-24 — Active superadmin bypass in the Identity authorizer
Decision: active users with `is_superadmin = true` are allowed by the central
Identity `Authorizer` for every dotted permission code checked through Laravel
Gate, even when their tenant role does not carry that explicit permission. The
bypass lives in the authorizer rather than in individual policies or screens, so
the same rule applies to admin routes, Livewire actions, controllers, and
Application actions that call the Identity contract.
Reason: platform superadmins need break-glass operational access for tenant
configuration and maintenance without copying every new tenant permission onto a
role first. A central rule avoids one screen accidentally treating superadmin
differently from another.
Blast radius: this changes authorization semantics application-wide for
permission checks that flow through the Identity authorizer and Gate's dotted
ability hook. It does not bypass authentication, inactive-user checks,
tenant-scoped Eloquent models, PostgreSQL RLS, branch assignment checks,
route-model tenant isolation, or any explicit same-tenant actor validation in
Application actions. Superadmin can pass the permission check, but it cannot use
that permission to read or mutate another tenant's scoped records unless the
tenant context itself has been resolved to that tenant through the normal
context mechanisms.
Rejected: adding one-off superadmin allowances to each policy, controller, or
Livewire component, because that would be inconsistent and easy to miss as new
permissions are introduced.
Cross-reference: the broader two-axis authorization model and platform-operator identity distinction are recorded in the 2026-07-28 authorization decision below.

## 2026-07-27 — Pest memory limit lives in phpunit.xml
Decision: the Pest/PHPUnit test process sets `memory_limit=256M` in
`phpunit.xml`.
Reason: the local PHP-FPM test container has no loaded `php.ini` and defaults
to PHP's `128M` memory limit. After the workspace item-write coverage was added,
the full one-process Pest suite exceeded that limit while parsing architecture
tests. CI invokes `vendor/bin/pest` directly rather than `make test`, so the
limit must live in project-level PHPUnit configuration that both local make
targets and CI honour.
Rejected: setting the limit only in `Makefile` — CI would not use it; reducing
required coverage — would weaken the regression proof for this slice.

## 2026-07-28 — Authorization uses separate availability and permission axes
Decision: authorization is recorded as two separate axes that must not be merged
into one mechanism or table. Feature availability answers whether a feature
exists for a tenant/branch at all. It is explicitly not named tariff, plan,
subscription, or billing because it records only on/off and does not encode why
a feature is off; future billing plans, if introduced, sit above this layer and
set availability switches in bulk. Availability is scoped to tenant and branch,
is controlled only by the SmartRest platform superadmin, and supports module
states plus point exceptions. A point exception overrides the module-level
state: module on plus feature off means that feature is off.
User permissions answer whether one user may perform an action in one branch.
Roles are live links rather than copies: adding a permission to a role affects
all users holding that role immediately, while personal deviations survive the
role change. Deviations are branch-scoped and bidirectional, so they can grant
a permission missing from the role or deny a permission granted by the role.
Active `is_superadmin` users bypass both axes. `is_superadmin` identifies a
regular account belonging to the SmartRest platform operator; a tenant owner is
never a platform superadmin.
Reason: feature availability and user permission answer different questions and
have different operators. Combining them would let tenant owners change the
platform-controlled product surface, or would make platform availability look
like personal permission management. Live-link roles avoid touching every user
when a role changes and avoid wiping personal settings through a re-apply
template workflow. Per-branch scope matches restaurant operations where one
person can be senior in one branch and new in another.
Current gap: the existing `EloquentAuthorizer` implements only inactive-user
deny, active-superadmin allow, and role permission lookup. Feature availability
and personal deviations are not implemented yet. The current schema also has no
above-tenant platform-operator account because `users.tenant_id` is non-null,
cascades on tenant delete, and `User` uses `BelongsToTenant`.
Rejected: naming the availability layer tariffs/plans/subscriptions, because
that would imply billing semantics this layer intentionally does not model;
copying role permissions onto users, because role updates would become manual
fleet-wide edits and re-applying a template would destroy personal deviations;
letting tenant owners control availability, because then the layer would no
longer be a platform-controlled product boundary.

## 2026-07-28 — Destructive operations and archive visibility use permissions
Decision: restore, permanent delete, and archive visibility are ordinary
permissions, not `is_superadmin` gates. Restore and permanent delete are
separate permissions because restore is reversible while permanent delete is
not. Any tenant user may hold these permissions, including a waiter, if the
tenant owner grants them. `is_superadmin` therefore returns to its single
documented purpose: the active-user break-glass bypass in the Identity
authorizer.
Reason: the platform flag had drifted into two jobs: the documented central
authorizer bypass and hard gates on destructive routes/archive visibility.
Keeping destructive maintenance behind real permissions makes the behavior
tenant-manageable and lets the project later remove `is_superadmin` from demo
tenant owner accounts without silently removing their archive maintenance
capability.
Rejected: continuing to gate restore, permanent delete, or archive visibility
on `is_superadmin`, because that keeps tenant-owner maintenance capability tied
to a platform-operator flag; using one shared destructive permission for both
restore and permanent delete, because the owner may grant reversible restore
without granting irreversible permanent deletion.

## 2026-07-28 — Platform administration uses a separate UI and guard
Decision: platform administration lives in a separate UI from restaurant
administration. It uses the `/platform` route prefix, `platform.*` route names,
its own middleware group, and its own `platform` auth guard. `/admin` remains
restaurant administration; `/platform` is platform administration. The platform
UI covers creating restaurants and branches, activating and deactivating them,
listing all restaurants, and seeing payment status so non-paying tenants can be
switched off. Tenant lifecycle, feature availability, and payment are separate
concepts and must not be merged.
Reason: platform administration operates on the tenant list and tenant
lifecycle, not on tenant-owned restaurant data. The `tenants` table is not
tenant-scoped, so those platform tasks do not require entering a tenant's data.
That makes tenant entry an optional later capability rather than a prerequisite
for the first platform UI. This is evidence toward a separate platform identity
and guard, but it does not decide the platform-operator account shape. With the
platform UI separated, `is_superadmin` no longer carries platform identity and
remains only the break-glass bypass recorded in the central Identity authorizer
decision.
Naming rationale: `/platform` describes the system-side administration surface.
`/product-owner` was rejected because "product owner" is an established scrum
role with a different meaning. `/operator` was rejected because it describes
the person rather than the system. `/console` was rejected because it is too
generic.
Cost accepted: two UIs mean two navigations, two layouts, duplicated
authentication surface, and drift risk over time. The accepted mitigation is
that the platform UI stays deliberately minimal: English only, no three-locale
parity, tables and filters rather than rich Livewire screens.
Rejected: putting platform administration inside `/admin`, because that would
keep platform lifecycle work mixed with restaurant administration; using
feature availability as payment state, because the availability layer records
what exists for a tenant or branch and deliberately does not model billing;
treating payment as tenant lifecycle itself, because payment is the reason a
tenant may be switched off, not the switch.

## 2026-07-28 — Audit log report uses a bounded date-window read path
Decision: restaurant administration exposes a read-only audit log report under
`/admin/audit-logs`. The screen uses a thin Blade controller plus an
Application query object rather than Livewire because the neighboring Halls and
Tables screens use that pattern for straightforward filter/list/detail CRUD,
while Menu uses Livewire only for its dense master-detail interaction. The
permission is `audit.logs.view`: `audit` is the cross-cutting resource family,
`logs` names the concrete append-only records, and `view` matches the existing
archive visibility convention for read-only access. Demo owner roles get the
permission by default; manager, cashier, and waiter defaults are deliberately
not decided in this slice.

Audit listing queries require a date window for every request. The default
window is the last 7 calendar days, and the server rejects windows longer than
31 calendar days with a translated validation message. Thirty-one days covers
a normal monthly operational review while keeping every page bounded; larger
investigations should be split into explicit windows rather than paging an
unbounded append-only table. Filters are optional and composable, but the
leading access path always stays on existing composite indexes:
`audit_logs_tenant_created_at_idx` for the required tenant/date window and for
actor-only residual filtering, `audit_logs_tenant_action_created_at_idx` when
`action` is present without a branch filter,
`audit_logs_tenant_branch_created_at_idx` when `branch_id` is present, and
`audit_logs_tenant_target_idx` for target-type filtering. Actor filtering is a
residual predicate inside the already bounded tenant/date set.

Reason: `audit_logs` grows without bound and is written from every audited
mutation path, including hot order mutations. Adding
`(tenant_id, actor_id, created_at)` now would add write amplification to the
hot path for an occasional admin report. A tenant-scoped date range is an index
range scan on the existing `audit_logs_tenant_created_at_idx`, and residual
actor filtering inside that bounded set is the better current trade. The actor
index remains a cheap follow-up if real data shows actor filtering is slow, but
that decision must come from measurements, not speculation.

Non-goals: no export, no CSV, no printing, no retention or purge policy, and no
new audit write sites. The report reads existing audit records only and does
not change audit recorders, audit models, schema, migrations, or indexes.

## 2026-07-29 — Tenant lifecycle blocks authenticated restaurant traffic
Decision: authenticated restaurant-admin and protected JSON/API routes require
the resolved tenant to be serviceable through the Tenancy `TenantDirectory`
contract. Serviceable means the existing `tenants.status` value is `active`;
no additional lifecycle status vocabulary, subscription table, billing model,
grace period, scheduler, or migration is introduced. The same predicate gates
resolver-supplied login tenant ids so session and non-production
`X-Tenant-ID` resolution cannot authenticate into a non-active tenant.

The request gate is explicit route middleware, registered as `tenant.active`,
and sits after authentication on protected routes. Guest requests and requests
with no resolved tenant pass through unchanged so `/login`, guest redirects,
and the `/up` health endpoint cannot redirect-loop. Logout is intentionally
not behind the gate so a user can always terminate a suspended tenant session.
HTML blocks log the user out, clear tenant and branch context, invalidate the
session, and redirect to the existing login form with a translated error.
JSON/API blocks return the existing `ApiResponse::error` envelope with
`tenant.suspended`.

Reason: tenant lifecycle is the coarse on/off switch for whether a restaurant
can use SmartRest at all, and the 2026-07-28 platform decision separates it
from feature availability and payment. Enforcing it only during login leaves
already-authenticated sessions usable after a platform operator switches a
tenant off. A single Tenancy contract predicate keeps the active-only rule in
one decision point and adds one indexed lookup by tenant primary key and
status to protected requests.

Livewire closure: Livewire 4 update requests do not automatically replay every
middleware from the original page route. The installed Livewire
`PersistentMiddleware` mechanism reconstructs the originating route from the
snapshot but filters it through Livewire's persistent middleware whitelist. The
application closes that gap by registering only
`EnsureTenantIsServiceable::class` through Livewire's supported
`addPersistentMiddleware()` API in `AppServiceProvider`, where the project
already wires global cross-cutting concerns. The Livewire update route itself
still carries the `web` middleware group, so `AttachLogContext`,
`ResolveTenant`, and `ResolveBranch` continue to run on the real update
request and are not duplicated in the persistent list.

Livewire update requests are detected by the installed client's guaranteed
`X-Livewire` header, not by `Accept`. The installed client sends
`Content-type: application/json` and `X-Livewire: 1`, but does not send
`Accept: application/json` on normal update requests. Therefore Livewire must
branch before the JSON/API branch. Suspended Livewire updates terminate the
session exactly like HTML requests and return a normal redirect to login. The
installed client uses `fetch()` with default redirect following and, when the
final response is marked `redirected`, assigns `window.location.href` to the
final response URL; this is the response shape that moves an already-open
Livewire page to `/login`. Protected JSON/API routes continue to return the
existing `ApiResponse::error` envelope unchanged.

Livewire's persistent middleware runner ignores ordinary non-redirect responses
returned from persisted middleware and aborts only redirect responses. That is
why the Livewire block uses a redirect rather than returning the API envelope.
This decision would silently reopen if a future route-level middleware is added
after authentication and must also apply to already-mounted Livewire
components, but that middleware is not added to Livewire's persistent list and
covered by a real HTTP update test.

Rejected: putting the check in the global `web` group, because that would
block guests and can create login loops; adding status enums or a migration,
because the existing `tenants.status` switch already exists; using billing or
subscription concepts, because payment is a later driver of tenant lifecycle,
not this enforcement mechanism; blocking logout, because users must be able to
end a suspended session safely.

## 2026-07-29 — Tenant subscriptions use anchored monthly billing dates
Decision: tenant subscription state lives in the Tenancy module as a read-only
tenant-lifecycle input, not in a new Billing module. This slice records one
subscription row per tenant with a fixed monthly `billing_anchor_day`, the
next expected due date, per-tenant grace days, and an informational last-paid
date. It does not record money, plans, payments, invoices, card charging,
reactivation, scheduling, or tenant suspension, and it does not write
`tenants.status`.

Billing dates are monthly and anchored to the original tenant day of month.
The anchor never drifts: if a tenant is due on 01 July, the following due
dates are 01 August and 01 September regardless of when payment arrives. When
the anchor does not exist in the target month, the due date clamps to that
month's last calendar day, but the stored anchor is preserved. For example,
anchor 31 advances 31 January to 28 February, or 29 February in a leap year,
then back to 31 March rather than sticking to the clamped February day.

Grace is stored per tenant and defaults to 3 days from configuration. Grace is
inclusive: with due date 01 August and grace 3, the tenant is still within
grace through 04 August and becomes suspendable on 05 August. Automatic
suspension, when implemented later, is intended to run at 05:00 local platform
time so it happens during a quiet operational hour rather than during a
restaurant's active evening service. This slice records that timing decision
only; it adds no scheduler, job, command, route, or status mutation.

All subscription date decisions use calendar dates in the platform timezone,
`Asia/Yerevan`, read from config. Tenants do not get their own timezone column.
Branch timezones remain branch-operational concerns for restaurant activity
and are unrelated to platform billing.

Reason: a fixed anchor makes payment timing an accounting fact rather than a
schedule mutation. Late or early payment must not shift the future due cadence
or create customer-specific drift that becomes difficult to explain and audit.
Per-tenant grace keeps the serviceability decision explicit and queryable while
leaving room for manually approved exceptions without changing the billing
algorithm. The 05:00 quiet hour minimizes disruption when a later suspension
job consumes the suspendable read model. A single platform timezone avoids
ambiguous due-date boundaries across tenants and keeps billing independent of
branch-local operating time. The Tenancy module owns this because the current
slice feeds tenant lifecycle/serviceability; a Billing module would be
premature until the product has real payments, plans, invoices, or provider
integration.

Rejected: deriving the next due date from payment arrival, because that causes
anchor drift; storing only the clamped due day, because that loses the original
anchor and makes 31st-of-month tenants stick to February; making grace a
global constant only, because exceptions would require code changes; adding a
tenant timezone, because billing is a platform-level calendar concern; adding
a scheduler or status writer now, because this slice is intentionally schema
and read-model only; creating a Billing module now, because no billing-domain
behavior exists yet.

## 2026-07-29 — Tenant subscriptions are platform-owned, not RLS-scoped
Decision: `tenant_subscriptions` is platform-owned tenant lifecycle data, in
the same ownership category as `tenants`, not tenant-owned restaurant
operational data. The unreleased migration no longer enables or forces
PostgreSQL RLS and no longer creates a tenant-isolation policy for this table.
The `TenantSubscription` model does not use `BelongsToTenant` / `TenantScoped`.
Every per-tenant read and write must set or filter `tenant_id` explicitly.

This supersedes the earlier subscription RLS assumption made in the
subscription schema slice. Removing RLS before release avoids depending on the
current runtime database role's `BYPASSRLS` capability. That role capability is
a known security debt already scheduled for removal; the suspendable fleet scan
must return the same tenant ids after the runtime role no longer has
`BYPASSRLS`.

Reason: the suspendable subscription read model is a platform fleet operation.
It must run without a tenant context and must not be silently narrowed by a
leftover tenant context. Tenant-owned RLS semantics intentionally hide rows
when no `smartrest.tenant_id` is set, which is correct for restaurant
operational tables but wrong for this platform lifecycle table.

Rejected: keeping RLS and relying on the current runtime role bypass, because
that would break as soon as the BYPASSRLS debt is fixed; adding a special RLS
policy for fleet jobs now, because this table does not need tenant-owned RLS
classification; keeping `BelongsToTenant` with manual scope bypasses, because
platform reads and writes are clearer and safer when `tenant_id` is explicit.

## 2026-07-29 — Manual subscription operations are console-only and audited
Decision: the first subscription operation interface is console-only. Platform
operators may manually record one subscription payment, suspend a tenant, or
reactivate a tenant through Artisan commands. No HTTP route, Livewire screen,
platform guard, platform operator entity, scheduler, queued job, or UI is added
in this slice. Console access on the server is the platform authorization
boundary until the platform operator identity exists.

One recorded payment advances `tenant_subscriptions.next_due_on` by exactly one
monthly billing period from the stored current due date using
`MonthlyBillingCycle`, regardless of how late the payment is. A tenant that is
two periods overdue remains overdue after one payment; this is intentional and
keeps payment recording from silently forgiving missing periods. Recording a
payment also stores `last_paid_on`, but it never writes `tenants.status` and
does not reactivate the tenant.

Payment recording uses an optimistic-concurrency guard: the console displays
the current `next_due_on`, passes that displayed date to the Application action
as the expected value, and the action rejects the mutation if the row-lock read
sees a different stored due date. This prevents a repeated or stale console run
from silently advancing the subscription twice.

Manual suspension and reactivation are the only Application write paths for
`tenants.status`. They reject no-op transitions with stable Tenancy domain
error codes so the later automatic suspension job can call the same actions
instead of duplicating status rules. Reactivation is allowed even when the
subscription remains suspendable, but the console prints a warning that the
future automated job will suspend the tenant again unless the subscription is
advanced or intentionally forgiven. Tenant owners must never be able to
reactivate themselves: tenant lifecycle is a platform operation, and allowing a
non-paying tenant to flip itself active would bypass the kill-switch and the
future platform administration boundary.

Audit rows for these console operations are written with the target tenant
context set and branch context cleared. That satisfies the existing
RLS-protected `audit_logs` policy without weakening isolation. The actor is
left null in console context because the current audit schema already permits a
null `actor_id` and no platform operator user exists yet.

No money is modeled in this slice. There are no amounts, currencies, fees,
plans, payment ledger rows, invoices, cashbox entries, or provider records; the
manual payment operation records only subscription date state.

Reason: the platform must be able to operate the subscription lifecycle before
the platform UI and platform identity exist, but tenant users must not receive
self-service lifecycle controls. One-period advancement preserves the anchored
schedule and makes forgiveness an explicit operator choice. The expected
due-date confirmation is a cheap concurrency guard that matches the console
workflow and avoids introducing a ledger before money exists.

Rejected: advancing to the first non-overdue period, because that would hide
missed periods; reactivating automatically on payment, because payment and
tenant lifecycle remain separate operator decisions; adding money columns or a
payment ledger now, because amounts and provider semantics are out of scope;
using a fake system user for console audit rows, because no platform operator
identity exists and `actor_id` is already nullable.

## 2026-07-31 — Automatic subscription suspension uses tenant-row lock boundary
Decision: automatic overdue-subscription suspension runs through Laravel
Scheduler hourly, with a repository-defined scheduler process executing
`php artisan schedule:work`. The scheduled command still gates actual
suspension until `05:00` in the platform billing timezone. The scheduler mutex
expiry is 60 minutes, not Laravel's 1440-minute default, so a crashed run does
not suppress the next operational suspension window for a full day.

The automatic suspension batch uses
`TenantSubscriptionReader::suspendableTenantIds()` only for the fleet candidate
read. Before each actual tenant suspension it opens a transaction, locks the
target `tenants` row, rechecks serviceability through `TenantDirectory`, and
rechecks subscription status through `TenantSubscriptionReader`. Only then does
it call the existing `SuspendTenant` action for the status write and audit row.
Manual subscription payment now locks the same tenant row before locking and
advancing `tenant_subscriptions`, so payment/renewal and automatic suspension
share an effective concurrency boundary.

Reason: schedule registration alone is not enough; an application process must
actually execute Laravel's scheduler. The automatic suspension read model is a
fleet scan, but the final eligibility decision must be atomic with the tenant
status mutation so a just-recorded payment cannot be overwritten by a stale
suspension run. A one-hour overlap mutex matches the hourly schedule better
than the framework default: it still prevents concurrent scheduler executions
but recovers at the next hourly tick after a crashed process.

Rejected: duplicating tenant status mutation or audit logic in the batch,
because `SuspendTenant` is the canonical writer; relying only on a
pre-suspension `statusForTenant()` read, because that creates a TOCTOU race
with payment recording; leaving the default 24-hour scheduler mutex, because
one crashed run would delay suspension too long; adding a platform UI,
operator identity, invoices, ledger, notification, or schema migration here,
because those are outside this slice.

## 2026-07-31 — Tenant-scoped login uses explicit tenant slug field
Decision: the ADR-011 tenant-aware login slice keeps the existing `GET /login`
and `POST /login` routes and requires users to submit a `tenant_slug` field
alongside email and password. Authentication resolves exactly one serviceable
tenant by slug through the Tenancy `TenantDirectory` contract, sets that tenant
context, and then queries the tenant-owned `users` table only inside that
context. The fleet-wide `activeTenantIds()` credential scan is not used for
login.

Reason: this closes the immediate scale and duplicate-email ambiguity in the
current shared-DB login flow without introducing domain, subdomain, or
path-based tenant discovery before production routing is decided. It also keeps
Identity dependent only on Tenancy contracts, so Identity does not import
Tenancy infrastructure or query Tenancy-owned tables directly.

Rejected: domain/subdomain/path discovery in this slice, because the owner
explicitly deferred it; a global login identity table, because ADR-011 deferred
that design; platform-operator authentication, because platform identity remains
undecided; and treating a stale session tenant or non-production tenant header
as credential-verification context, because the submitted slug must be the
single source for this login attempt.

## 2026-07-31 — Order waiter assignment requires active branch staff with orders-take
Decision: assigning a waiter to an order requires the selected user to be an
active user in the current tenant, assigned to the current branch, and allowed
by Identity's effective authorization model for the existing `orders.take`
permission. In the current implementation that means the active superadmin
bypass or the user's live role permission. The Orders Application action
validates that rule through `Identity\Contracts\UserDirectory`; UI adapters
only present the branch-scoped staff list and call the action. Clearing a
waiter remains a valid null assignment.

Reason: `orders.waiter_id` is an operational responsibility assignment, not a
free-form user reference. The action must enforce tenant, branch, active-user,
and capability boundaries even when called outside the current Livewire screen.
Using the existing `orders.take` permission keeps assignment aligned with the
same effective capability that allows a user to work in the order workspace,
including the currently implemented superadmin bypass.

Rejected: trusting Livewire or future HTTP/API callers to submit only valid
ids, because Application actions are the business boundary; adding a new
permission or changing demo role defaults, because the existing `orders.take`
capability already models taking orders; querying Identity tables directly
from Orders, because module boundaries require cross-module access through
Contracts.

## 2026-08-03 — Pre-production data migration policy
Decision: while SmartRest v2 has no real tenant data that must be preserved,
permission, reference/dictionary, and seed-data changes are made in
deterministic seeders and the database is recreated with `make fresh`; new
backfill/data migrations are not written for those changes.

Trigger to cancel: the first tenant with non-disposable real data. From that
point onward, any change that would break or omit existing data requires a
backfill/data migration.

Reason: the project is still pre-production, so maintaining future backfills
for disposable seed/local data adds migration risk and review overhead without
protecting client data.

Owner responsibility: the project owner declares when the first
non-disposable tenant exists. Agents must not infer that state.
