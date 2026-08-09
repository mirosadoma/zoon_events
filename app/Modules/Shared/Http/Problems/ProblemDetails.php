<?php

namespace App\Modules\Shared\Http\Problems;

final readonly class ProblemDetails
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  list<string>  $missing
     * @param  list<string>  $publishBlockers
     */
    public function __construct(
        public string $type,
        public string $title,
        public int $status,
        public string $code,
        public string $detail,
        public string $instance,
        public string $correlationId,
        public array $errors = [],
        public array $missing = [],
        public array $publishBlockers = [],
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'code' => $this->code,
            'detail' => $this->detail,
            'instance' => $this->instance,
            'correlation_id' => $this->correlationId,
            'errors' => $this->errors === [] ? null : $this->errors,
            'missing' => $this->missing === [] ? null : $this->missing,
            'publish_blockers' => $this->publishBlockers === [] ? null : $this->publishBlockers,
        ], static fn ($value) => $value !== null);
    }
}
