# Spike: Platform Operator Identity

Task: `2.20-spike-platform-operator-identity`

Base inspected: `origin/main` at
`bb3c3a2fd8cd7d340cb03bd5cd0bdf6d18b172c3`.

## The question

`docs/BLUEPRINT.md` section 5 leaves one authorization question open: where
does a SmartRest platform operator account live, given that `users.tenant_id`
is not nullable, cascades on tenant delete, and `User` uses `BelongsToTenant`.
This spike compares the three shapes already recorded in the Blueprint:

- S1. Make `users.tenant_id` nullable; a platform operator is a user with no
  tenant.
- S2. Introduce a dedicated platform tenant that hosts operator accounts.
- S3. Move platform operators to a separate entity with its own auth guard.

This document does not choose an option and does not answer the other
authorization open questions.

## Why it blocks other work

The decided authorization model gives only the platform superadmin control over
feature availability. It also records three platform/tenant boundary debts that
must close before real tenant onboarding: tenant owner accounts are currently
seeded as superadmins, the platform-operator account has no structural place,
and runtime database role/RLS separation remains a production gate. The current
schema confirms the structural problem: `users.tenant_id` is
`foreignId()->constrained()->cascadeOnDelete()` in
`database/migrations/0001_01_01_000000_create_users_table.php:65`,
`users` has tenant-scoped unique constraints on `email` and `username` at
lines 77-78, and `User` uses `BelongsToTenant` in
`app/Modules/Identity/Infrastructure/Models/User.php:22`.

The current runtime role debt is already recorded:
`docs/DECISIONS.md:235-251` says production runtime should use an
unprivileged `smartrest_app` role with no `BYPASSRLS`, but current
`docker-compose.yml`, `.env.example`, and `config/database.php` still point
runtime traffic at `smartrest`. CI and local PostgreSQL test targets do create
non-`BYPASSRLS` roles for test execution
(`.github/workflows/ci.yml:103-109`, `.github/workflows/ci.yml:172-178`,
`Makefile:61-68`).

## S1: nullable `users.tenant_id`

### A. Schema impact

This shape changes the existing `users.tenant_id` foreign key from not-null to
nullable. The current column is created at
`database/migrations/0001_01_01_000000_create_users_table.php:65` and currently
cascades when its tenant is deleted. It also participates in
`unique(['tenant_id', 'email'])` and `unique(['tenant_id', 'username'])` at
lines 77-78.

In PostgreSQL, a unique index treats `NULL` values as distinct by default. If
`users.tenant_id` becomes nullable and the current composite unique constraints
remain unchanged, multiple platform users with `tenant_id = NULL` can share the
same non-null `email`, and multiple platform users with `tenant_id = NULL` can
share the same non-null `username`. That would silently permit duplicates unless
an additional partial unique index or another uniqueness rule is added for the
platform-user subset.

Related nullable propagation would have to be reviewed wherever `users` is
referenced by foreign keys. `role_id` is nullable today at
`0001_01_01_000000_create_users_table.php:66`; `user_branch_assignments.user_id`
cascades at lines 95-100; `audit_logs.actor_id` references `users` and is
nullable/restricted at
`database/migrations/2026_07_23_000000_create_audit_logs_table.php:16-18`.

### B. RLS impact

The base Identity migration enables and forces RLS on `users` and related
Identity tables, then creates policies comparing each row's `tenant_id` to
`current_setting('smartrest.tenant_id', true)` at
`0001_01_01_000000_create_users_table.php:144-147`. With
`tenant_id = NULL`, those rows do not match any tenant id. With no tenant
setting, `nullif(current_setting(...), '')::bigint` is null, and SQL
`tenant_id = NULL` does not evaluate true. Therefore a nullable platform user
row is invisible to the existing RLS policy unless the policy is changed.

This shape does not by itself improve or worsen the recorded `BYPASSRLS` debt.
The debt remains: a role with `BYPASSRLS` or superuser privileges can bypass
RLS regardless of whether platform users are represented by null tenant ids.
Under the intended non-`BYPASSRLS` runtime role, nullable platform rows need a
separate policy path or a non-RLS authentication query path.

