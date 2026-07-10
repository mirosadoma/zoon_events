<?php

namespace Tests\Integration\Security;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-3')]
#[Group('phase-3-isolation')]
final class Phase3ModuleBoundaryQueryTest extends TestCase
{
    private const PROTECTED_CLASSES = [
        'App\\Modules\\Attendees\\Infrastructure\\Persistence\\Models\\Attendee',
        'App\\Modules\\Credentials\\Infrastructure\\Persistence\\Models\\Credential',
        'App\\Modules\\Scanning\\Infrastructure\\Persistence\\Models\\ScanEvent',
    ];

    private const ALLOWED_MODULES = [
        'Attendees',
        'Credentials',
        'Scanning',
        'Audit',
        'Shared',
        'Tenancy',
        'Identity',
    ];

    public function test_kiosk_controllers_do_not_reference_protected_models_directly(): void
    {
        $violations = [];
        $kioskPath = app_path('Modules/Kiosk/Http/Controllers');

        if (! is_dir($kioskPath)) {
            $this->markTestSkipped('No kiosk controllers exist yet.');
        }

        $this->checkForDirectModelReferences($kioskPath, $violations);

        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function test_badge_printing_controllers_do_not_reference_protected_models_directly(): void
    {
        $violations = [];
        $badgePath = app_path('Modules/BadgePrinting/Http/Controllers');

        if (! is_dir($badgePath)) {
            $this->markTestSkipped('No badge printing controllers exist yet.');
        }

        $this->checkForDirectModelReferences($badgePath, $violations);

        self::assertSame([], $violations, implode("\n", $violations));
    }

    /** @param list<string> $violations */
    private function checkForDirectModelReferences(string $directory, array &$violations): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';

            foreach (self::PROTECTED_CLASSES as $class) {
                if (str_contains($contents, $class)) {
                    $violations[] = $file->getPathname().' directly references '.$class;
                }
            }
        }
    }
}
