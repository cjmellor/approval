<?php

namespace Cjmellor\Approval\Scopes;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ApprovalStateScope implements Scope
{
    /**
     * Add extra extensions.
     */
    protected array $extensions = [
        // Model with no state
        'WithAnyState',
        // Get Models with state
        'Approved',
        'Pending',
        'Rejected',
        // Set Models with state
        'Approve',
        'Postpone',
        'Reject',
    ];

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->withAnyState();
    }

    /**
     * Extend the query builder with the needed functions.
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    /**
     * Return all query results with no state.
     */
    protected function addWithAnyState(Builder $builder)
    {
        $builder->macro('withAnyState', fn (Builder $builder) => $builder->withoutGlobalScope($this));
    }

    /**
     * Return only Approval states that are set to 'approved'.
     */
    protected function addApproved(Builder $builder)
    {
        $builder->macro('approved', fn (Builder $builder) => $builder
            ->withAnyState()
            ->where(column: 'state', operator: ApprovalStatus::Approved));
    }

    /**
     * Return only Approval states that are set to 'pending'.
     */
    protected function addPending(Builder $builder)
    {
        $builder->macro('pending', fn (Builder $builder) => $builder
            ->withAnyState()
            ->where(column: 'state', operator: ApprovalStatus::Pending));
    }

    /**
     * Return only Approval states that are set to 'rejected'.
     */
    protected function addRejected(Builder $builder)
    {
        $builder->macro('rejected', fn (Builder $builder) => $builder
            ->withAnyState()
            ->where(column: 'state', operator: ApprovalStatus::Rejected));
    }

    /**
     * Set state as 'approved'.
     */
    protected function addApprove(Builder $builder)
    {
        $builder->macro('approve', fn (Builder $builder) => $this->updateApprovalState($builder, state: ApprovalStatus::Approved));
    }

    /**
     * Set state as 'pending' (default).
     */
    protected function addPostpone(Builder $builder)
    {
        $builder->macro('postpone', fn (Builder $builder) => $this->updateApprovalState($builder, state: ApprovalStatus::Pending));
    }

    /**
     * Set state as 'rejected'
     */
    protected function addReject(Builder $builder)
    {
        $builder->macro('reject', fn (Builder $builder) => $this->updateApprovalState($builder, state: ApprovalStatus::Rejected));
    }

    /**
     * A helper method for updating the approvals state.
     */
    protected function updateApprovalState(Builder $builder, $state): int
    {
        return $builder->update([
            'state' => $state,
        ]);
    }
}
