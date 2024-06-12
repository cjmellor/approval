<?php

namespace Cjmellor\Approval\Concerns;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

trait MustBeApprovedFactory
{
    public function withoutApproval(): Factory
    {
        return $this->afterMaking(function (Model $model) {
            if (in_array(MustBeApproved::class, class_uses($model))) {
                $model->withoutApproval();
            }
        });
    }
}
