<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Tests\Models;

use Cjmellor\Approval\Concerns\MustBeApproved;
use Cjmellor\Approval\Tests\Feature\Factories\FakeModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FakeModel extends Model
{
    use HasFactory, MustBeApproved;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $guarded = [];

    protected static function newFactory(): Factory
    {
        return FakeModelFactory::new();
    }
}
