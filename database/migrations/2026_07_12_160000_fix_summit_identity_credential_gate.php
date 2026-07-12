<?php

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Application\Actions\IssueCredential;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $event = Event::query()->where('slug', 'zonetec-summit-2026')->first();

        if ($event === null) {
            return;
        }

        IdentityVerificationRequirement::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('level', 'required_before_credential')
            ->update(['level' => 'required_before_gate']);

        $attendeeIdsWithCredentials = Credential::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->pluck('attendee_id');

        $attendees = Attendee::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->when($attendeeIdsWithCredentials->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $attendeeIdsWithCredentials))
            ->get(['id', 'ticket_type_id']);

        if ($attendees->isEmpty()) {
            return;
        }

        $expiresAt = $event->end_at === null
            ? CarbonImmutable::now()->addDay()
            : CarbonImmutable::parse($event->end_at);

        DB::transaction(function () use ($attendees, $event, $expiresAt): void {
            $issuer = app(IssueCredential::class);

            foreach ($attendees as $attendee) {
                if ($attendee->ticket_type_id === null) {
                    continue;
                }

                $issuer->execute(
                    (string) $event->tenant_id,
                    (string) $event->id,
                    (string) $attendee->id,
                    (string) $attendee->ticket_type_id,
                    $expiresAt,
                );
            }
        });
    }

    public function down(): void
    {
        $event = Event::query()->where('slug', 'zonetec-summit-2026')->first();

        if ($event === null) {
            return;
        }

        IdentityVerificationRequirement::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('level', 'required_before_gate')
            ->update(['level' => 'required_before_credential']);
    }
};
