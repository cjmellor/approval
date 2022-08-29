<?php

namespace Cjmellor\Approval\Concerns;

trait MayBeApproved
{

  use MustBeApproved;

  public function __construct()
  {
    $this->bypassApproval = true;
  }

}
