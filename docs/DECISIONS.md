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
