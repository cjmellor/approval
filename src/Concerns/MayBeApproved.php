<?php

namespace Cjmellor\Approval\Concerns;

trait MayBeApproved
{

  use MustBeApproved;
  protected bool $requiresApproval = false;

  public function isApprovalBypassed(): bool
  {
    return (!$this->requiresApproval);
  }

  public function requireApproval()
  {
    $this->requiresApproval = true;
  }

  public function withApproval(): static
  {
    $this->bypassApproval = false;
    $this->requiresApproval = true;
    return $this;
  }
}
