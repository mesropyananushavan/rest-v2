# Worklog — Phase 3: Payments/Cashbox/Fiscal/Printing

Status: Cashbox Configuration Foundation merged; Payable Order Foundation strict-review repairs verified locally
Branch: feature/payments-payable-order-foundation

Phase 2 was closed by merge commit
`085759f4c929e9f9ebf2fe551314996b58a95f0a` for PR #59. Phase 3 starts with
the bounded Payments cashbox configuration slice only. Cashbox Configuration
Foundation was merged by PR #60 as merge commit
`b63ecbce05ed5565e94e6a8de1c49e035a132c41` from final head
`ecfdea9b46d25ad4877325d3b30011d18a070406`.

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

## Approved Second Slice: Payable Order Foundation

Approved owner decisions:
- An order is payable only when it belongs to the current tenant and branch,
  has status `open`, and has `total_minor > 0`.
- The first future payment-capture slice will accept only the full remaining
  balance, support cash only, require an active cashbox, reject partial
  payment, reject overpayment, reject mixed payment methods, and use exact
  integer minor units without cash rounding.
- Captured financial records will eventually be append-only: no editing or
  deleting captured payments; corrections use explicit reversal records.
- Order closing remains a separate Orders action. Payment capture must not
  automatically close the order; an order may later be closed only when its
  remaining payable balance is zero.
- Payment authorization uses existing `payments.capture`; owner, manager, and
  cashier receive it through approved role configuration, while waiter does
  not receive it by default.

Scope:
- Add an Orders-owned public contract/DTO for a future payment process to read
  a payable order snapshot.
- Expose only currently authoritative order data: order id, tenant id, branch
  id, status, currency, and current `total_minor`.
- Derive current remaining payable amount as equal to `total_minor` only
  because payment allocations do not exist yet.
- Document in code/tests that future payment allocations become authoritative
  for paid and remaining amounts.
- Support acquiring the order row lock inside a caller-owned transaction so a
  future payment capture can hold the lock until financial writes complete.
- Preserve tenant/branch isolation and return not-found semantics for foreign
  tenant or branch order ids.
- Produce stable Orders domain error codes for non-payable zero-total and
  non-open orders.

Explicit non-goals:
- No payment capture behavior, payment tables, payment allocations,
  cashbox ledger entries, order closing, fiscal receipts, print jobs, external
  providers, refunds, reversals, or UI.
- No `paid_minor`, `remaining_minor`, payment status columns, or other
  payment-derived columns on Orders.
- No audit records or events for this read-only foundation.
- No new migrations unless implementation reveals a required owner-approved
  schema prerequisite.

Draft PR:
- Branch: `feature/payments-payable-order-foundation`.
- Original implementation commit:
  `dd750b343a0e744fe1cc4b0bcc17ae91adc7e7ab`.
- PR: https://github.com/mesropyananushavan/rest-v2/pull/61.
- Strict read-only owner review verdict: `CHANGES REQUIRED`.

Strict-review repair scope for PR #61:
- Major: add a reliable PostgreSQL test proving
  `lockPayableForUpdate()` rejects calls outside a caller-owned transaction
  with `orders.payable_lock_requires_transaction`.
- Minor: clarify the public contract so `findPayable()` is explicitly an
  unlocked read-only inspection path and not a basis for payment capture or
  other financial writes.
- Minor: record that Draft PR #61 was opened from commit
  `dd750b343a0e744fe1cc4b0bcc17ae91adc7e7ab`.

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
- [x] Step CB5: verification, commit, push, and draft PR. Run focused tests
  first, then `make pint`, `make stan`, `make test`,
  `make tenant-isolation-pgsql`, `make orders-concurrency-pgsql`,
  `make runtime-role-pgsql`, the new cashbox PostgreSQL target if added, and
  `make fresh`; review the full diff; commit scoped logical changes; push the
  branch; open a draft PR against `main`; do not mark ready and do not merge.
  Result: verification is green through `make fresh`; implementation commit
  `4b7576d` was pushed; draft PR #60 was opened at
  https://github.com/mesropyananushavan/rest-v2/pull/60 and was not marked
  ready or merged.
- [x] Step POF0: precondition verification, branch setup, and worklog plan.
  Read required docs and relevant Orders, Payments, Tenancy, RLS, Audit,
  architecture, concurrency, and testing code; fetch `origin`; verify local
  `main`, `origin/main`, and GitHub `main` are exact
  `b63ecbce05ed5565e94e6a8de1c49e035a132c41`; verify exact-head CI is green
  and no open PR supersedes this plan; create
  `feature/payments-payable-order-foundation`; and write this plan before
  code changes.
  Result: repository state matched exactly, GitHub Actions were green on
  exact `main` SHA `b63ecbce05ed5565e94e6a8de1c49e035a132c41`, there were no
  open PRs, and the feature branch was created from exact `origin/main`.
- [x] Step POF1: Orders contract and Application reader. Add an Orders-owned
  payable snapshot DTO/contract and implementation that resolves the current
  tenant/branch order, validates open positive-total payable state, logs
  expected domain failures with stable error codes, and offers a locked read
  that relies on caller-owned transaction scope.
  Result: added `PayableOrderReader`, `PayableOrderSnapshot`, and
  `ReadPayableOrder`; the locked reader requires an existing caller-owned
  transaction and does not create payment schema, audit rows, or events.
- [x] Step POF2: SQLite and architecture coverage. Add focused application
  tests for positive snapshots, zero/non-open rejections, foreign tenant/branch
  hiding, exact integer/currency values, no mutation, and module-boundary tests
  proving future Payments may depend only on Orders contracts while Orders does
  not depend on Payments internals.
  Result: added focused payable-reader tests and architecture tests for
  Orders-owned contracts, provider binding, and allowed future Payments to
  Orders dependency direction.