### C. BelongsToTenant impact

`BelongsToTenant` attaches `TenantScoped` to every model using the trait in
`app/Modules/Tenancy/Contracts/BelongsToTenant.php:11-20`. `TenantScoped`
returns `whereRaw('1 = 0')` when no tenant is resolved
(`app/Modules/Tenancy/Contracts/TenantScoped.php:18-23`) and otherwise adds
`where tenant_id = current tenant` at line 26. A platform user with
`tenant_id = NULL` cannot be found through `User::query()` unless either a
tenant context is set and a special scope path exists, or the query bypasses
global scopes.

The current `User` model uses `BelongsToTenant`
(`User.php:22`), and role, permission, and branch-assignment models do too:
`Role.php:15`, `Permission.php:14`, and `UserBranchAssignment.php:14`. S1 would
need a special case for platform users in the user scope and likely in the
login query. It also needs a decision on whether platform users have roles at
all, because roles are tenant-owned today
(`0001_01_01_000000_create_users_table.php:41-49`).

### D. Authentication impact

Login uses one `web` guard and one `users` provider
(`config/auth.php:42-70`). `AuthenticateUser` loops through active tenant ids,
sets each as tenant context, then performs a tenant-scoped `User::query()`
lookup by email and active flag
(`app/Modules/Identity/Application/AuthenticateUser.php:29-35`). On success it
logs in that `User` at line 41. `LoginController` writes
`$user->tenant_id` to the session at
`app/Modules/Identity/Http/Controllers/LoginController.php:32-34`.

S1 breaks the current login path for platform users because active-tenant
iteration never searches `tenant_id = NULL`, `User::query()` with no tenant
context returns no rows, and the controller casts `null` tenant id to integer
for session storage. The existing login tests assert that login stores
`tenant_id` in session and that middleware resolves context from the logged-in
user (`tests/Feature/Identity/LoginTest.php:37-49`,
`tests/Feature/Identity/LoginTest.php:87-109`).

### E. Tenant entry and audit

Current tenant entry mechanisms are not platform-operator entry mechanisms.
`ResolveTenant` chooses the authenticated user's `tenant_id` first
(`app/Modules/Tenancy/Http/Middleware/ResolveTenant.php:54-60`), then session,
then non-production `X-Tenant-ID` (`ResolveTenant.php:62-72`). Production
headers are ignored. Branch entry is constrained by assigned branch ids for
authenticated users in `ResolveBranch`
(`app/Modules/Tenancy/Http/Middleware/ResolveBranch.php:50-95`,
`101-124`).

The audit recorder requires tenant context before it can write:
`EloquentAuditRecorder` obtains current context at
`app/Support/Audit/EloquentAuditRecorder.php:25`, reads `tenant_id` at
`app/Support/Audit/EloquentAuditRecorder.php:32`, and throws if it is not an
integer at `app/Support/Audit/EloquentAuditRecorder.php:34-35`. The audit
table stores `actor_id` as a nullable FK to `users` at
`database/migrations/2026_07_23_000000_create_audit_logs_table.php:16-18`, and
the model is tenant-scoped through `BelongsToTenant`
(`app/Support/Audit/AuditLog.php:24-27`). The recorder writes `actor_id` from
log context at `app/Support/Audit/EloquentAuditRecorder.php:44-55`.

For S1, a tenant-entry event by an operator with `tenant_id = NULL` is not
recordable by the current recorder before a tenant context is set. After a
tenant context is set, the current audit table can store the platform user's id
in `actor_id`, but that actor does not belong to the audit row's tenant. The
current table can express that only because `actor_id` is a global FK to
`users`; it does not explicitly mark the actor as a platform actor. A tenant
entry implementation would need a way to set tenant context, record the entry
inside that tenant's audit stream, and mark that the actor is not a tenant
member. The current audit table cannot explicitly express platform actor type.

