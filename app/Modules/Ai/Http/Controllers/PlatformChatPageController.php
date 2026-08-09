<?php

namespace App\Modules\Ai\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\Ai\Application\Support\AiProviderStatus;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformChatPageController extends Controller
{
    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
    ) {}

    public function __invoke(string $locale): Response
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);
        abort_unless($this->permissions->hasTenantPermission($context, 'reports.view'), 403);

        $aiStatus = app(AiProviderStatus::class)->describe();

        return Inertia::render('tenant/PlatformChat', [
            'tenantId' => (string) $context->tenant->id,
            'aiAvailable' => $aiStatus['available'],
            'aiStatus' => $aiStatus,
            'locale' => $locale,
        ]);
    }
}
