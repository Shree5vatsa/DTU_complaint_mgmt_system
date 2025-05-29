<?php
require_once 'includes/db_config.php';

try {
    // Create admin user
    $stmt = $pdo->prepare("
        INSERT INTO user (
            name, email, password, role_id, status
        ) VALUES (
            'Admin User',
            'admin@dtu.ac.in',
            ?,
            (SELECT id FROM roles WHERE role_name = 'Administrator'),
            'active'
        )
    ");
    
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $stmt->execute([$hashed_password]);
    
    echo "Admin user created successfully!\n";
    echo "Email: admin@dtu.ac.in\n";
    echo "Password: admin123\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 