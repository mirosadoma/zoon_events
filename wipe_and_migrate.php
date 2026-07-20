<?php
/**
 * Wipes all tables (without DROP DATABASE) then migrates.
 * Avoids MySQL 8.4 InnoDB dictionary corruption from DROP/CREATE DATABASE.
 */
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

putenv('APP_ENV=testing');
putenv('APP_MAINTENANCE_DRIVER=file');
putenv('BCRYPT_ROUNDS=4');
putenv('CACHE_STORE=array');
putenv('DB_CONNECTION=mysql');
putenv('DB_TESTING_HOST=127.0.0.1');
putenv('DB_TESTING_PORT=3306');
putenv('DB_TESTING_DATABASE=zonetec_testing');
putenv('DB_TESTING_USERNAME=root');
putenv('DB_TESTING_PASSWORD=');
putenv('MAIL_MAILER=array');
putenv('QUEUE_CONNECTION=database');
putenv('SESSION_DRIVER=array');
putenv('TELESCOPE_ENABLED=false');

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = config('database.connections.mysql.database');
echo "DB: {$db}\n";

// Drop all tables without dropping the database
Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS = 0');
$tables = Illuminate\Support\Facades\DB::select("SHOW TABLES");
echo "Dropping " . count($tables) . " tables...\n";
foreach ($tables as $t) {
    $name = array_values((array) $t)[0];
    Illuminate\Support\Facades\DB::statement("DROP TABLE IF EXISTS `{$name}`");
}
Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS = 1');

$tablesAfter = Illuminate\Support\Facades\DB::select("SHOW TABLES");
echo "Tables after wipe: " . count($tablesAfter) . "\n";

// Run migrations
$exitCode = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo "migrate exit code: {$exitCode}\n";
echo Illuminate\Support\Facades\Artisan::output();
