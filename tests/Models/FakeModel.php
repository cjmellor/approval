<?php

namespace Cjmellor\Approval\Tests\Models;

use Cjmellor\Approval\Concerns\MustBeApproved;
use Illuminate\Database\Eloquent\Model;

class FakeModel extends Model
{
    use MustBeApproved;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    public $timestamps = false;
}
