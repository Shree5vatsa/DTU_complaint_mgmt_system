<?php
require_once 'includes/db_config.php';

try {
    // Drop existing tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Create tables
    $pdo->exec("
        CREATE TABLE roles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            role_name VARCHAR(50) NOT NULL UNIQUE,
            role_level INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    $pdo->exec("
        CREATE TABLE user (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(128) NOT NULL,
            email VARCHAR(128) NOT NULL UNIQUE,
            password VARCHAR(256) NOT NULL,
            role_id INT NOT NULL,
            department_id INT,
            roll_number VARCHAR(20),
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            image VARCHAR(128) NOT NULL DEFAULT 'default.jpg',
            last_login DATETIME,
            failed_attempts INT NOT NULL DEFAULT 0,
            last_failed_attempt INT,
            date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (role_id) REFERENCES roles(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    $pdo->exec("
        CREATE TABLE departments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Insert roles
    $roles = [
        ['Administrator', 100],
        ['HOD', 75],
        ['Warden', 75],
        ['Teacher', 50],
        ['Student', 10]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO roles (role_name, role_level) VALUES (?, ?)");
    foreach ($roles as $role) {
        $stmt->execute($role);
    }
    
    // Insert departments
    $departments = [
        'Computer Engineering',
        'Electronics Engineering',
        'Mechanical Engineering',
        'Civil Engineering',
        'Information Technology'
    ];
    
    $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
    foreach ($departments as $dept) {
        $stmt->execute([$dept]);
    }
    
    // Create admin user
    $stmt = $pdo->prepare("
        INSERT INTO user (
            name, email, password, role_id, status
        ) VALUES (
            'Administrator',
            'admin@dtu.ac.in',
            ?,
            (SELECT id FROM roles WHERE role_name = 'Administrator'),
            'active'
        )
    ");
    
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $stmt->execute([$hashed_password]);
    
    echo "Database reimported successfully!\n";
    echo "\nAdmin credentials:\n";
    echo "Email: admin@dtu.ac.in\n";
    echo "Password: admin123\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 