# Upgrade Guide

## v1.3.1 -> v1.4.0

To support the new `rollback` functionality, a new migration file is needed

```bash
2023_10_09_204810_add_rolled_back_at_column_to_approvals_table
```

Be sure to migrate your database if you plan on using the `rollback` feature.

If you'd prefer to do it manually, you can add the following column to your `approvals` table:

```php
Schema::table('approvals', function (Blueprint $table) {
    $table->timestamp(column: 'rolled_back_at')->nullable()->after('original_data');
});
```
