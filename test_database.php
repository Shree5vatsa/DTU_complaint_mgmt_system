<?php
require_once 'includes/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

try {
    // Test database connection
    echo "Testing database connection...<br>";
    $pdo->query("SELECT 1");
    echo "Database connection successful!<br><br>";
    
    // Check if tables exist
    echo "<h3>Checking Required Tables:</h3>";
    $required_tables = ['user', 'roles', 'complaint_categories', 'complaint_status_types', 'departments'];
    
    foreach ($required_tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Table '$table' exists<br>";
            
            // Show table structure
            echo "<pre>";
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            foreach ($columns as $column) {
                echo "  - {$column['Field']}: {$column['Type']} ";
                echo $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL';
                if ($column['Key'] === 'PRI') echo ' (PRIMARY KEY)';
                if ($column['Default'] !== null) echo " DEFAULT '{$column['Default']}'";
                echo "\n";
            }
            echo "</pre>";
        } else {
            echo "✗ Table '$table' does not exist!<br>";
        }
    }
    
    // Check admin account
    echo "<h3>Checking Admin Account:</h3>";
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name 
        FROM user u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.email = ?
    ");
    $stmt->execute(['admin@dtu.ac.in']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "✓ Admin account exists<br>";
        echo "- ID: {$admin['id']}<br>";
        echo "- Name: {$admin['name']}<br>";
        echo "- Role: {$admin['role_name']}<br>";
        echo "- Status: {$admin['status']}<br>";
        
        // Test password verification
        $test_pass = 'admin123';
        $verified = password_verify($test_pass, $admin['password']);
        echo "- Password verification: " . ($verified ? "VALID" : "INVALID") . "<br>";
        
        if (!$verified) {
            // Update admin password
            $new_hash = password_hash($test_pass, PASSWORD_BCRYPT, ['cost' => 10]);
            $update = $pdo->prepare("UPDATE user SET password = ? WHERE id = ?");
            $update->execute([$new_hash, $admin['id']]);
            echo "- Admin password has been reset to 'admin123'<br>";
        }
    } else {
        echo "✗ Admin account not found!<br>";
        
        // Create admin role if it doesn't exist
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'Administrator'");
        $stmt->execute();
        $admin_role = $stmt->fetch();
        
        if (!$admin_role) {
            $stmt = $pdo->prepare("INSERT INTO roles (role_name, role_level) VALUES ('Administrator', 100)");
            $stmt->execute();
            $admin_role_id = $pdo->lastInsertId();
            echo "- Created Administrator role<br>";
        } else {
            $admin_role_id = $admin_role['id'];
        }
        
        // Create admin account
        $admin_hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $pdo->prepare("
            INSERT INTO user (name, email, password, role_id, status) 
            VALUES ('Administrator', 'admin@dtu.ac.in', ?, ?, 'active')
        ");
        $stmt->execute([$admin_hash, $admin_role_id]);
        echo "- Created admin account with email 'admin@dtu.ac.in' and password 'admin123'<br>";
    }
    
    // Check roles
    echo "<h3>Checking Roles:</h3>";
    $required_roles = [
        ['Administrator', 100],
        ['HOD', 80],
        ['Warden', 70],
        ['Teacher', 50],
        ['Student', 10]
    ];
    
    foreach ($required_roles as $role) {
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE role_name = ?");
        $stmt->execute([$role[0]]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "✓ Role '{$role[0]}' exists (Level: {$exists['role_level']})<br>";
            
            // Update role level if different
            if ($exists['role_level'] != $role[1]) {
                $update = $pdo->prepare("UPDATE roles SET role_level = ? WHERE id = ?");
                $update->execute([$role[1], $exists['id']]);
                echo "  - Updated role level to {$role[1]}<br>";
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO roles (role_name, role_level) VALUES (?, ?)");
            $stmt->execute($role);
            echo "- Created role '{$role[0]}' with level {$role[1]}<br>";
        }
    }
    
    echo "<br>Database check completed successfully!";
    
} catch (PDOException $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Error code: " . $e->getCode() . "<br>";
    
    if ($e->getCode() == 1049) {
        echo "<br>The database 'dtu_portal' does not exist. Creating it...<br>";
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "Database created successfully! Please refresh this page.<br>";
        } catch (PDOException $e2) {
            echo "Failed to create database: " . htmlspecialchars($e2->getMessage());
        }
    }
} catch (Exception $e) {
    echo "General error: " . htmlspecialchars($e->getMessage());
}
?> 