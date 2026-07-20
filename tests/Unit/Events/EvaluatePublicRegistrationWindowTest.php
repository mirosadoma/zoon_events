<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Application\Support\EvaluatePublicRegistrationWindow;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('events')]
final class EvaluatePublicRegistrationWindowTest extends TestCase
{
    public function test_open_when_now_is_within_registration_window(): void
    {
        $event = new Event([
            'timezone' => 'UTC',
            'registration_opens_at' => CarbonImmutable::parse('2026-07-10 08:00:00', 'UTC'),
            'registration_closes_at' => CarbonImmutable::parse('2026-07-25 23:59:00', 'UTC'),
        ]);

        $status = app(EvaluatePublicRegistrationWindow::class)->status(
            $event,
            CarbonImmutable::parse('2026-07-19 12:00:00', 'UTC'),
        );

        self::assertSame(EvaluatePublicRegistrationWindow::OPEN, $status);
    }

    public function test_not_open_before_registration_opens_on_same_day(): void
    {
        $event = new Event([
            'timezone' => 'Asia/Riyadh',
            'registration_opens_at' => CarbonImmutable::parse('2026-07-19 09:00:00', 'Asia/Riyadh')->utc(),
            'registration_closes_at' => CarbonImmutable::parse('2026-07-19 17:00:00', 'Asia/Riyadh')->utc(),
        ]);

        $status = app(EvaluatePublicRegistrationWindow::class)->status(
            $event,
            CarbonImmutable::parse('2026-07-19 08:59:00', 'Asia/Riyadh'),
        );

        self::assertSame(EvaluatePublicRegistrationWindow::NOT_OPEN, $status);
    }

    public function test_open_at_exact_registration_opens_time(): void
    {
        $event = new Event([
            'timezone' => 'Asia/Riyadh',
            'registration_opens_at' => CarbonImmutable::parse('2026-07-19 09:00:00', 'Asia/Riyadh')->utc(),
            'registration_closes_at' => CarbonImmutable::parse('2026-07-19 17:00:00', 'Asia/Riyadh')->utc(),
        ]);

        $status = app(EvaluatePublicRegistrationWindow::class)->status(
            $event,
            CarbonImmutable::parse('2026-07-19 09:00:00', 'Asia/Riyadh'),
        );

        self::assertSame(EvaluatePublicRegistrationWindow::OPEN, $status);
    }

    public function test_closed_after_registration_closes_on_same_day(): void
    {
        $event = new Event([
            'timezone' => 'Asia/Riyadh',
            'registration_opens_at' => CarbonImmutable::parse('2026-07-19 09:00:00', 'Asia/Riyadh')->utc(),
            'registration_closes_at' => CarbonImmutable::parse('2026-07-19 17:00:00', 'Asia/Riyadh')->utc(),
        ]);

        $status = app(EvaluatePublicRegistrationWindow::class)->status(
            $event,
            CarbonImmutable::parse('2026-07-19 17:00:01', 'Asia/Riyadh'),
        );

        self::assertSame(EvaluatePublicRegistrationWindow::CLOSED, $status);
    }

    public function test_open_at_exact_registration_closes_time(): void
    {
        $event = new Event([
            'timezone' => 'Asia/Riyadh',
            'registration_opens_at' => CarbonImmutable::parse('2026-07-19 09:00:00', 'Asia/Riyadh')->utc(),
            'registration_closes_at' => CarbonImmutable::parse('2026-07-19 17:00:00', 'Asia/Riyadh')->utc(),
        ]);

        $status = app(EvaluatePublicRegistrationWindow::class)->status(
            $event,
            CarbonImmutable::parse('2026-07-19 17:00:00', 'Asia/Riyadh'),
        );

        self::assertSame(EvaluatePublicRegistrationWindow::OPEN, $status);
    }

    public function test_not_open_before_registration_opens(): void
    {
        $event = new Event([
            'timezone' => 'UTC',
            'registration_opens_at' => CarbonImmutable::parse('2026-07-20 00:00:00', 'UTC'),
            'registration_closes_at' => CarbonImmutable::parse('2026-07-30 00:00:00', 'UTC'),
        ]);

        $status = app(EvaluatePublicRegistrationWindow::class)->status(
            $event,
            CarbonImmutable::parse('2026-07-19 23:00:00', 'UTC'),
        );

        self::assertSame(EvaluatePublicRegistrationWindow::NOT_OPEN, $status);
    }

    public function test_closed_after_registration_closes(): void
    {
        $event = new Event([
            'timezone' => 'UTC',
            'registration_opens_at' => CarbonImmutable::parse('2026-07-01 00:00:00', 'UTC'),
            'registration_closes_at' => CarbonImmutable::parse('2026-07-18 00:00:00', 'UTC'),
        ]);

        $status = app(EvaluatePublicRegistrationWindow::class)->status(
            $event,
            CarbonImmutable::parse('2026-07-19 00:00:00', 'UTC'),
        );

        self::assertSame(EvaluatePublicRegistrationWindow::CLOSED, $status);
    }

    public function test_open_when_no_window_configured(): void
    {
        $event = new Event([
            'timezone' => 'UTC',
            'registration_opens_at' => null,
            'registration_closes_at' => null,
        ]);

        $status = app(EvaluatePublicRegistrationWindow::class)->status($event);

        self::assertSame(EvaluatePublicRegistrationWindow::OPEN, $status);
    }
}
