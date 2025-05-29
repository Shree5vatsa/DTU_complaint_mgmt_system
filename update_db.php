<?php
require_once 'includes/db_config.php';

try {
    // Add missing columns if they don't exist
    $pdo->exec("ALTER TABLE `user` 
                ADD COLUMN IF NOT EXISTS `failed_attempts` int(11) NOT NULL DEFAULT '0',
                ADD COLUMN IF NOT EXISTS `last_failed_attempt` int(11) DEFAULT NULL");
    
    // Update admin password to known good hash
    $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE email = ?");
    $stmt->execute([
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin@dtu.ac.in'
    ]);
    
    echo "Database updated successfully!\n";
    echo "\nYou can now log in with:\n";
    echo "Email: admin@dtu.ac.in\n";
    echo "Password: admin123\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} 