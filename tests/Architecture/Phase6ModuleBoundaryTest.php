<?php

namespace Tests\Architecture;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\Authorization\Application\Contracts\DelegatedControlGuard;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\Events\Application\Contracts\MarketplaceEventReader;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-6')]
#[Group('phase-6-isolation')]
final class Phase6ModuleBoundaryTest extends TestCase
{
    /** @var list<string> */
    private array $operationalModules = [
        'AccessControl',
        'Kiosk',
        'BadgePrinting',
        'Scanning',
        'Events',
        'Tenancy',
        'Authorization',
    ];

    private const PORT_OWNERS = [
        OrganizationEligibility::class => 'App\\Modules\\Tenancy\\',
        MarketplaceEventReader::class => 'App\\Modules\\Events\\',
        DelegatedControlGuard::class => 'App\\Modules\\Authorization\\',
        DelegatedAcsAssetPort::class => 'App\\Modules\\AccessControl\\',
        DelegatedKioskAssetPort::class => 'App\\Modules\\Kiosk\\',
        DelegatedPrinterAssetPort::class => 'App\\Modules\\BadgePrinting\\',
        DelegatedScannerAssetPort::class => 'App\\Modules\\Scanning\\',
        MarketplaceAuditWriter::class => 'App\\Modules\\VenueMarketplace\\',
    ];

    public function test_phase_six_ports_are_owned_by_the_contracting_module(): void
    {
        foreach (self::PORT_OWNERS as $contract => $namespacePrefix) {
            self::assertTrue(interface_exists($contract), "Missing port {$contract}");
            self::assertStringStartsWith($namespacePrefix, $contract);
        }
    }

    public function test_boundary_mutation_fixtures_are_detected(): void
    {
        $fixtures = [
            ['VenueMarketplace', 'use App\\Modules\\Events\\Infrastructure\\Persistence\\Models\\Event;', 'operational infrastructure'],
            ['Events', 'use App\\Modules\\VenueMarketplace\\Infrastructure\\Persistence\\Models\\Rental;', 'marketplace persistence'],
            ['VenueMarketplace', 'use App\\Modules\\Payments\\Contracts\\PaymentGateway;', 'payments'],
            ['VenueMarketplace', 'use App\\Modules\\Federation\\Contracts\\RemoteCatalog;', 'federation'],
        ];

        foreach ($fixtures as [$module, $contents, $expected]) {
            self::assertContains($expected, $this->boundaryViolations($module, $contents));
        }
    }

