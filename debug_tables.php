<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=zonetec_testing', 'root', '');
echo "Tables in zonetec_testing:\n";
foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    echo "  - {$t}\n";
}
echo "\nAll databases:\n";
foreach ($pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN) as $d) {
    echo "  - {$d}";
    if ($d === 'zonetec_testing' || $d === 'zonetec') {
        $count = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA = '{$d}'")->fetchColumn();
        echo " ({$count} tables)";
    }
    echo "\n";
}
