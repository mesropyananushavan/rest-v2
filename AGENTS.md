# AGENTS.md — SmartRest v2

## Read first, every session
1. `docs/BLUEPRINT.md` — the source of truth for architecture, data model,
   API, and roadmap. If a task conflicts with the blueprint, STOP and ask;
   never silently deviate. Blueprint changes require explicit approval and
   go in a separate commit.
2. `docs/DECISIONS.md` — log of decisions made after blueprint v1.0.
3. The latest `docs/worklog/PHASE-{N}.md` — current phase state and plan.
4. This file.

## Repository map
- `docs/BLUEPRINT.md` — architecture blueprint (see above)
- `docs/DECISIONS.md` — dated operational/tooling decisions with reasons
- `docs/worklog/PHASE-{N}.md` — per-phase working memory (plan, progress,
  gotchas, next steps)
- `template/` — READ-ONLY legacy UI reference (HTML/CSS/JS). Never modify.
  Used only to extract design tokens and understand screen semantics.
- `app/Modules/{Name}/` — modular monolith modules:
  Domain / Application / Infrastructure / Http / Contracts / Events

## Hard rules (violations = broken build or rejected PR)
- **Module boundaries:** a module may reference another module ONLY via its
  `Contracts/` and `Events/`. Never import another module's Domain,
  Application, Infrastructure, Http, or Eloquent models. Never query another
  module's tables. Architecture tests enforce this — keep them green and
  extend them when adding modules.
- **Tenancy:** every tenant-owned table has `tenant_id` + index; every
  tenant-owned model uses `BelongsToTenant` + `TenantScoped`. Any query
  bypassing the tenant scope must be explicitly justified in a comment
  and covered by a test. Every new resourceful route must include a tenant
  isolation test proving a user from tenant A requesting tenant B's resource
  by id receives 404, not 403 or 200.
- **Money:** integer minor units + currency only (ADR-007). No floats,
  no decimals for money. Use the Money value object.
- **i18n:** no hardcoded user-facing strings, in any language. Translation
  keys with `hy`/`ru`/`en` files. User-editable names are JSON translation
  value objects.
- **Logic placement:** business logic lives in Application actions.
  Controllers (Blade and API) and Livewire components are thin adapters:
  validate, authorize, call action, present. No business rules in
  controllers, views, models, or migrations.
- **External effects** (payments, fiscal, printing, webhooks): idempotent,
  queued, retry-safe, never inside a DB transaction.
- **Financial/stock records** (payments, cashbox entries, fiscal receipts,
  stock movements, audit logs): append-only. No updates, no soft deletes —
  corrections are reversal records.
- Seeders must be deterministic, cover two tenants, and refuse to run
  outside local/testing environments.

## Code style
- PHP 8.3, `declare(strict_types=1)` in every file, typed properties and
  return types everywhere, `final` classes by default, constructor property
  promotion, readonly where applicable.
- Naming: Actions are imperative verbs (`CreateMenuItem`), events are past
  tense (`MenuItemCreated`), contracts are capabilities (`PriceResolver`).
- No new abstractions "for the future". Build what the current phase needs;
  the blueprint already defines the extension points.
- Follow existing patterns in the codebase before inventing new ones.
  If two patterns conflict, ask.

## Workflow
- Host PHP may be outdated — never run PHP directly on the host.
  Use `make` targets for everything: `make up`, `make fresh`, `make test`,
  `make stan`, `make pint`, `make shell`. If a needed target is missing,
  add it to the Makefile rather than running raw docker commands.
- Before declaring any task done: `make pint && make stan && make test`
  all green, and demo seeders updated so the change is visible in the UI
  after `make fresh`.
- Work in the current feature branch; never commit to `main`.
- Respect STOP checkpoints in task prompts: present the requested
  artifacts and wait for approval.
- When blocked by dependency/version incompatibilities, report the issue
  with options — never swap in an incompatible or abandoned package
  silently (see the Horizon note in docker-compose.yml).

## Task decomposition
- Before starting any task, break it into small steps (each roughly
  15-60 minutes of work, one coherent change) and WRITE the plan into the
  current phase worklog (`docs/worklog/PHASE-{N}.md`) before writing code.
- Execute steps one at a time. After each step: run the relevant checks,
  mark the step done in the worklog with a one-line result.
- If a step turns out bigger than expected, split it in the worklog
  instead of pushing through a large uncommitted change.
- One step = one logical commit where practical. Never accumulate a huge
  uncommitted diff across many steps.
- Documentation moves WITH the code, in the same commit:
    * every commit that completes a step includes the worklog update for
      that step (checkbox marked, one-line result, "Next steps" adjusted);
    * a commit that introduces a new decision includes the DECISIONS.md
      entry;
    * a commit whose changes affect setup, URLs, credentials, or commands
      includes the README update.
      A code commit without its documentation counterpart is an incomplete
      commit. Do not defer documentation to "a cleanup commit later".
- If a task prompt contains STOP checkpoints, they override this plan's
  pacing: stop where told.

