<?php

namespace Tests\Fixtures\Architecture\Tenancy;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;

class TenancyForbiddenImport
{
    public function __construct(
        public User $user,
    ) {}
}
