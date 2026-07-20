<?php
// Debug migration issue: manually connect and test

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=zonetec_testing', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "MySQL version: " . $pdo->query("SELECT VERSION()")->fetchColumn() . "\n";
echo "Current database: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n";

// Show existing tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables before anything: " . (empty($tables) ? "(none)" : implode(', ', $tables)) . "\n";

// Create migrations table
$pdo->exec("CREATE TABLE migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, batch INT NOT NULL)");
echo "Created migrations table OK\n";

// Create sessions table (like framework_tables migration)
$pdo->exec("CREATE TABLE sessions (id VARCHAR(255) NOT NULL PRIMARY KEY, user_id BIGINT UNSIGNED NULL, ip_address VARCHAR(45) NULL, user_agent TEXT NULL, payload LONGTEXT NOT NULL, last_activity INT NOT NULL)");
echo "Created sessions table OK\n";

// Insert migration record
$pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2026_07_02_000000_create_framework_tables', 1)");
echo "Inserted first migration record OK\n";

// Check if migrations table still exists
$check = $pdo->query("SELECT COUNT(*) FROM migrations")->fetchColumn();
echo "Migrations table has {$check} rows\n";

// Show all tables 
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables now: " . implode(', ', $tables) . "\n";

// Try inserting second migration record
$pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2026_07_02_000001_create_users_table', 1)");
echo "Inserted second migration record OK\n";

echo "\nAll operations succeeded - no MySQL-level issue\n";
