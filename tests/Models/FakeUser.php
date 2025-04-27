<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Tests\Models;

class FakeUser extends \Illuminate\Foundation\Auth\User
{
    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'fake_users';
}
