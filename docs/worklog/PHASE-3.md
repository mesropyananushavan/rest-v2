# Worklog — Phase 3: Payments/Cashbox/Fiscal/Printing

Status: Cashbox Configuration Foundation approved
Branch: feature/payments-cashbox-foundation

Phase 2 was closed by merge commit
`085759f4c929e9f9ebf2fe551314996b58a95f0a` for PR #59. Phase 3 starts with
the bounded Payments cashbox configuration slice only.

## Approved First Slice: Cashbox Configuration Foundation

Approved product decisions:
- A branch may have multiple cashboxes.
- Among active cashboxes in one tenant and branch, at most one may be default.
- The first active cashbox created for a branch becomes default automatically.
- A default cashbox cannot be deactivated while another active cashbox exists
  unless a replacement default is selected atomically.
- If the branch has no other active cashboxes, deactivating its only cashbox is
  allowed and leaves the branch without a default.
- Cashboxes are not physically deleted. They use active/inactive lifecycle.
- Cashbox management is allowed for owner and manager only.
- Cashier and waiter must not receive cashbox-management permission.
- Add `payments.cashboxes.manage`; keep `payments.capture` unchanged.
- Cashbox name is a normal operational string, not a localized database field.
- Currency does not belong to the cashbox in this slice.
- Blueprint fields `non_cash`, `card`, and `fiscal_enabled` remain deferred.
- Phase 2 optional whole-order move UI remains out of scope.
- Production credentials and platform-operator identity remain go-live concerns.

Schema and invariant target:
- Add Payments module with tenant-owned, branch-owned `cashboxes`.
- Fields: `id`, `tenant_id`, `branch_id`, `name`, `is_active`,
  `is_default`, timestamps.
- Name is trimmed, required, and bounded by the repository-consistent string
  limit.
- Active cashbox names are case-insensitively unique within one tenant and
  branch. The same active name is allowed in another branch or tenant.
- Duplicate-name uniqueness applies only to active cashboxes, so an inactive
  name may be reused unless implementation reveals a documented conflict.
- At most one active default cashbox may exist per tenant and branch.
- First active cashbox in a branch becomes default automatically.
- Default replacement and default deactivation must be atomic.
- Failed mutations create no partial state and no success audit row.

Concurrency decision to implement:
- Use PostgreSQL partial unique indexes for active normalized names and active
  defaults because Laravel's portable schema builder cannot express the needed
  partial expressions.
- Use a branch-scoped database lock inside Cashbox Application transactions for
  multi-row default/deactivation transitions, including the empty-branch first
  create race where no existing cashbox row can be locked.
- Normalize expected PostgreSQL unique violations to stable Payments domain
  exception codes.

Permissions and authorization:
- Add `payments.cashboxes.manage`.
- Seed/grant it only to management roles (`owner`, `manager`) through the
  deterministic Identity seeder.
- Do not grant it to `cashier`, `waiter`, or other roles.
- Keep `payments.capture` unchanged.
- Hide cashbox navigation without permission and reject all routes without the
  permission.

RLS and isolation coverage:
- `cashboxes` must use `BelongsToTenant`, `TenantScoped`, tenant and branch
  indexes, and forced PostgreSQL RLS.
- UI and Application tests must prove inaccessible branch and foreign-tenant
  ids return repository-consistent denial, normally 404 for resource ids.
- PostgreSQL tests must prove RLS blocks cross-tenant select, insert, and
  update, and that the restricted runtime role can perform representative
  allowed cashbox operations.

Audit events:
- `payments.cashbox.created`
- `payments.cashbox.updated`
- `payments.cashbox.activated`
- `payments.cashbox.deactivated`
- `payments.cashbox.default_selected`

Explicit deferred work:
- Payment capture, payment allocations, cashbox ledger entries, order closing,
  fiscal receipts, print jobs, external providers, and device integrations.
- Cashbox currency, `non_cash`, `card`, `fiscal_enabled`, fiscal-device
  semantics, and provider credentials.
- Whole-order move UI and platform-operator identity.
- Physical delete behavior for cashboxes.

## Plan

- [x] Step CB0: precondition verification and worklog setup. Read required
  docs and relevant architecture, tenancy, audit, permission, RLS, admin-shell,
  seeding, and testing patterns; fetch `origin`; verify clean local `main`
  equals `origin/main`; verify `origin/main` is
  `085759f4c929e9f9ebf2fe551314996b58a95f0a`; verify no open PR conflicts;
  create `feature/payments-cashbox-foundation`; close the stale Phase 2 PR #59
  worklog action; and write this Phase 3 plan before implementation.
  Result: repository state matched exactly, `gh pr list --state open` returned
  no open PRs, and the feature branch was created from exact `origin/main`.
