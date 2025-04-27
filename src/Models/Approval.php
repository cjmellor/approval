<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Models;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Events\ApprovalExpired;
use Cjmellor\Approval\Events\ModelRolledBackEvent;
use Cjmellor\Approval\Scopes\ApprovalStateScope;
use Closure;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

#[ScopedBy(ApprovalStateScope::class)]
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

    /**
     * Process all expired approvals.
     */
    public static function processExpired(): int
    {
        $processed = 0;

        // Find expired approvals that haven't been actioned yet
        $expiredApprovals = self::query()
            ->whereNotNull(columns: 'expires_at')
            ->whereNull(columns: 'actioned_at')
            ->where(column: 'expires_at', operator: '<', value: now())
            ->get();

        foreach ($expiredApprovals as $approval) {
            // Mark as actioned
            $approval->actioned_at = now();
            $approval->save();

            // Fire the expired event
            Event::dispatch(new ApprovalExpired($approval, auth()->user()));

            // Process based on expiration action
            switch ($approval->expiration_action) {
                case 'reject':
                    $approval->reject();
                    break;

                case 'postpone':
                    $approval->postpone();
                    break;

                case 'custom':
                    // For custom actions, we just rely on event listeners
                    $approval->save(); // Save the actioned_at timestamp
                    break;

                default:
                    // No action specified, just mark as actioned
                    $approval->save();
            }

            $processed++;
        }

        return $processed;
    }

    public function approvalable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeRequestedBy(Builder $query, Model $requestor): Builder
    {
        return $query->where('creator_type', get_class($requestor))
            ->where('creator_id', $requestor->getKey());
    }

    public function wasRequestedBy(Model $requestor): bool
    {
        return $this->requestor()->is($requestor);
    }

    public function requestor(): MorphTo
    {
        return $this->morphTo('creator');
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

    public function rollback(?Closure $condition = null, bool $bypass = true): void
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

    /**
     * Set the approval to be automatically rejected when it expires.
     */
    public function thenReject(): self
    {
        $this->expiration_action = 'reject';
        $this->save();

        return $this;
    }

    /**
     * Set the approval to be automatically postponed when it expires.
     */
    public function thenPostpone(): self
    {
        $this->expiration_action = 'postpone';
        $this->save();

        return $this;
    }

    /**
     * Set a custom action to be executed when the approval expires.
     *
     * Note: Since we can't store callbacks in the database, this will set the action
     * to 'custom' and applications should listen for the ApprovalExpired event to
     * handle custom actions.
     */
    public function thenDo(callable $callback): self
    {
        $this->expiration_action = 'custom';
        $this->save();

        return $this;
    }

    /**
     * Set the state of the approval.
     */
    public function setState(string $state): self
    {
        // Get states from config
        $states = config('approval.states');

        if (! $states || ! array_key_exists($state, $states)) {
            throw new InvalidArgumentException("State '{$state}' is not defined in the approval configuration.");
        }

        // For standard states, use the enum
        if (in_array($state, ['pending', 'approved', 'rejected'])) {
            $this->state = ApprovalStatus::from($state);
            $this->attributes['custom_state'] = null; // Clear any custom state
        } else {
            // For custom states, set a valid base state and the custom state
            $this->state = ApprovalStatus::Pending; // Default enum value
            $this->attributes['custom_state'] = $state;
        }

        $this->save();

        return $this;
    }

    /**
     * Get the current state of the approval.
     */
    public function getState(): string
    {
        if (Arr::exists(array: $this->attributes, key: 'custom_state')) {
            return $this->attributes['custom_state'];
        }

        // Otherwise, return the standard state
        return $this->state->value;
    }
}
