<?php

namespace App\Modules\Integrations\Domain;

enum AdapterErrorCategory: string
{
    case InvalidRequest = 'invalid_request';
    case AuthenticationFailed = 'authentication_failed';
    case AuthorizationFailed = 'authorization_failed';
    case RateLimited = 'rate_limited';
    case TimeoutBeforeSend = 'timeout_before_send';
    case TimeoutUnknownOutcome = 'timeout_unknown_outcome';
    case ProviderUnavailable = 'provider_unavailable';
    case ProviderRejected = 'provider_rejected';
    case MalformedResponse = 'malformed_response';
    case ConfigurationInvalid = 'configuration_invalid';
    case InternalAdapterFailure = 'internal_adapter_failure';
}
