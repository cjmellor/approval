<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
