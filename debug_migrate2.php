<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=zonetec_testing', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "MySQL version: " . $pdo->query("SELECT VERSION()")->fetchColumn() . "\n";

// Check SHOW TABLES
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "SHOW TABLES: " . (empty($tables) ? "(none)" : implode(', ', $tables)) . "\n";

// Check information_schema
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = 'zonetec_testing'");
$is_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "information_schema tables: " . (empty($is_tables) ? "(none)" : implode(', ', $is_tables)) . "\n";

// Check InnoDB tablespace files  
$stmt = $pdo->query("SELECT FILE_NAME FROM information_schema.FILES WHERE TABLESPACE_NAME LIKE 'zonetec_testing%' LIMIT 20");
$files = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "InnoDB files: " . (empty($files) ? "(none)" : implode(', ', $files)) . "\n";

// Try CREATE IF NOT EXISTS
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, batch INT NOT NULL)");
    echo "CREATE IF NOT EXISTS: succeeded\n";
} catch (Exception $e) {
    echo "CREATE IF NOT EXISTS failed: " . $e->getMessage() . "\n";
}

// Now check if it exists
$check = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA = 'zonetec_testing' AND TABLE_NAME = 'migrations'")->fetchColumn();
echo "migrations exists in IS: {$check}\n";

// Try inserting
try {
    $pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('test', 1)");
    echo "INSERT succeeded\n";
} catch (Exception $e) {
    echo "INSERT failed: " . $e->getMessage() . "\n";
}
