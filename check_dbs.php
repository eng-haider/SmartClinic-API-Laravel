<?php
$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '');
$dbs = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
echo "All databases:\n";
foreach ($dbs as $db) {
    if (in_array($db, ['information_schema','performance_schema','mysql','sys'])) continue;
    $pdo2 = new PDO("mysql:host=127.0.0.1;dbname={$db};charset=utf8mb4", 'root', '');
    $tables = $pdo2->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "  [{$db}] " . count($tables) . " tables: " . implode(', ', array_slice($tables, 0, 10));
    if (count($tables) > 10) echo ' ...';
    echo "\n";
}
