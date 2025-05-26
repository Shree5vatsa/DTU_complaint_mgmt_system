<?php
require_once 'includes/config.php';

// The password we want to use
$password = 'admin123';

// Generate hash
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: $password\n";
echo "Generated hash: $hash\n";
echo "Verification test: " . (password_verify($password, $hash) ? 'Success' : 'Failed') . "\n";

// Test against stored hash
$stored_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
echo "\nTesting against stored hash:\n";
echo "Stored hash: $stored_hash\n";
echo "Verification test: " . (password_verify($password, $stored_hash) ? 'Success' : 'Failed') . "\n";

// Query database
try {
    $stmt = $pdo->prepare("SELECT email, password FROM user WHERE email = ?");
    $stmt->execute(['admin@dtu.ac.in']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "\nDatabase test:\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Stored hash in DB: " . $user['password'] . "\n";
        echo "Verification test: " . (password_verify($password, $user['password']) ? 'Success' : 'Failed') . "\n";
    } else {
        echo "\nNo admin user found in database\n";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} 