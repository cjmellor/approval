<?php

namespace Cjmellor\Approval\Tests\Models;

class FakeUser extends \Illuminate\Foundation\Auth\User
{
    protected $guarded = [];

    protected $table = 'fake_users';

    public $timestamps = false;
}
