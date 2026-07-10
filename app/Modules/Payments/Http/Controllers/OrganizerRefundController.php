<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Application\Actions\RequestRefund;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

final class OrganizerRefundController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly TenantContextStore $contexts) {}

    public function store(Request $request, string $eventId, string $orderId, RequestRefund $action)
    {
        $validated = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $refund = $action->execute(
            $this->contexts->current(),
            $eventId,
            $orderId,
            $validated['amount_minor'],
            $validated['reason'],
            (string) $request->header('Idempotency-Key'),
        );

        return $this->success([
            'id' => $refund->id,
            'status' => $refund->status,
            'amount_minor' => $refund->amount_minor,
            'currency' => $refund->currency,
        ], 201);
    }
}
