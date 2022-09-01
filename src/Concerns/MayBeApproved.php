<?php

namespace Cjmellor\Approval\Concerns;

trait MayBeApproved
{
  use MustBeApproved;
  protected static bool $requiresApproval = false;

  /**
   * Check if the approval can be bypassed, based on static $requiresApproval flag;
   */
  public function isApprovalBypassed(): bool
  {
    return !self::$requiresApproval;
  }

  /**
   * Sets the $requiresApproval flag (defaults to true)
   */
  public static function requireApproval($requires = true)
  {
    self::$requiresApproval = $requires;
  }

  /**
   * Check if the Approval model been created already exists with a 'pending' state
   */
  protected static function approvalModelExists($model): bool
  {
    return Approval::where('state', ApprovalStatus::Pending)
      ->where('approvalable_id', $model->id)
      ->where('approvalable_type', $model)
      ->whereJsonContains('new_data', $model->getDirty())
      ->exists();
  }
}
