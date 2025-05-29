<?php
require_once 'includes/db_config.php';

try {
    // Test users data
    $users = [
        // Students
        [
            'name' => 'Rahul Kumar',
            'email' => 'rahul.kumar@dtu.ac.in',
            'password' => 'student123',
            'role' => 'Student',
            'department' => 'Computer Engineering',
            'roll_number' => '2K21/CO/123'
        ],
        // Teachers
        [
            'name' => 'Dr. Ramesh Chandra',
            'email' => 'ramesh.chandra@dtu.ac.in',
            'password' => 'teacher123',
            'role' => 'Teacher',
            'department' => 'Computer Engineering'
        ],
        // HODs
        [
            'name' => 'Prof. Rajeev Malhotra',
            'email' => 'hod.coe@dtu.ac.in',
            'password' => 'hod123',
            'role' => 'HOD',
            'department' => 'Computer Engineering'
        ],
        // Wardens
        [
            'name' => 'Dr. Suresh Kumar',
            'email' => 'boys.hostel.warden@dtu.ac.in',
            'password' => 'warden123',
            'role' => 'Warden',
            'department' => 'Computer Engineering'
        ]
    ];

    foreach ($users as $user) {
        // Get role ID
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = ?");
        $stmt->execute([$user['role']]);
        $role = $stmt->fetch();
        
        if (!$role) {
            echo "Role {$user['role']} not found\n";
            continue;
        }
        
        // Get department ID
        $stmt = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
        $stmt->execute([$user['department']]);
        $department = $stmt->fetch();
        
        if (!$department) {
            echo "Department {$user['department']} not found\n";
            continue;
        }
        
        // Hash password
        $hash = password_hash($user['password'], PASSWORD_BCRYPT);
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ?");
        $stmt->execute([$user['email']]);
        $existing_user = $stmt->fetch();
        
        if ($existing_user) {
            // Update existing user
            $stmt = $pdo->prepare("
                UPDATE user 
                SET password = ?,
                    name = ?,
                    role_id = ?,
                    department_id = ?,
                    roll_number = ?,
                    status = 'active',
                    failed_attempts = 0,
                    last_failed_attempt = NULL
                WHERE email = ?
            ");
            $stmt->execute([
                $hash,
                $user['name'],
                $role['id'],
                $department['id'],
                $user['roll_number'] ?? NULL,
                $user['email']
            ]);
            echo "Updated user: {$user['email']}\n";
        } else {
            // Create new user
            $stmt = $pdo->prepare("
                INSERT INTO user (
                    name, 
                    email, 
                    password, 
                    role_id, 
                    department_id,
                    roll_number,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $user['name'],
                $user['email'],
                $hash,
                $role['id'],
                $department['id'],
                $user['roll_number'] ?? NULL
            ]);
            echo "Created user: {$user['email']}\n";
        }
    }
    
    echo "\nTest users have been set up successfully!\n";
    echo "\nYou can now login with these credentials:\n";
    echo "Student: rahul.kumar@dtu.ac.in / student123\n";
    echo "Teacher: ramesh.chandra@dtu.ac.in / teacher123\n";
    echo "HOD: hod.coe@dtu.ac.in / hod123\n";
    echo "Warden: boys.hostel.warden@dtu.ac.in / warden123\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 