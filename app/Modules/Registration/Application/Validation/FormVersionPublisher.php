<?php

namespace App\Modules\Registration\Application\Validation;

use InvalidArgumentException;

final readonly class FormVersionPublisher
{
    public function __construct(private FormSchemaValidator $schemas) {}

    /**
     * @param  list<array<string,mixed>>  $fields
     * @return array{fields:list<array<string,mixed>>,schema_hash:string,privacy_notice_version:string,terms_version:string}
     */
    public function prepare(array $fields, string $privacyNoticeVersion, string $termsVersion): array
    {
        if (trim($privacyNoticeVersion) === '' || trim($termsVersion) === '') {
            throw new InvalidArgumentException('Published forms require consent document versions.');
        }

        return [
            'fields' => $fields,
            'schema_hash' => $this->schemas->canonicalHash($fields),
            'privacy_notice_version' => $privacyNoticeVersion,
            'terms_version' => $termsVersion,
        ];
    }
}
