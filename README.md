
# Approve new Model data before it is persisted

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cjmellor/approval.svg?style=flat-square)](https://packagist.org/packages/cjmellor/approval)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/cjmellor/approval/run-tests?label=tests)](https://github.com/cjmellor/approval/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/cjmellor/approval/Check%20&%20fix%20styling?label=code%20style)](https://github.com/cjmellor/approval/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/cjmellor/approval.svg?style=flat-square)](https://packagist.org/packages/cjmellor/approval)

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

> This packages utilises Enums, so both PHP 8.1 and Laravel 9 must be used.
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

If you want to check if the Model data will be bypassed, use the `isApprovalBypassed` method.

```php
return $model->isApprovalBypassed();
```

## Scopes

The package comes with some helper methods for the Builder, utilising a custom scope - `ApprovalStateScope`

By default, all queries to the `approvals` table will return all the Models' no matter the state. 

There are three methods to help you retrieve the state of an Approval.

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

## Disable Approvals

If you don't want Model data to be approved, you can bypass it with the `withoutApproval` method.

```php
$model->withoutApproval()->update(['title' => 'Some Title']);
```

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

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
