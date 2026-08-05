# Worklog — Phase 3: Payments/Cashbox/Fiscal/Printing

Status: Cashbox Configuration Foundation merged; Payable Order Foundation merged through PR #61; Full Cash Payment Capture Foundation merged through PR #64; PR #65 post-merge documentation reconciliation recorded
Branch: docs/phase-3-pr65-post-merge

Phase 2 was closed by merge commit
`085759f4c929e9f9ebf2fe551314996b58a95f0a` for PR #59. Phase 3 starts with
the bounded Payments cashbox configuration slice only. Cashbox Configuration
Foundation was merged by PR #60 as merge commit
`b63ecbce05ed5565e94e6a8de1c49e035a132c41` from final head
`ecfdea9b46d25ad4877325d3b30011d18a070406`. Payable Order Foundation was
merged by PR #61 as merge commit
`a019f26dec9095bf34b69cfa2a334aa78685e6a1` from approved head
`14e05b36145baee494131bf648d49c2063b097f6`.

After PR #61 merged, local `main` was fast-forward aligned with `origin/main`
at merge commit `a019f26dec9095bf34b69cfa2a334aa78685e6a1`.

After PR #63 merged, local `main` and `origin/main` were verified clean and
aligned at merge commit `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd`.

Full Cash Payment Capture Foundation was merged by PR #64 as merge commit
`db7579d5d450b0638a71a2d795770b5ca3c99858` from approved head
`de22d6c9453aad7edba336077f6d6b598d7e15e8`.

After PR #64 merged, local `main` was fast-forward aligned with `origin/main`
at merge commit `db7579d5d450b0638a71a2d795770b5ca3c99858`.

PR #65 merged the PR #64 post-merge documentation housekeeping as merge commit
`9867476a828567b6e06f5adce8ba5bfb908942d9` from docs head
`ab0a57c66d5e62fbaf8a345f0cc7f8c30ff1c8fc` at
`2026-08-05T11:22:43Z`.

After PR #65 merged, local `main` was safely fast-forwarded and verified clean
and aligned with `origin/main` at merge commit
`9867476a828567b6e06f5adce8ba5bfb908942d9`.

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

Merge record for PR #61:
- Approved head:
  `14e05b36145baee494131bf648d49c2063b097f6`.
- Merge commit:
  `a019f26dec9095bf34b69cfa2a334aa78685e6a1`.
- Final PR size: 2 commits and 15 changed files.
- Final exact-head CI succeeded for both push and pull-request workflow runs.
- Payable Order Foundation is complete as the second Phase 3 slice.
- No payment capture, payment schema, payment allocations, cashbox ledger,
  order closing, fiscalization, printing, refunds, reversals, routes,
  controllers, Livewire, or UI work has begun.
- No formally named third Phase 3 slice has been defined in the repository.

## Approved Third Slice: Full Cash Payment Capture Foundation

Owner approval:
- The owner approved this slice in the documentation/planning turn that
  recorded this section.
- Approved source proposal:
  `FINALIZED PROPOSAL — NOT APPROVED`, with final verdict
  `APPROVE PROPOSAL FOR OWNER DECISION`.
- This approval authorizes future implementation planning only. No payment
  capture implementation has started in this documentation branch.

Objective:
- Add the smallest durable, Application-only cash payment capture foundation
  after the Cashbox Configuration Foundation and Payable Order Foundation.
- Capture exactly the locked remaining payable balance for one open order,
  using cash, through one explicit active cashbox, and persist all financial
  facts atomically.

Module ownership contract:
- Orders owns payable eligibility, gross payable amount, currency, tenant and
  branch identity, and the locked payable-order snapshot.
- Payments owns captured payment facts, allocations, paid/remaining balance
  derived from allocations, and cashbox ledger entries.
- Payments may depend only on public Orders contracts. It must not import
  Orders Domain, Application, Infrastructure, Http, Eloquent models, or query
  Orders tables directly.
- `PayableOrderSnapshot::currentRemainingPayableMinor()` remains a temporary
  compatibility helper and must not be used by capture once payment
  allocations exist.

Approved behavior:
- The slice is Application-only: no UI, Livewire, controller, API, route, or
  delivery adapter.
- Capture is cash only.
- Capture is for the complete locked remaining balance only.
- An explicit active `cashbox_id` is required; no implicit default cashbox
  selection.
- Payment capture never closes the order and never otherwise mutates order
  workflow state.
- Payment, allocation, cashbox-entry, and audit persistence happens atomically
  in one database transaction.
- No domain event or outbox is introduced until a real consumer exists.

Approved command contract:
- Add `CaptureCashPaymentCommand` with exactly:
  `orderId`, `cashboxId`, `expectedAmountMinor`, `expectedCurrency`,
  and `idempotencyKey`.
- Actor comes from the authenticated runtime user.
- Tenant comes from `TenantResolver`.
- Branch comes from `BranchContext`.
- The server captures the locked remaining balance. Expected amount and
  currency are stale-client and tampering guards, not the source of captured
  amount.
- `idempotencyKey` is an opaque, case-sensitive string, not trimmed, 1-128
  characters, with no leading/trailing whitespace, no control characters, and
  no UUID-format requirement.

Approved persistence contract:
- Add append-only `payments`, `payment_allocations`, and `cashbox_entries`.
- `payments.order_id` exists as the direct order relationship and
  tenant/branch/order query shortcut for this order-only slice.
- Allocations use the Blueprint shape: `payable_type = order` and
  `payable_id`.
- `payment_allocations` is authoritative for paid and remaining balance.
- Cross-table consistency is protected by the approved transaction,
  Application checks, and PostgreSQL insert triggers.
- Append-only behavior is enforced by Eloquent model guards and PostgreSQL
  update/delete rejection triggers.
