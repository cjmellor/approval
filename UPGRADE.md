# Upgrade Guide

## Upgrading from v1 to v2

This guide will help you safely upgrade from v1.x to v2.x of the Approval package.

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

#### Step 5: (Optional) Publish and Update Configuration

To use custom approval states, publish the config file:

```bash
php artisan vendor:publish --tag="approval-config"
```

Then edit `config/approval.php` to define your custom states.

### Verifying Your Upgrade

After upgrading, verify that:

1. Your existing approvals are still accessible
2. Standard approval operations (approve, reject) work correctly
3. The new features like custom states are available

### Potential Issues

- If you encounter a "table approvals has no column named custom_state" error after upgrading, run `php artisan approval:upgrade-to-v2`
- If you see errors about missing creator columns, ensure you've completed step 4 (Apply Additional Migrations)
