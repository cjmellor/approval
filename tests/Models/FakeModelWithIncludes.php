<?php

namespace Cjmellor\Approval\Tests\Models;

use Cjmellor\Approval\Concerns\MustBeApproved;
use Illuminate\Database\Eloquent\Model;

class FakeModelWithIncludes extends Model
{
    use MustBeApproved;

    protected array $approvalInclude = [
        'name',
        'meta'
    ];

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    public $timestamps = false;
}
