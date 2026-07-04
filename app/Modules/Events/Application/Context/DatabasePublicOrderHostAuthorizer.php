<?php

namespace App\Modules\Events\Application\Context;

use App\Modules\Events\Contracts\PublicOrderHostAuthorizer;
use Illuminate\Support\Facades\DB;

final class DatabasePublicOrderHostAuthorizer implements PublicOrderHostAuthorizer
{
    public function allows(string $host, string $tenantId, string $eventId): bool
    {
        return DB::table('event_branding')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('domain_reference', mb_strtolower($host))
            ->where('status', 'active')
            ->exists();
    }
}
