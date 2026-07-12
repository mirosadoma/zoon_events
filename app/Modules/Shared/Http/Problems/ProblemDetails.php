<?php

namespace App\Modules\Shared\Http\Problems;

final readonly class ProblemDetails
{
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
        ], static fn ($value) => $value !== null);
    }
}
