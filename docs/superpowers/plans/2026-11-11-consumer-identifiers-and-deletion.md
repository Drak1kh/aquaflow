# Consumer Identifiers and Deletion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate compact provider-specific consumer, connection, and meter identifiers and allow providers to permanently delete a consumer account and its dependent data.

**Architecture:** Identifier allocation uses three counters on the provider record so generated identifiers are sequential and never reused after deletion. Account deletion is an authorized, typed-confirmation action that deletes dependent records in foreign-key-safe order inside one transaction while retaining the audit log.

**Tech Stack:** Laravel, Eloquent, Blade/Flux, Pest.

---

### Task 1: Provider-scoped identifiers

**Files:**
- Create: `database/migrations/2026_11_11_000001_add_identifier_sequences_to_water_providers_table.php`
- Create: `app/Actions/ProviderIdentifiers.php`
- Modify: `app/Http/Controllers/Provider/OperationsController.php`
- Modify: `app/Http/Controllers/MvpController.php`
- Modify: `resources/views/provider/consumers.blade.php`
- Test: `tests/Feature/ConsumerAccountManagementTest.php`

- [ ] Write failing tests for blank consumer registration and edit fields producing `ACC-001`, `CN-001`, and `MTR-001`; assert independent provider sequences and no identifier reuse after deletion.
- [ ] Run `vendor\\bin\\pest tests\\Feature\\ConsumerAccountManagementTest.php --filter="identifier"` and confirm the assertions fail before implementation.
- [ ] Add nullable counters to `water_providers`; allocate the next unused padded identifier under a provider row lock in the existing transactions.
- [ ] Replace ULID fallback values only when the applicable request field is blank; preserve explicit values and legacy values.
- [ ] Update consumer form placeholders to `ACC-001`, `CN-001`, and `MTR-001` and explain blank automatic generation.
- [ ] Re-run the targeted identifier test and confirm it passes.

### Task 2: Permanent consumer account deletion

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Provider/OperationsController.php`
- Modify: `resources/views/provider/consumers.blade.php`
- Test: `tests/Feature/ConsumerAccountManagementTest.php`

- [ ] Write failing tests for a provider-authorized, exact-account-number-confirmed deletion; include portal user, payment allocations/payments, bill adjustments/bills, readings, service events, connections, tickets, notices, and a second consumer that remains intact.
- [ ] Run `vendor\\bin\\pest tests\\Feature\\ConsumerAccountManagementTest.php --filter="delete"` and confirm deletion is not yet routed or handled.
- [ ] Add a `DELETE provider/consumers/{consumer}` provider-scoped and permission-protected route.
- [ ] Add a selected-consumer Delete Account confirmation modal from the action kebab. It must require retyping the account number and make permanence clear.
- [ ] In one database transaction, delete portal access and all dependents in foreign-key-safe order: payment allocations, payments, bill adjustments, bills, readings, service events, connections, tickets, notices, then the consumer. Record the deletion audit event and redirect to the consumer index.
- [ ] Re-run the targeted deletion test and confirm it passes.

### Task 3: Verification

**Files:**
- Test: `tests/Feature/ConsumerAccountManagementTest.php`

- [ ] Run `vendor\\bin\\pest tests\\Feature\\ConsumerAccountManagementTest.php tests\\Feature\\ProviderModulesTest.php`.
- [ ] Run `git diff --check`.
- [ ] Do not run `php artisan test` without explicit user permission.
