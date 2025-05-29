<?php
require_once 'includes/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Updating Database Schema for Enhanced Authentication</h2>";

try {
    // Add new columns to user table if they don't exist
    $columns_to_add = [
        'failed_attempts' => "ALTER TABLE user ADD COLUMN IF NOT EXISTS failed_attempts INT DEFAULT 0",
        'last_failed_attempt' => "ALTER TABLE user ADD COLUMN IF NOT EXISTS last_failed_attempt INT DEFAULT NULL",
        'last_login' => "ALTER TABLE user ADD COLUMN IF NOT EXISTS last_login TIMESTAMP DEFAULT NULL"
    ];
    
    foreach ($columns_to_add as $column => $sql) {
        try {
            $pdo->exec($sql);
            echo "Added column '$column' successfully<br>";
        } catch (PDOException $e) {
            if ($e->getCode() == '42S21') { // Duplicate column
                echo "Column '$column' already exists<br>";
            } else {
                throw $e;
            }
        }
    }
    
    // Reset any locked accounts
    $reset_sql = "UPDATE user SET failed_attempts = 0, last_failed_attempt = NULL WHERE failed_attempts > 0";
    $pdo->exec($reset_sql);
    echo "Reset locked accounts successfully<br>";
    
    // Verify warden accounts
    $wardens = [
        'boys.hostel.warden@dtu.ac.in',
        'girls.hostel.warden@dtu.ac.in'
    ];
    
    foreach ($wardens as $warden_email) {
        $stmt = $pdo->prepare("SELECT id, email, password FROM user WHERE email = ?");
        $stmt->execute([$warden_email]);
        $warden = $stmt->fetch();
        
        if ($warden) {
            echo "<br>Found warden account: {$warden_email}<br>";
            echo "Current password hash: {$warden['password']}<br>";
            
            // Check if password needs rehashing
            $test_hash = password_hash('warden123', PASSWORD_BCRYPT, ['cost' => 10]);
            echo "Test hash for 'warden123': {$test_hash}<br>";
            
            if (password_verify('warden123', $warden['password'])) {
                echo "Current password hash is valid<br>";
                
                if (password_needs_rehash($warden['password'], PASSWORD_BCRYPT, ['cost' => 10])) {
                    $update = $pdo->prepare("UPDATE user SET password = ? WHERE id = ?");
                    $update->execute([$test_hash, $warden['id']]);
                    echo "Updated password hash for better security<br>";
                }
            } else {
                // Reset password if hash is invalid
                $update = $pdo->prepare("UPDATE user SET password = ? WHERE id = ?");
                $update->execute([$test_hash, $warden['id']]);
                echo "Reset password hash to ensure proper format<br>";
            }
        } else {
            echo "<br>Warning: Warden account not found: {$warden_email}<br>";
        }
    }
    
    echo "<br>Database update completed successfully!";
    
} catch (PDOException $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage());
} catch (Exception $e) {
    echo "General error: " . htmlspecialchars($e->getMessage());
}
?> 