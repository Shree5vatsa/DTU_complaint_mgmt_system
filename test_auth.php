<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Authentication Test</h2>";

// Test credentials
$test_users = [
    [
        'email' => 'boys.hostel.warden@dtu.ac.in',
        'password' => 'warden123',
        'role' => 'Warden'
    ],
    [
        'email' => 'admin@dtu.ac.in',
        'password' => 'admin123',
        'role' => 'Administrator'
    ]
];

foreach ($test_users as $test_user) {
    echo "<h3>Testing {$test_user['role']} Login</h3>";
    echo "Email: {$test_user['email']}<br>";
    echo "Password: {$test_user['password']}<br>";
    
    // Get user from database
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.role_name, r.role_level
            FROM user u
            JOIN roles r ON u.role_id = r.id
            WHERE u.email = ?
        ");
        $stmt->execute([$test_user['email']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "User found in database:<br>";
            echo "- ID: {$user['id']}<br>";
            echo "- Name: {$user['name']}<br>";
            echo "- Role: {$user['role_name']}<br>";
            echo "- Status: {$user['status']}<br>";
            echo "- Stored Password Hash: {$user['password']}<br>";
            
            // Generate test hash
            $test_hash = password_hash($test_user['password'], PASSWORD_BCRYPT);
            echo "Test hash for '{$test_user['password']}': {$test_hash}<br>";
            
            // Verify password
            $verified = password_verify($test_user['password'], $user['password']);
            echo "Password verification result: " . ($verified ? "SUCCESS" : "FAILED") . "<br>";
            
            // Try authentication
            $auth_result = authenticateUser($test_user['email'], $test_user['password']);
            echo "Authentication result: " . ($auth_result ? "SUCCESS" : "FAILED") . "<br>";
        } else {
            echo "No user found with email {$test_user['email']}<br>";
        }
    } catch (PDOException $e) {
        echo "Database error: " . htmlspecialchars($e->getMessage()) . "<br>";
    } catch (Exception $e) {
        echo "General error: " . htmlspecialchars($e->getMessage()) . "<br>";
    }
    
    echo "<hr>";
}

// Test password hashing
$test_password = 'admin123';
$hashed = password_hash($test_password, PASSWORD_BCRYPT);
echo "Test password hash for 'admin123': " . $hashed . "\n";

// Get admin user from database
$stmt = $pdo->prepare("SELECT id, email, password FROM user WHERE email = ?");
$stmt->execute(['admin@dtu.ac.in']);
$user = $stmt->fetch();

if ($user) {
    echo "\nStored hash in database for admin: " . $user['password'] . "\n";
    
    // Test verification
    echo "\nTesting password verification:\n";
    echo "Verifying 'admin123': " . (password_verify('admin123', $user['password']) ? "SUCCESS" : "FAIL") . "\n";
} else {
    echo "\nAdmin user not found in database\n";
}

// Let's update admin password to a known good hash
$new_hash = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE user SET password = ? WHERE email = ?");
$stmt->execute([$new_hash, 'admin@dtu.ac.in']);
echo "\nUpdated admin password with new hash: " . $new_hash . "\n";
?> 