<?php

namespace Tests\Integration\MySql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MySqlTestCase;

class FrameworkConnectionTest extends MySqlTestCase
{
    #[Test]
    public function mysql_transaction_queries_can_run_against_the_testing_database(): void
    {
        $this->assertMySqlConnectionIsAvailable();
    }
}
