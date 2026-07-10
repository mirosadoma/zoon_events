<?php

namespace App\Exceptions;

use Exception;

class FoundationException extends Exception
{
    public function __construct(
        public readonly string $problemCode,
        public readonly int $status,
        public readonly string $title,
        string $detail,
    ) {
        parent::__construct($detail, 0);
    }

    public static function conflict(string $code, string $detail): self
    {
        return new self($code, 409, 'Conflict', $detail);
    }

    public static function forbidden(string $code = 'forbidden', string $detail = 'You are not allowed to perform this action.'): self
    {
        return new self($code, 403, 'Forbidden', $detail);
    }

    public static function validation(string $code = 'validation_failed', string $detail = 'One or more fields are invalid.'): self
    {
        return new self($code, 422, 'Validation failed', $detail);
    }

    public static function unauthenticated(string $detail = 'Authentication is required to access this resource.'): self
    {
        return new self('unauthenticated', 401, 'Unauthenticated', $detail);
    }

    public static function notFound(string $detail = 'The requested resource could not be found.'): self
    {
        return new self('resource_not_found', 404, 'Resource not found', $detail);
    }

    public function detail(): string
    {
        return $this->getMessage();
    }
}
