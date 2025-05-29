<?php
require_once 'includes/db_config.php';

echo "Checking user accounts:\n";
try {
    // First check raw user table
    echo "\nRaw user table contents:\n";
    $users = $pdo->query("SELECT * FROM user")->fetchAll();
    print_r($users);
    
    echo "\n\nDetailed user info with roles:\n";
    $users = $pdo->query("
        SELECT 
            u.*, 
            r.role_name,
            r.role_level
        FROM user u 
        LEFT JOIN roles r ON u.role_id = r.id
    ")->fetchAll();
    
    foreach ($users as $user) {
        echo "\nUser: {$user['name']}\n";
        echo "Email: {$user['email']}\n";
        echo "Role: " . ($user['role_name'] ?? 'No role') . "\n";
        echo "Status: {$user['status']}\n";
        echo "------------------------\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 