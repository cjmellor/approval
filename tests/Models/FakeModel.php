<?php

namespace Cjmellor\Approval\Tests\Models;

use Cjmellor\Approval\Concerns\MustBeApproved;
use Cjmellor\Approval\Tests\Feature\Factories\FakeModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FakeModel extends Model
{
    use MustBeApproved, HasFactory;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    public $timestamps = false;

    protected static function newFactory(): Factory
    {
        return FakeModelFactory::new();
    }
}
