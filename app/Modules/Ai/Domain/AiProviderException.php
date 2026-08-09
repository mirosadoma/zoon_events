<?php

namespace App\Modules\Ai\Domain;

use Exception;

final class AiProviderException extends Exception
{
    public const CODE_NETWORK_DISABLED = 'network_disabled';

    public const CODE_NOT_CONFIGURED = 'not_configured';

    public const CODE_TIMEOUT = 'timeout';

    public const CODE_RATE_LIMITED = 'rate_limited';

    public const CODE_INVALID_REQUEST = 'invalid_request';

    public const CODE_PROVIDER_ERROR = 'provider_error';

    public const CODE_PAYLOAD_TOO_LARGE = 'payload_too_large';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        ?Exception $previous = null,
    ) {
        parent::__construct($message ?: $errorCode, 0, $previous);
    }

    public static function networkDisabled(): self
    {
        return new self(self::CODE_NETWORK_DISABLED, 'Network access is disabled for AI providers.');
    }

    public static function notConfigured(): self
    {
        return new self(self::CODE_NOT_CONFIGURED, 'AI provider is not configured.');
    }

    public static function timeout(): self
    {
        return new self(self::CODE_TIMEOUT, 'AI provider request timed out.');
    }

    public static function rateLimited(): self
    {
        return new self(self::CODE_RATE_LIMITED, 'AI provider rate limit exceeded.');
    }

    public static function invalidRequest(string $detail = ''): self
    {
        return new self(self::CODE_INVALID_REQUEST, $detail ?: 'Invalid request to AI provider.');
    }

    public static function providerError(string $detail = ''): self
    {
        return new self(self::CODE_PROVIDER_ERROR, $detail ?: 'AI provider returned an error.');
    }

    public static function payloadTooLarge(): self
    {
        return new self(self::CODE_PAYLOAD_TOO_LARGE, 'Request payload exceeds size limit.');
    }
}
