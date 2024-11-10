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
        static::creating(callback: fn ($model): ?bool => static::insertApprovalRequest($model));
        static::updating(callback: fn ($model): ?bool => static::insertApprovalRequest($model));
    }

    /**
     * Create an Approval request before committing to the database.
     */
    protected static function insertApprovalRequest($model): ?bool
    {
        $filteredDirty = $model->getDirtyAttributes();
        $foreignKey = $model->getApprovalForeignKeyName();
        $foreignKeyValue = $filteredDirty[$foreignKey] ?? null;

        // Remove the foreign key from the dirty attributes
        unset($filteredDirty[$foreignKey]);

        foreach ($filteredDirty as $key => $value) {
            if (isset($model->casts[$key]) && ($model->casts[$key] === 'json' || $model->casts[$key] === 'array')) {
                $filteredDirty[$key] = json_decode(json: $value, associative: true);
            }
        }

        if ($model->isApprovalBypassed() || empty($filteredDirty)) {
            return null;
        }

        $approvalAttributes = $model->getApprovalAttributes();

        if (! empty($approvalAttributes)) {
            $noApprovalNeeded = collect($model->getDirty())
                ->except($approvalAttributes)
                ->toArray();

            if (! empty($noApprovalNeeded)) {
                $model->discardChanges();
                $model->forceFill($noApprovalNeeded);
            }
        }

        if (self::approvalModelExists($model) && empty($noApprovalNeeded)) {
            return false;
        }

        $model->approvals()->create([
            'new_data' => $filteredDirty,
            'original_data' => $model->getOriginalMatchingChanges(),
            'foreign_key' => $foreignKeyValue,
        ]);

        if (empty($noApprovalNeeded)) {
            return false;
        }

        return true;
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
     * Get the name of the foreign key for the model.
     */
    public function getApprovalForeignKeyName(): string
    {
        return 'user_id';
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

    /**
     * Wrapper to access the castAttribute function
     *
     * @param $key
     * @param $value
     * @return mixed
     */
    public function callCastAttribute($key, $value): mixed
    {
        if (array_key_exists($key, $this->casts)) {
            // If the value is already an array, return it as is
            if (is_array($value)) {
                return $value;
            }

            // Otherwise, cast the attribute to its defined type
            return $this->castAttribute($key, $value);
        }

        return $value;
    }
}
