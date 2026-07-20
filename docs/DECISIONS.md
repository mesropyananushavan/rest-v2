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
(`deleted_at`) rather than physical deletion. Users with the relevant Menu
manage permission may archive categories/items; restoring archived records is
superadmin-only. Archiving a category also archives its currently
non-archived child items and marks those items as archived by that category
cascade. Restoring
the category restores only items carrying that cascade marker; items archived
independently before the category archive remain archived. An item cannot be
restored while its category is still archived.
Reason: Menu catalog records can be referenced by historical orders later, so
retaining rows preserves history and tenant isolation while still removing
records from normal workflows. The explicit cascade marker avoids relying on
timestamp equality and prevents accidental restoration of items that a manager
had intentionally archived before the category was archived.
Rejected: physical deletion from admin UI — unsafe for catalog history;
restoring every item in a category — would revive independently archived
items; inferring cascade membership from matching `deleted_at` timestamps —
fragile and hard to reason about under retries or concurrent requests.
