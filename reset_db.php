<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
$pdo->exec('DROP DATABASE IF EXISTS zonetec_testing');
$pdo->exec('CREATE DATABASE zonetec_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo "Database recreated successfully\n";
