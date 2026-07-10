<?php

namespace Database\Factories;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BadgePrintJob> */
final class BadgePrintJobFactory extends Factory
{
    protected $model = BadgePrintJob::class;

    public function definition(): array
    {
        return [
            'status' => 'queued',
            'failure_reason' => null,
            'is_reprint' => false,
            'reprint_reason' => null,
            'original_print_job_id' => null,
            'printed_at' => null,
            'kiosk_id' => null,
            'printed_by_user_id' => null,
        ];
    }

    public function printed(): static
    {
        return $this->state(['status' => 'printed', 'printed_at' => now()]);
    }

    public function failed(string $reason = 'printer_error'): static
    {
        return $this->state(['status' => 'failed', 'failure_reason' => $reason]);
    }

    public function forCredential(Credential $credential, Attendee $attendee, BadgeTemplate $template): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $credential->tenant_id,
            'event_id' => $credential->event_id,
            'attendee_id' => $attendee->id,
            'credential_id' => $credential->id,
            'badge_template_id' => $template->id,
        ]);
    }
}
