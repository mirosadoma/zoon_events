<?php
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

// Replicate ALL phpunit.xml env vars
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

echo "DB: " . config('database.connections.mysql.database') . "\n";

$tables = Illuminate\Support\Facades\DB::select("SHOW TABLES");
echo "Tables before migrate: " . count($tables) . "\n";
foreach ($tables as $t) {
    $val = (array) $t;
    echo "  - " . array_values($val)[0] . "\n";
}

$exitCode = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo "migrate exit code: {$exitCode}\n";
echo Illuminate\Support\Facades\Artisan::output();