### F. Blast radius

Grep-backed surface:

- `is_superadmin` appears in 11 application files and 21 test files.
- `BelongsToTenant` is used by 16 application model files.
- `tenant_id` appears in 36 application files and 39 test files.
- RLS policy creation appears in 11 migration files.
- Login/session tenant assumptions appear in 22 files.
- `actingAs`, `Livewire::actingAs`, `auth()->login`, or `Auth::login` appear
  in 34 files.

For S1, likely affected areas include the users migration and unique indexes,
`User`, `TenantScoped` or platform-user query paths, `AuthenticateUser`,
`LoginController`, `ResolveTenant`, audit recording, tests that expect login to
store tenant id, tests that expect no visible users without tenant context, and
tests that create every user inside a tenant helper.

### G. Reversibility

Moving away from S1 later requires backfilling nullable platform users into a
new home, removing any special nullable-user RLS policies, replacing any
platform-user partial unique indexes, and changing login/session assumptions
again. The hardest part is not the nullable column itself; it is undoing any
scope and auth special cases added so a tenant-scoped model can also represent
above-tenant users.

### H. Effect on dependent security debts

S1 can let the demo seeder stop marking tenant owners as superadmin because
platform operators can exist outside tenant ownership. It also removes the
specific cascade-delete defect for platform users because a null tenant id has
no tenant row to cascade from. It does not by itself define replacement
authorization for archive visibility or destructive routes that currently gate
on `is_superadmin`.

## S2: dedicated platform tenant

### A. Schema impact

This shape can keep `users.tenant_id` not-null and keep the existing tenant FK
and composite unique constraints unchanged
(`0001_01_01_000000_create_users_table.php:65`, `77-78`). Platform operators
would be ordinary rows in `users` whose `tenant_id` points to a special tenant
row. No PostgreSQL null-unique duplicate issue appears because platform
operator rows use a real tenant id.

The schema impact moves to tenant semantics. `tenants` currently has `name`,
unique `slug`, defaults, currency, `status`, and timestamps
(`0001_01_01_000000_create_users_table.php:17-24`). There is no current column
that marks a tenant as "platform" rather than restaurant. Branches require a
tenant id and cascade on tenant delete at lines 27-38. Users can be assigned
to branches through `user_branch_assignments` at lines 93-101, but cannot
enter another tenant through that table because each assignment row is
tenant-owned.

### B. RLS impact

The existing RLS policies work mechanically for platform-tenant rows because
they have a normal tenant id. When `smartrest.tenant_id` is set to the platform
tenant id, platform users, roles, permissions, and assignments are visible
under the existing Identity policy at
`0001_01_01_000000_create_users_table.php:144-147`.

Entering a restaurant tenant still requires setting `smartrest.tenant_id` to
that restaurant's id. At that point, RLS exposes that restaurant's rows and
hides platform-tenant rows. The current `Auth::id()` remains available from the
session, but tenant-scoped `User::query()` lookups for the operator would no
longer find the operator while the restaurant tenant context is active.

This shape does not improve or worsen the recorded `BYPASSRLS` debt. It keeps
the existing tenant-id equality policy shape intact, so the debt remains
unchanged: the runtime role still must be non-`BYPASSRLS` before real tenant
onboarding.

### C. BelongsToTenant impact

`BelongsToTenant` and `TenantScoped` require a concrete tenant context
(`BelongsToTenant.php:11-20`, `TenantScoped.php:18-26`). S2 fits that model for
platform account storage because platform users live in a tenant. It creates a
different special case: application code must know when the current tenant is
the platform tenant versus a restaurant tenant.

During support entry into a restaurant tenant, `TenantScoped` would scope
tenant-owned queries to the restaurant. That is correct for restaurant data,
but any platform-user metadata stored under the platform tenant would be hidden
from normal `User::query()` while the operator is inside the restaurant tenant.
Code that needs the operator row during entry must use the authenticated
session identity, an unscoped user lookup, or a separate platform-identity read
path. Which one is intended cannot determine from code; an implementation
choice would settle it.

