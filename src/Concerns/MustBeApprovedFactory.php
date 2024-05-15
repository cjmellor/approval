<?php

namespace Cjmellor\Approval\Concerns;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Models\Approval;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait MustBeApprovedFactory
{
    public function withoutApproval(): Factory
    {
        return $this->afterMaking(function (Model $model) {
            if (!in_array(MustBeApproved::class, class_uses($model))) {
                $model->withoutApproval();
            }
        });
    }
}
