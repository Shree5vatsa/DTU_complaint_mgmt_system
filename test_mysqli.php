<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing MySQL connection...\n";

// Test mysqli connection
$mysqli = new mysqli('localhost', 'root', '', 'dtu_portal');

if ($mysqli->connect_error) {
    echo "MySQL Connection Error: " . $mysqli->connect_error . "\n";
} else {
    echo "MySQL Connection Successful!\n";
    
    // Test query
    $result = $mysqli->query("SELECT COUNT(*) as count FROM user");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Number of users in database: " . $row['count'] . "\n";
    } else {
        echo "Query Error: " . $mysqli->error . "\n";
    }
}

// Print loaded extensions for debugging
echo "\nLoaded Extensions:\n";
print_r(get_loaded_extensions());

$mysqli->close(); 