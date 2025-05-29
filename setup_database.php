<?php
require_once 'includes/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Setting up Database Tables</h2>";

try {
    // Create roles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            role_name VARCHAR(50) NOT NULL UNIQUE,
            role_level INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created roles table<br>";
    
    // Create departments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS departments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(10) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created departments table<br>";
    
    // Create complaint_status_types table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS complaint_status_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            status_name VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created complaint_status_types table<br>";
    
    // Create complaint_categories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS complaint_categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            category_name VARCHAR(100) NOT NULL,
            department_id INT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created complaint_categories table<br>";
    
    // Create user table with all required fields
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role_id INT NOT NULL,
            department_id INT,
            gender ENUM('male', 'female') DEFAULT 'male',
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            failed_attempts INT DEFAULT 0,
            last_failed_attempt INT DEFAULT NULL,
            last_login TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (role_id) REFERENCES roles(id),
            FOREIGN KEY (department_id) REFERENCES departments(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created user table<br>";
    
    // Insert default roles if they don't exist
    $roles = [
        ['Administrator', 100],
        ['HOD', 80],
        ['Warden', 70],
        ['Teacher', 50],
        ['Student', 10]
    ];
    
    $role_stmt = $pdo->prepare("INSERT IGNORE INTO roles (role_name, role_level) VALUES (?, ?)");
    foreach ($roles as $role) {
        $role_stmt->execute($role);
    }
    echo "✓ Added default roles<br>";
    
    // Insert default departments
    $departments = [
        ['Computer Science', 'CSE'],
        ['Information Technology', 'IT'],
        ['Hostels', 'HST']
    ];
    
    $dept_stmt = $pdo->prepare("INSERT IGNORE INTO departments (name, code) VALUES (?, ?)");
    foreach ($departments as $dept) {
        $dept_stmt->execute($dept);
    }
    echo "✓ Added default departments<br>";
    
    // Insert default complaint status types
    $statuses = [
        ['pending', 'Complaint is waiting to be processed'],
        ['in_progress', 'Complaint is being handled'],
        ['resolved', 'Complaint has been resolved'],
        ['rejected', 'Complaint has been rejected']
    ];
    
    $status_stmt = $pdo->prepare("INSERT IGNORE INTO complaint_status_types (status_name, description) VALUES (?, ?)");
    foreach ($statuses as $status) {
        $status_stmt->execute($status);
    }
    echo "✓ Added default complaint status types<br>";
    
    // Create admin account if it doesn't exist
    $admin_email = 'admin@dtu.ac.in';
    $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ?");
    $stmt->execute([$admin_email]);
    
    if (!$stmt->fetch()) {
        // Get admin role ID
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'Administrator'");
        $stmt->execute();
        $admin_role = $stmt->fetch();
        
        if ($admin_role) {
            $admin_hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10]);
            $stmt = $pdo->prepare("
                INSERT INTO user (name, email, password, role_id, status) 
                VALUES ('Administrator', ?, ?, ?, 'active')
            ");
            $stmt->execute([$admin_email, $admin_hash, $admin_role['id']]);
            echo "✓ Created admin account (admin@dtu.ac.in / admin123)<br>";
        }
    }
    
    // Create warden accounts if they don't exist
    $wardens = [
        ['Boys Hostel Warden', 'boys.hostel.warden@dtu.ac.in'],
        ['Girls Hostel Warden', 'girls.hostel.warden@dtu.ac.in']
    ];
    
    // Get warden role ID and hostel department ID
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'Warden'");
    $stmt->execute();
    $warden_role = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE code = 'HST'");
    $stmt->execute();
    $hostel_dept = $stmt->fetch();
    
    if ($warden_role && $hostel_dept) {
        $warden_hash = password_hash('warden123', PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO user (name, email, password, role_id, department_id, status) 
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        
        foreach ($wardens as $warden) {
            $stmt->execute([$warden[0], $warden[1], $warden_hash, $warden_role['id'], $hostel_dept['id']]);
        }
        echo "✓ Created warden accounts<br>";
    }
    
    echo "<br>Database setup completed successfully!";
    
} catch (PDOException $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Error code: " . $e->getCode();
} catch (Exception $e) {
    echo "General error: " . htmlspecialchars($e->getMessage());
}
?> 