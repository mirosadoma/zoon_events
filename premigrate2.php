<?php
// Pre-migrate the testing database by bootstrapping Laravel in testing mode
// and running only "migrate" (no db:wipe, since the database is already empty)

$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

putenv('APP_ENV=testing');
putenv('DB_TESTING_HOST=127.0.0.1');
putenv('DB_TESTING_PORT=3306');
putenv('DB_TESTING_DATABASE=zonetec_testing');
putenv('DB_TESTING_USERNAME=root');
putenv('DB_TESTING_PASSWORD=');

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "DB: " . config('database.connections.mysql.database') . "\n";

// Check tables first
$tables = Illuminate\Support\Facades\DB::select("SHOW TABLES");
echo "Tables before migrate: " . count($tables) . "\n";

// Just migrate (no fresh/wipe since DB is already empty from reset_db.php)
$exitCode = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo "migrate exit code: {$exitCode}\n";
echo Illuminate\Support\Facades\Artisan::output();

// Check tables after
$tables = Illuminate\Support\Facades\DB::select("SHOW TABLES");
echo "\nTables after migrate: " . count($tables) . "\n";
