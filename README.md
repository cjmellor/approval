[![Latest Version on Packagist](https://img.shields.io/packagist/v/cjmellor/approval?color=rgb%2856%20189%20248%29&label=release&style=for-the-badge)](https://packagist.org/packages/cjmellor/approval)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/cjmellor/approval/run-pest.yml?branch=main&label=tests&style=for-the-badge&color=rgb%28134%20239%20128%29)](https://github.com/cjmellor/approval/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/cjmellor/approval.svg?color=rgb%28249%20115%2022%29&style=for-the-badge)](https://packagist.org/packages/cjmellor/approval)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/cjmellor/approval/php?color=rgb%28165%20180%20252%29&logo=php&logoColor=rgb%28165%20180%20252%29&style=for-the-badge)
![Laravel Version](https://img.shields.io/badge/laravel-^10-rgb(235%2068%2050)?style=for-the-badge&logo=laravel)

Approval is a Laravel package that provides a simple way to approve new Model data before it is persisted.

![](https://banners.beyondco.de/Approval.png?theme=light&packageManager=composer+require&packageName=cjmellor%2Fapproval&pattern=brickWall&style=style_2&description=Approve+new+Model+data+before+it+is+persisted&md=1&showWatermark=0&fontSize=100px&images=check-circle&widths=300&heights=300)

## Installation

You can install the package via composer:

```bash
composer require cjmellor/approval
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="approval-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="approval-config"
```

This is the contents of the published config file:

```php
return [
    'approval' => [
        /**
         * The approval polymorphic pivot name
         *
         * Default: 'approvalable'
         */
        'approval_pivot' => 'approvalable',
    ],
];
```

The config allows you to change the polymorphic pivot name. It should end with `able` though.

## Usage

> [!NOTE]
> The package utilises Enums, so both PHP >= 8.1 and Laravel 10 must be used.
>
> **Note** This package does not approve/deny the data for you, it just stores the new/amended data into the database. It is up to you to decide how you implement a function to approve or deny the Model.

Add the `MustBeApproved` trait to your Model and now the data will be stored in an `approvals` table, ready for you to approve or deny.

For example, you add it to a `Post` Model and each time a Post is created or updated, all the _dirty_ data will be stored in the database as JSON for you to do something with it.

```php
<?php

use Cjmellor\Approval\Concerns\MustBeApproved;

class Post extends Model
{
    use MustBeApproved;

    // ...
}
```

All Models using the Trait will now be stored in a new table -- `approvals`. This is a polymorphic relationship.

Here is some info about the columns in the `approvals` table:

`approvalable_type` => The class name of the Model that the approval is for

`approvalable_id` => The ID of the Model that the approval is for

`state` => The state of the approval. This uses an Enum class. This column is cast to an `ApprovalStatus` Enum class

`new_data` => All the fields created or updated in the Model. This is a JSON column. This column is cast to the `AsArrayObject` [Cast](https://laravel.com/docs/9.x/eloquent-mutators#array-object-and-collection-casting)

`original_data` => All the fields in the Model before they were updated. This is a JSON column. This column is cast to the `AsArrayObject` [Cast](https://laravel.com/docs/9.x/eloquent-mutators#array-object-and-collection-casting)

`rolled_back_at` => A timestamp of when this was last rolled back to its original state

`audited_at` => The ID of the User who set the state

`foreign_key` => A foreign key to the Model that the approval is for

### Bypassing Approval Check

If you want to check if the Model data will be bypassed, use the `isApprovalBypassed` method.

```php
return $model->isApprovalBypassed();
```

### Foreign Keys for New Models

> [!NOTE]
> It is recommended to read the below section on how foreign keys work in this package.

> [!IMPORTANT]
> By default, the foreign key will always be `user_id` because this is the most common foreign key used in Laravel.

If you create a new Model directly via the Model, e.g.

```php
Post::create(['title' => 'Some Title']);
```

be sure to also add the foreign key to the Model, e.g.

```php
Post::create(['title' => 'Some Title', 'user_id' => 1]);
```

Now when the Model is sent for approval, the foreign key will be stored in the `foreign_key` column.

### Customise the Foreign Key

Your Model might not use the `user_id` as the foreign key, so you can customise it by adding this method to your Model:

```php
public function getApprovalForeignKeyName(): string
{
    return 'author_id';
}
```

## Scopes

The package comes with some helper methods for the Builder, utilising a custom scope - `ApprovalStateScope`

By default, all queries to the `approvals` table will return all the Models' no matter the state.

There are three methods to help you retrieve the state of the Approval.

```php
<?php

use App\Models\Approval;

Approval::approved()->get();
Approval::rejected()->get();
Approval::pending()->count();
```

You can also set a state for an approval:

```php
<?php

use App\Models\Approval;

Approval::where('id', 1)->approve();
Approval::where('id', 2)->reject();
Approval::where('id', 3)->postpone();
```

In the event you need to reset a state, you can use the `withAnyState` helper.

### Helpers

Conditional helper methods are used, so you can set the state of an Approval when a condition is met.

```php
$approval->approveIf(true);
$approval->rejectIf(false);
$approval->postponeIf(true);

$approval->approveUnless(false);
$approval->rejectUnless(true);
$approval->postponeUnless(false);
```

### Events

Once a Model's state has been changed, an event will be fired.

```php
ModelApproved::class
ModelPostponed::class
ModelRejected::class
```

### Persisting data

By default, once you approve a Model, it will be inserted into the database.

If you don't want to persist to the database on approval, set a `false` flag on the  `approve` method.

```php
Approval::find(1)->approve(persist: false);
```

## Rollbacks

If you need to roll back an approval, you can use the `rollback` method.

> [!NOTE]
> By default, a Rollback will bypass been added back to the `approvals` table

```php
Approval::first()->rollback();
```

This will revert the data and set the state to `pending` and touch the `rolled_back_at` timestamp, so you have a record of when it was rolled back.

If you want a Rollback to be re-approved, pass the `bypass` parameter as `false` to the `rollback` method

```php
Approval::first()->rollback(bypass: false); // default is true
```

### Conditional Rollbacks

A roll-back can be conditional, so you can roll back an approval if a condition is met.

```php
Approval::first()->rollback(fn () => true);
```

### Events

When a Model has been rolled back, a `ModelRolledBack` event will be fired with the Approval Model that was rolled back, as well as the User that rolled it back.

```php
// ModelRolledBackEvent::class

public Model $approval,
public Authenticatable|null $user,
````

## Disable Approvals

If you don't want Model data to be approved, you can bypass it with the `withoutApproval` method.

```php
$model->withoutApproval()->update(['title' => 'Some Title']);
```

## Specify Approvable Attributes

By default, all attributes of the model will go through the approval process, however if you only wish certain attributes to go through this process, you can specify them using the `approvalAttributes` property in your model.

```php
<?php

use Cjmellor\Approval\Concerns\MustBeApproved;

class Post extends Model
{
    use MustBeApproved;

    protected array $approvalAttributes = ['name'];

    // ...
}
```

In this example, only the name attribute of this model will go through the approval process, all mutations on other attributes will bypass the approval process.

If you omit the `approvalAttributes` property from your model, all attributes will go through the approval process.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please open a PR with as much detail as possible about what you're trying to achieve.

## Credits

- [Chris Mellor](https://github.com/cjmellor)

## License

The MIT Licence (MIT). Please see [Licence File](LICENSE.md) for more information.
