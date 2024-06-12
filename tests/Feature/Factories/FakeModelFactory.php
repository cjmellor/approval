<?php

namespace Cjmellor\Approval\Tests\Feature\Factories;

use Cjmellor\Approval\Concerns\MustBeApprovedFactory;
use Cjmellor\Approval\Tests\Models\FakeModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class FakeModelFactory extends Factory
{
    use MustBeApprovedFactory;

    protected $model = FakeModel::class;

    public function definition(): array
    {
        return [
            'name' => 'Bob',
            'meta' => 'green',
        ];
    }
}
