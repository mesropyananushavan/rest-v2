# Go-Live Checklist

This checklist must be completed before onboarding the first tenant with real
data that cannot be recreated with `make fresh`.

## Required Before First Non-Disposable Tenant

- [ ] Replace the runtime database role with a role that has `NOBYPASSRLS`.
  Description: application runtime containers must stop using the privileged
  `smartrest` database role and must use a dedicated runtime role with no
  `SUPERUSER` and no `BYPASSRLS`.
  Reason: PostgreSQL RLS tenant isolation is not an effective runtime boundary
  while application traffic uses a role that bypasses row-level security.
  Sources: `.env.example:24-29`, `docker-compose.yml:13-19`,
  `docker-compose.yml:70-77`, `docker-compose.yml:91-98`,
  `config/database.php:89-96`,
  `docs/DECISIONS.md#2026-07-22-runtime-database-role-must-not-bypass-rls`.

- [ ] Cancel the pre-production "no data backfills" rule.
  Description: from the first non-disposable tenant onward, permission,
  reference/dictionary, and seed-data changes must include idempotent backfill
  or data migrations whenever existing data would otherwise be missing or
  broken.
  Reason: after real tenant data exists, `make fresh` is no longer a valid data
  migration strategy.
  Sources: `AGENTS.md:52-64`,
  `docs/DECISIONS.md#2026-08-03-pre-production-data-migration-policy`.

- [ ] Stop using only string role codes as the signal for managing roles.
  Description: introduce a durable way to identify managing roles that is not
  tied only to tenant-local string codes such as `owner` and `manager`.
  Reason: `roles.code` is only unique per tenant and is not enum-constrained.
  A tenant without roles whose codes are exactly `owner` or `manager` can miss
  permission grants such as `orders.cancel`.
  Sources: `database/migrations/0001_01_01_000000_create_users_table.php:41-49`,
  `app/Modules/Identity/Infrastructure/Seeders/IdentityDemoSeeder.php:90-115`,
  `app/Modules/Identity/Infrastructure/Seeders/IdentityDemoSeeder.php:152-159`,
  `docs/worklog/PHASE-2.md:5974-5979`.

- [ ] Decide and implement platform operator identity and platform admin
  authorization.
  Description: replace console-only platform subscription operations with an
  explicit platform-operator identity and authorization boundary, or document
  a conscious production decision to keep server-console access as the platform
  operator boundary.
  Reason: tenant lifecycle operations are platform operations. Before real
  tenant data exists, console-only operation is acceptable; before go-live, the
  production operator boundary must be explicit so tenant users cannot self-
  service suspend/reactivate/payment state and platform operators are auditable.
  Sources: `docs/DECISIONS.md#2026-07-29-manual-subscription-operations-are-console-only-and-audited`,
  `docs/worklog/PHASE-2.md:5703-5706`, `docs/worklog/PHASE-2.md:5808-5835`.

## Search Notes

- Additional search was performed in `AGENTS.md`, `docs/DECISIONS.md`, and
  `docs/worklog/PHASE-2.md` for temporary/pre-production/security debt and
  before-production markers. No other item with the same first-real-tenant
  deadline was found.
- The Horizon placeholder has a different deadline: revisit before Phase 3
  fiscal/print queues, not before the first non-disposable tenant.
