<?php

// One-shot schema bootstrap for local testing DB.
$source = 'zonetec';
$target = 'zoon_test_v2';

$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$existingCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ".$pdo->quote($target)
)->fetchColumn();

if ($existingCount >= 100) {
    echo "Already healthy ({$existingCount} tables)\n";
    exit(0);
}

echo "Bootstrapping {$target} from {$source} (had {$existingCount} tables)\n";
$pdo->exec("DROP DATABASE IF EXISTS `{$target}`");
$pdo->exec("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$tables = $pdo->query("SHOW TABLES FROM `{$source}`")->fetchAll(PDO::FETCH_COLUMN);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $i => $table) {
    $pdo->exec("CREATE TABLE `{$target}`.`{$table}` LIKE `{$source}`.`{$table}`");
    if (($i + 1) % 20 === 0) {
        echo ($i + 1).'/'.count($tables)."\n";
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec("INSERT INTO `{$target}`.`migrations` (`id`, `migration`, `batch`) SELECT `id`, `migration`, `batch` FROM `{$source}`.`migrations`");

$cols = $pdo->query("SHOW COLUMNS FROM `{$target}`.`tenants` LIKE 'is_active'")->fetchAll();
if (count($cols) === 0) {
    $pdo->exec("ALTER TABLE `{$target}`.`tenants` ADD `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`");
    $pdo->exec("INSERT INTO `{$target}`.`migrations` (`migration`, `batch`) VALUES ('2026_08_09_000001_add_is_active_to_tenants_table', 999)");
}

$phoneCols = $pdo->query("SHOW COLUMNS FROM `{$target}`.`users` LIKE 'phone'")->fetchAll();
if (count($phoneCols) === 0) {
    $pdo->exec("ALTER TABLE `{$target}`.`users` ADD `phone` VARCHAR(30) NULL AFTER `email`");
    $pdo->exec("INSERT INTO `{$target}`.`migrations` (`migration`, `batch`) VALUES ('2026_08_09_000002_add_phone_to_users_table', 999)");
}

echo 'Done tables='.count($tables)."\n";