- [x] Step CB1: schema, model, domain, and Application actions. Add the
  Payments module, `cashboxes` migration/model, PostgreSQL RLS and partial
  indexes, stable domain exceptions, logging/audit trait, list/find/create/
  update/activate/deactivate/select-default actions, and branch-scoped locking
  plus PostgreSQL unique-violation normalization.
  Result: added `cashboxes`, Payments contracts/domain/actions/model, forced
  PostgreSQL RLS, partial unique indexes for active names/defaults, advisory
  branch locks, transactional audit/logging, and stable domain failures.
- [x] Step CB2: permissions, demo data, routes, UI, and translations. Add
  `payments.cashboxes.manage` contract/seed grant for owner/manager only,
  deterministic cashbox seed data for both demo tenants, Blade admin routes and
  controllers, responsive management views using existing components and shared
  confirm modal, navigation gating, and `hy`/`ru`/`en` strings.
  Result: seeded management-only permission grants and demo cashboxes, added
  permission-gated admin routes/navigation/views, and translated all visible
  strings and Payments domain errors in `hy`, `ru`, and `en`.
- [x] Step CB3: focused SQLite and architecture coverage. Add tests for schema,
  tenant/branch ownership, validation, active duplicate semantics, inactive
  name reuse, default invariants, deactivation rules, atomic replacement,
  audit behavior, no delete route/action, owner/manager/cashier authorization,
  route isolation, translated errors, valid UI flows, and module-boundary
  coverage including Payments.
  Result: added focused schema/action/Blade tests and extended architecture
  coverage to intentionally include Payments while guarding Orders from direct
  Payments infrastructure dependencies.
- [x] Step CB4: PostgreSQL RLS, runtime-role, and concurrency coverage. Add
  PostgreSQL tests for RLS select/insert/update, restricted runtime-role
  representative operations, concurrent first creation, concurrent duplicate
  creation, and concurrent competing default selection. Add a narrowly named
  cashbox concurrency Make target if required by test organization.
  Result: added cashbox RLS/runtime-role coverage, concurrent worker harness,
  PostgreSQL concurrency tests, and `make cashboxes-concurrency-pgsql`.
- [ ] Step CB5: verification, commit, push, and draft PR. Run focused tests
  first, then `make pint`, `make stan`, `make test`,
  `make tenant-isolation-pgsql`, `make orders-concurrency-pgsql`,
  `make runtime-role-pgsql`, the new cashbox PostgreSQL target if added, and
  `make fresh`; review the full diff; commit scoped logical changes; push the
  branch; open a draft PR against `main`; do not mark ready and do not merge.
  Result: verification is green through `make fresh`; commit, push, and draft
  PR remain.

## Gotchas

- PostgreSQL RLS insert-denial assertions must isolate the expected failure in
  a savepoint; otherwise SQLSTATE `42501` aborts the outer transaction and
  hides the intended follow-up assertions.
- Cashbox PostgreSQL concurrency workers run in separate processes and restore
  tenant context independently. Parent-process assertions must explicitly
  restore tenant and branch context after reading worker results.

## Verification Results

- Focused SQLite/architecture:
  `make test ARGS='tests/Feature/Payments/CashboxSchemaTest.php tests/Feature/Payments/CashboxActionsTest.php tests/Feature/Payments/CashboxBladeTest.php tests/Architecture/ModuleBoundariesTest.php'`
  passed with 22 tests and 353 assertions.
- `make cashboxes-concurrency-pgsql` passed with 3 tests and 28 assertions.
- `make tenant-isolation-pgsql` passed with 71 tests and 383 assertions.
- `make runtime-role-pgsql` passed with 4 tests and 66 assertions.
- `make orders-concurrency-pgsql` passed with 7 tests and 47 assertions.
- `make pint` passed and fixed one style issue in the cashbox controller.
- `make stan` passed with no errors.
- `make test` passed with 435 tests, 29 skipped PostgreSQL-only tests, and
  4140 assertions.
- `make fresh` passed, including the `cashboxes` migration and deterministic
  Payments demo seeder.

## Next Steps

Finish Step CB5: review the final diff, commit the scoped slice, push
`feature/payments-cashbox-foundation`, and open a draft PR against `main`
without marking it ready or merging it.
