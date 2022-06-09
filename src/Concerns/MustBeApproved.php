<?php

namespace Cjmellor\Approval\Concerns;

use Cjmellor\Approval\Models\Approval;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait MustBeApproved
{
    protected bool $bypassApproval = false;

    public static function bootMustBeApproved(): void
    {
        static::saved(callback: function ($model) {
            if (! $model->wasChanged()) {
                return;
            }

            if ($model->isApprovalBypassed()) {
                return;
            }

            $model->approvals()->create([
                (string)config(key: 'approval.approval.new_data') => $model->when($model->wasChanged(), fn () => $model->getChanges()),
                (string)config(key: 'approval.approval.original_data') => $model->when($model->wasChanged(), fn () => $model->getOriginalMatchingChanges()),
            ]);
        });
    }

    public function isApprovalBypassed(): bool
    {
        return $this->bypassApproval;
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(related: Approval::class, name: config(key: 'approval.approval.approval_pivot'));
    }

    protected function getOriginalMatchingChanges(): Collection
    {
        return collect($this->getOriginal())
            ->only(collect($this->getChanges())->keys());
    }

    public function withoutApproval(): static
    {
        $this->bypassApproval = true;

        return $this;
    }
}
