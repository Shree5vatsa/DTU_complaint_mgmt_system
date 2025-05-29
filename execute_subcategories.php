<?php
require_once 'includes/db_config.php';

try {
    // Read the SQL file
    $sql = file_get_contents('add_subcategories.sql');
    
    // Execute the SQL commands
    $pdo->exec($sql);
    
    echo "Successfully added subcategories!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 