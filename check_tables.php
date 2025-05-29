<?php
require_once 'includes/db_config.php';

echo "Checking database tables:\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

echo "\nChecking roles table:\n";
try {
    $roles = $pdo->query("SELECT * FROM roles")->fetchAll();
    print_r($roles);
} catch (PDOException $e) {
    echo "Error with roles table: " . $e->getMessage() . "\n";
} 