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
