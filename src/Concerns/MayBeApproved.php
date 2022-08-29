<?php

namespace Cjmellor\Approval\Concerns;

trait MayBeApproved
{
  use MustBeApproved;
  protected bool $bypassApproval = true;

  public function withApproval(): static
  {
    $this->bypassApproval = false;
    return $this;
  }
}
