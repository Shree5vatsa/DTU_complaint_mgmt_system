<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=dtu_portal",
        "root",
        "",
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
    echo "Database connection successful!\n";
    
    // Test if we can query the user table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user");
    $result = $stmt->fetch();
    echo "Number of users in database: " . $result['count'] . "\n";
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    
    // Additional debug information
    echo "\nDebug information:\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "PDO Drivers: " . implode(", ", PDO::getAvailableDrivers()) . "\n";
} 