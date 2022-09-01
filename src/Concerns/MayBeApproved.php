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
}
