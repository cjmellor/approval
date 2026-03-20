<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Concerns;

use Illuminate\Database\Eloquent\Model;

trait MustBeApprovedFactory
{
    public function withoutApproval(): static
    {
        return $this->afterMaking(function (Model $model): void {
            if (in_array(MustBeApproved::class, class_uses_recursive($model), true)) {
                $model->withoutApproval();
            }
        });
    }
}
