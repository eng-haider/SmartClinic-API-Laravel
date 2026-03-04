<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=min_new;charset=utf8mb4', 'root', '');
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables in min_new:\n";
echo implode("\n", $tables) . "\n";
