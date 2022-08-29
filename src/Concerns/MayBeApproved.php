<?php

namespace Cjmellor\Approval\Concerns;

trait MayBeApproved
{

  use MustBeApproved;
  protected bool $requiresApproval = false;

  /**
   * Check is the approval can be bypassed.
   */
  public function isApprovalBypassed(): bool
  {
    return (!$this->requiresApproval);
  }

  public function requireApproval()
  {
    $this->requiresApproval = true;
  }

  /**
   * Approval is explicitly required for this update
   */
  public function withApproval(): static
  {
    $this->bypassApproval = false;
    $this->requiresApproval = true;
    return $this;
  }
}
