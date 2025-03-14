<?php

namespace Cjmellor\Approval\Models;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Events\ModelRolledBackEvent;
use Cjmellor\Approval\Scopes\ApprovalStateScope;
use Closure;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class Approval extends Model
{
    protected $guarded = [];

    protected $casts = [
        'new_data' => AsArrayObject::class,
        'original_data' => AsArrayObject::class,
        'state' => ApprovalStatus::class,
        'rolled_back_at' => 'datetime',
        'expires_at' => 'datetime',
        'actioned_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(scope: new ApprovalStateScope());
    }

    public function approvalable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestor(): MorphTo
    {
        return $this->morphTo('creator');
    }

    public function getRequestorAttribute()
    {
        return $this->requestor()->first();
    }

    public function scopeRequestedBy($query, $requestor)
    {
        return $query->where('creator_type', get_class($requestor))
            ->where('creator_id', $requestor->getKey());
    }

    public function wasRequestedBy($requestor): bool
    {
        return $this->requestor()->is($requestor);
    }

    public function approveIf(bool $boolean): void
    {
        if ($boolean) {
            $this->approve();
        }
    }

    public function approveUnless(bool $boolean): void
    {
        if (! $boolean) {
            $this->approve();
        }
    }

    public function postponeIf(bool $boolean): void
    {
        if ($boolean) {
            $this->postpone();
        }
    }

    public function postponeUnless(bool $boolean): void
    {
        if (! $boolean) {
            $this->postpone();
        }
    }

    public function rejectIf(bool $boolean): void
    {
        if ($boolean) {
            $this->reject();
        }
    }

    public function rejectUnless(bool $boolean): void
    {
        if (! $boolean) {
            $this->reject();
        }
    }

    public function rollback(?Closure $condition = null, $bypass = true): void
    {
        if ($condition && ! $condition($this)) {
            return;
        }

        throw_if(
            condition: $this->state !== ApprovalStatus::Approved,
            exception: Exception::class,
            message: 'Cannot rollback an Approval that has not been approved.'
        );

        $this->approvalable->withoutApproval()->update($this->original_data->getArrayCopy());

        $this->update([
            'state' => $bypass ? $this->state : ApprovalStatus::Pending,
            'new_data' => $this->original_data,
            'original_data' => $this->new_data,
            'rolled_back_at' => now(),
        ]);

        Event::dispatch(new ModelRolledBackEvent(approval: $this, user: auth()->user()));
    }

    /**
     * Set the expiration time for this approval.
     */
    public function expiresIn(
        ?int $minutes = null,
        ?int $hours = null,
        ?int $days = null,
        ?DateTimeInterface $datetime = null
    ): self {
        $this->expires_at = match (true) {
            $datetime !== null => $datetime,
            $days !== null => now()->addDays($days),
            $hours !== null => now()->addHours($hours),
            $minutes !== null => now()->addMinutes($minutes),
            default => throw new InvalidArgumentException(message: 'You must specify an expiration time')
        };

        $this->save();

        return $this;
    }

    /**
     * Check if the approval has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }
}
