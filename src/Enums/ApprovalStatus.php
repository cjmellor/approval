<?php

namespace Cjmellor\Approval\Enums;

enum ApprovalStatus: int
{
    case Pending = 0;
    case Approved = 1;
    case Rejected = 2;
}
