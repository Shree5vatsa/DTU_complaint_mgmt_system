<?php
require_once 'includes/config.php';

$email = 'admin@dtu.ac.in';
$password = 'admin123';
$new_hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE email = ?");
    $result = $stmt->execute([$new_hash, $email]);
    
    if ($result) {
        echo "Admin password has been reset successfully!\n";
        echo "Email: admin@dtu.ac.in\n";
        echo "Password: admin123\n";
    } else {
        echo "Failed to reset password. No user found with email: admin@dtu.ac.in";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 