- Tenant and branch fields are required on all three tables with tenant,
  branch, and query-path indexes.
- Forced PostgreSQL RLS is required on all three tables.

Approved idempotency contract:
- Scope idempotency by tenant, branch, and key.
- Store a canonical SHA-256 fingerprint over version, action,
  `order_id`, `cashbox_id`, `expected_amount_minor`, and
  `expected_currency`.
- Identical replay returns the original committed result without duplicate
  financial rows or audit rows.
- A reused key with a mismatched payload returns
  `payments.idempotency_conflict` and must never return the previous
  successful result.
- The same key in another tenant or branch is independent.
- PostgreSQL unique-constraint races on the idempotency key are resolved only
  after the failed transaction has rolled back; do not continue inside an
  aborted PostgreSQL transaction.

Approved transaction and lock order:
- Canonical lock/write order:
  existing idempotency lookup; selected order row through the Orders payable
  contract; idempotency recheck; selected cashbox row; financial and audit
  inserts.
- The selected order row is locked before the selected cashbox row.
- Capture must not take the cashbox branch advisory lock and must not lock
  every cashbox in the branch.
- Locking the selected cashbox row is required only to coordinate with
  concurrent activation/deactivation and prove the cashbox is active at
  capture time.

Approved remaining-balance contract:
- Remaining balance equals the Orders-owned locked gross amount minus
  Payments-owned captured allocations for the order.
- Include only captured allocations/payments in the sum.
- No allocations means paid amount is zero.
- Remaining zero rejects with `payments.order_already_fully_paid`.
- Allocations above gross reject with `payments.order_over_allocated`.
- All money calculations use integer minor units only.
- Future Orders closing logic must ask Payments through a public Payments
  contract; Orders must not import Payments internals or query Payments
  tables.

Authorization, tenancy, audit, and logging:
- Enforce `payments.capture` inside the `CaptureCashPayment` Application
  action.
- Owner, manager, and cashier are permitted by the existing approved role
  configuration; waiter is not.
- Cross-tenant or cross-branch order/cashbox ids must use not-found
  semantics.
- Tenant and branch isolation apply at context, query, model/RLS, FK, and
  consistency-trigger boundaries.
- Audit action: `payments.payment.captured`.
- Audit target type: `payments_payment`.
- Audit persistence is transaction-bound: audit failure rolls back capture.
- Idempotent replay must not create duplicate audit rows.
- Structured logs must use stable English messages and safe redacted context.

Explicit exclusions:
- No UI, Livewire, controller, API, route, or broad payment-management screen.
- No partial or split payments.
- No overpayment.
- No card, non-cash, or mixed payment methods.
- No refunds, voids, or reversals.
- No tips.
- No automatic order closing.
- No printing, fiscalization, receipts, reports, settlement flows, or
  historical imports.
- No domain events or outbox infrastructure.
- No unrelated order workflow changes.

Expected implementation inventory:
- One financial-schema migration for `payments`, `payment_allocations`, and
  `cashbox_entries`.
- New append-only Payment, PaymentAllocation, and CashboxEntry models.
- New command/result DTOs, fingerprint support, capture errors, and
  translations.
- New `CaptureCashPayment` Application action.
- New SQLite-compatible tests for functional behavior, authorization,
  validation, idempotency, audit, append-only behavior, and proof that order
  workflow state does not change.
- New PostgreSQL tests for RLS, runtime role behavior, triggers, and
  concurrency/idempotency.
- New payment concurrency worker and `payments-concurrency-pgsql` Make target.

Merge record for PR #64:
- PR URL: https://github.com/mesropyananushavan/rest-v2/pull/64.
- Approved and merged feature head:
  `de22d6c9453aad7edba336077f6d6b598d7e15e8`.
- Merge commit:
  `db7579d5d450b0638a71a2d795770b5ca3c99858`.
