<?php

namespace Cjmellor\Approval\Tests\Models;

use Cjmellor\Approval\Concerns\MustBeApproved;
use Illuminate\Database\Eloquent\Model;

class FakeModelWithArray extends Model
{
    use MustBeApproved;

    protected $table = 'fake_models_with_array';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'data' => 'array'
    ];
}
