<?php

namespace Tests\Security;

use Tests\TestCase;

class VenueMarketplaceSecretRedactionTest extends TestCase
{
    private const PHASE_6_SOURCE_DIRS = [
        'app/Modules/VenueMarketplace/Http/Resources',
        'app/Modules/VenueMarketplace/Http/Controllers',
        'app/Modules/VenueMarketplace/Application/Listeners',
        'app/Modules/VenueMarketplace/Application/Audit',
        'app/Modules/VenueMarketplace/Application/Jobs',
        'app/Modules/VenueMarketplace/Application/Exports',
        'app/Modules/VenueMarketplace/Application/Actions',
        'app/Modules/VenueMarketplace/Application/Services',
        'app/Modules/VenueMarketplace/Application/Queries',
        'app/Modules/VenueMarketplace/Domain/Events',
        'app/Modules/VenueMarketplace/Domain/Exceptions',
        'app/Modules/VenueMarketplace/Infrastructure/Persistence/Models',
    ];

    private const SECRET_FIELD_PATTERNS = [
        '/\bopaque_reference\b/' => 'opaque_reference (binding credential)',
        '/\bbinding_metadata\b/' => 'binding_metadata (adapter secrets)',
        '/\bpairing_secret\b/' => 'pairing_secret (device pairing)',
        '/\bbusiness_contact_email\b/' => 'business_contact_email (private contact)',
        '/\bbusiness_contact_phone\b/' => 'business_contact_phone (private contact)',
        '/\bbusiness_contact_name\b/' => 'business_contact_name (private contact)',
    ];

    private const ALLOWED_SECRET_LOCATIONS = [
        'CreateVenueAction.php',
        'UpdateVenueAction.php',
        'CreateVenueAssetAction.php',
        'UpdateVenueAssetAction.php',
        'PublishVenueAssetAction.php',
        'OwnerVenueResource.php',
        'OwnerVenueAssetResource.php',
        'Venue.php',
        'VenueAsset.php',
        'VenueAssetBinding.php',
        'VenueCatalogSchemaTest.php',
    ];

    // ─── API response scanning ──────────────────────────────────────

    public function test_api_resources_do_not_expose_binding_credentials(): void
    {
        $resourceDir = base_path('app/Modules/VenueMarketplace/Http/Resources');
        if (! is_dir($resourceDir)) {
            self::markTestSkipped('Resources directory not found.');
        }

        $violations = [];
        foreach ($this->phpFilesIn($resourceDir) as $file) {
            $basename = basename($file);
            if (in_array($basename, ['OwnerVenueResource.php', 'OwnerVenueAssetResource.php'], true)) {
                continue;
            }

            $source = file_get_contents($file);
            foreach (['/\bopaque_reference\b/', '/\bbinding_metadata\b/', '/\bpairing_secret\b/'] as $pattern) {
                if (preg_match($pattern, $source)) {
                    $violations[] = "{$basename} leaks {$pattern}";
                }
            }
        }

        self::assertEmpty($violations, "API resources expose secrets:\n".implode("\n", $violations));
    }

    public function test_participant_resources_exclude_private_contacts(): void
    {
        $resourceDir = base_path('app/Modules/VenueMarketplace/Http/Resources');
        $participantResources = [
            'ParticipantStatementResource.php',
            'ParticipantDisputeResource.php',
            'ParticipantRentalResource.php',
            'ParticipantDelegationResource.php',
            'MarketplaceCatalogResource.php',
            'PublishedCatalogItemResource.php',
        ];

        foreach ($participantResources as $resourceFile) {
            $path = "{$resourceDir}/{$resourceFile}";
            if (! file_exists($path)) {
                continue;
            }

            $source = file_get_contents($path);

            self::assertStringNotContainsString(
                'business_contact_email',
                $source,
                "{$resourceFile} must not expose business_contact_email.",
            );
            self::assertStringNotContainsString(
                'business_contact_phone',
                $source,
                "{$resourceFile} must not expose business_contact_phone.",
            );
        }
    }

    // ─── Log and exception scanning ─────────────────────────────────

    public function test_log_statements_do_not_include_secrets(): void
    {
        $violations = [];

        foreach (self::PHASE_6_SOURCE_DIRS as $dir) {
            $fullDir = base_path($dir);
            if (! is_dir($fullDir)) {
                continue;
            }

            foreach ($this->phpFilesIn($fullDir) as $file) {
                $source = file_get_contents($file);
                if (! preg_match('/Log::(error|warning|info|debug|notice|critical|alert|emergency)\s*\(/', $source)) {
                    continue;
                }

                foreach ([
                    'opaque_reference', 'binding_metadata', 'pairing_secret',
                    'business_contact_email', 'business_contact_phone',
                    'access_token', 'credential',
                ] as $secret) {
                    if (str_contains($source, "'{$secret}'") && str_contains($source, 'Log::')) {
                        $violations[] = basename($file)." logs '{$secret}'";
                    }
                }
            }
        }

        self::assertEmpty($violations, "Log statements expose secrets:\n".implode("\n", $violations));
    }

