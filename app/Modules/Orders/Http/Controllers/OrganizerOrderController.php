<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Application\Actions\CancelOrder;
use App\Modules\Orders\Application\Queries\OrganizerOrderQuery;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

final class OrganizerOrderController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly TenantContextStore $contexts) {}

    public function index(Request $request, string $eventId, OrganizerOrderQuery $query)
    {
        $page = $query->execute(
            $this->contexts->current()->tenant->id,
            $eventId,
            $request->string('status')->toString() ?: null,
            $request->string('cursor')->toString() ?: null,
            $request->integer('page_size', 50),
        );

        return $this->success($page['items']->map(fn ($order): array => [
            'id' => $order->id,
            'public_reference' => $order->public_reference,
            'status' => $order->status,
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'created_at' => $order->created_at?->toIso8601String(),
        ])->all(), meta: ['next_cursor' => $page['next_cursor']]);
    }

    public function cancel(Request $request, string $eventId, string $orderId, CancelOrder $action)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $order = $action->execute($this->contexts->current(), $eventId, $orderId, $validated['reason']);

        return $this->success([
            'id' => $order->id,
            'status' => $order->status,
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
        ]);
    }
}
