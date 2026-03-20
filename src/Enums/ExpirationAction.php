<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Enums;

enum ExpirationAction: string
{
    case Reject = 'reject';
    case Postpone = 'postpone';
    case Custom = 'custom';
}