- Merge parents:
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd` and
  `de22d6c9453aad7edba336077f6d6b598d7e15e8`.
- Merge method: GitHub merge commit with exact-head SHA guard.
- Merge timestamp: `2026-08-05T10:40:01Z`.
- Final PR size: 12 commits and 24 changed files.
- Final exact-head CI succeeded on
  `de22d6c9453aad7edba336077f6d6b598d7e15e8` for both push and pull-request
  workflow runs, including `quality`, `tenant-isolation-pgsql`,
  `orders-concurrency-pgsql`, and `runtime-role-pgsql`.
- GitHub review/comment state at merge verification: zero reviews, zero issue
  comments, zero review comments, and zero review threads.
- The feature branch
  `feature/payments-cash-payment-capture-foundation` was retained locally and
  on `origin`; no branch cleanup was performed.
- Full Cash Payment Capture Foundation is complete as this Phase 3 foundation
  slice.
- No fiscalization, printing, UI, routes, controllers, Livewire, API, domain
  events, outbox, order closing, refund/reversal, branch deletion, or next
  implementation slice work was performed after merge.

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
- [x] Step POF5: strict-review repair, exact-head CI, and merge. Apply the
  approved PR #61 repair only; push the repair; repeat strict review; mark the
  PR Ready only after approval; verify exact-head CI success; merge with a
  merge commit; and fast-forward local `main` to `origin/main`.
  Result: PR #61 merged from approved head
  `14e05b36145baee494131bf648d49c2063b097f6` as merge commit
  `a019f26dec9095bf34b69cfa2a334aa78685e6a1`; final PR size was 2 commits and
  15 files; exact-head CI succeeded for both push and pull-request workflow
  runs; local `main` was aligned with `origin/main` at the merge commit.
- [x] Step POF6: post-merge documentation housekeeping. Record the PR #61 merge
  facts, replace stale continuation instructions, and preserve historical
  review and repair evidence without starting another implementation slice.
  Result: this documentation-only update is scoped to
  `docs/worklog/PHASE-3.md` on branch `docs/phase-3-pr61-post-merge`.
- [x] Step FCPF0: implementation branch setup from verified `main`. In a later
  explicitly authorized implementation turn, fetch `origin`; verify local
  `main` and `origin/main` are aligned and clean; create the implementation
  branch from the then-current verified `main`; and do not start from this
  documentation branch.
  Result: baseline verification passed after `git fetch origin`; PR #63 was
  still merged at `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd`, local `main`
  and `origin/main` matched with ahead/behind `0/0`, no existing payment-capture
  implementation branch or code was present, and
  `feature/payments-cash-payment-capture-foundation` was created locally from
  the exact verified `main`. No payment capture implementation work was started.
- [x] Step FCPF1: financial schema migration. Add the single migration for
  `payments`, `payment_allocations`, and `cashbox_entries`, including tenant
  and branch fields, indexes, foreign keys, row-level checks, forced
  PostgreSQL RLS, append-only update/delete triggers, and insert consistency
  triggers.
  Result: added the append-only financial schema migration for `payments`,
  `payment_allocations`, and `cashbox_entries` with integer minor-unit money,
  tenant/branch ownership, restrictive foreign keys, query indexes, scoped
  idempotency key uniqueness, PostgreSQL check constraints, forced RLS,
  update/delete rejection triggers, and insert consistency triggers; added
  schema-focused SQLite and PostgreSQL tests. No capture action, models,
  DTOs, authorization, UI, routes, events, outbox, fiscal, printing, or
  concurrency worker behavior was introduced.
- [x] Step FCPF2: append-only financial models. Add Payment,
  PaymentAllocation, and CashboxEntry models with `BelongsToTenant`,
  `TenantScoped`, typed casts, no soft deletes, and update/delete model guards.
  Result: added append-only `Payment`, `PaymentAllocation`, and `CashboxEntry`
  Eloquent models for the FCPF1 financial schema with tenant scoping, integer
  minor-unit casts, same-module cashbox/payment/allocation relationships, no
  soft deletes, and persisted update/delete/force-delete guards. Query-builder
  update/delete/increment/decrement mutation paths remain blocked by the FCPF1
  database append-only triggers. No capture command, action, authorization,
  allocation orchestration, audit orchestration, UI, routes, API, fiscal,
  printing, events, outbox, or concurrency worker behavior was introduced.
- [x] Step FCPF3: command/result, fingerprint, errors, and translations. Add
  `CaptureCashPaymentCommand`, the capture result DTO, canonical idempotency
  fingerprint support, stable Payments domain errors, and matching `hy`, `ru`,
  and `en` translation keys.
  Result: added immutable command/result DTOs, canonical SHA-256 fingerprint
  support over the approved capture inputs, stable Payments domain error
  factories, matching `hy`, `ru`, and `en` translations, and focused contract
  tests. No capture action, authorization, persistence orchestration, audit
  orchestration, UI, routes, API, fiscal, printing, events, outbox, or
  concurrency worker behavior was introduced.
- [x] Step FCPF4: `CaptureCashPayment` Application action. Implement
  action-level `payments.capture` authorization, tenant/branch/actor
  resolution, command validation, order lock through the Orders public
  contract, selected cashbox row lock, remaining-balance calculation,
  idempotency handling, financial inserts, transaction-bound audit, and
  structured logs.
  Result: added the Application-only `CaptureCashPayment` action with
  action-level authorization through the existing Identity authorizer, explicit
  tenant/branch/authenticated-actor resolution, approved command validation,
  canonical order-then-cashbox lock order, allocation-derived remaining balance,
  scoped idempotency replay/conflict handling, atomic payment/allocation/
  cashbox-entry/audit inserts, and safe structured logging. Added only the
  minimal focused FCPF4 action tests. No UI, routes, controllers, API, order
  closing, fiscalization, printing, events, outbox, worker, Make target,
  PostgreSQL concurrency tests, or comprehensive FCPF5 matrix was introduced.
- [x] Step FCPF5: SQLite-compatible coverage. Add tests for successful full
  cash capture, authorization, validation, exact amount and currency guards,
  idempotency replay/conflict, inactive and inaccessible cashboxes,
  inaccessible/non-payable orders, audit rollback, append-only model guards,
  and proof that payment capture does not change order status, closed time,
  totals, items, or other workflow state.
  Result: added comprehensive SQLite-compatible `CaptureCashPayment` coverage
  for persisted financial/audit facts, capture-created append-only guards,
  action-level authorization and actor/context failures, command validation,
  cashbox selection/isolation, Orders public-contract boundaries,
  allocation-derived remaining balance, expected-value guards, sequential
  idempotency replay/conflict/scope, rollback atomicity, no order workflow
  mutation, and safe structured logging. No production correction, migration,
  UI, routes, controllers, API, order closing, fiscalization, printing, events,
  outbox, worker, Make target, PostgreSQL RLS/runtime-role, or concurrency
  coverage was introduced.
- [x] Step FCPF6: PostgreSQL RLS, runtime-role, trigger, and concurrency
  coverage. Add tests proving financial-table RLS isolation, runtime-role
  capture behavior, raw update/delete trigger rejection, identical and
  mismatched idempotency races, two different payment attempts for one order,
  order mutation/cancellation coordination, and cashbox deactivation
  coordination.
  Result: added PostgreSQL `CaptureCashPayment` coverage for forced
  financial-table RLS isolation, insert-consistency triggers, raw append-only
  update/delete rejection, restricted runtime-role capture execution,
  transaction/audit/persistence rollback, order-before-cashbox lock
  coordination, no branch-wide cashbox/advisory lock behavior, real
  separate-process idempotency races, competing capture attempts, order
  mutation/cancellation coordination, and selected cashbox deactivation
  coordination. The Payments concurrency worker and
  `payments-concurrency-pgsql` Make target were implemented early under
  explicit owner authorization because FCPF6 could not honestly execute real
  PostgreSQL capture races without those FCPF7 prerequisites. No production
  correction, migration, UI, route, controller, API, order closing,
  fiscalization, printing, refund/reversal, event, or outbox behavior was
  introduced.
- [x] Step FCPF7: payment concurrency worker and Make target. Add a Payments
  concurrency worker following the existing PostgreSQL worker pattern and add
  a narrowly scoped `payments-concurrency-pgsql` Make target.
  Result: reconciled and completed FCPF7. The Payments
  `CaptureCashPayment` PostgreSQL concurrency worker and process entrypoint
  were implemented early under explicit FCPF6 prerequisite authorization in
  commit `8d692eee480d765acf1a98075b1712a68e598b9b`; the narrowly scoped
  `payments-concurrency-pgsql` Make target was implemented in commit
  `6763c85955f6d3e2162c278d7958dd4fb464ffb1`. Verification confirmed the
  worker boots the real Laravel application, requires PostgreSQL, runs the
  real `CaptureCashPayment` Application action through deterministic
  process-level barriers, returns machine-readable results, avoids Orders
  internals and direct Orders-table queries, and does not use owner-role or
  SQLite fallback behavior. No implementation correction, production change,
  migration, UI, route, controller, API, fiscal, printing, refund/reversal,
  event, outbox, or FCPF8 behavior was introduced.
- [x] Step FCPF8: focused and complete verification. Run focused tests first,
  then `make pint`, `make stan`, `make test`,
  `make tenant-isolation-pgsql`, `make orders-concurrency-pgsql`,
  `make cashboxes-concurrency-pgsql`, `make runtime-role-pgsql`, the new
  `make payments-concurrency-pgsql`, and `make fresh`.
  Result: focused SQLite/architecture coverage, PostgreSQL tenant-test and
  restricted runtime-role gates, real Payments/Orders/Cashbox concurrency
  targets, Pint, PHPStan, the full SQLite suite, fresh PostgreSQL migrations
  and seeders, runtime grants, and `git diff --check` all passed on the final
  source state. No production, test, migration, UI, route, controller, API,
  fiscal, printing, event, outbox, or FCPF9 behavior was introduced.
- [x] Step FCPF9: exact diff and inventory review. Confirm the implementation
  diff matches the approved slice, contains no UI/delivery adapter or excluded
  feature, and changes only expected implementation, test, translation,
  Makefile, and worklog files.
  Result: reviewed the complete committed range from authoritative base
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd` through head
  `c4400f757d0d2948218ddc6451d48ad658797ea6`; all 10 local commits mapped to
  their intended FCPF0-FCPF8 steps with no empty, duplicated, accidental, or
  uncommitted-state-dependent commit. The aggregate inventory was 24 changed
  files with 5149 insertions and 20 deletions: Payments production
  Application/domain/model code; the public Orders payable-contract consumer
  only; one financial migration; three payment translations; SQLite,
  PostgreSQL, RLS/runtime-role, model/schema, architecture-adjacent tests; the
  Payments concurrency worker and entrypoint; the `payments-concurrency-pgsql`
  Make target; and this worklog. No deletes, renames, binaries, generated
  artifacts, executable-bit changes, ignored files, temporary files, secrets,
  unrelated files, UI, route, controller, API, fiscal, printing, order closing,
  refund/reversal, event, outbox, or later-step behavior were found.
  Architecture and scope review confirmed Payments production code imports
  Orders only through `App\Modules\Orders\Contracts\PayableOrderReader` and
  `PayableOrderSnapshot`, does not query Orders tables directly, preserves the
  approved transaction/idempotency/selected-order-before-selected-cashbox lock
  order, locks only the selected cashbox, avoids cashbox branch advisory locks,
  keeps integer minor-unit money, and preserves append-only, audit, forced RLS,
  restricted runtime-role, insert-consistency trigger, and real concurrency
  contracts. Review verification commands passed: `git diff --check
  origin/main...HEAD`; aggregate `git diff --stat`, `--name-status`,
  `--summary`, `--numstat`, and final changed-file reads; commit-by-commit
  `git log`, `git diff-tree`, and `git show` inspection; static `rg` searches
  for forbidden Orders internals, direct Orders-table access, excluded
  features, generated/secret-bearing paths, debug artifacts, and money floats;
  and `make test ARGS='tests/Architecture/ModuleBoundariesTest.php'` with
  11 tests and 257 assertions.
