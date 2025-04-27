<?php

declare(strict_types=1);

namespace Cjmellor\Approval\Tests\Traits;

use Workbench\App\Models\User;

trait WithTestUser
{
    protected function createAndAuthenticateUser(?array $userData = null): User
    {
        $user = User::create($userData ?? $this->fakeUserData);
        $this->be($user);

        return $user;
    }
}
