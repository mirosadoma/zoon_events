<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class Phase1ModuleBoundaryTest extends TestCase
{
    public function test_phase_one_modules_do_not_import_another_modules_infrastructure(): void
    {
        $roots = ['Events', 'Registration', 'Ticketing', 'Orders', 'Payments', 'Attendees', 'Credentials', 'Notifications'];
        $violations = [];

        foreach ($roots as $owner) {
            $root = app_path("Modules/{$owner}");
            if (! is_dir($root)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $contents = file_get_contents($file->getPathname()) ?: '';
                if (preg_match('/use App\\\\Modules\\\\(?!'.$owner.'\\\\)[A-Za-z]+\\\\Infrastructure\\\\/', $contents)) {
                    $violations[] = $file->getPathname();
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function test_phase_two_namespaces_are_absent(): void
    {
        foreach (['Wallet', 'Scanning', 'CheckIn', 'Kiosk', 'Badges', 'ACS', 'IdentityVerification', 'Marketplace'] as $module) {
            self::assertDirectoryDoesNotExist(app_path("Modules/{$module}"));
        }
    }

    public function test_phase_one_modules_do_not_query_another_modules_tables_directly(): void
    {
        $owners = [
            'events' => 'Events', 'event_branding' => 'Events',
            'registration_forms' => 'Registration', 'registration_form_versions' => 'Registration',
            'registration_submissions' => 'Registration',
            'ticket_types' => 'Ticketing', 'ticket_inventories' => 'Ticketing',
            'inventory_holds' => 'Ticketing', 'price_tiers' => 'Ticketing',
            'orders' => 'Orders', 'order_items' => 'Orders',
            'payment_accounts' => 'Payments', 'payment_attempts' => 'Payments',
            'payment_webhook_receipts' => 'Payments', 'refunds' => 'Payments',
            'attendees' => 'Attendees', 'attendee_corrections' => 'Attendees',
            'credential_signing_keys' => 'Credentials', 'credentials' => 'Credentials',
            'notifications' => 'Notifications',
        ];
        $violations = [];
        foreach (array_unique($owners) as $module) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                app_path("Modules/{$module}"),
                \FilesystemIterator::SKIP_DOTS,
            ));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $contents = file_get_contents($file->getPathname()) ?: '';
                preg_match_all("/DB::table\\('([a-z_]+)'/", $contents, $matches);
                foreach ($matches[1] as $table) {
                    if (isset($owners[$table]) && $owners[$table] !== $module) {
                        $violations[] = "{$module} queries {$table} in {$file->getPathname()}";
                    }
                }
            }
        }

        self::assertSame([], $violations);
    }
}
