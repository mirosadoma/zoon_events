<?php
// Check tables BEFORE and AFTER Laravel bootstrap

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=zonetec_testing', 'root', '');
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "BEFORE bootstrap: " . count($tables) . " tables " . (empty($tables) ? "(none)" : implode(', ', $tables)) . "\n";

// Set testing env vars like phpunit.xml does
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');
putenv('DB_TESTING_HOST=127.0.0.1');
putenv('DB_TESTING_PORT=3306');
putenv('DB_TESTING_DATABASE=zonetec_testing');
putenv('DB_TESTING_USERNAME=root');
putenv('DB_TESTING_PASSWORD=');
putenv('CACHE_STORE=array');
putenv('SESSION_DRIVER=array');
putenv('QUEUE_CONNECTION=database');
putenv('TELESCOPE_ENABLED=false');

require __DIR__ . '/vendor/autoload.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "AFTER autoload: " . count($tables) . " tables " . (empty($tables) ? "(none)" : implode(', ', $tables)) . "\n";

$app = require __DIR__ . '/bootstrap/app.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "AFTER app.php: " . count($tables) . " tables " . (empty($tables) ? "(none)" : implode(', ', $tables)) . "\n";

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "AFTER make kernel: " . count($tables) . " tables " . (empty($tables) ? "(none)" : implode(', ', $tables)) . "\n";

$kernel->bootstrap();

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "AFTER bootstrap: " . count($tables) . " tables " . (empty($tables) ? "(none)" : implode(', ', $tables)) . "\n";
