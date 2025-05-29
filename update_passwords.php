<?php
require_once 'includes/config.php';

// Function to update password for a user
function updatePassword($email, $password) {
    global $pdo;
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE email = ?");
    return $stmt->execute([$hash, $email]);
}

// Update passwords for all user types
$updates = [
    'admin@dtu.ac.in' => 'admin123',
    'rahul.kumar@dtu.ac.in' => 'student123',
    'priya.singh@dtu.ac.in' => 'student123',
    'ramesh.chandra@dtu.ac.in' => 'teacher123',
    'sarita.agarwal@dtu.ac.in' => 'teacher123',
    'hod.coe@dtu.ac.in' => 'hod123',
    'hod.ece@dtu.ac.in' => 'hod123',
    'boys.hostel.warden@dtu.ac.in' => 'warden123',
    'girls.hostel.warden@dtu.ac.in' => 'warden123'
];

foreach ($updates as $email => $password) {
    if (updatePassword($email, $password)) {
        echo "Updated password for $email\n";
    } else {
        echo "Failed to update password for $email\n";
    }
}

echo "\nAll passwords have been updated. You can now try logging in with these credentials:\n";
echo "Admin: admin@dtu.ac.in / admin123\n";
echo "Student: rahul.kumar@dtu.ac.in / student123\n";
echo "Teacher: ramesh.chandra@dtu.ac.in / teacher123\n";
echo "HOD: hod.coe@dtu.ac.in / hod123\n";
echo "Warden: boys.hostel.warden@dtu.ac.in / warden123\n"; 