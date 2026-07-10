<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class MySqlTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
    }

    protected function assertMySqlConnectionIsAvailable(): void
    {
        $result = DB::connection('mysql')->select('SELECT 1 AS value');

        self::assertSame(1, $result[0]->value);
    }
}
