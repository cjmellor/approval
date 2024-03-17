<?php

namespace Approval\Approval\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Approval\Approval\Approval
 */
class Approval extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'approval';
    }
}
