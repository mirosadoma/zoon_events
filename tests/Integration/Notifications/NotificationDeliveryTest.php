<?php

namespace Tests\Integration\Notifications;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Contracts\ConfirmationEventReader;
use App\Modules\Notifications\Application\Actions\ProcessNotificationCallback;
use App\Modules\Notifications\Application\Jobs\DeliverNotificationJob;
use App\Modules\Notifications\Application\NotificationAdapterRegistry;
use App\Modules\Notifications\Application\Rendering\ConfirmationRenderer;
use App\Modules\Notifications\Domain\NotificationResult;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use App\Modules\Notifications\Testing\FakeEmailAdapter;
use App\Modules\Orders\Contracts\ConfirmationOrderReader;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('notifications')]
final class NotificationDeliveryTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_duplicate_jobs_converge_and_callback_is_idempotent(): void
    {
        $notification = $this->notification();
        $fake = new FakeEmailAdapter;
        $job = new DeliverNotificationJob($notification->id);

        $this->handle($job, $fake);
        $this->handle($job, $fake);

        self::assertCount(1, $fake->sent);
        self::assertSame('sent', $notification->refresh()->status);
        self::assertTrue(app(ProcessNotificationCallback::class)->handle((string) $notification->provider_message_id, 'delivered'));
        self::assertTrue(app(ProcessNotificationCallback::class)->handle((string) $notification->provider_message_id, 'delivered'));
        self::assertSame('delivered', $notification->refresh()->status);
    }

    public function test_temporary_and_unknown_results_are_bounded_and_permanent_is_terminal(): void
    {
        foreach ([NotificationStatus::TemporaryFailure, NotificationStatus::Unknown] as $status) {
            $notification = $this->notification();
            try {
                $this->handle(new DeliverNotificationJob($notification->id), new FakeEmailAdapter(new NotificationResult($status)));
                self::fail('Retryable result should release the job through the queue retry path.');
            } catch (RuntimeException) {
                self::assertSame('temporary_failure', $notification->refresh()->status);
                self::assertNotNull($notification->next_attempt_at);
            }
        }

        $notification = $this->notification();
        $this->handle(
            new DeliverNotificationJob($notification->id),
            new FakeEmailAdapter(new NotificationResult(NotificationStatus::PermanentFailure, reasonCode: 'rejected')),
        );
        self::assertSame('permanent_failure', $notification->refresh()->status);
        self::assertSame('rejected', $notification->last_reason_code);
    }

    public function test_enabled_sms_creates_one_idempotent_phone_intent_only_for_valid_saudi_number(): void
    {
        config()->set('notifications.sms_adapter', 'fake');
        $fixture = $this->createRegistrationFixture();
        $payload = $this->registrationPayload($fixture);
        $payload['attendee']['phone'] = '+966512345678';
        $url = "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations";

        $this->withHeader('Idempotency-Key', 'sms-intent')->postJson($url, $payload)->assertCreated();
        $this->withHeader('Idempotency-Key', 'sms-intent')->postJson($url, $payload)->assertOk();

        self::assertSame(
            ['email', 'sms'],
            Notification::query()->where('tenant_id', $fixture['tenant']->id)->orderBy('channel')->pluck('channel')->all(),
        );
    }

    public function test_terminal_state_rolls_back_when_required_audit_evidence_fails(): void
    {
        $notification = $this->notification();
        $audit = \Mockery::mock(AuditWriter::class);
        $audit->shouldReceive('write')->andThrow(new RuntimeException('Synthetic audit failure.'));
        app()->instance(AuditWriter::class, $audit);

        try {
            $this->handle(
                new DeliverNotificationJob($notification->id),
                new FakeEmailAdapter(new NotificationResult(NotificationStatus::PermanentFailure, reasonCode: 'rejected')),
            );
            self::fail('Terminal state must fail with required audit evidence.');
        } catch (RuntimeException $exception) {
            self::assertSame('Synthetic audit failure.', $exception->getMessage());
        }

        self::assertSame('temporary_failure', $notification->refresh()->status);
        self::assertSame('audit_write_failed', $notification->last_reason_code);
        self::assertNotNull($notification->next_attempt_at);
        self::assertFalse(DB::table('audit_logs')->where('action', 'notification.permanent_failure')
            ->where('target_id', $notification->id)->exists());
    }

    public function test_scheduler_recovers_a_stale_processing_intent(): void
    {
        Queue::fake();
        $notification = $this->notification();
        $notification->forceFill([
            'status' => 'processing',
            'updated_at' => now()->subMinutes(11),
        ])->save();

        $this->artisan('zonetec:notifications:deliver-due')->assertSuccessful();

        Queue::assertPushed(
            DeliverNotificationJob::class,
            fn (DeliverNotificationJob $job): bool => $job->notificationId === $notification->id,
        );
    }

    public function test_unknown_callback_is_uniform_and_audited_without_provider_payload(): void
    {
        config()->set('notifications.unifonic.callback_route_token', 'known-notification-route-token-12345');

        $this->postJson('/api/v1/webhooks/notifications/unifonic/unknown-route-token-123456789', [
            'MessageID' => 'sensitive-provider-message',
            'Status' => 'delivered',
        ])->assertNotFound();

        $audit = DB::table('audit_logs')->where('action', 'notification.callback_denied')->latest('occurred_at')->first();
        self::assertNotNull($audit);
        self::assertSame('route_unknown', $audit->reason_code);
        self::assertStringNotContainsString('sensitive-provider-message', json_encode($audit));
    }

    private function notification(): Notification
    {
        $fixture = $this->createRegistrationFixture();
        $this->withHeader('Idempotency-Key', 'notice-'.uniqid())->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated();

        return Notification::query()->where('tenant_id', $fixture['tenant']->id)->firstOrFail();
    }

    private function handle(DeliverNotificationJob $job, FakeEmailAdapter $adapter): void
    {
        $job->handle(
            new NotificationAdapterRegistry(['fake' => $adapter, 'log' => $adapter]),
            app(ConfirmationRenderer::class),
            app(PersonalDataCipher::class),
            app(ConfirmationEventReader::class),
            app(ConfirmationOrderReader::class),
        );
    }
}
