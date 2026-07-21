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

## 2026-07-21 — Menu category searchable select
Decision: Menu item create/edit will replace the bare category `<select>` with
a Livewire + Alpine searchable combobox backed by a server-side, debounced,
paginated category lookup. No third-party UI library is added for this stage.
Reason: the expected category scale is about 200 categories per tenant, which
does not justify adding an npm widget dependency when Livewire already handles
server-side filtering and Alpine can handle the small local disclosure state.
This keeps the form consistent with the existing Blade/Livewire/Tailwind stack
and avoids introducing a library that must be maintained for one simple
combobox.
Rejected: rendering all categories in a native select — poor tablet UX and
violates the Part C scope; installing Tom Select or another widget now —
unnecessary dependency surface until a measured accessibility or behavior gap
appears that Livewire + Alpine cannot cover cleanly.