### D. Authentication impact

S2 is closest to the current auth flow because `AuthenticateUser` already loops
through `TenantDirectory::activeTenantIds()` when no tenant is resolved
(`AuthenticateUser.php:69-78`), and `EloquentTenantDirectory` returns active
tenant ids ordered by id (`app/Modules/Tenancy/Infrastructure/Directory/EloquentTenantDirectory.php:13-23`).
If the platform tenant is active, login can find platform users the same way it
finds tenant users. `LoginController` can still store a concrete tenant id in
session (`LoginController.php:32-34`).

The missing code-level distinction is that nothing in `tenants` currently marks
one tenant as platform. Without that distinction, the login flow cannot
separate platform operator login behavior from restaurant user login behavior.
Also, if platform and restaurant users can have the same email in different
tenants, the current active-tenant iteration order determines which account is
found first (`AuthenticateUser.php:29-35`). That ambiguity already exists for
same-email users across tenants; S2 would include platform accounts in the same
search. Whether that is acceptable cannot determine from code.

### E. Tenant entry and audit

S2 needs an explicit transition from platform tenant context to restaurant
tenant context. Current `ResolveTenant` does not provide that: authenticated
user tenant id wins over session tenant id (`ResolveTenant.php:54-64`), so a
platform-tenant user cannot enter a restaurant tenant merely by setting session
`tenant_id`. Current branch resolution also authorizes authenticated branch ids
through assigned branch ids (`ResolveBranch.php:91-95`, `101-124`), and a
platform-tenant user has no restaurant branch assignment under the current
model.

Audit evidence is the same as S1. `EloquentAuditRecorder` requires tenant
context (`app/Support/Audit/EloquentAuditRecorder.php:32-35`),
`audit_logs.actor_id` points to `users`
(`database/migrations/2026_07_23_000000_create_audit_logs_table.php:16-18`),
and `AuditLog` is tenant-scoped (`app/Support/Audit/AuditLog.php:24-27`). Under
S2, a restaurant tenant-entry event could be recorded after restaurant context
is set, and `actor_id` could point to the platform-tenant user because it is
still a row in `users`. The current audit table still cannot explicitly mark
that the actor is a platform actor or record the source platform tenant. That
would need a new field or encoded audit payload convention.

### F. Blast radius

Grep-backed surface:

- `tenant_id` appears in 36 application files and 39 test files.
- `BelongsToTenant` is used by 16 application model files.
- Login/session tenant assumptions appear in 22 files.
- `TenantDirectory::activeTenantIds()` is used by login to scan tenant ids
  (`AuthenticateUser.php:69-78`; `EloquentTenantDirectory.php:13-23`).
- Branch assignment authorization is centralized in `ResolveBranch` and
  `EloquentUserDirectory` (`ResolveBranch.php:91-95`, `101-140`;
  `EloquentUserDirectory.php:18-37`).

S2 avoids nullable-user migration churn but touches tenant seeding/provisioning,
tenant classification, login ambiguity, tenant-entry flow, branch-entry flow,
audit marking, and tests that currently treat every tenant as a restaurant.

### G. Reversibility

Moving away from S2 later means moving platform users from the platform tenant
to nullable users or a separate table, then removing the special platform
tenant and any tenant-type checks. Tenant-scoped data movement is concrete but
bounded: platform accounts are rows under one tenant. The main reversibility
risk is if other platform configuration starts accumulating under the platform
tenant and becomes mixed with normal tenant assumptions.

### H. Effect on dependent security debts

S2 can let the demo seeder stop marking tenant owners as superadmin because
platform operators can exist in the platform tenant. It prevents the current
defect where deleting an arbitrary restaurant tenant could delete a platform
operator account, but it introduces a narrower lifecycle concern: deleting the
dedicated platform tenant would still delete platform users because
`users.tenant_id` cascades. The implementation would need to make the platform
tenant non-deletable or otherwise protected. S2 does not by itself define what
replaces `is_superadmin` for archive visibility and destructive route gates.

