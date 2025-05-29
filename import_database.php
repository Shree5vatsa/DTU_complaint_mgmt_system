<?php
require_once 'includes/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Importing Database</h2>";

try {
    // Check if the SQL file exists
    $sql_file = __DIR__ . '/DATABASE FILE/Dtu_portal.sql';
    if (!file_exists($sql_file)) {
        die("Error: SQL file not found at: " . $sql_file);
    }

    // Read SQL file
    $sql = file_get_contents($sql_file);
    if ($sql === false) {
        die("Error: Could not read SQL file");
    }

    echo "Reading SQL file...<br>";

    // Split SQL file into individual queries
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    // Execute each query
    foreach ($queries as $query) {
        if (empty($query)) continue;
        
        try {
            $pdo->exec($query);
            echo "✓ Executed query successfully<br>";
        } catch (PDOException $e) {
            echo "⚠ Query failed: " . htmlspecialchars($e->getMessage()) . "<br>";
            // Continue with next query even if this one fails
        }
    }

    echo "<br>Database import completed!<br>";
    echo "<a href='test_database.php'>Click here to test the database</a>";

} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
?> 