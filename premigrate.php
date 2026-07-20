<?php
// Pre-migrate the testing database by bootstrapping Laravel in testing mode
// and running migrations

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

echo "DB connection database: " . config('database.connections.mysql.database') . "\n";
echo "APP_ENV: " . config('app.env') . "\n";

// First, drop all tables
$exitCode = Illuminate\Support\Facades\Artisan::call('db:wipe', ['--force' => true]);
echo "db:wipe exit code: {$exitCode}\n";

// Sleep a bit to let InnoDB settle
sleep(2);

// Then migrate
$exitCode = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo "migrate exit code: {$exitCode}\n";
echo Illuminate\Support\Facades\Artisan::output();
