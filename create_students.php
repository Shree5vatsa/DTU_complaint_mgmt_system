<?php
require_once 'includes/config.php';

// Student role ID (5 for students as per the database schema)
$student_role = 5;

// Sample student data
$students = [
    [
        'name' => 'Rahul Kumar',
        'email' => 'rahul.kumar@dtu.ac.in',
        'password' => 'student123',
        'department_id' => 1, // Computer Engineering
        'roll_number' => '2K21/CO/123'
    ],
    [
        'name' => 'Priya Singh',
        'email' => 'priya.singh@dtu.ac.in',
        'password' => 'student123',
        'department_id' => 2, // Electronics & Communication
        'roll_number' => '2K21/EC/456'
    ],
    [
        'name' => 'Amit Sharma',
        'email' => 'amit.sharma@dtu.ac.in',
        'password' => 'student123',
        'department_id' => 3, // Information Technology
        'roll_number' => '2K21/IT/789'
    ]
];

try {
    foreach ($students as $student) {
        // Hash the password
        $hash = password_hash($student['password'], PASSWORD_BCRYPT);
        
        // Check if student already exists
        $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ?");
        $stmt->execute([$student['email']]);
        
        if (!$stmt->fetch()) {
            // Insert new student
            $stmt = $pdo->prepare("INSERT INTO user (name, email, password, role_id, department_id, roll_number, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([
                $student['name'],
                $student['email'],
                $hash,
                $student_role,
                $student['department_id'],
                $student['roll_number']
            ]);
            echo "Created student account: " . $student['email'] . "\n";
        } else {
            echo "Student already exists: " . $student['email'] . "\n";
        }
    }
    
    echo "\nStudent Login Credentials:\n";
    echo "----------------------------------------\n";
    foreach ($students as $student) {
        echo "Name: " . $student['name'] . "\n";
        echo "Email: " . $student['email'] . "\n";
        echo "Password: student123\n";
        echo "----------------------------------------\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 