- [x] Step FCPF10: commit, push, and Draft PR only with later authorization.
  Commit the implementation and worklog update, push the implementation
  branch, and open a Draft PR only after the owner explicitly authorizes that
  release-flow work.
  Result: after owner authorization, rechecked the clean local branch at
  `ee1e45baf93e7015a4cac4b505771375764bf4e0` against authoritative base
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd`; verified the branch was
  11 commits ahead and 0 behind `origin/main`; confirmed no remote branch or
  existing PR for the head branch; reran pre-push gates `git diff --check
  origin/main...HEAD`, `make pint`, `make stan`, `make test`, and
  `make fresh`, all passing. Pushed the branch normally to
  `origin/feature/payments-cash-payment-capture-foundation`, created Draft PR
  #64 (`https://github.com/mesropyananushavan/rest-v2/pull/64`) against
  `main`, verified the PR was open/draft with base `main`, head
  `feature/payments-cash-payment-capture-foundation`, 24 changed files, and
  initial remote head `ee1e45baf93e7015a4cac4b505771375764bf4e0`. No
  force-push, rebase, merge, Ready-for-review transition, reviewer request,
  approval, branch deletion, production/test/migration/schema/UI/API change,
  or GitHub state change beyond the authorized branch push and Draft PR
  creation was performed.
