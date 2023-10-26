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
        $filteredDirty = $model->getDirtyAttributes();

        if ($model->isApprovalBypassed() || empty($filteredDirty)) {
            return;
        }

        $noNeedToProceed = true;
        $approvalAttributes = $model->getApprovalAttributes();

        if (! empty($approvalAttributes)) {
            $noNeedToProceed = collect($model->getDirty())
                ->except($approvalAttributes)
                ->isEmpty();

            if (! $noNeedToProceed) {
                $noApprovalNeeded = collect($model->getDirty())
                    ->except($approvalAttributes)
                    ->toArray();

                $model->discardChanges();

                $model->forceFill($noApprovalNeeded);
            }
        }

        if (self::approvalModelExists($model) && $noNeedToProceed) {
            return false;
        }

        $model->approvals()->create([
            'new_data' => $filteredDirty,
            'original_data' => $model->getOriginalMatchingChanges(),
        ]);

        if ($noNeedToProceed) {
            return false;
        }
        return;
    }

    /**
     * Get the dirty attributes, but only those which should be included
     */
    protected function getDirtyAttributes(): array
    {
        if (empty($this->getApprovalAttributes())) {
            return $this->getDirty();
        }

        return collect($this->getDirty())
            ->only($this->getApprovalAttributes())
            ->toArray();
    }

    public function getApprovalAttributes(): array
    {
        return $this->approvalAttributes ?? [];
    }

    /**
     * Check is the approval can be bypassed.
     */
    public function isApprovalBypassed(): bool
    {
        return $this->bypassApproval;
    }

    /**
     * Check if the Approval model been created already exists with a 'pending' state
     */
    protected static function approvalModelExists($model): bool
    {
        return Approval::where([
            ['state', '=', ApprovalStatus::Pending],
            ['new_data', '=', json_encode($model->getDirtyAttributes())],
            ['original_data', '=', json_encode($model->getOriginalMatchingChanges())],
        ])->exists();
    }

    /**
     * Gets the original model data and only gets the keys that match the dirty attributes.
     */
    protected function getOriginalMatchingChanges(): array
    {
        return collect($this->getOriginal())
            ->only(collect($this->getDirtyAttributes())->keys())
            ->toArray();
    }

    /**
     * The polymorphic relationship for the Approval model.
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(related: Approval::class, name: 'approvalable');
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
