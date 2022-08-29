<?php

namespace Cjmellor\Approval\Concerns;

trait MayBeApproved
{

  use MustBeApproved;

  public function __construct()
  {
    $this->bypassApproval = true;
  }

  public function withApproval(): static
  {
    $this->bypassApproval = false;
    return $this;
  }
}
