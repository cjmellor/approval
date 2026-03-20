<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Concerns;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Events\ApprovalCreated;
use Cjmellor\Approval\Models\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Event;

trait MustBeApproved
{
    protected bool $bypassApproval = false;

    public static function bootMustBeApproved(): void
    {
        static::creating(callback: fn (Model $model): ?bool => static::insertApprovalRequest($model));
        static::updating(callback: fn (Model $model): ?bool => static::insertApprovalRequest($model));
    }

    public function getApprovalAttributes(): array
    {
        return $this->approvalAttributes ?? [];
    }

    public function getApprovalForeignKeyName(): string
    {
        return 'user_id';
    }

    public function isApprovalBypassed(): bool
    {
        return $this->bypassApproval;
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(related: Approval::class, name: 'approvalable');
    }

    public function withoutApproval(): static
    {
        $this->bypassApproval = true;

        return $this;
    }

    public function callCastAttribute(string $key, mixed $value): mixed
    {
        if (! array_key_exists($key, $this->getCasts()) || is_array($value)) {
            return $value;
        }

        return $this->castAttribute($key, $value);
    }

    /**
     * @return bool|null Returns null to allow the operation to proceed (bypass or no dirty attributes),
     */
    protected static function insertApprovalRequest(Model $model): ?bool
    {
        $filteredDirty = $model->getDirtyAttributes();
        $foreignKey = $model->getApprovalForeignKeyName();
        $foreignKeyValue = $filteredDirty[$foreignKey] ?? null;

        unset($filteredDirty[$foreignKey]);

        foreach ($filteredDirty as $key => $value) {
            if ($model->hasCast($key, ['json', 'array']) && is_string($value)) {
                $filteredDirty[$key] = json_decode(json: $value, associative: true, flags: JSON_THROW_ON_ERROR);
            }
        }

        if ($model->isApprovalBypassed() || empty($filteredDirty)) {
            return null;
        }

        $approvalAttributes = $model->getApprovalAttributes();
        $noApprovalNeeded = [];

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

        $user = auth()->user();

        $approval = $model->approvals()->create([
            'new_data' => $filteredDirty,
            'original_data' => $model->getOriginalMatchingChanges(),
            'creator_id' => $user?->getAuthIdentifier(),
            'creator_type' => $user ? $user::class : null,
            'foreign_key' => $foreignKeyValue,
        ]);

        Event::dispatch(new ApprovalCreated($approval, $user));

        return ! empty($noApprovalNeeded);
    }

    protected static function approvalModelExists(Model $model): bool
    {
        $query = Approval::where('approvalable_type', $model->getMorphClass());

        if ($model->getKey() !== null) {
            $query->where('approvalable_id', $model->getKey());
        } else {
            $query->whereNull('approvalable_id');
        }

        return $query->where([
            ['state', '=', ApprovalStatus::Pending],
            ['new_data', '=', json_encode($model->getDirtyAttributes(), JSON_THROW_ON_ERROR)],
            ['original_data', '=', json_encode($model->getOriginalMatchingChanges(), JSON_THROW_ON_ERROR)],
        ])->exists();
    }

    protected function getDirtyAttributes(): array
    {
        if (empty($this->getApprovalAttributes())) {
            return $this->getDirty();
        }

        return collect($this->getDirty())
            ->only($this->getApprovalAttributes())
            ->toArray();
    }

    protected function getOriginalMatchingChanges(): array
    {
        return collect($this->getOriginal())
            ->only(collect($this->getDirtyAttributes())->keys())
            ->toArray();
    }
}
