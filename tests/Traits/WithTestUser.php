<?php

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
