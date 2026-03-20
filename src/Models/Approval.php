<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Models;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Enums\ExpirationAction;
use Cjmellor\Approval\Events\ApprovalExpired;
use Cjmellor\Approval\Events\ModelRolledBack;
use Cjmellor\Approval\Scopes\ApprovalStateScope;
use Closure;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

#[ScopedBy(ApprovalStateScope::class)]
class Approval extends Model
{
    protected $fillable = [
        'approvalable_type',
        'approvalable_id',
        'state',
        'custom_state',
        'new_data',
        'original_data',
        'rolled_back_at',
        'audited_by',
        'foreign_key',
        'creator_type',
        'creator_id',
        'expires_at',
        'expiration_action',
        'actioned_at',
        'actioned_by',
    ];

    protected $casts = [
        'new_data' => AsArrayObject::class,
        'original_data' => AsArrayObject::class,
        'state' => ApprovalStatus::class,
        'expiration_action' => ExpirationAction::class,
        'rolled_back_at' => 'datetime',
        'expires_at' => 'datetime',
        'actioned_at' => 'datetime',
    ];

    public static function processExpired(): int
    {
        $processed = 0;

        self::query()
            ->where(column: 'state', operator: ApprovalStatus::Pending)
            ->whereNotNull(columns: 'expires_at')
            ->whereNull(columns: 'actioned_at')
            ->where(column: 'expires_at', operator: '<', value: now())
            ->chunkById(100, function ($approvals) use (&$processed): void {
                foreach ($approvals as $approval) {
                    try {
                        Event::dispatch(new ApprovalExpired($approval, auth()->user()));

                        match ($approval->expiration_action) {
                            ExpirationAction::Reject => $approval->reject(),
                            ExpirationAction::Postpone => $approval->postpone(),
                            ExpirationAction::Custom, null => null,
                        };

                        $approval->update([
                            'actioned_at' => now(),
                            'actioned_by' => auth()->id(),
                        ]);

                        $processed++;
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

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
        return $query->where('creator_type', $requestor::class)
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

    /**
     * @param  Closure|null  $condition  Optional gate; rollback is skipped if this returns false.
     * @param  bool  $bypass  If true, the approval retains its Approved state after rollback.
     *
     * @throws Exception If the approval has not been approved.
     * @throws RuntimeException If the related model no longer exists.
     */
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

        throw_if(
            condition: $this->approvalable === null,
            exception: new RuntimeException(
                "Cannot rollback Approval #{$this->id}: the related model "
                ."({$this->approvalable_type} #{$this->approvalable_id}) no longer exists."
            )
        );

        $this->approvalable->withoutApproval()->update($this->original_data->getArrayCopy());

        $this->update([
            'state' => $bypass ? $this->state : ApprovalStatus::Pending,
            'new_data' => $this->original_data,
            'original_data' => $this->new_data,
            'rolled_back_at' => now(),
        ]);

        Event::dispatch(new ModelRolledBack(approval: $this, user: auth()->user()));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function expiresIn(
        ?int $minutes = null,
        ?int $hours = null,
        ?int $days = null,
        ?DateTimeInterface $datetime = null
    ): static {
        $this->expires_at = match (true) {
            $datetime instanceof DateTimeInterface => $datetime,
            $days !== null => now()->addDays($days),
            $hours !== null => now()->addHours($hours),
            $minutes !== null => now()->addMinutes($minutes),
            default => throw new InvalidArgumentException(message: 'You must specify an expiration time')
        };

        $this->save();

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function thenReject(): static
    {
        return $this->setExpirationAction(ExpirationAction::Reject);
    }

    public function thenPostpone(): static
    {
        return $this->setExpirationAction(ExpirationAction::Postpone);
    }

    public function thenCustom(): static
    {
        return $this->setExpirationAction(ExpirationAction::Custom);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setState(string $state): static
    {
        $states = config('approval.states');

        throw_if(! $states || ! array_key_exists($state, $states), new InvalidArgumentException("State '{$state}' is not defined in the approval configuration."));

        if (in_array($state, ApprovalStatus::values(), true)) {
            $this->state = ApprovalStatus::from($state);
            $this->attributes['custom_state'] = null;
        } else {
            $this->state = ApprovalStatus::Pending;
            $this->attributes['custom_state'] = $state;
        }

        $this->save();

        return $this;
    }

    public function getState(): string
    {
        $customState = $this->attributes['custom_state'] ?? null;

        if (! empty($customState)) {
            return $customState;
        }

        return $this->state->value;
    }

    private function setExpirationAction(ExpirationAction $action): static
    {
        $this->expiration_action = $action;
        $this->save();

        return $this;
    }
}
