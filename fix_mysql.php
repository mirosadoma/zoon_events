<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Drop database completely
$pdo->exec('DROP DATABASE IF EXISTS zonetec_testing');
echo "Dropped database\n";

// Flush tables to clear InnoDB dictionary cache
$pdo->exec('FLUSH TABLES');
echo "Flushed tables\n";

// Wait a moment for InnoDB to settle
sleep(2);

// Recreate
$pdo->exec('CREATE DATABASE zonetec_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo "Created database\n";

// Verify it's truly empty
$pdo2 = new PDO('mysql:host=127.0.0.1;port=3306;dbname=zonetec_testing', 'root', '');
$pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = $pdo2->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . (empty($tables) ? "(none)" : implode(', ', $tables)) . "\n";

// Try creating migrations table without IF NOT EXISTS
try {
    $pdo2->exec("CREATE TABLE migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, batch INT NOT NULL)");
    echo "CREATE TABLE migrations: OK\n";
} catch (Exception $e) {
    echo "CREATE TABLE migrations FAILED: " . $e->getMessage() . "\n";
}

// Clean up test table
$pdo2->exec("DROP TABLE IF EXISTS migrations");
echo "\nDatabase ready for testing\n";
