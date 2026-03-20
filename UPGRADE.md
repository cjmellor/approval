# Upgrade Guide

## Upgrading from v1 to v2

This guide will help you safely upgrade from v1.x to v2.x of the Approval package.

### Breaking Changes

The following changes in v2 may require updates to your application code:

| Change | Before (v1) | After (v2) | Action |
|--------|-------------|------------|--------|
| Custom expiration method | `thenDo(callable $callback)` | `thenCustom()` | Rename calls; move callback logic to an `ApprovalExpired` event listener |
| Rollback event class | `ModelRolledBackEvent` | `ModelRolledBack` | Update event listener type-hints and references |
| Facade removed | `Cjmellor\Approval\Facades\Approval` | *(removed)* | Use `Cjmellor\Approval\Models\Approval` directly |
| Config keys flattened | `config('approval.approval.approval_pivot')` | `config('approval.approval_pivot')` | Re-publish config: `php artisan vendor:publish --tag="approval-config" --force` |
| Event `$approval` property | Typed as `Illuminate\Database\Eloquent\Model` | Typed as `Cjmellor\Approval\Models\Approval` | Update any event listener type-hints |
| `pending()` scope | Returns all approvals with `state=pending` | Excludes approvals with a `custom_state` set | Use `whereState('pending')` if you need the old behaviour |
| Expiration actions | Stored as raw strings (`'reject'`, `'postpone'`) | Stored via `ExpirationAction` enum | Update any code that reads `expiration_action` directly |
| Mass assignment | `$guarded = []` | Explicit `$fillable` array | If you were mass-assigning unusual columns, use `forceFill()` |

### Before You Begin

1. **Create a backup of your database** - This is critical as schema changes will be made
2. **Ensure your application has no pending migrations** - Run `php artisan migrate` to apply any outstanding migrations

### Upgrade Process

#### Step 1: Update the Package

```bash
composer require cjmellor/approval:"^2.0"
```

#### Step 2: Publish New Migrations

The v2 package includes new migrations that need to be published:

```bash
php artisan vendor:publish --tag="approval-migrations"
```

#### Step 3: Run the Automated Upgrade Command

```bash
php artisan approval:upgrade-to-v2
```

This command will:
- Verify your database backup (with a confirmation prompt)
- Add the necessary `custom_state` column for configurable states
- Preserve all your existing approval data
- Validate the data integrity after the migration

#### Step 4: Apply Additional Migrations

```bash
php artisan migrate
```

This will apply any remaining migrations included with v2, such as those for expiration and requestor tracking.

#### Step 5: Re-Publish Configuration

> **Required if you published the config in v1.** The config structure changed — keys were flattened from `approval.approval.*` to `approval.*`, and new options were added (`users_table`, `states`).

```bash
php artisan vendor:publish --tag="approval-config" --force
```

The v1 config looked like this:

```php
return [
    'approval' => [
        'approval_pivot' => 'approvalable',
    ],
];
```

The v2 config is now:

```php
return [
    'approval_pivot' => 'approvalable',
    'users_table' => 'users',
    'states' => [
        'approved' => ['name' => 'Approved'],
        'pending' => ['name' => 'Pending', 'default' => true],
        'rejected' => ['name' => 'Rejected'],
    ],
];
```

If you had customised the `approval_pivot` value, re-apply your customisation after re-publishing. If you reference config values anywhere in your app, update the paths:

```php
// Before (v1)
config('approval.approval.approval_pivot');

// After (v2)
config('approval.approval_pivot');
```

### Verifying Your Upgrade

After upgrading, verify that:

1. Your existing approvals are still accessible
2. Standard approval operations (approve, reject) work correctly
3. The new features like custom states are available

### Potential Issues

- If you encounter a "table approvals has no column named custom_state" error after upgrading, run `php artisan approval:upgrade-to-v2`
- If you see errors about missing creator columns, ensure you've completed step 4 (Apply Additional Migrations)