## S3: separate platform entity and auth guard

### A. Schema impact

This shape leaves restaurant `users` and their tenant FK constraints unchanged.
It introduces a separate platform-operator table or entity, plus any password
reset/session/profile fields that platform login requires. Current auth config
has only one guard, `web`, and one Eloquent provider, `users`, backed by
`User::class` (`config/auth.php:42-70`). S3 would add at least another provider
and probably another session guard.

The existing `audit_logs.actor_id` references `users` at
`2026_07_23_000000_create_audit_logs_table.php:18`, so a separate platform
entity cannot be represented as `actor_id` without either a nullable
`platform_actor_id`, a polymorphic actor shape, or some other schema change.
The current audit table cannot express a platform actor as a first-class
foreign key.

### B. RLS impact

Restaurant data RLS remains unchanged because restaurant users and tenant-owned
tables keep the existing tenant-id equality policies. Platform-operator rows
would not be in tenant-owned tables unless the new table is itself tenant-owned.
If the platform-operator table is not tenant-owned, the existing
`smartrest.tenant_id` RLS pattern does not apply to it unless new platform RLS
rules are designed.

During tenant entry, restaurant row visibility is still controlled by setting
`smartrest.tenant_id` to the restaurant tenant. This shape separates platform
authentication identity from tenant data visibility more explicitly than S1 or
S2, but it does not remove the runtime role debt. Any runtime role with
`BYPASSRLS` can still bypass tenant RLS, so the recorded pre-onboarding gate is
unchanged.

### C. BelongsToTenant impact

S3 avoids forcing platform operators through `BelongsToTenant` and
`TenantScoped`. Existing tenant-owned models keep their current behavior:
without tenant context, `TenantScoped` returns no rows
(`TenantScoped.php:18-23`); with tenant context, it filters by tenant id at
line 26. Platform identity reads would use a separate model that does not use
`BelongsToTenant`, or a separate scope designed for platform records.

The impact is at integration points: services expecting
`Illuminate\Contracts\Auth\Authenticatable` plus a `tenant_id` attribute would
need review. For example `ResolveTenant` reads `data_get($request->user(),
'tenant_id')` (`ResolveTenant.php:54-60`), and tenant translation override
actions compare actor `tenant_id` to current tenant before permission checks
(`SetTenantTranslationOverride.php:96-105`,
`ResetTenantTranslationOverride.php:94-103`).

### D. Authentication impact

A second guard requires at least config/auth changes for a new guard and
provider, a platform-authenticatable model, login/logout routes or branching in
the existing login controller, middleware that can authenticate platform routes
or tenant-entry routes, and authorization integration that knows whether the
current actor came from the tenant guard or platform guard. The current
application has one `web` session guard (`config/auth.php:42-47`) and one
`users` provider (`config/auth.php:66-70`). Routes use `auth` middleware
without guard qualifiers (`routes/web.php:34-64`, `67`, `123`, `150`;
`routes/api.php:8-13`).

`AuthenticateUser` is tied to `User`: it imports the User model, returns
`?User`, loops active tenant ids, and logs in through `Auth::login($user)`
(`AuthenticateUser.php:7`, `25-41`). `LoginController` writes
`$user->tenant_id` to session (`LoginController.php:32-34`). Under S3, the
tenant login flow can stay as-is, but platform login requires a parallel flow
or a branching flow. Existing tests use `actingAs` or Livewire `actingAs`
widely: grep found 34 files with `actingAs`, `Livewire::actingAs`,
`auth()->login`, or `Auth::login`.

### E. Tenant entry and audit

