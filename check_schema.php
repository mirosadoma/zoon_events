<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=zonetec_testing', 'root', '');
$cols = $pdo->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
echo "Columns in tenants: " . implode(', ', $cols) . "\n";
echo "Has organization_type: " . (in_array('organization_type', $cols) ? 'YES' : 'NO') . "\n";
