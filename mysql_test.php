<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP Version: " . PHP_VERSION . "\n";
echo "Checking MySQL connection...\n\n";

// Try mysqli first
if (function_exists('mysqli_connect')) {
    echo "mysqli extension is loaded\n";
    try {
        $mysqli = @new mysqli('localhost', 'root', '');
        if ($mysqli->connect_error) {
            echo "MySQL Connection Error: " . $mysqli->connect_error . "\n";
        } else {
            echo "MySQL Connection Successful!\n";
            echo "MySQL Server Version: " . $mysqli->server_info . "\n";
            $mysqli->close();
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "mysqli extension is NOT loaded\n";
}

echo "\nChecking PDO drivers:\n";
if (class_exists('PDO')) {
    echo "Available PDO drivers:\n";
    print_r(PDO::getAvailableDrivers());
} else {
    echo "PDO is not available\n";
}

echo "\nLoaded Extensions:\n";
print_r(get_loaded_extensions()); 