S3 needs a tenant-entry mechanism that accepts a platform-authenticated actor
and then sets tenant context for the target restaurant. Current
`ResolveTenant` cannot do that because it reads `tenant_id` from
`$request->user()` and otherwise uses session/header rules intended for tenant
users (`app/Modules/Tenancy/Http/Middleware/ResolveTenant.php:54-72`). Current routes apply `tenant`, `branch`, and
`auth` middleware to tenant admin areas (`routes/web.php:42-64`, `67`, `123`,
`150`).

Current audit cannot record a platform actor first-class. The recorder requires
tenant context (`app/Support/Audit/EloquentAuditRecorder.php:32-35`), writes
`actor_id` from log context (`app/Support/Audit/EloquentAuditRecorder.php:44-55`),
and `LogContext` gets user id from the default auth facade
(`app/Support/Logging/LogContext.php:115-119`). `audit_logs.actor_id` references
`users`
(`database/migrations/2026_07_23_000000_create_audit_logs_table.php:18`).
For S3, a tenant-entry event would require either an audit schema that can store
a platform actor id/type, or a mapping from platform actor to a user row. The
current audit table cannot express a platform actor as its own entity.

### F. Blast radius

Grep-backed surface:

- `config/auth.php` has one guard and one provider.
- Routes use unqualified `auth` middleware across admin and API routes
  (`routes/web.php:34-64`, `67`, `123`, `150`; `routes/api.php:8-13`).
- Login/logout code is in three Identity files:
  `LoginRequest.php`, `LoginController.php`, `LogoutController.php`, plus
  `AuthenticateUser.php`.
- 34 files use `actingAs`, `Livewire::actingAs`, `auth()->login`, or
  `Auth::login`.
- `LogContext` gets actor id from the default auth facade at
  `LogContext.php:115-119`.
- Audit schema currently points actors at `users` only.

S3 is the largest auth blast radius because it adds a second identity path, a
second guard/provider, tenant-entry middleware or routes, audit actor support,
and test helpers for both tenant users and platform operators.

### G. Reversibility

S3 is structurally clear but heavier to reverse. Moving away from it later means
migrating platform operator rows into `users`, removing a guard/provider,
removing platform-auth routes/middleware, and changing audit actor references
back to `users`. It avoids contaminating tenant user semantics while it exists,
but it creates a separate identity subsystem that must be intentionally retired
if the shape changes later.

### H. Effect on dependent security debts

S3 can let the demo seeder stop marking tenant owners as superadmin because
platform operators are not tenant owners and are not rows in restaurant
`users`. The cascade-delete defect goes away for platform operators because
restaurant tenant deletion cannot cascade to a separate platform entity.
However, S3 does not by itself define what replaces `is_superadmin` for
archive visibility and destructive route gates, and it requires audit schema
changes before tenant-entry events can identify platform actors first-class.

## `is_superadmin` controls two different things today

This section is true regardless of S1, S2, or S3.

Purpose 1 is a break-glass bypass in the central authorizer.
`EloquentAuthorizer` allows any active `User` with `is_superadmin = true` at
`app/Modules/Identity/Infrastructure/Authorization/EloquentAuthorizer.php:19`.
`AppServiceProvider` sends dotted Gate abilities through that authorizer at
`app/Providers/AppServiceProvider.php:148-154`. This is the behavior recorded
by `docs/DECISIONS.md:649-671`.

Purpose 2 is a hard gate outside the authorizer. `EnsureSuperAdmin` aborts
unless the authenticated user has `is_superadmin` at
`app/Http/Middleware/EnsureSuperAdmin.php:18`, and
`EnsureSuperAdminForDeletes` does the same at
`app/Http/Middleware/EnsureSuperAdminForDeletes.php:18`. Those middleware are
aliased as `superadmin` and `superadmin.delete` in `bootstrap/app.php:35-40`.
`routes/web.php` applies `superadmin` to eight routes: menu category restore
and force-delete (`routes/web.php:90-95`), menu item restore and force-delete
(`routes/web.php:115-120`), hall restore and force-delete
(`routes/web.php:142-147`), and table restore and force-delete
(`routes/web.php:169-174`).