- [x] Step FCPF11: post-merge documentation housekeeping. After separate owner
  authorization and PR #64 merge, verify the merge commit and parents,
  fast-forward local `main` to `origin/main`, record the PR #64 merge facts in
  this worklog on a docs-only branch, and publish that documentation branch as
  a Draft PR without deleting branches or starting another implementation
  slice.
  Result: PR #64 was verified merged with merge commit
  `db7579d5d450b0638a71a2d795770b5ca3c99858` and merged feature head
  `de22d6c9453aad7edba336077f6d6b598d7e15e8`; local `main` was
  fast-forwarded from `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd` to
  `db7579d5d450b0638a71a2d795770b5ca3c99858`; this documentation-only update
  is scoped to `docs/worklog/PHASE-3.md` on branch
  `docs/phase-3-pr64-post-merge`. That branch was later merged through PR #65
  as merge commit `9867476a828567b6e06f5adce8ba5bfb908942d9` from docs head
  `ab0a57c66d5e62fbaf8a345f0cc7f8c30ff1c8fc`.

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
- PostgreSQL unique-constraint violations abort the current transaction. The
  future payment idempotency race handler must let the failed transaction roll
  back before loading and comparing the winning committed payment row.
- FCPF6 required real `CaptureCashPayment` race execution, but the approved
  plan had placed the Payments concurrency worker and
  `payments-concurrency-pgsql` Make target in FCPF7. Owner authorization on
  2026-08-05 allowed implementing exactly those FCPF7 prerequisites early;
  FCPF7 was later reconciled and completed without changing those artifacts.

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
- PR #61 final exact-head CI succeeded on approved head
  `14e05b36145baee494131bf648d49c2063b097f6` for both push and pull-request
  workflow runs before merge.
- FCPF0 baseline and branch validation passed: `git fetch origin` completed;
  local `main` and `origin/main` were both
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd` with ahead/behind `0/0`; PR #63
  was still merged; `docs/phase-3-payment-capture-approved-plan` was preserved
  locally and remotely at `118b5d5deea60ceb90db7ee904758b11dd0dcd83`; every
  `FCPF0` through `FCPF10` step was pending before branch creation; the new
  local branch `feature/payments-cash-payment-capture-foundation` started at
  the exact verified `main`; and no payment capture implementation files were
  introduced. Final focused validation passed with `git diff --check`, and the
  only changed file was this worklog.
- FCPF1 baseline validation passed after `git fetch origin`: current branch
  was `feature/payments-cash-payment-capture-foundation` at
  `af0e2efc501352e1191465634aafa67c948b2331` with no upstream and clean
  worktree; local `main` and `origin/main` were both
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd`; `main..HEAD` contained only the
  FCPF0 worklog commit; PR #62 and PR #63 were still merged; the documentation
  branch remained preserved locally and remotely at
  `118b5d5deea60ceb90db7ee904758b11dd0dcd83`; no remote implementation branch
  or PR existed.
- FCPF1 focused validation passed: `make test
  ARGS='tests/Feature/Payments/PaymentFinancialSchemaTest.php'` passed with
  3 tests and 44 assertions; `make tenant-isolation-pgsql` passed with
  73 tests and 403 assertions, including financial table RLS and insert
  consistency coverage; `make test ARGS='tests/Feature/Payments'` passed with
  16 tests, 3 skipped PostgreSQL-only tests, and 147 assertions;
  `make runtime-role-pgsql` passed with 4 tests and 69 assertions;
  `make pint` passed across 390 files; `make stan` passed with no errors; and
  `git diff --check` passed.
- FCPF2 baseline validation passed after `git fetch origin`: current branch was
  `feature/payments-cash-payment-capture-foundation` at
  `8e69787c67d62090a438a67d4adad3f148c1c913` with no upstream and clean
  worktree; local `main` and `origin/main` were both
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd`; `main..HEAD` contained only the
  FCPF0 and FCPF1 commits; PR #62 and PR #63 were still merged; the
  documentation branch remained preserved locally and remotely at
  `118b5d5deea60ceb90db7ee904758b11dd0dcd83`; no remote implementation branch
  or PR existed; and no FCPF2 model implementation was present before work
  began.
- FCPF2 focused validation passed: `make test
  ARGS='tests/Feature/Payments/PaymentFinancialModelsTest.php'` passed with
  3 tests and 83 assertions; `make test
  ARGS='tests/Feature/Payments/PaymentFinancialSchemaTest.php
  tests/Feature/Payments/PaymentFinancialModelsTest.php
  tests/Architecture/ModuleBoundariesTest.php'` passed with 17 tests and
  384 assertions, including migration rollback/re-apply coverage and the
  Payments-to-Orders architecture boundary; `make test
  ARGS='tests/Feature/Payments'` passed with 19 tests, 3 skipped
  PostgreSQL-only tests, and 230 assertions; `make tenant-isolation-pgsql`
  passed with 73 tests and 403 assertions; `make runtime-role-pgsql` passed
  with 4 tests and 69 assertions; `make pint` passed across 394 files and fixed
  one style issue in the new model test; `make stan` passed with no errors; the
  post-Pint model test rerun passed with 3 tests and 83 assertions; the
  post-Pint Payments suite rerun passed with 19 tests, 3 skipped
  PostgreSQL-only tests, and 230 assertions; and `git diff --check` passed.
