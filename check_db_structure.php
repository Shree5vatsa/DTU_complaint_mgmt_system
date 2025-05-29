<?php
require_once 'includes/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Structure Check</h2>";

try {
    // Check if the user table exists
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('user', $tables)) {
        echo "Creating user table...<br>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role_id INT NOT NULL,
                department_id INT,
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                failed_attempts INT DEFAULT 0,
                last_failed_attempt INT DEFAULT NULL,
                last_login TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // Check user table structure
    echo "<h3>Current User Table Structure:</h3>";
    $columns = $pdo->query("SHOW COLUMNS FROM user")->fetchAll();
    echo "<pre>";
    print_r($columns);
    echo "</pre>";

    // Check if required columns exist
    $required_columns = [
        'failed_attempts' => "ALTER TABLE user ADD COLUMN failed_attempts INT DEFAULT 0",
        'last_failed_attempt' => "ALTER TABLE user ADD COLUMN last_failed_attempt INT DEFAULT NULL",
        'last_login' => "ALTER TABLE user ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL"
    ];

    foreach ($required_columns as $column => $sql) {
        $exists = false;
        foreach ($columns as $col) {
            if ($col['Field'] === $column) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            echo "Adding missing column '$column'...<br>";
            $pdo->exec($sql);
        }
    }

    // Check admin account
    $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->execute(['admin@dtu.ac.in']);
    $admin = $stmt->fetch();

    if (!$admin) {
        echo "Creating admin account...<br>";
        // First ensure admin role exists
        $pdo->exec("INSERT IGNORE INTO roles (role_name, role_level) VALUES ('Administrator', 100)");
        $role_id = $pdo->query("SELECT id FROM roles WHERE role_name = 'Administrator'")->fetch()['id'];
        
        // Create admin user
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO user (name, email, password, role_id, status) 
            VALUES ('Administrator', 'admin@dtu.ac.in', ?, ?, 'active')
        ");
        $stmt->execute([$hash, $role_id]);
        echo "Admin account created with email: admin@dtu.ac.in and password: admin123<br>";
    } else {
        // Reset admin password
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE email = ?");
        $stmt->execute([$hash, 'admin@dtu.ac.in']);
        echo "Admin password reset to: admin123<br>";
    }

    echo "<br>Database structure check completed!<br>";
    echo "<a href='login.php'>Try logging in now</a>";

} catch (PDOException $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Error code: " . $e->getCode();
} catch (Exception $e) {
    echo "General error: " . htmlspecialchars($e->getMessage());
}
?> 