<?php

namespace Approval\Approval\Tests\Models;

use Approval\Approval\Concerns\MustBeApproved;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use MustBeApproved;

    protected $guarded = [];
}