- FCPF3 baseline validation passed after read-only reconciliation and
  `git fetch origin`: current branch was
  `feature/payments-cash-payment-capture-foundation` at
  `bf268b91edddd0288ba281816327eccde6c67b28` with no upstream and clean
  worktree; local `main` and `origin/main` were both
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd`; `main..HEAD` contained only the
  FCPF0, FCPF1, and FCPF2 commits; FCPF1 and FCPF2 file contents matched the
  approved scopes; the worklog body accurately recorded FCPF2 completion, but
  its `Next Steps` section was stale because FCPF2 was already committed.
- FCPF3 focused validation passed: `make test
  ARGS='tests/Feature/Payments/CaptureCashPaymentContractTest.php'` passed
  with 4 tests and 58 assertions; `make test
  ARGS='tests/Feature/Payments/CaptureCashPaymentContractTest.php
  tests/Feature/Payments/PaymentFinancialSchemaTest.php
  tests/Feature/Payments/PaymentFinancialModelsTest.php
  tests/Architecture/ModuleBoundariesTest.php'` passed with 21 tests and
  442 assertions; `make test ARGS='tests/Feature/Payments'` passed with
  23 tests, 3 skipped PostgreSQL-only tests, and 288 assertions; `make pint`
  passed across 398 files and fixed one style issue in the new contract test;
  the post-Pint focused rerun passed with 4 tests and 58 assertions;
  `make stan` passed with no errors; `make test` passed with 455 tests,
  34 skipped PostgreSQL-only tests, and 4350 assertions; and
  `git diff --check` passed.
- FCPF4 baseline validation passed after read-only reconciliation and
  `git fetch origin`: current branch was
  `feature/payments-cash-payment-capture-foundation` at
  `1a6297573097b5484e024c34dba1d7dff3e5a00d` with no upstream and clean
  worktree; `origin/main` was
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd`; the branch was 4 commits ahead
  and 0 behind `origin/main`; FCPF0 through FCPF3 were committed and matched
  their approved scopes; and the worklog accurately named FCPF4 as the next
  unfinished step.
- FCPF4 focused validation passed: `make test
  ARGS='tests/Feature/Payments/CaptureCashPaymentActionTest.php'` passed with
  6 tests and 57 assertions; `make test
  ARGS='tests/Feature/Payments/CaptureCashPaymentActionTest.php
  tests/Feature/Payments/CaptureCashPaymentContractTest.php
  tests/Feature/Payments/PaymentFinancialSchemaTest.php
  tests/Feature/Payments/PaymentFinancialModelsTest.php
  tests/Feature/Orders/PayableOrderReaderTest.php
  tests/Architecture/ModuleBoundariesTest.php'` passed with 35 tests and
  517 assertions before and after Pint; `make test
  ARGS='tests/Feature/Payments'` passed with 29 tests, 3 skipped
  PostgreSQL-only tests, and 345 assertions; `make pint` passed across
  400 files; the first `make stan` run found one new typed-cast issue in
  `CaptureCashPayment`, which was corrected inside the FCPF4 action; the final
  `make stan` passed with no errors; and `make test` passed with 461 tests,
  34 skipped PostgreSQL-only tests, and 4407 assertions. FCPF4 intentionally
  did not add or run payment PostgreSQL/RLS/concurrency gates, which remain
  assigned to FCPF6 through FCPF8.
- FCPF5 baseline validation passed after read-only reconciliation and
  `git fetch origin`: current branch was
  `feature/payments-cash-payment-capture-foundation` at
  `4548de49272ace2b8063404a63397896028e6ae9` with no upstream and clean
  worktree; `origin/main` was
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd`; the branch was 5 commits ahead
  and 0 behind `origin/main`; FCPF0 through FCPF4 were committed and matched
  their approved scopes; and the worklog accurately named FCPF5 as the next
  unfinished step.
- FCPF5 focused validation passed: the new `make test
  ARGS='tests/Feature/Payments/CaptureCashPaymentCoverageTest.php'` initially
  exposed two test-fixture issues only and then passed with 14 tests and
  242 assertions after fixture correction; `make test
  ARGS='tests/Feature/Payments/CaptureCashPaymentActionTest.php'` passed with
  6 tests and 57 assertions; `make test
  ARGS='tests/Feature/Payments/CaptureCashPaymentContractTest.php'` passed
  with 4 tests and 58 assertions; `make test
  ARGS='tests/Feature/Payments/PaymentFinancialSchemaTest.php
  tests/Feature/Payments/PaymentFinancialModelsTest.php'` passed with 6 tests
  and 127 assertions; `make test
  ARGS='tests/Feature/Orders/PayableOrderReaderTest.php'` passed with 8 tests
  and 18 assertions; `make test
  ARGS='tests/Architecture/ModuleBoundariesTest.php'` passed with 11 tests and
  257 assertions; and `make test ARGS='tests/Feature/Payments'` passed with
  43 tests, 3 skipped PostgreSQL-only tests, and 587 assertions.
- FCPF5 repository gates passed on the final source state: `make pint` passed
  across 401 files; the post-Pint affected rerun `make test
  ARGS='tests/Feature/Payments/CaptureCashPaymentCoverageTest.php
  tests/Feature/Payments/CaptureCashPaymentActionTest.php
  tests/Feature/Payments/CaptureCashPaymentContractTest.php
  tests/Feature/Payments/PaymentFinancialSchemaTest.php
  tests/Feature/Payments/PaymentFinancialModelsTest.php
  tests/Feature/Orders/PayableOrderReaderTest.php
  tests/Architecture/ModuleBoundariesTest.php'` passed with 49 tests and
  759 assertions; `make stan` passed with no errors; `make test` passed with
  475 tests, 34 skipped PostgreSQL-only tests, and 4649 assertions; and
  `make fresh` passed, including migrations, deterministic demo seeding, and
  runtime database grants.
