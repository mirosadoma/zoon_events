<?php

namespace App\Modules\Ai\Application\Support;

final class EventMetricsPayload
{
    private const ALLOWED_KEYS = [
        'registered_count',
        'checked_in_count',
        'rejected_count',
        'duplicate_count',
        'orders_total',
        'orders_by_status',
        'paid_orders',
        'revenue_minor',
        'currency',
        'payment_success_rate',
        'credentials_issued',
        'credentials_revoked',
        'registrations_by_day',
        'checkins_by_day',
        'funnel',
        'category_breakdown',
        'ticket_type_breakdown',
        'zone_occupancy_summary',
        'top_reject_reasons',
        'capacity',
        'event_window',
    ];

    private int $minBucketSize;

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function __construct(
        private array $metrics = [],
        ?int $minBucketSize = null,
    ) {
        $this->minBucketSize = $minBucketSize ?? (int) config('ai.insights.min_bucket_size', 5);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $payload = [];

        foreach ($this->metrics as $key => $value) {
            if (! in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }

            $payload[$key] = $this->sanitizeValue($key, $value);
        }

        return $payload;
    }

    public function hash(): string
    {
        $payload = $this->build();
        ksort($payload);

        return hash('sha256', json_encode($payload));
    }

    public function set(string $key, mixed $value): self
    {
        if (in_array($key, self::ALLOWED_KEYS, true)) {
            $this->metrics[$key] = $value;
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        $payload = $this->build();

        return $payload === [] || ($payload['registered_count'] ?? 0) === 0;
    }

    private function sanitizeValue(string $key, mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeBreakdown($value);
        }

        return $value;
    }

    /**
     * @param  array<string|int, mixed>  $breakdown
     * @return array<string|int, mixed>
     */
    private function sanitizeBreakdown(array $breakdown): array
    {
        $result = [];

        foreach ($breakdown as $label => $count) {
            if (is_array($count)) {
                $result[$label] = $this->sanitizeBreakdown($count);

                continue;
            }

            if (is_numeric($count) && (int) $count < $this->minBucketSize && (int) $count > 0) {
                $result['other_small_buckets'] = ($result['other_small_buckets'] ?? 0) + (int) $count;
            } else {
                $result[$label] = $count;
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public static function allowedKeys(): array
    {
        return self::ALLOWED_KEYS;
    }
}