## Session continuity (mandatory)
You have no memory between sessions. The worklog is your memory.
- START of every session: read `AGENTS.md`, `docs/DECISIONS.md`, and the
  current `docs/worklog/PHASE-{N}.md` (latest N). Resume from
  "Next steps" — do not re-plan finished work, do not redo done steps.
- DURING the session: keep the worklog current — check off steps, add
  discovered problems and their resolutions to "Gotchas".
- END of every session (or when the context is getting long, or before
  any STOP checkpoint): update the worklog so that a fresh session with
  zero context could continue the work. This update is NOT optional and
  is never skipped "to save time".
- New architectural/tooling decisions made mid-work go to
  `docs/DECISIONS.md` (one dated entry: decision, reason, alternatives
  rejected). The worklog is for progress; DECISIONS.md is for "why".
- The "Next steps" section of the worklog must ALWAYS end the session in
  a state where the single word "continue" is enough to resume: it names
  the exact next step and any decision that is pending from the owner.

## Session start protocol
When the owner starts a session with a minimal or empty message — such as
"continue", "start", "продолжай", "davai", a bare mention of this file
(e.g. "@AGENTS.md"), a single "+", or any message that gives no specific
task — treat it as the signal to resume work. Do the following without
asking any questions:

1. Read `AGENTS.md`, `docs/DECISIONS.md`, and the latest
   `docs/worklog/PHASE-{N}.md`.
2. Run `git status` and `git log --oneline -5` to see the real state of
   the working tree (uncommitted leftovers from a crashed session are
   possible — reconcile them with the worklog before doing anything).
3. Reply with a SHORT status summary (3-6 lines): current phase, last
   completed step, next step from the worklog, any blockers.
4. Then immediately proceed with the next unchecked step from the
   worklog plan — unless the next step is marked as a STOP checkpoint
   or "awaiting owner decision", in which case present what is needed
   and wait.

Never ask "what would you like to work on?" — the worklog answers that.
Only ask a question if the worklog and repo state genuinely contradict
each other and you cannot resolve it safely.

## Definition of done (every task)
1. Blueprint-conformant (or approved deviation, documented).
2. Architecture, unit, and feature tests updated and green.
3. PHPStan and Pint clean.
4. Translations added for all three locales.
5. Demo seed data updated; feature manually testable after `make fresh`.
6. No changes to `template/`; no unapproved changes to `docs/BLUEPRINT.md`.
7. Worklog reflects reality: all completed steps checked with results,
   "Next steps" names the exact next action, new gotchas recorded.

## UI Definition of Done
- Every new admin screen uses `resources/views/layouts/admin.blade.php` and
  existing `x-` Blade components for page structure, cards, tables, actions,
  forms, badges, flash messages, and destructive confirmations.
- Every user-facing string is a translation key with `hy`, `ru`, and `en`
  entries. User-editable names remain localized value objects.
- Money is displayed only through the `App\Support\Money` formatter and forms
  accept major units while storage remains integer minor units.
- Delete/destructive actions use the shared confirm-modal component, never a
  bare delete button.
- Mutating actions return translated success/error flash messages.
- Screens are responsive for desktop, tablets, and mobile using Bootstrap 5
  plus existing SmartRest tokens; no new JS framework or package is added
  without explicit approval.


## Logging (mandatory from Phase 1)
- All logs are structured JSON (single line per record) via a dedicated
  logging channel. Local dev may additionally use a pretty channel, but
  the JSON channel always works.
- Every record automatically carries context: `request_id`
  (correlation id), `tenant_id`, `branch_id`, `user_id`, `module`. This
  is wired once via middleware + Log::shareContext / a context processor —
  individual log calls never pass these manually.
- Correlation id: generated (or accepted from `X-Request-Id` header) at
  request start, returned in every response, propagated into every queued
  job payload and restored in the job's log context. One user action must
  be traceable across web request, events, and background jobs by one id.
- WHAT to log:
    * every Application action: one INFO on success ("action performed",
      action name, key ids, duration ms) and one WARNING/ERROR on domain
      failure (stable error code, input summary WITHOUT sensitive data);
    * every external effect (payment, fiscal, print, webhook): queued,
      attempt started, attempt result, with attempt number and idempotency
      key;
    * every queue job: failure with full context; success only for
      money/fiscal/print jobs;
    * auth events: login success/failure, permission denied (who, what
      permission, on what).
- WHAT NOT to log: passwords, tokens, card numbers, full request bodies,
  per-row loops. Sensitive values are redacted, never truncated by hand
  in each call — use a shared redaction helper.
- Levels: DEBUG local-only diagnostics; INFO business operations
  completed; WARNING expected failures (validation, domain rules,
  retries); ERROR unexpected failures requiring attention. Never log
  expected domain failures as ERROR — alert noise kills alerting.
- Exceptions: unhandled exceptions are logged once, at the boundary
  (handler), with stack trace and full context — never caught, logged,
  and rethrown at every layer (no duplicate stack traces).
- Every log message is a stable English string; dynamic data goes in
  context fields, not interpolated into the message (greppability).
