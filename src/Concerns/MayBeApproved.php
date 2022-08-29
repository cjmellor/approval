<?php

namespace Cjmellor\Approval\Concerns;

trait MayBeApproved
{

  use MustBeApproved;
  protected bool $requireApproval = false;

  /**
   * Check is the approval can be bypassed.
   */
  public function isApprovalBypassed(): bool
  {
    if ($this->requireApproval) return false;
    return true;
  }
}