- FCPF6 baseline validation passed after read-only reconciliation and
  `git fetch origin`: current branch was
  `feature/payments-cash-payment-capture-foundation` at
  `de56213cd2bc47179e3335ab57564ad380549831` with no upstream and clean
  worktree; `origin/main` was
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd`; the branch was 6 commits ahead
  and 0 behind `origin/main`; FCPF0 through FCPF5 were committed and matched
  their approved scopes; FCPF6 was the next unchecked step; and the worklog
  explicitly assigned the Payments concurrency worker and
  `payments-concurrency-pgsql` Make target to FCPF7.
- FCPF6 PostgreSQL verification passed: `make payments-concurrency-pgsql`
  passed before and after Pint with 6 tests and 103 assertions, running on the
  PostgreSQL tenant-test database with schema-owner setup and real
  separate-process `CaptureCashPayment` workers. It verified forced RLS and
  policies for `payments`, `payment_allocations`, and `cashbox_entries`;
  direct append-only update/delete trigger rejection for all three financial
  tables; insert-consistency trigger rejection for invalid payment,
  allocation, and cashbox-entry relationships; PostgreSQL capture/audit
  atomicity; rollback on audit and persistence failures; order-before-cashbox
  locking; no worker advisory lock; selected-cashbox-only lock behavior; real
  identical same-key replay races; different-key same-order winner/domain
  races; same-key different-order unique-constraint conflict races with
  transaction level restored to zero; same-key independence across tenants and
  branches; order total mutation, order cancellation, and selected cashbox
  deactivation coordination. `make runtime-role-pgsql` passed after adding
  restricted runtime-role capture coverage with 5 tests and 93 assertions,
  proving the action succeeds as the non-owner `NOBYPASSRLS` runtime role,
  persists one payment/allocation/cashbox-entry/audit row, preserves order
  workflow state, cannot see another tenant's financial rows, and rejects
  forged cross-tenant financial inserts. `make tenant-isolation-pgsql` passed
  with 73 tests and 403 assertions; `make orders-concurrency-pgsql` passed
  with 10 tests and 60 assertions; and `make cashboxes-concurrency-pgsql`
  passed with 3 tests and 28 assertions.
- FCPF6 repository gates passed on the final source state: `make test
  ARGS='tests/Feature/Payments/CaptureCashPaymentCoverageTest.php
  tests/Feature/Payments/CaptureCashPaymentActionTest.php
  tests/Feature/Payments/CaptureCashPaymentContractTest.php
  tests/Feature/Payments/PaymentFinancialSchemaTest.php
  tests/Feature/Payments/PaymentFinancialModelsTest.php
  tests/Feature/Orders/PayableOrderReaderTest.php
  tests/Architecture/ModuleBoundariesTest.php'` passed before and after Pint
  with 49 tests and 759 assertions; `make test ARGS='tests/Feature/Payments'`
  passed with 43 tests, 9 skipped PostgreSQL-only tests, and 587 assertions;
  `make pint` passed across 404 files and fixed one style issue in the new
  PostgreSQL test; `make stan` passed with no errors; `make test` passed with
  475 tests, 41 skipped PostgreSQL-only tests, and 4649 assertions; and
  `make fresh` passed, including migrations, deterministic demo seeding, and
  runtime database grants.
- FCPF7 reconciliation passed after `git fetch origin`: current branch was
  `feature/payments-cash-payment-capture-foundation` at
  `6763c85955f6d3e2162c278d7958dd4fb464ffb1` with no upstream and clean
  worktree; `origin/main` was
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd`; the branch was 8 commits ahead
  and 0 behind `origin/main`; FCPF0 through FCPF6 were committed and matched
  the worklog; the authoritative FCPF7 checklist contained only the Payments
  PostgreSQL concurrency worker and the `payments-concurrency-pgsql` Make
  target; and both artifacts were already present from commits
  `8d692eee480d765acf1a98075b1712a68e598b9b` and
  `6763c85955f6d3e2162c278d7958dd4fb464ffb1`.
- FCPF7 verification passed: PostgreSQL version was
  `PostgreSQL 17.10` on the repository Docker service; `make
  payments-concurrency-pgsql` passed before and after Pint with 6 tests and
  103 assertions, using the tenant-test PostgreSQL connection with
  privileged schema setup and real separate-process Payments workers; `make
  runtime-role-pgsql` passed before and after Pint with 5 tests and
  93 assertions, using the restricted non-owner `NOBYPASSRLS` runtime-test
  role; `make tenant-isolation-pgsql` passed with 73 tests and
  403 assertions; `make test ARGS='tests/Architecture/ModuleBoundariesTest.php'`
  passed before and after Pint with 11 tests and 257 assertions; `make pint`
  passed across 404 files; `make orders-concurrency-pgsql` and
  `make cashboxes-concurrency-pgsql` initially failed only because they were
  run in parallel against the same PostgreSQL test database and collided during
  `migrate:fresh`, then passed when rerun sequentially with 10 tests /
  60 assertions and 3 tests / 28 assertions respectively; `make stan` passed
  with no errors; `make test` passed with 475 tests, 41 skipped
  PostgreSQL-only tests, and 4649 assertions; and `make fresh` passed,
  including migrations, deterministic demo seeding, and runtime database
  grants.
- FCPF8 reconciliation passed after read-only document/code inspection and
  `git fetch origin`: current branch was
  `feature/payments-cash-payment-capture-foundation` at
  `62f215fa1f108f5437bfc0adcfcecc5c5df0ea5a` with no upstream and clean
  worktree/index; `origin/main` was
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd`; the branch was 9 commits ahead
  and 0 behind `origin/main`; FCPF0 through FCPF7 were committed and matched
  the worklog; and FCPF8 was the next unchecked verification-only step.
