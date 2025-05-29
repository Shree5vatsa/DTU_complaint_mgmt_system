<?php
require_once 'includes/db_config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Fixing Password Hashes</h2>";

// User credentials mapping
$users = [
    // Admin
    ['email' => 'admin@dtu.ac.in', 'password' => 'admin123'],
    
    // Students
    ['email' => 'rahul.kumar@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'priya.singh@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'michael.thomas@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'sarah.wilson@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'ankit.sharma@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'neha.gupta@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'arjun.patel@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'riya.verma@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'aditya.kumar@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'ishaan.mehta@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'zara.khan@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'rohan.malhotra@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'shreya.reddy@dtu.ac.in', 'password' => 'student123'],
    ['email' => 'dev.kapoor@dtu.ac.in', 'password' => 'student123'],
    
    // Teachers
    ['email' => 'ramesh.chandra@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'sarita.agarwal@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'rajesh.kumar@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'sunita.sharma@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'amit.singh@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'meera.patel@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'vikram.mehta@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'priya.verma@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'suresh.yadav@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'anjali.gupta@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'rakesh.sharma@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'neha.singh@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'anand.kumar.it@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'meena.tiwari.it@dtu.ac.in', 'password' => 'teacher123'],
    
    // HODs
    ['email' => 'hod.coe@dtu.ac.in', 'password' => 'hod123'],
    ['email' => 'hod.ece@dtu.ac.in', 'password' => 'hod123'],
    
    // Library Staff
    ['email' => 'chief.librarian@dtu.ac.in', 'password' => 'teacher123'],
    ['email' => 'lib.assistant@dtu.ac.in', 'password' => 'teacher123']
];

try {
    // Update each user's password
    foreach ($users as $user) {
        // Generate proper bcrypt hash
        $hash = password_hash($user['password'], PASSWORD_BCRYPT);
        
        // Update the password in the database
        $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE email = ?");
        $result = $stmt->execute([$hash, $user['email']]);
        
        echo "Updating {$user['email']}: " . ($result ? "SUCCESS" : "FAILED") . "<br>";
    }
    
    echo "<h3>Password Update Complete!</h3>";
    echo "All user passwords have been properly hashed using bcrypt.";
    
} catch (PDOException $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage());
} 