<?php

namespace App\Modules\Integrations\Domain;

final readonly class AdapterResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public AdapterStatus $status,
        public AdapterRetryPolicy $retryPolicy,
        public ?string $reasonCode = null,
        public array $data = [],
        public array $metadata = [],
    ) {
        if ($status === AdapterStatus::Succeeded && $reasonCode !== null) {
            throw new \InvalidArgumentException('Successful adapter results cannot contain a failure reason.');
        }

        if ($status === AdapterStatus::Unknown && $retryPolicy !== AdapterRetryPolicy::ReconcileFirst) {
            throw new \InvalidArgumentException('Unknown outcomes must reconcile before retry.');
        }
    }
}
