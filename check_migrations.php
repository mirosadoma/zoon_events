<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=zonetec_testing', 'root', '');
$stmt = $pdo->query("SELECT migration FROM migrations WHERE migration LIKE '%organization_type%' OR migration LIKE '%2026_07_14%' ORDER BY id");
echo "Marketplace-era migrations recorded:\n";
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $m) {
    echo "  - {$m}\n";
}

// Check total count
$count = $pdo->query("SELECT COUNT(*) FROM migrations")->fetchColumn();
echo "\nTotal migrations recorded: {$count}\n";

// Check if venues table exists  
$venues = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA = 'zonetec_testing' AND TABLE_NAME = 'venues'")->fetchColumn();
echo "venues table exists: " . ($venues ? 'YES' : 'NO') . "\n";

$rental = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA = 'zonetec_testing' AND TABLE_NAME = 'rental_requests'")->fetchColumn();
echo "rental_requests table exists: " . ($rental ? 'YES' : 'NO') . "\n";