- FCPF8 focused SQLite/architecture verification passed before and after Pint:
  `make test ARGS='tests/Feature/Payments/CaptureCashPaymentActionTest.php
  tests/Feature/Payments/CaptureCashPaymentCoverageTest.php
  tests/Feature/Payments/CaptureCashPaymentContractTest.php
  tests/Feature/Payments/PaymentFinancialSchemaTest.php
  tests/Feature/Payments/PaymentFinancialModelsTest.php
  tests/Feature/Orders/PayableOrderReaderTest.php
  tests/Architecture/ModuleBoundariesTest.php'` passed with 49 tests and
  759 assertions both times, covering the committed capture action, FCPF5
  SQLite matrix, FCPF3 contracts, financial schema/models, payable-order
  reader, and module boundaries.
- FCPF8 PostgreSQL verification passed sequentially on PostgreSQL `17.10`:
  `make payments-concurrency-pgsql` passed before and after Pint with 6 tests
  and 103 assertions, using the tenant-test PostgreSQL database with
  privileged schema preparation and real separate-process Payments workers;
  `make tenant-isolation-pgsql` passed with 73 tests and 403 assertions,
  including financial forced RLS, tenant isolation, insert-consistency,
  trigger, and append-only coverage; `make orders-concurrency-pgsql` passed
  with 10 tests and 60 assertions; `make cashboxes-concurrency-pgsql` passed
  with 3 tests and 28 assertions; and `make runtime-role-pgsql` passed before
  and after Pint with 5 tests and 93 assertions, using privileged
  migration/setup followed by the restricted non-owner `NOBYPASSRLS`
  runtime-test role. No PostgreSQL target was run concurrently in FCPF8, so
  the known shared test-database contention did not recur.
- FCPF8 repository-wide gates passed on the final source state: `make pint`
  passed across 404 files; `make stan` passed with no errors; `make test`
  passed with 475 tests, 41 skipped PostgreSQL-only tests, and
  4649 assertions, with those SQLite-suite skips not counted as PostgreSQL
  verification; `make fresh` passed, including migrations, deterministic demo
  seeding, and runtime database grants; and `git diff --check` passed. No
  correction was necessary.
- PR #64 Ready transition verification passed on exact head
  `de22d6c9453aad7edba336077f6d6b598d7e15e8`: the PR was open/draft before
  the authorized transition, local and remote feature-branch SHAs matched,
  branch protection/mergeability checks were clean, zero reviews/comments/
  threads existed, and exact-head push and pull-request CI runs had completed
  successfully for `quality`, `tenant-isolation-pgsql`,
  `orders-concurrency-pgsql`, and `runtime-role-pgsql`.
- PR #64 merge verification passed: GitHub reported PR #64 merged at
  `2026-08-05T10:40:01Z` with merge commit
  `db7579d5d450b0638a71a2d795770b5ca3c99858`; the merge commit parents were
  `6a7b38890c7350e48b0c2b5c0d3fd263a30376fd` and
  `de22d6c9453aad7edba336077f6d6b598d7e15e8`; `origin/main` contained the
  merge; exact-head CI remained successful; and the retained local/remote
  feature branch stayed at
  `de22d6c9453aad7edba336077f6d6b598d7e15e8`.
- PR #64 post-merge housekeeping validation passed: local `main` was
  fast-forwarded to `origin/main` at
  `db7579d5d450b0638a71a2d795770b5ca3c99858`; the housekeeping branch
  `docs/phase-3-pr64-post-merge` was created from that synchronized `main`;
  and this worklog update changed only `docs/worklog/PHASE-3.md`.
- PR #65 merge verification passed: GitHub reported PR #65 merged at
  `2026-08-05T11:22:43Z` with merge commit
  `9867476a828567b6e06f5adce8ba5bfb908942d9`; the merge commit parents were
  `db7579d5d450b0638a71a2d795770b5ca3c99858` and
  `ab0a57c66d5e62fbaf8a345f0cc7f8c30ff1c8fc`; local `main`, `origin/main`,
  and GitHub `main` were aligned at the merge commit with ahead/behind
  `0/0`; the worktree, index, and untracked-file state remained clean; and
  there were no open duplicate housekeeping PRs.
- PR #65 post-merge documentation reconciliation preserved retained branches:
  local and remote `docs/phase-3-pr64-post-merge` and
  `feature/payments-cash-payment-capture-foundation` still existed, and no
  branch cleanup was performed.

## Next Steps

Full Cash Payment Capture Foundation FCPF0 through FCPF11 remains complete
through merged PR #64, the merged PR #65 post-merge documentation
housekeeping, and this PR #65 reconciliation branch. No FCPF implementation
item remains pending. The previous lifecycle instruction to review CI for the
Draft PR created from `docs/phase-3-pr64-post-merge`, then decide whether to
mark that docs-only PR Ready and merge it, is complete and is no longer the
next action.

No new bounded Phase 3 implementation slice is currently approved. The next
authoritative action is owner selection and explicit approval of the next
bounded Phase 3 slice. Fiscal/printing, split or partial payments, prepayments,
debts, and printer monitoring remain broad Blueprint Phase 3 areas, not
automatically authorized tasks. Retained documentation and feature branch
cleanup is optional and requires separate explicit authorization.

Payment financial schema now exists as the FCPF1 migration and schema-focused
tests, the FCPF2 append-only financial Eloquent models and model-focused tests,
the FCPF3 command/result DTOs, canonical fingerprint helper, stable domain
errors, translations, contract tests, the FCPF4 Application-only cash capture
action with minimal focused action tests, and the FCPF5 comprehensive
SQLite-compatible coverage. FCPF6 PostgreSQL RLS/runtime-role/trigger/
append-only/atomicity/concurrency coverage is complete. The FCPF7 Payments
PostgreSQL concurrency worker and Make target are complete. FCPF8 focused and
complete verification is complete. FCPF9 exact diff and inventory review is
complete. FCPF10 pushed the reviewed branch and opened Draft PR #64. No
fiscalization, printing, UI, routes, controllers, Livewire, API, domain events,
outbox, branch cleanup, or next-slice implementation work has begun.
