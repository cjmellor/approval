<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Tests\Models;

use Illuminate\Foundation\Auth\User;

class FakeUser extends User
{
    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'fake_users';
}
