<?php

namespace App\Modules\Attendees\Application\Jobs;

use App\Modules\Notifications\Contracts\NotificationDestinationAnonymizer;
use App\Modules\Orders\Contracts\OrderPersonalDataAnonymizer;
use App\Modules\Registration\Contracts\SubmissionPersonalDataAnonymizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class AnonymizeEligibleAttendees
{
    public function __construct(
        private OrderPersonalDataAnonymizer $orders,
        private SubmissionPersonalDataAnonymizer $submissions,
        private NotificationDestinationAnonymizer $notifications,
    ) {}

    /** @return array{eligible:int,anonymized:int} */
    public function handle(string $tenantId, Carbon $before, bool $dryRun = true): array
    {
        $query = DB::table('attendees')
            ->where('tenant_id', $tenantId)
            ->where('registration_status', '!=', 'anonymized')
            ->whereNull('legal_hold_at')
            ->where('registered_at', '<', $before)
            ->orderBy('id');
        $eligible = (clone $query)->count();
        if ($dryRun || $eligible === 0) {
            return ['eligible' => $eligible, 'anonymized' => 0];
        }

        $anonymized = 0;
        $query->pluck('id')->chunk(100)->each(function ($ids) use ($tenantId, &$anonymized): void {
            DB::transaction(function () use ($ids, $tenantId, &$anonymized): void {
                $attendees = DB::table('attendees')->where('tenant_id', $tenantId)
                    ->whereIn('id', $ids)->whereNull('legal_hold_at')->lockForUpdate()->get();
                foreach ($attendees as $attendee) {
                    $tombstone = hash('sha256', "anonymized:{$attendee->id}");
                    DB::table('attendees')->where('tenant_id', $tenantId)->where('id', $attendee->id)->update([
                        'first_name_ciphertext' => 'anonymized',
                        'last_name_ciphertext' => 'anonymized',
                        'email_ciphertext' => 'anonymized',
                        'phone_ciphertext' => null,
                        'email_index' => $tombstone,
                        'phone_index' => null,
                        'encryption_key_id' => 'anonymized',
                        'registration_status' => 'anonymized',
                        'anonymized_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->orders->anonymize($tenantId, $attendee->order_id, $tombstone);
                    $this->submissions->anonymize($tenantId, $attendee->submission_id);
                    $this->notifications->anonymizeForAttendee($tenantId, $attendee->id, $tombstone);
                    $anonymized++;
                }
            });
        });

        return ['eligible' => $eligible, 'anonymized' => $anonymized];
    }
}
