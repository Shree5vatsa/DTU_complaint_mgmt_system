<?php
// Show all PHP info
phpinfo();

// Specifically check MySQL extensions
echo "\n\nMySQL Extensions:\n";
echo "mysqli extension loaded: " . (extension_loaded('mysqli') ? 'Yes' : 'No') . "\n";
echo "PDO extension loaded: " . (extension_loaded('pdo') ? 'Yes' : 'No') . "\n";
echo "PDO MySQL extension loaded: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "\n";

// Check available PDO drivers
echo "\nAvailable PDO drivers:\n";
print_r(PDO::getAvailableDrivers()); 