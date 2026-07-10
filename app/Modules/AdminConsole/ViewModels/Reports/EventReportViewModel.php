<?php

namespace App\Modules\AdminConsole\ViewModels\Reports;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;

final readonly class EventReportViewModel
{
    /**
     * @return array{event: array<string, mixed>, tenantId: string, report: array<string, mixed>}
     */
    public function make(Event $event, string $tenantId): array
    {
        $registrations = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->count();

        $paidOrders = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('status', 'paid')
            ->count();

        $ordersTotal = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->count();

        $credentialsIssued = Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->count();

        $credentialsRevoked = Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('status', 'revoked')
            ->count();

        $walletPasses = WalletPass::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->count();

        $checkins = ScanEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->whereIn('result', ['accepted', 'manual_override'])
            ->count();

        $acceptedScans = ScanEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->whereIn('result', ['accepted', 'manual_override'])
            ->count();

        $rejectedScans = ScanEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('result', 'rejected')
            ->count();

        $badgePrints = BadgePrintJob::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('status', 'printed')
            ->count();

        $acsAccepted = AccessEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('decision', 'allow')
            ->count();

        $acsRejected = AccessEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('decision', 'deny')
            ->count();

        $walletAdoption = $credentialsIssued > 0
            ? round(($walletPasses / $credentialsIssued) * 100, 1)
            : null;

        $scanTotal = $acceptedScans + $rejectedScans;

        return [
            'event' => [
                'id' => $event->id,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            ],
            'tenantId' => $tenantId,
            'report' => [
                'registrations' => ['value' => $registrations, 'available' => true],
                'paid_orders' => ['value' => $paidOrders, 'available' => true],
                'payment_success_rate' => [
                    'value' => $ordersTotal > 0 ? round(($paidOrders / $ordersTotal) * 100, 1) : null,
                    'available' => $ordersTotal > 0,
                ],
                'credentials_issued' => ['value' => $credentialsIssued, 'available' => true],
                'credentials_revoked' => ['value' => $credentialsRevoked, 'available' => true],
                'wallet_adoption' => ['value' => $walletAdoption, 'available' => $credentialsIssued > 0],
                'checkins' => ['value' => $checkins, 'available' => true],
                'first_scan_success_rate' => [
                    'value' => null,
                    'available' => false,
                    'label' => 'not available yet',
                ],
                'checkin_success_rate' => [
                    'value' => $scanTotal > 0 ? round(($acceptedScans / $scanTotal) * 100, 1) : null,
                    'available' => $scanTotal > 0,
                ],
                'badge_prints' => ['value' => $badgePrints, 'available' => true],
                'acs_entries_accepted' => ['value' => $acsAccepted, 'available' => true],
                'acs_entries_rejected' => ['value' => $acsRejected, 'available' => true],
            ],
        ];
    }
}
