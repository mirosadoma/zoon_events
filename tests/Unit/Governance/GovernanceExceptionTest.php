<?php

namespace Tests\Unit\Governance;

use PHPUnit\Framework\TestCase;

class GovernanceExceptionTest extends TestCase
{
    public function test_exception_template_requires_all_governance_fields(): void
    {
        $template = file_get_contents(dirname(__DIR__, 3).'/docs/governance/exception-template.md');

        foreach (['Violated rule', 'Business necessity', 'Risk owner', 'Risk assessment', 'Compensating controls', 'Approval', 'Expiry or remediation date', 'Status'] as $field) {
            self::assertStringContainsString($field, $template);
        }
    }
}
