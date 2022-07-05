<?php

namespace Cjmellor\Approval\Concerns;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Models\Approval;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait MustBeApproved
{
    protected bool $bypassApproval = false;

    public static function bootMustBeApproved(): void
    {
        static::creating(callback: fn ($model) => static::insertApprovalRequest($model));
        static::updating(callback: fn ($model) => static::insertApprovalRequest($model));
    }

    /**
     * Create an Approval request before committing to the database.
     */
    protected static function insertApprovalRequest($model)
    {
        if (! $model->isApprovalBypassed()) {
            /**
             * Create the new Approval model
             */
            if (self::approvalModelExists($model)) {
                return false;
            }

            $model->approvals()->create([
                'new_data' => $model->getDirty(),
                'original_data' => $model->getOriginalMatchingChanges(),
            ]);

            return false;
        }
    }

    /**
     * Check if the Approval model been created already exists with a 'pending' state
     */
    protected static function approvalModelExists($model): bool
    {
        return Approval::where([
            ['state', '=', ApprovalStatus::Pending],
            ['new_data', '=', json_encode($model->getDirty())],
            ['original_data', '=', json_encode($model->getOriginalMatchingChanges())],
        ])->exists();
    }

    /**
     * The polymorphic relationship for the Approval model.
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(related: Approval::class, name: 'approvalable');
    }

    /**
     * Gets the original model data and only gets the keys that match the dirty attributes.
     */
    protected function getOriginalMatchingChanges(): array
    {
        return collect($this->getOriginal())
            ->only(collect($this->getDirty())->keys())
            ->toArray();
    }

    /**
     * Check is the approval can be bypassed.
     */
    public function isApprovalBypassed(): bool
    {
        return $this->bypassApproval;
    }

    /**
     * Approval is ignored and persisted to the database.
     */
    public function withoutApproval(): static
    {
        $this->bypassApproval = true;

        return $this;
    }
}
