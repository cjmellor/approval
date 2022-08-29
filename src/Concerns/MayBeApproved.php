<?php

namespace Cjmellor\Approval\Concerns;

trait MayBeApproved
{

  use MustBeApproved {
    MustBeApproved::__construct as private __parentConstruct;
  }

  public function __construct()
  {
    $this->__parentConstruct();
    $this->bypassApproval = true;
  }

  public function withApproval(): static
  {
    $this->bypassApproval = false;
    return $this;
  }
}
