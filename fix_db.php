<?php
require_once 'includes/db_config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Fixing Database Schema and Passwords</h2>";

try {
    // 1. Remove failed attempts columns
    $pdo->exec("ALTER TABLE user 
                DROP COLUMN IF EXISTS failed_attempts,
                DROP COLUMN IF EXISTS last_failed_attempt");
    
    echo "Removed failed attempts columns<br>";
    
    // 2. Update all passwords with fresh bcrypt hashes
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
    
    // Update each user's password with a fresh hash
    foreach ($users as $user) {
        $hash = password_hash($user['password'], PASSWORD_BCRYPT);
        
        // First, verify if the user exists
        $check = $pdo->prepare("SELECT id FROM user WHERE email = ?");
        $check->execute([$user['email']]);
        
        if ($check->fetch()) {
            // Update existing user
            $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE email = ?");
            $result = $stmt->execute([$hash, $user['email']]);
            echo "Updated password for {$user['email']}: " . ($result ? "SUCCESS" : "FAILED") . "<br>";
        } else {
            echo "Warning: User {$user['email']} not found in database<br>";
        }
    }
    
    echo "<h3>Database Update Complete!</h3>";
    echo "All passwords have been reset to their default values and properly hashed.";
    
} catch (PDOException $e) {
    echo "Database error: " . htmlspecialchars($e->getMessage());
} 