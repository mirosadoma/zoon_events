<?php

namespace Tests\Unit\Integrations;

use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationRequest;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Testing\FakeEmailAdapter;
use App\Modules\Payments\Domain\PaymentContext;
use App\Modules\Payments\Domain\PaymentRequest;
use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Testing\FakePaymentGateway;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('phase-1')]
final class Phase1FakeAdaptersTest extends TestCase
{
    public function test_payment_fake_is_deterministic_and_offline(): void
    {
        $fake = new FakePaymentGateway;
        $fake->push(new PaymentResult(PaymentStatus::Captured, 'payment-1', 1000, 0, 'SAR'));
        $result = $fake->create(
            new PaymentContext('tenant', 'account', 'correlation', 'idempotency-key', false, 1000),
            new PaymentRequest('order', 1000, 'SAR', 'https://example.test/return'),
        );

        self::assertSame(PaymentStatus::Captured, $result->status);
        self::assertSame('tenant', $fake->calls[0]['tenant_id']);
    }

    public function test_notification_fake_captures_request_without_network(): void
    {
        $fake = new FakeEmailAdapter;
        $result = $fake->send(new NotificationRequest(
            'tenant',
            'notification',
            NotificationChannel::Email,
            'synthetic@example.test',
            'sender',
            'Subject',
            'Body',
            'en',
            'correlation',
            'idempotency',
        ));

        self::assertSame(NotificationStatus::Accepted, $result->status);
        self::assertCount(1, $fake->sent);
    }
}