    public function test_exception_messages_do_not_leak_secrets(): void
    {
        $exceptionsDir = base_path('app/Modules/VenueMarketplace/Domain/Exceptions');
        if (! is_dir($exceptionsDir)) {
            self::markTestSkipped('Exceptions directory not found.');
        }

        foreach ($this->phpFilesIn($exceptionsDir) as $file) {
            $source = strtolower(file_get_contents($file));

            self::assertStringNotContainsString('opaque_reference', $source, basename($file).' leaks opaque_reference.');
            self::assertStringNotContainsString('binding_metadata', $source, basename($file).' leaks binding_metadata.');
            self::assertStringNotContainsString('pairing_secret', $source, basename($file).' leaks pairing_secret.');
        }
    }

    // ─── Audit payload scanning ─────────────────────────────────────

    public function test_audit_event_class_rejects_secret_payload_keys(): void
    {
        $source = file_get_contents(
            base_path('app/Modules/VenueMarketplace/Application/Audit/MarketplaceAuditEvent.php'),
        );

        self::assertStringContainsString('FORBIDDEN_KEY_FRAGMENTS', $source, 'Audit event must define forbidden key fragments.');
        self::assertStringContainsString('secret', $source);
        self::assertStringContainsString('credential', $source);
        self::assertStringContainsString('password', $source);
        self::assertStringContainsString('token', $source);
        self::assertStringContainsString('binding', $source);
    }

    public function test_audit_listeners_do_not_forward_raw_event_bodies(): void
    {
        $listenersDir = base_path('app/Modules/VenueMarketplace/Application/Listeners');
        if (! is_dir($listenersDir)) {
            self::markTestSkipped('Listeners directory not found.');
        }

        foreach ($this->phpFilesIn($listenersDir) as $file) {
            $source = file_get_contents($file);
            if (! str_contains($source, 'MarketplaceAuditEvent')) {
                continue;
            }

            self::assertStringNotContainsString(
                '$event->reason',
                $source === false ? '' : preg_replace('/\$event->reasonCode/', '', $source),
                basename($file).' must not forward raw reason text to audit (only reason_code is allowed).',
            );
            self::assertStringNotContainsString(
                'request_body',
                $source,
                basename($file).' must not forward raw request body to audit.',
            );
        }
    }

    // ─── Notification scanning ──────────────────────────────────────

    public function test_notification_listeners_do_not_serialize_secrets(): void
    {
        $listenersDir = base_path('app/Modules/VenueMarketplace/Application/Listeners');
        if (! is_dir($listenersDir)) {
            self::markTestSkipped('Listeners directory not found.');
        }

        foreach ($this->phpFilesIn($listenersDir) as $file) {
            $basename = basename($file);
            if (! str_contains($basename, 'Notification')) {
                continue;
            }

            $source = file_get_contents($file);

            foreach (['opaque_reference', 'binding_metadata', 'pairing_secret', 'access_token'] as $secret) {
                self::assertStringNotContainsString(
                    $secret,
                    $source,
                    "{$basename} notification listener must not reference {$secret}.",
                );
            }
        }
    }

    // ─── Job payload scanning ───────────────────────────────────────

    public function test_job_constructors_accept_only_identifiers(): void
    {
        $jobsDir = base_path('app/Modules/VenueMarketplace/Application/Jobs');
        if (! is_dir($jobsDir)) {
            self::markTestSkipped('Jobs directory not found.');
        }

        foreach ($this->phpFilesIn($jobsDir) as $file) {
            $source = file_get_contents($file);

            foreach (['opaque_reference', 'binding_metadata', 'pairing_secret', 'business_contact'] as $secret) {
                self::assertStringNotContainsString(
                    $secret,
                    $source,
                    basename($file)." job must not carry {$secret} in its payload.",
                );
            }
        }
    }

    // ─── CSV export scanning ────────────────────────────────────────

    public function test_csv_export_does_not_include_binding_or_private_contacts(): void
    {
        $exportFile = base_path('app/Modules/VenueMarketplace/Application/Exports/StreamSettlementStatementCsv.php');
        if (! file_exists($exportFile)) {
            self::markTestSkipped('CSV export file not found.');
        }

        $source = file_get_contents($exportFile);

        self::assertStringNotContainsString('opaque_reference', $source, 'CSV must not export opaque_reference.');
        self::assertStringNotContainsString('binding_metadata', $source, 'CSV must not export binding_metadata.');
        self::assertStringNotContainsString('business_contact_email', $source, 'CSV must not export business_contact_email.');
        self::assertStringNotContainsString('business_contact_phone', $source, 'CSV must not export business_contact_phone.');
    }

    public function test_csv_export_applies_formula_injection_escaping(): void
    {
        $exportFile = base_path('app/Modules/VenueMarketplace/Application/Exports/StreamSettlementStatementCsv.php');
        if (! file_exists($exportFile)) {
            self::markTestSkipped('CSV export file not found.');
        }

        $source = file_get_contents($exportFile);

        self::assertStringContainsString('escapeFormula', $source, 'CSV export must apply formula injection escaping.');
        self::assertMatchesRegularExpression(
            '/preg_match.*[=+@\\\\-]/',
            $source,
            'Formula escaping must guard against =, +, -, @ prefixes.',
        );
    }