    public function test_marketplace_has_no_payments_or_federation_dependency(): void
    {
        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            app_path('Modules/VenueMarketplace'),
            \FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname()) ?: '';
            $filtered = $this->stripAllowedTenancyConcerns($contents);
            foreach ($this->boundaryViolations('VenueMarketplace', $filtered) as $violation) {
                $violations[] = $file->getPathname().': '.$violation;
            }
        }

        self::assertSame([], $violations);
    }

    public function test_venue_marketplace_does_not_import_operational_infrastructure(): void
    {
        $root = app_path('Modules/VenueMarketplace');

        if (! is_dir($root)) {
            self::fail('Missing module directory: '.$root);
        }

        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = $this->stripAllowedTenancyConcerns(file_get_contents($file->getPathname()) ?: '');

            foreach ($this->operationalModules as $module) {
                if (preg_match('/use App\\\\Modules\\\\'.$module.'\\\\Infrastructure\\\\/', $contents) === 1) {
                    $violations[] = $file->getPathname().' imports '.$module.' Infrastructure';
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function test_operational_modules_do_not_import_venue_marketplace_infrastructure_models(): void
    {
        $violations = [];
        $root = app_path('Modules');

        if (! is_dir($root)) {
            self::fail('Missing module directory: '.$root);
        }

        $skipModules = ['VenueMarketplace', 'AdminConsole', 'Operations'];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            foreach ($skipModules as $skipModule) {
                if (str_contains($path, "/app/Modules/{$skipModule}/")) {
                    continue 2;
                }
            }

            $contents = file_get_contents($file->getPathname()) ?: '';

            if (preg_match('/use App\\\\Modules\\\\VenueMarketplace\\\\Infrastructure\\\\Persistence\\\\Models\\\\/', $contents) === 1) {
                $violations[] = $file->getPathname();
            }
        }

        self::assertSame([], $violations);
    }

    public function test_venue_marketplace_application_and_domain_layers_do_not_import_transport_clients(): void
    {
        $forbidden = ['GuzzleHttp', 'Http::', 'fsockopen', 'MqttClient'];
        $roots = [
            app_path('Modules/VenueMarketplace/Application'),
            app_path('Modules/VenueMarketplace/Domain'),
        ];
        $violations = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname()) ?: '';
                foreach ($forbidden as $needle) {
                    if (str_contains($contents, $needle)) {
                        $violations[] = "{$file->getPathname()} contains {$needle}";
                    }
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function test_no_direct_operational_model_imports(): void
    {
        $root = app_path('Modules/VenueMarketplace');
        if (! is_dir($root)) {
            self::fail('Missing module directory: '.$root);
        }

        $forbiddenModels = ['AccessControl', 'Kiosk', 'BadgePrinting', 'Scanning'];
        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = $this->stripAllowedTenancyConcerns(file_get_contents($file->getPathname()) ?: '');

            foreach ($forbiddenModels as $module) {
                if (preg_match('/use App\\\\Modules\\\\'.$module.'\\\\Infrastructure\\\\Persistence\\\\Models\\\\/', $contents) === 1) {
                    $violations[] = $file->getPathname().' imports '.$module.' model directly';
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function test_no_reverse_marketplace_model_imports(): void
    {
        $reverseModules = ['AccessControl', 'Kiosk', 'BadgePrinting', 'Scanning', 'Events', 'Registration'];
        $violations = [];
        $root = app_path('Modules');

        if (! is_dir($root)) {
            self::fail('Missing module directory: '.$root);
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            $inScope = false;
            foreach ($reverseModules as $module) {
                if (str_contains($path, "/app/Modules/{$module}/") || str_contains($path, "\\app\\Modules\\{$module}\\")) {
                    $inScope = true;
                    break;
                }
            }
            if (! $inScope) {
                continue;
            }

            $basename = basename($path);
            if (preg_match('/^Database?Delegated\w+Port\.php$/', $basename) === 1) {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';

            if (preg_match('/use App\\\\Modules\\\\VenueMarketplace\\\\Infrastructure\\\\Persistence\\\\Models\\\\/', $contents) === 1) {
                $violations[] = $file->getPathname();
            }
        }

        self::assertSame([], $violations);
    }

    public function test_no_secret_shaped_fields(): void
    {
        $root = app_path('Modules/VenueMarketplace');
        if (! is_dir($root)) {
            self::fail('Missing module directory: '.$root);
        }

        $pattern = '/\b(password|secret|credential|api_key|private_key|pairing_secret)\b/i';
        $tokenPattern = '/\btoken\b/i';
        $allowedTokens = ['presentation_token', 'idempotency_token', 'csrf_token'];

        $securityEnforcementFiles = [
            'MarketplaceAuditEvent.php',
            'VenueAssetBindingPolicy.php',
            'CreateVenueAssetRequest.php',
            'UpdateVenueAssetRequest.php',
            'VenueAssetBinding.php',
        ];

        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (in_array(basename($file->getPathname()), $securityEnforcementFiles, true)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';

            if (preg_match($pattern, $contents, $m) === 1) {
                $violations[] = $file->getPathname().' contains secret-shaped field: '.$m[1];
            }

            if (preg_match($tokenPattern, $contents) === 1) {
                $stripped = $contents;
                foreach ($allowedTokens as $allowed) {
                    $stripped = str_ireplace($allowed, '', $stripped);
                }
                if (preg_match($tokenPattern, $stripped) === 1) {
                    $violations[] = $file->getPathname().' contains secret-shaped field: token';
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function test_no_remote_federation_clients(): void
    {
        $root = app_path('Modules/VenueMarketplace');
        if (! is_dir($root)) {
            self::fail('Missing module directory: '.$root);
        }

        $forbidden = [
            'Http::' => 'Http facade',
            'GuzzleHttp' => 'Guzzle client',
            'curl_' => 'cURL function',
            "file_get_contents('http" => 'remote file_get_contents',
            'file_get_contents("http' => 'remote file_get_contents',
            "fopen('http" => 'remote fopen',
            'fopen("http' => 'remote fopen',
            'RemoteApi' => 'remote API client',
            'Federation' => 'federation reference',
            'CrossDeployment' => 'cross-deployment reference',
        ];

        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';

            foreach ($forbidden as $needle => $label) {
                if (str_contains($contents, $needle)) {
                    $violations[] = "{$file->getPathname()} contains {$label} ({$needle})";
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function test_no_payment_module_references(): void
    {
        $root = app_path('Modules/VenueMarketplace');
        if (! is_dir($root)) {
            self::fail('Missing module directory: '.$root);
        }

        $pattern = '/\b(Payment|Payout|Refund|Stripe|PayPal|funds_moved|charge|invoice)\b/i';

        $fundsMovedAllowlist = [
            'ParticipantStatementResource.php',
        ];

        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';

            if (preg_match($pattern, $contents, $m) === 1) {
                $basename = basename($file->getPathname());
                if (strtolower($m[1]) === 'funds_moved' && in_array($basename, $fundsMovedAllowlist, true)) {
                    continue;
                }
                $violations[] = $file->getPathname().' references payment concept: '.$m[1];
            }
        }

        self::assertSame([], $violations);
    }

    public function test_no_unapproved_filesystem_exports(): void
    {
        $root = app_path('Modules/VenueMarketplace');
        if (! is_dir($root)) {
            self::fail('Missing module directory: '.$root);
        }

        $forbidden = ['Storage::', 'file_put_contents', 'tmpfile', 'tempnam', 'sys_get_temp_dir'];
        $streamExporter = 'StreamSettlementStatementCsv.php';
        $streamOnlyForbidden = ['fwrite', 'fopen'];

        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $basename = basename($file->getPathname());
            $contents = file_get_contents($file->getPathname()) ?: '';

            foreach ($forbidden as $needle) {
                if (str_contains($contents, $needle)) {
                    $violations[] = "{$file->getPathname()} uses filesystem: {$needle}";
                }
            }

            if ($basename !== $streamExporter) {
                foreach ($streamOnlyForbidden as $needle) {
                    if (str_contains($contents, $needle)) {
                        $violations[] = "{$file->getPathname()} uses filesystem: {$needle}";
                    }
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function test_controllers_have_no_business_logic(): void
    {
        $root = app_path('Modules/VenueMarketplace/Http/Controllers');
        if (! is_dir($root)) {
            self::fail('Missing controller directory: '.$root);
        }

        $forbiddenStrings = ['DB::', 'Schema::', '::where(', '::find(', '::create('];
        $loopPattern = '/\b(for|while|foreach)\s*\(/';

        $loopAllowlist = [
            'ParticipantRentalController.php',
        ];

        $violations = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $basename = basename($file->getPathname());
            $contents = file_get_contents($file->getPathname()) ?: '';

            foreach ($forbiddenStrings as $needle) {
                if (str_contains($contents, $needle)) {
                    $violations[] = "{$file->getPathname()} contains {$needle}";
                }
            }

            if (! in_array($basename, $loopAllowlist, true)
                && preg_match($loopPattern, $contents) === 1) {
                $violations[] = "{$file->getPathname()} contains loop logic";
            }
        }

        self::assertSame([], $violations);
    }

    /**
     * BelongsToTenant / TenantOwned are cross-cutting tenant-scoping concerns
     * that all multi-tenant models legitimately import.
     */
    private function stripAllowedTenancyConcerns(string $contents): string
    {
        return preg_replace(
            '/use App\\\\Modules\\\\Tenancy\\\\Infrastructure\\\\Persistence\\\\(?:Concerns|Contracts)\\\\[A-Za-z]+;/',
            '',
            $contents,
        ) ?? $contents;
    }

    /** @return list<string> */
    private function boundaryViolations(string $module, string $contents): array
    {
        $violations = [];

        if ($module === 'VenueMarketplace'
            && preg_match('/App\\\\Modules\\\\(?:AccessControl|Kiosk|BadgePrinting|Scanning|Events|Tenancy|Authorization)\\\\Infrastructure\\\\/', $contents)) {
            $violations[] = 'operational infrastructure';
        }
        if ($module !== 'VenueMarketplace'
            && str_contains($contents, 'App\\Modules\\VenueMarketplace\\Infrastructure\\Persistence\\Models\\')) {
            $violations[] = 'marketplace persistence';
        }
        if ($module === 'VenueMarketplace' && str_contains($contents, 'App\\Modules\\Payments\\')) {
            $violations[] = 'payments';
        }
        if ($module === 'VenueMarketplace' && str_contains($contents, 'App\\Modules\\Federation\\')) {
            $violations[] = 'federation';
        }

        return $violations;
    }
}
