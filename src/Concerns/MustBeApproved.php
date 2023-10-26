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
        // some optimization
        $filteredDirty = $model->getFilteredDirty();

        if (!$model->isApprovalBypassed() && !empty($filteredDirty))
        {
            $noNeedToProceed = true;

            if (!empty($model->getApprovalInclude())) {
                /**
                 * If there is nothing changed besides the attributes
                 * in the includes list, we can return false.
                 */
                $noNeedToProceed = collect($model->getDirty())
                    ->except($model->getApprovalInclude())
                    ->isEmpty();

                /**
                 * If we do have some attributes which are not subject for
                 * approval, make sure that those go through the regular
                 * process
                 */
                if (!$noNeedToProceed && !empty($model->getApprovalInclude())) {
                    $noApprovalNeeded = collect($model->getDirty())
                        ->except($model->getApprovalInclude())
                        ->toArray();

                    $model->discardChanges();
                    $model->forceFill($noApprovalNeeded);
                }
            }

            /**
             * Create the new Approval model
             */
            if (self::approvalModelExists($model)) {
                if( $noNeedToProceed ) return false;
            }

            $model->approvals()->create([
                'new_data' => $filteredDirty,
                'original_data' => $model->getOriginalMatchingChanges(),
            ]);

            if( $noNeedToProceed ) return false;
        }
    }

    /**
     * Check if the Approval model been created already exists with a 'pending' state
     */
    protected static function approvalModelExists($model): bool
    {
        return Approval::where([
            ['state', '=', ApprovalStatus::Pending],
            ['new_data', '=', json_encode($model->getFilteredDirty())],
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
            ->only(collect($this->getFilteredDirty())->keys())
            ->toArray();
    }

    /**
     * Get the dirty attributes, but only those which should be included
     *
     * @return array
     */
    protected function getFilteredDirty(): array
    {
        if (empty($this->getApprovalInclude())) {
            return $this->getDirty();
        }

        return collect($this->getDirty())
            ->only($this->getApprovalInclude())
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
     * @return array
     */
    public function getApprovalInclude(): array
    {
        return $this->approvalInclude ?? [];
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
