# Upgrade Guide

## v1.4.3 -> v1.5.0

> [!IMPORTANT]
> v1.5.0 will now only work with PHP versions >= 8.2. If you are using a version of PHP < 8.2, please use v1.4.5

A new migration needs to be run. Run:

```shell
php artisan vendor:publish
```
then

```shell
php artisan migrate
```

or you add the migration manually:

```php
Schema::table('approvals', function (Blueprint $table) {
    $table->unsignedBigInteger('foreign_key')->nullable()->after('original_data');
});
```

## v1.4.2 -> 1.4.3

If you wish to audit which User set the state for the Model, you need to publish and run a new Migration.

Run the `vendor:publish` command to bring in the new migration file:

```shell
php artisan vendor:publish
```

and choose `approval-migrations`

Run `php artisan migrate`

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