    // ─── Database column scanning ───────────────────────────────────

    public function test_settlement_and_dispute_models_do_not_expose_binding_fields(): void
    {
        $modelsDir = base_path('app/Modules/VenueMarketplace/Infrastructure/Persistence/Models');
        $targetModels = [
            'SettlementStatement.php',
            'SettlementStatementLine.php',
            'MarketplaceDispute.php',
            'MarketplaceDisputeEvent.php',
        ];

        foreach ($targetModels as $modelFile) {
            $path = "{$modelsDir}/{$modelFile}";
            if (! file_exists($path)) {
                continue;
            }

            $source = file_get_contents($path);

            self::assertStringNotContainsString('opaque_reference', $source, "{$modelFile} must not reference opaque_reference.");
            self::assertStringNotContainsString('binding_metadata', $source, "{$modelFile} must not reference binding_metadata.");
            self::assertStringNotContainsString('pairing_secret', $source, "{$modelFile} must not reference pairing_secret.");
        }
    }

    // ─── Serialized event scanning ──────────────────────────────────

    public function test_domain_events_carry_no_secret_fields(): void
    {
        $eventsDir = base_path('app/Modules/VenueMarketplace/Domain/Events');
        if (! is_dir($eventsDir)) {
            self::markTestSkipped('Domain events directory not found.');
        }

        foreach ($this->phpFilesIn($eventsDir) as $file) {
            $source = file_get_contents($file);

            foreach ([
                'opaque_reference', 'binding_metadata', 'pairing_secret',
                'business_contact_email', 'business_contact_phone',
                'access_token', 'credential', 'password',
            ] as $secret) {
                self::assertStringNotContainsString(
                    $secret,
                    $source,
                    basename($file)." domain event must not carry {$secret}.",
                );
            }
        }
    }

    // ─── Cross-cutting static scan ──────────────────────────────────

    public function test_no_secret_field_leaks_into_unauthorized_locations(): void
    {
        $violations = [];

        foreach (self::PHASE_6_SOURCE_DIRS as $dir) {
            $fullDir = base_path($dir);
            if (! is_dir($fullDir)) {
                continue;
            }

            foreach ($this->phpFilesIn($fullDir) as $file) {
                $basename = basename($file);
                if (in_array($basename, self::ALLOWED_SECRET_LOCATIONS, true)) {
                    continue;
                }

                $source = file_get_contents($file);
                foreach (self::SECRET_FIELD_PATTERNS as $pattern => $label) {
                    if (preg_match($pattern, $source)) {
                        $violations[] = "{$basename}: {$label}";
                    }
                }
            }
        }

        self::assertEmpty(
            $violations,
            "Secret fields found in unauthorized locations:\n".implode("\n", $violations),
        );
    }

    public function test_inertia_view_models_exclude_secrets(): void
    {
        $viewModelsDir = base_path('app/Modules/AdminConsole/ViewModels/Marketplace');
        if (! is_dir($viewModelsDir)) {
            self::markTestSkipped('Marketplace view models directory not found.');
        }

        foreach ($this->phpFilesIn($viewModelsDir) as $file) {
            $source = file_get_contents($file);

            foreach ([
                'opaque_reference', 'binding_metadata', 'pairing_secret',
                'business_contact_email', 'business_contact_phone',
            ] as $secret) {
                self::assertStringNotContainsString(
                    $secret,
                    $source,
                    basename($file)." Inertia view model must not expose {$secret}.",
                );
            }
        }
    }

    // ─── Canary seeded secrets assertion ────────────────────────────

    public function test_canary_secrets_pattern_is_excluded_from_public_surface(): void
    {
        $canaryPatterns = [
            'private-fixture@example.test',
            'opaque:turnstile:',
            'opaque:scanner:',
            'opaque:kiosk:',
            'opaque:printer:',
        ];

        $publicSurfaceDirs = [
            'app/Modules/VenueMarketplace/Http/Resources',
            'app/Modules/VenueMarketplace/Application/Exports',
            'app/Modules/VenueMarketplace/Domain/Events',
        ];

        $violations = [];
        foreach ($publicSurfaceDirs as $dir) {
            $fullDir = base_path($dir);
            if (! is_dir($fullDir)) {
                continue;
            }

            foreach ($this->phpFilesIn($fullDir) as $file) {
                $source = file_get_contents($file);
                foreach ($canaryPatterns as $canary) {
                    if (str_contains($source, $canary)) {
                        $violations[] = basename($file)." contains canary '{$canary}'";
                    }
                }
            }
        }

        self::assertEmpty($violations, "Canary secrets leak into public surface:\n".implode("\n", $violations));
    }

    /** @return list<string> */
    private function phpFilesIn(string $directory): array
    {
        $files = glob("{$directory}/*.php");

        return $files === false ? [] : $files;
    }
}
