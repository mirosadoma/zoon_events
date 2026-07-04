<?php

use App\Modules\Audit\Http\Controllers\AuditExportController;
use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\Audit\Http\Controllers\AuditVerificationController;
use App\Modules\Authorization\Http\Controllers\PlatformRoleController;
use App\Modules\Authorization\Http\Controllers\TenantRoleController;
use App\Modules\FeatureFlags\Http\Controllers\FeatureFlagController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\PlatformUserController;
use App\Modules\Operations\Http\Controllers\PlatformHealthController;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Http\Controllers\ConfigurationController;
use App\Modules\Tenancy\Http\Controllers\PlatformTenantController;
use App\Modules\Tenancy\Http\Controllers\TenantMembershipController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require base_path('app/Modules/Events/Routes/api.php');
    require base_path('app/Modules/Registration/Routes/api.php');
    require base_path('app/Modules/Ticketing/Routes/api.php');
    require base_path('app/Modules/Orders/Routes/api.php');
    require base_path('app/Modules/Payments/Routes/api.php');
    require base_path('app/Modules/Attendees/Routes/api.php');
    require base_path('app/Modules/Credentials/Routes/api.php');
    require base_path('app/Modules/Notifications/Routes/api.php');

    Route::post('/auth/token', [AuthController::class, 'issueToken'])
        ->middleware('throttle:auth')
        ->name('api.v1.auth.token.issue');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/auth/token/current', [AuthController::class, 'revokeCurrentToken'])->name('api.v1.auth.token.revoke');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::get('/auth/tenants', [AuthController::class, 'tenants'])->name('api.v1.auth.tenants');

        if (app()->environment('testing')) {
            Route::get('/tenant/__probe/store-state', function (TenantContextStore $store) {
                return response()->json([
                    'bound' => $store->currentOrNull() !== null,
                ]);
            });
        }

        Route::prefix('platform')->middleware('throttle:platform')->group(function (): void {
            Route::get('/tenants', [PlatformTenantController::class, 'index'])->middleware('permission:platform.tenant.view,platform');
            Route::post('/tenants', [PlatformTenantController::class, 'store'])->middleware(['permission:platform.tenant.manage,platform', 'idempotency'])->name('api.v1.platform.tenants.store');
            Route::get('/tenants/{tenant_id}', [PlatformTenantController::class, 'show'])->middleware('permission:platform.tenant.view,platform');
            Route::patch('/tenants/{tenant_id}', [PlatformTenantController::class, 'update'])->middleware(['permission:platform.tenant.manage,platform', 'idempotency'])->name('api.v1.platform.tenants.update');

            Route::get('/users', [PlatformUserController::class, 'index'])->middleware('permission:platform.user.view,platform');
            Route::post('/users', [PlatformUserController::class, 'store'])->middleware(['permission:platform.user.manage,platform', 'idempotency'])->name('api.v1.platform.users.store');
            Route::patch('/users/{user_id}', [PlatformUserController::class, 'update'])->middleware(['permission:platform.user.manage,platform', 'idempotency'])->name('api.v1.platform.users.update');

            Route::get('/roles', [PlatformRoleController::class, 'index'])->middleware('permission:platform.role.view,platform');
            Route::post('/roles', [PlatformRoleController::class, 'store'])->middleware(['permission:platform.role.manage,platform', 'idempotency'])->name('api.v1.platform.roles.store');
            Route::patch('/roles/{role_id}', [PlatformRoleController::class, 'update'])->middleware(['permission:platform.role.manage,platform', 'idempotency'])->name('api.v1.platform.roles.update');
            Route::post('/role-assignments', [PlatformRoleController::class, 'assign'])->middleware(['permission:platform.role.assign,platform', 'idempotency'])->name('api.v1.platform.assignments.store');
            Route::delete('/role-assignments/{assignment_id}', [PlatformRoleController::class, 'revoke'])->middleware(['permission:platform.role.assign,platform', 'idempotency'])->name('api.v1.platform.assignments.revoke');

            Route::get('/health', PlatformHealthController::class)->middleware('permission:operations.health.view,platform');
            Route::get('/audit-logs', [AuditLogController::class, 'platform'])->middleware('permission:platform.audit.view,platform');
            Route::get('/feature-flags', [FeatureFlagController::class, 'platformIndex'])->middleware('permission:platform.feature_flag.view,platform');
            Route::post('/feature-flags', [FeatureFlagController::class, 'platformStore'])->middleware(['permission:platform.feature_flag.manage,platform', 'idempotency'])->name('api.v1.platform.flags.store');
            Route::patch('/feature-flags/{flag_key}', [FeatureFlagController::class, 'platformUpdate'])->middleware(['permission:platform.feature_flag.manage,platform', 'idempotency'])->name('api.v1.platform.flags.update');
            Route::get('/configuration-schemas', [ConfigurationController::class, 'platformSchemas'])->middleware('permission:platform.configuration.view,platform');
        });

        Route::prefix('tenant')->middleware(['throttle:tenant', 'tenant.context.clear', 'tenant.context'])->group(function (): void {
            if (app()->environment('testing')) {
                Route::get('/__probe/context', function (TenantContextStore $store) {
                    $context = $store->current();

                    return response()->json([
                        'tenant_id' => $context->tenant->id,
                        'membership_id' => $context->membership->id,
                    ]);
                });

                Route::get('/__probe/roles/{role_id}', function (string $role_id) {
                    return response()->json(['role_id' => $role_id]);
                });
            }

            Route::get('/memberships', [TenantMembershipController::class, 'index'])->middleware('permission:membership.view,tenant');
            Route::post('/memberships', [TenantMembershipController::class, 'store'])->middleware(['permission:membership.manage,tenant', 'idempotency'])->name('api.v1.tenant.memberships.store');
            Route::patch('/memberships/{membership_id}', [TenantMembershipController::class, 'update'])->middleware(['permission:membership.manage,tenant', 'idempotency'])->name('api.v1.tenant.memberships.update');

            Route::get('/roles', [TenantRoleController::class, 'index'])->middleware('permission:role.view,tenant');
            Route::get('/roles/{role_id}', [TenantRoleController::class, 'show'])->middleware('permission:role.view,tenant');
            Route::post('/roles', [TenantRoleController::class, 'store'])->middleware(['permission:role.manage,tenant', 'idempotency'])->name('api.v1.tenant.roles.store');
            Route::patch('/roles/{role_id}', [TenantRoleController::class, 'update'])->middleware(['permission:role.manage,tenant', 'idempotency'])->name('api.v1.tenant.roles.update');
            Route::delete('/roles/{role_id}', [TenantRoleController::class, 'destroy'])->middleware(['permission:role.manage,tenant', 'idempotency'])->name('api.v1.tenant.roles.destroy');
            Route::put('/roles/{role_id}/permissions', [TenantRoleController::class, 'replacePermissions'])->middleware(['permission:role.manage,tenant', 'idempotency'])->name('api.v1.tenant.roles.permissions');
            Route::post('/role-assignments', [TenantRoleController::class, 'assign'])->middleware(['permission:role.assign,tenant', 'idempotency'])->name('api.v1.tenant.assignments.store');
            Route::delete('/role-assignments/{assignment_id}', [TenantRoleController::class, 'revoke'])->middleware(['permission:role.assign,tenant', 'idempotency'])->name('api.v1.tenant.assignments.revoke');

            Route::get('/audit-logs', [AuditLogController::class, 'tenant'])->middleware('permission:audit.view,tenant');
            Route::post('/audit-exports', [AuditExportController::class, 'store'])->middleware(['permission:audit.export,tenant', 'throttle:privileged-export', 'idempotency'])->name('api.v1.tenant.audit-exports.store');
            Route::get('/audit-exports/{export_id}', [AuditExportController::class, 'show'])->middleware('permission:audit.export,tenant');
            Route::get('/audit-exports/{export_id}/download', [AuditExportController::class, 'download'])->middleware(['permission:audit.export,tenant', 'throttle:privileged-export']);
            Route::post('/audit-verifications', AuditVerificationController::class)->middleware('permission:audit.verify,tenant');
            Route::get('/feature-flags', [FeatureFlagController::class, 'tenantIndex'])->middleware('permission:feature_flag.view,tenant');
            Route::put('/feature-flags/{flag_key}', [FeatureFlagController::class, 'tenantSet'])->middleware(['permission:feature_flag.manage,tenant', 'idempotency'])->name('api.v1.tenant.flags.set');
            Route::delete('/feature-flags/{flag_key}', [FeatureFlagController::class, 'tenantDelete'])->middleware(['permission:feature_flag.manage,tenant', 'idempotency'])->name('api.v1.tenant.flags.remove');
            Route::get('/configuration', [ConfigurationController::class, 'tenantConfigurations'])->middleware('permission:configuration.view,tenant');
        });
    });
});
