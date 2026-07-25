<?php

namespace Tests\Unit\Shared;

use App\Modules\Credentials\Application\Signing\EnvironmentSecretReferenceLoader;
use App\Modules\Shared\Support\Environment\EnvironmentValue;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EnvironmentValueTest extends TestCase
{
    public function test_prefers_config_secrets_over_env(): void
    {
        config(['secrets.DEMO_SECRET' => 'from-config']);

        self::assertSame('from-config', EnvironmentValue::get('DEMO_SECRET'));
    }

    public function test_falls_back_to_env_when_config_missing(): void
    {
        config(['secrets' => []]);

        self::assertSame(
            (string) env('APP_NAME'),
            (string) EnvironmentValue::get('APP_NAME'),
        );
    }

    public function test_credential_secret_loader_works_after_config_cache(): void
    {
        $expected = (string) env('CREDENTIAL_TEST_PRIVATE_KEY');
        self::assertNotSame('', $expected, 'CREDENTIAL_TEST_PRIVATE_KEY must be set in the test environment.');

        Artisan::call('config:cache');

        try {
            self::assertSame($expected, config('secrets.CREDENTIAL_TEST_PRIVATE_KEY'));
            self::assertSame($expected, EnvironmentValue::get('CREDENTIAL_TEST_PRIVATE_KEY'));
            self::assertSame($expected, (new EnvironmentSecretReferenceLoader)->load('CREDENTIAL_TEST_PRIVATE_KEY'));
            // APP_KEY must come from config after cache — never read via env() in app code.
            self::assertNotSame('', (string) config('app.key'));
        } finally {
            Artisan::call('config:clear');
        }
    }
}
