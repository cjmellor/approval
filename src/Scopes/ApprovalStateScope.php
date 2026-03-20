<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Scopes;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Events\ModelApproved;
use Cjmellor\Approval\Events\ModelRejected;
use Cjmellor\Approval\Events\ModelSetPending;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Event;
use RuntimeException;

class ApprovalStateScope implements Scope
{
    private array $extensions = [
        'WithAnyState',
        'Approved',
        'Pending',
        'Rejected',
        'Approve',
        'Postpone',
        'Reject',
        'Expired',
        'NotExpired',
        'HasExpiration',
        'WhereState',
    ];

    public function apply(Builder $builder, Model $model): void
    {
        $builder->withAnyState();
    }

    public function extend(Builder $builder): void
    {
        foreach ($this->extensions as $extension) {
            $this->{"add$extension"}($builder);
        }
    }

    private function addWithAnyState(Builder $builder): void
    {
        $builder->macro('withAnyState', fn (Builder $builder): Builder => $builder->withoutGlobalScope(scope: $this));
    }

    private function addApproved(Builder $builder): void
    {
        $builder->macro('approved', fn (Builder $builder): Builder => $builder
            ->withAnyState()
            ->where(column: 'state', operator: ApprovalStatus::Approved));
    }

    private function addPending(Builder $builder): void
    {
        $builder->macro('pending', fn (Builder $builder): Builder => $builder
            ->withAnyState()
            ->where(column: 'state', operator: ApprovalStatus::Pending)
            ->whereNull('custom_state'));
    }

    private function addRejected(Builder $builder): void
    {
        $builder->macro('rejected', fn (Builder $builder): Builder => $builder
            ->withAnyState()
            ->where(column: 'state', operator: ApprovalStatus::Rejected));
    }

    private function addApprove(Builder $builder): void
    {
        $builder->macro('approve', function (Builder $builder, bool $persist = true): int {
            if ($persist) {
                $modelClass = $builder->getModel()->approvalable_type;
                $modelId = $builder->getModel()->approvalable_id;

                $morphedModel = Relation::getMorphedModel($modelClass) ?? $modelClass;
                $model = new $morphedModel();

                if ($modelId) {
                    $model = $model->find($modelId);

                    throw_if(
                        $model === null,
                        new RuntimeException("Cannot approve: the related model ({$morphedModel} #{$modelId}) no longer exists.")
                    );
                }

                $newData = $builder->getModel()->new_data->toArray();

                $foreignKey = $builder->getModel()->foreign_key;
                if ($foreignKey) {
                    $newData[$model->getApprovalForeignKeyName()] = $foreignKey;
                }

                foreach ($newData as $key => $value) {
                    $newData[$key] = $model->callCastAttribute($key, $value);
                }

                $model->forceFill($newData);
                $model->withoutApproval()->save();
            }

            return $this->updateApprovalState($builder, state: ApprovalStatus::Approved);
        });
    }

    private function updateApprovalState(Builder $builder, ApprovalStatus $state): int
    {
        $auditedData = [
            'state' => $state,
            'audited_by' => auth()->id(),
        ];

        $result = (int) $builder
            ->find(id: $builder->getModel()->id)
            ->update($auditedData);

        if ($result > 0) {
            $user = auth()->user();

            match ($state) {
                ApprovalStatus::Approved => Event::dispatch(new ModelApproved($builder->getModel(), $user)),
                ApprovalStatus::Pending => Event::dispatch(new ModelSetPending($builder->getModel(), $user)),
                ApprovalStatus::Rejected => Event::dispatch(new ModelRejected($builder->getModel(), $user)),
            };
        }

        return $result;
    }

    private function addPostpone(Builder $builder): void
    {
        $builder->macro('postpone',
            fn (Builder $builder): int => $this->updateApprovalState($builder, state: ApprovalStatus::Pending));
    }

    private function addReject(Builder $builder): void
    {
        $builder->macro('reject',
            fn (Builder $builder): int => $this->updateApprovalState($builder, state: ApprovalStatus::Rejected));
    }

    private function addExpired(Builder $builder): void
    {
        $builder->macro(
            'expired',
            fn (Builder $builder): Builder => $builder
                ->withAnyState()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
        );
    }

    private function addNotExpired(Builder $builder): void
    {
        $builder->macro(
            'notExpired',
            fn (Builder $builder): Builder => $builder
                ->withAnyState()
                ->where(function (Builder $query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', now());
                })
        );
    }

    private function addHasExpiration(Builder $builder): void
    {
        $builder->macro(
            'hasExpiration',
            fn (Builder $builder): Builder => $builder
                ->withAnyState()
                ->whereNotNull('expires_at')
        );
    }

    private function addWhereState(Builder $builder): void
    {
        $builder->macro('whereState', function (Builder $builder, string $state): Builder {
            if (in_array($state, ApprovalStatus::values(), true)) {
                return $builder
                    ->withAnyState()
                    ->where('state', ApprovalStatus::from($state))
                    ->whereNull('custom_state');
            }

            return $builder
                ->withAnyState()
                ->where('custom_state', $state);
        });
    }
}
