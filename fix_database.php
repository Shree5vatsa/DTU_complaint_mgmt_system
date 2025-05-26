<?php
require_once 'includes/config.php';

try {
    // 1. Fix the date_created column
    $pdo->exec("ALTER TABLE `user` MODIFY `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP");
    
    // 2. Update admin user if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user WHERE email = ?");
    $stmt->execute(['admin@dtu.ac.in']);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        // Admin doesn't exist, create it
        $password = 'admin123';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("INSERT INTO user (name, email, password, role_id, status, date_created) 
                              VALUES ('Administrator', 'admin@dtu.ac.in', ?, 1, 'active', CURRENT_TIMESTAMP)");
        $stmt->execute([$hash]);
        echo "Admin user created successfully!\n";
    } else {
        // Admin exists, update password
        $password = 'admin123';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE email = ?");
        $stmt->execute([$hash, 'admin@dtu.ac.in']);
        echo "Admin password updated successfully!\n";
    }
    
    echo "\nDatabase fixes applied successfully!\n";
    echo "You can now login with:\n";
    echo "Email: admin@dtu.ac.in\n";
    echo "Password: admin123\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 