- [x] Step POF3: PostgreSQL RLS, runtime-role, and concurrency coverage. Extend
  PostgreSQL tenant/RLS and runtime-role coverage for the payable reader, and
  extend Orders concurrency coverage to prove the locked payable path shares
  the order-row lock boundary with item mutation and cancellation without
  introducing an obvious deadlock path.
  Result: extended tenant-isolation, runtime-role, and Orders concurrency
  tests so the payable reader is exercised under forced RLS, restricted
  runtime-role access, and PostgreSQL row-lock coordination.
- [x] Step POF4: verification, commit, push, and draft PR. Run focused tests,
  then `make pint`, `make stan`, `make test`, relevant PostgreSQL targets,
  and `make fresh`; inspect the full diff; commit only this slice; push the
  branch; open a Draft PR against `main`; do not mark ready or merge.
  Result: verification was green through `make fresh`; original implementation
  commit `dd750b343a0e744fe1cc4b0bcc17ae91adc7e7ab` was pushed; Draft PR #61
  was opened at https://github.com/mesropyananushavan/rest-v2/pull/61 and was
  not marked ready or merged. Strict read-only owner review returned
  `CHANGES REQUIRED`; the approved repair is limited to the three findings
  recorded above.

## Gotchas

- PostgreSQL RLS insert-denial assertions must isolate the expected failure in
  a savepoint; otherwise SQLSTATE `42501` aborts the outer transaction and
  hides the intended follow-up assertions.
- Cashbox PostgreSQL concurrency workers run in separate processes and restore
  tenant context independently. Parent-process assertions must explicitly
  restore tenant and branch context after reading worker results.
- Payable order locking must be invoked inside a caller-owned transaction. The
  reader intentionally rejects ambient no-transaction calls so a future payment
  capture cannot accidentally release the order lock before financial rows are
  written.
- `RefreshDatabase` can create an ambient transaction in SQLite/application
  tests, so the payable-lock no-transaction guard must be covered in the
  PostgreSQL concurrency suite where transaction level zero can be asserted on
  the same connection used by `ReadPayableOrder`.
- PostgreSQL lock coordination tests use `pg_blocking_pids` against real
  backend PIDs instead of sleeps as assertions; this keeps blocking checks
  deterministic without introducing a new lock order.

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
- Post-review focused rerun after tightening the duplicate-name precheck to a
  SQL predicate:
  `make test ARGS='tests/Feature/Payments/CashboxActionsTest.php tests/Feature/Payments/CashboxBladeTest.php tests/Feature/Payments/CashboxConcurrencyTest.php tests/Architecture/ModuleBoundariesTest.php'`
  passed with 21 tests, 3 skipped PostgreSQL-only tests, and 344 assertions.
- Post-review reruns: `make pint`, `make stan`,
  `make cashboxes-concurrency-pgsql`, `make test`, `make runtime-role-pgsql`,
  and `make fresh` all passed on the final source state.
- `make fresh` passed, including the `cashboxes` migration and deterministic
  Payments demo seeder.
- Payable Order Foundation focused SQLite/architecture:
  `make test ARGS='tests/Feature/Orders/PayableOrderReaderTest.php tests/Architecture/ModuleBoundariesTest.php'`
  passed with 19 tests and 275 assertions.
- `make orders-concurrency-pgsql` passed with 9 tests and 55 assertions.
- `make tenant-isolation-pgsql` passed with 72 tests and 388 assertions.
- `make runtime-role-pgsql` passed with 4 tests and 69 assertions.
- `make cashboxes-concurrency-pgsql` passed with 3 tests and 28 assertions.
- `make pint` passed and fixed two style issues in test files.
- `make stan` passed with no errors.
- `make test` passed with 445 tests, 32 skipped PostgreSQL-only tests, and
  4165 assertions.
- `make fresh` passed, including migrations, deterministic demo seeding, and
  runtime database grants.
- PR #61 strict-review repair focused SQLite/architecture:
  `make test ARGS='tests/Feature/Orders/PayableOrderReaderTest.php tests/Architecture/ModuleBoundariesTest.php'`
  passed with 19 tests and 275 assertions.
- PR #61 strict-review repair Orders PostgreSQL concurrency:
  `make orders-concurrency-pgsql` passed with 10 tests and 60 assertions,
  including the new outside-transaction payable-lock guard.
- PR #61 strict-review repair `make pint` passed across 388 files.
- PR #61 strict-review repair `make tenant-isolation-pgsql` passed with
  72 tests and 388 assertions.
- PR #61 strict-review repair `make runtime-role-pgsql` passed with 4 tests
  and 69 assertions.
- PR #61 strict-review repair `make stan` passed with no errors.
- PR #61 strict-review repair `make test` passed with 445 tests,
  33 skipped PostgreSQL-only tests, and 4165 assertions.
- PR #61 strict-review repair `make fresh` passed, including migrations,
  deterministic demo seeding, and runtime database grants.
- The repair commit SHA is intentionally not recorded in this worklog entry to
  avoid a self-referential documentation commit; use the final repair report
  and PR #61 head for the exact SHA.

## Next Steps

Push the approved PR #61 repair commit, then repeat strict read-only owner
review of the updated exact PR head before any Ready transition. Do not mark
PR #61 Ready or merge it without explicit owner approval, and do not implement
payment capture, payment schema, cashbox ledger, order closing, fiscal,
printing, refunds, reversals, or UI in this slice.
