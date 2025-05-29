<?php
require_once 'includes/db_config.php';

try {
    $stmt = $pdo->prepare("
        UPDATE user 
        SET failed_attempts = 0,
            last_failed_attempt = NULL
        WHERE email = ?
    ");
    
    $stmt->execute(['admin@dtu.ac.in']);
    
    echo "Login attempts reset successfully for admin@dtu.ac.in\n";
    echo "You can now try to login with:\n";
    echo "Email: admin@dtu.ac.in\n";
    echo "Password: admin123\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 