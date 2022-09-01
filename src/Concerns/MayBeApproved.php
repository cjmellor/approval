<?php

namespace Cjmellor\Approval\Concerns;

trait MayBeApproved
{

  use MustBeApproved;
  protected static bool $requiresApproval = false;

  public function isApprovalBypassed(): bool
  {
    return !self::$requiresApproval;
  }

  public static function requireApproval($requires = true)
  {
    self::$requiresApproval = $requires;
  }

  public function withApproval(): static
  {
    $this->bypassApproval = false;
    self::$requiresApproval = true;
    return $this;
  }
}