The flag also controls archive visibility and context normalization directly:
`HallController` reads it at
`app/Modules/Tables/Http/Controllers/HallController.php:23`,
`TableController` at
`app/Modules/Tables/Http/Controllers/TableController.php:24`,
`MenuIndexContext` at `app/Modules/Menu/Http/MenuIndexContext.php:48`,
`MenuIndex` at `app/Livewire/Admin/MenuIndex.php:200-203`, and
`MenuItemForm` at `app/Livewire/Admin/MenuItemForm.php:328-334`.

Consequence: the recorded security debt requires removing `is_superadmin` from
tenant owner accounts. The moment that happens, every tenant owner silently
loses the ability to restore or permanently delete archived menu categories,
menu items, halls, and tables, and loses archive visibility, because those
paths gate on the flag rather than on a permission. Cleaning the seeder is
therefore not a boolean change; something must replace the flag on those eight
routes and five visibility checks first.

Decision cross-check: the 2026-07-24 decision says the superadmin bypass "lives
in the authorizer rather than in individual policies or screens" and rejects
one-off superadmin allowances in policies, controllers, or Livewire components
(`docs/DECISIONS.md:649-671`). The hard gates and archive/context checks above
are not the same mechanism as the authorizer bypass: they are hard gates and
visibility gates, not permission bypasses. However, they are still
`is_superadmin` authorization semantics outside the central authorizer, and the
older decision did not record that second pattern. That is drift in
documentation coverage. It is not a direct contradiction of the bypass
implementation claim if read narrowly, but it can mislead a future reader
because `is_superadmin` now has unrecorded screen/controller/Livewire effects.

## What `is_superadmin` controls today

Grep scope: `rg -n "is_superadmin" app resources routes tests`.

Application references:

- `app/Console/Commands/MenuSeedLoadCommand.php:844` creates load users with
  `is_superadmin = false`.
- `app/Livewire/Admin/MenuItemForm.php:333` passes the flag into Menu context
  sanitation.
- `app/Livewire/Admin/MenuIndex.php:202` uses the flag for archive visibility.
- `app/Modules/Menu/Http/MenuIndexContext.php:48` uses the flag to normalize
  archive mode from request/context input.
- `app/Http/Middleware/EnsureSuperAdminForDeletes.php:18` gates requests on
  the flag.
- `app/Http/Middleware/EnsureSuperAdmin.php:18` gates requests on the flag.
- `app/Modules/Tables/Http/Controllers/TableController.php:24` uses the flag
  for archive visibility.
- `app/Modules/Tables/Http/Controllers/HallController.php:23` uses the flag for
  archive visibility.
- `app/Modules/Identity/Infrastructure/Models/User.php:17` marks the flag
  fillable.
- `app/Modules/Identity/Infrastructure/Models/User.php:44` casts the flag to
  boolean.
- `app/Modules/Identity/Infrastructure/Seeders/IdentityDemoSeeder.php:44`
  writes the seeded value.
- `app/Modules/Identity/Infrastructure/Authorization/EloquentAuthorizer.php:19`
  uses the flag for permission bypass.

Resource references: none found.

Route references: none found by raw `is_superadmin` grep. Routes use the
`superadmin` middleware alias instead (`routes/web.php:90-95`, `115-120`,
`142-147`, `169-174`).

Test references:

- `tests/Feature/Orders/OrderWorkspaceItemWritesTest.php:871`
- `tests/Feature/Orders/OrderBoardTest.php:327`
- `tests/Feature/Orders/OrderWorkspaceTest.php:560`
- `tests/Feature/Orders/OrderItemActionsTest.php:534`
- `tests/Feature/Orders/OrderTableOccupancyTest.php:250`
- `tests/Feature/Orders/OrderActionsTest.php:304`
- `tests/Feature/Orders/OrderConcurrencyTest.php:295`
- `tests/Feature/Orders/OpenTablelessOrderTest.php:169`
- `tests/Feature/Orders/OrderWorkspaceMenuPickerTest.php:289`
- `tests/Feature/Tables/HallBladeTest.php:220`
- `tests/Feature/Tables/HallActionsTest.php:167`
- `tests/Feature/Tables/TableBladeTest.php:262`
- `tests/Feature/Tables/TableActionsTest.php:263`
- `tests/Feature/I18n/TenantTranslationOverridePermissionTest.php:109`
- `tests/Feature/I18n/TenantTranslationOverrideEditorTest.php:338`
- `tests/Feature/Menu/MenuIndexLivewireTest.php:565`
- `tests/Feature/Menu/MenuContextReturnTest.php:250`
- `tests/Feature/Menu/MenuBladeTest.php:342`, `360`, `385`, `651`
- `tests/Feature/Menu/MenuOverflowTest.php:200`
- `tests/Feature/Menu/MenuDemoSeederTest.php:25-28`
- `tests/Feature/Menu/MenuContextRedirectSecurityTest.php:194`

## Existing tests likely to fail or need rewriting

S1 likely affects login and tenant context tests first.
`tests/Feature/Identity/LoginTest.php:37-49` expects successful login to store
`tenant_id` in session, and lines 87-109 expect middleware to resolve tenant
and branch from a logged-in user. `TenantIsolationTest.php:45-64` expects no
visible users or branches when tenant context is cleared; S1 needs a deliberate
decision on whether null-tenant platform users remain invisible there.
`TenantIsolationTest.php:218-237` asserts raw PostgreSQL RLS returns no branch
rows without tenant setting, which remains true for tenant rows but would not
cover nullable platform users without new policy tests. Tests that cast
`$user->tenant_id` to int, such as
`TenantTranslationOverridePermissionTest.php:24-49`, would need review for
platform users.

S2 likely preserves the existing tenant-user login tests if the platform tenant
is just another active tenant, but tests that assume all active tenants are
restaurants may need changes. `AuthenticateUser` scans all active tenant ids
(`AuthenticateUser.php:69-78`), and `EloquentTenantDirectory` returns active
tenant ids without excluding any platform tenant (`EloquentTenantDirectory.php:13-23`).
Branch context tests such as
`tests/Feature/Tenancy/BranchContextResolutionTest.php:46-93` assume
authenticated users have assigned restaurant branches; platform operators would
need a separate entry path. Demo seeder tests currently assert tenant owners
are superadmins (`tests/Feature/Menu/MenuDemoSeederTest.php:25-28`), so they
must change once the debt is closed.

S3 likely requires the widest test changes because the suite mostly authenticates
`User` rows through the default guard. Grep found 34 files using `actingAs`,
`Livewire::actingAs`, `auth()->login`, or `Auth::login`. Existing route tests
use unqualified `auth` middleware through the tenant admin routes
(`routes/web.php:34-64`, `67`, `123`, `150`). Any platform guard would need new
test helpers and tests for platform login, tenant entry, and audit actor
recording.

## Newly surfaced questions

- What authorizes destructive operations and archive visibility once tenant
  owners are no longer superadmins?
- Should a platform tenant, if S2 is chosen, be represented in `tenants` as an
  active tenant, or does it need a tenant type/status that excludes it from
  restaurant workflows and login ambiguity?
- Should tenant-entry audit rows store platform actor identity as an explicit
  actor type/id pair rather than overloading `audit_logs.actor_id`?
- Should `LogContext::userId()` continue reading only the default auth guard if
  platform authentication uses a second guard?
- If S1 is chosen, what exact PostgreSQL uniqueness rule protects platform
  operator email and username uniqueness when `tenant_id` is null?

## What the owner must decide

The owner must choose where platform operator accounts live before closing the
two dependent security debts. The decision must also name how a platform
operator enters a restaurant tenant context and how that entry is audited. The
current code cannot determine the intended tenant-entry UX, whether platform
operators should share the tenant login screen, whether platform operators need
branch context during support work, or what replaces `is_superadmin` for
destructive operations and archive visibility after tenant owners stop being
superadmins.
