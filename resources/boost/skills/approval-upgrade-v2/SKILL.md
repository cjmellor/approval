---
name: approval-upgrade-v2
description: Automatically upgrade cjmellor/approval from v1 to v2, applying all breaking changes to the user's codebase.
---

## When to use this skill

Use this skill when a user needs to upgrade from cjmellor/approval v1.x to v2.x.

## Step 1: Pre-flight checks

1. Confirm the user has backed up their database.
2. Run `php artisan migrate:status` to ensure no pending migrations.
3. Run `php artisan test` to ensure the test suite passes before starting.

If tests fail, stop and resolve with the user before proceeding.

## Step 2: Update the package

Run:

```bash
composer require cjmellor/approval:"^2.0"
```

## Step 3: Run migrations

Run these commands in order:

```bash
php artisan vendor:publish --tag="approval-migrations"
php artisan approval:upgrade-to-v2
php artisan migrate
```

## Step 4: Re-publish the config

The config structure changed from nested to flat. Run:

```bash
php artisan vendor:publish --tag="approval-config" --force
```

Then search the user's codebase for old config paths and replace them:

| Search for | Replace with |
|------------|-------------|
| `config('approval.approval.approval_pivot')` | `config('approval.approval_pivot')` |
| `config('approval.approval.users_table')` | `config('approval.users_table')` |

Use Grep to find all occurrences in `app/`, `config/`, and `routes/`:

```
approval\.approval\.
```

Edit every match.

## Step 5: Apply code changes

For each change below, use Grep to search the user's `app/`, `config/`, `routes/`, `database/`, and `tests/` directories. Edit every file that matches.

### 5a. `thenDo()` → `thenCustom()`

**Search:** `thenDo(`

**Action:** Replace `->thenDo(...)` with `->thenCustom()`. Remove the callback argument entirely.

If the callback contained logic (not just a placeholder), create an event listener for `Cjmellor\Approval\Events\ApprovalExpired` and move the logic there. Register the listener in `EventServiceProvider` or via `Event::listen()`.

### 5b. `ModelRolledBackEvent` → `ModelRolledBack`

**Search:** `ModelRolledBackEvent`

**Action:** Replace all occurrences with `ModelRolledBack` — in `use` imports, type-hints, string references, and event listener registrations.

### 5c. Facade removed

**Search:** `Facades\Approval` and `Cjmellor\Approval\Facades\Approval`

**Action:** Replace `use Cjmellor\Approval\Facades\Approval` with `use Cjmellor\Approval\Models\Approval`.

### 5d. Event `$approval` property type

**Search:** event listeners that handle any of: `ModelApproved`, `ModelRejected`, `ModelSetPending`, `ModelRolledBack`, `ApprovalCreated`, `ApprovalExpired`

**Action:** If any listener type-hints `$event->approval` as `Illuminate\Database\Eloquent\Model`, change it to `Cjmellor\Approval\Models\Approval`. If using PHPDoc `@var Model`, update that too.

### 5e. `pending()` scope behaviour

**Search:** `::pending()` or `->pending()`

**Action:** If the user relies on `pending()` returning approvals that have custom states set (e.g. `in_review`), change those calls to `Approval::withAnyState()->where('state', 'pending')->get()`. If they only use standard states, no change needed — inform the user of the behaviour change.

### 5f. Expiration action string comparisons

**Search:** `expiration_action` combined with string comparisons like `=== 'reject'`, `=== 'postpone'`, `=== 'custom'`

**Action:** Replace string comparisons with the `ExpirationAction` enum:

- `=== 'reject'` → `=== ExpirationAction::Reject`
- `=== 'postpone'` → `=== ExpirationAction::Postpone`
- `=== 'custom'` → `=== ExpirationAction::Custom`

Add `use Cjmellor\Approval\Enums\ExpirationAction;` to the file.

### 5g. Mass assignment via `$guarded`

**Search:** any code that calls `Approval::create()` or `Approval::update()` with columns not in the fillable list.

The fillable columns are: `approvalable_type`, `approvalable_id`, `state`, `custom_state`, `new_data`, `original_data`, `rolled_back_at`, `audited_by`, `foreign_key`, `creator_type`, `creator_id`, `expires_at`, `expiration_action`, `actioned_at`, `actioned_by`.

If any code mass-assigns columns not in this list, change it to use `forceFill()` instead.

## Step 6: Final sweep

Run a single grep to catch anything missed:

```
thenDo\(|ModelRolledBackEvent|Facades.Approval|approval\.approval\.|expiration_action.*===.*'
```

If any matches remain, apply the corresponding fix from Step 5.

## Step 7: Verify

1. Run `php artisan test` — all tests must pass.
2. If tests fail, read the failure output and apply fixes. Common issues:
   - Import paths changed (Facade → Model, event renames)
   - String comparisons on `expiration_action` now fail against enum values
   - `thenDo()` calls throw "method not found"
3. Re-run tests until green.
4. Inform the user: "Upgrade to v2 complete. All breaking changes have been applied."
