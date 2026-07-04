<?php

namespace App\Modules\Credentials\Application\Signing;

use InvalidArgumentException;

final readonly class CanonicalCredentialToken
{
    public function __construct(private CredentialKeyRing $keys) {}

    /** @param array{cid:string,eid:string,iat:int,exp:int,nonce:string,tid:string} $claims */
    public function issue(array $claims): string
    {
        ksort($claims, SORT_STRING);
        $payload = sodium_bin2base64(
            json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
        );
        $prefix = 'zt1';
        $keyId = $this->keys->currentKeyId();
        $message = "{$prefix}.{$keyId}.{$payload}";
        $signed = $this->keys->sign($message);

        return "{$message}.{$signed['signature']}";
    }

    /** @return array{cid:string,eid:string,iat:int,exp:int,nonce:string,tid:string} */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 4 || $parts[0] !== 'zt1') {
            throw new InvalidArgumentException('Credential token is malformed.');
        }
        [$version, $keyId, $payload, $signature] = $parts;
        if (! $this->keys->verify($keyId, "{$version}.{$keyId}.{$payload}", $signature)) {
            throw new InvalidArgumentException('Credential signature is invalid.');
        }

        try {
            $claims = json_decode(
                sodium_base642bin($payload, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (\Throwable) {
            throw new InvalidArgumentException('Credential token is malformed.');
        }
        $required = ['cid', 'eid', 'exp', 'iat', 'nonce', 'tid'];
        if (! is_array($claims) || array_keys($claims) !== $required) {
            throw new InvalidArgumentException('Credential claims are invalid.');
        }

        return $claims;
    }
}
