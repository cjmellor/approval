<?php

namespace Cjmellor\Approval\Concerns;

use Cjmellor\Approval\Enums\ApprovalStatus;
use Cjmellor\Approval\Models\Approval;

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
    print "testing " . get_class($model) . " $model->id ";
    print_r($model->getDirty());
    return Approval::where([
      ['state', '=', ApprovalStatus::Pending],
      ['new_data', '=', json_encode($model->getDirty())],
      ['original_data', '=', json_encode($model->getOriginalMatchingChanges())],
    ])->exists();
    return Approval::where('state', ApprovalStatus::Pending)
      ->where('approvalable_id', $model->id)
      ->where('approvalable_type', get_class($model))
      ->whereJsonContains('new_data', $model->getDirty())
      ->exists();
  }
}
