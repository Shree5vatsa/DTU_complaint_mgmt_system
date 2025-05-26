<?php
require_once 'includes/config.php';

function displayTable($pdo, $tableName) {
    echo "<h2>Table: $tableName</h2>";
    
    try {
        // Get table structure
        $stmt = $pdo->query("DESCRIBE `$tableName`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get table data
        $stmt = $pdo->query("SELECT * FROM `$tableName`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            echo "<div class='table-responsive'>";
            echo "<table class='table table-bordered table-striped'>";
            
            // Header
            echo "<thead class='thead-dark'><tr>";
            foreach ($columns as $column) {
                echo "<th>" . htmlspecialchars($column) . "</th>";
            }
            echo "</tr></thead>";
            
            // Data
            echo "<tbody>";
            foreach ($rows as $row) {
                echo "<tr>";
                foreach ($columns as $column) {
                    echo "<td>" . htmlspecialchars($row[$column] ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</tbody>";
            
            echo "</table>";
            echo "</div>";
            echo "<p>Total records: " . count($rows) . "</p>";
        } else {
            echo "<p>No records found in table.</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='text-danger'>Error displaying table: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "<hr>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTU Portal Database Contents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .table-responsive { margin-bottom: 20px; }
        h1 { margin-bottom: 30px; }
        h2 { margin-top: 20px; color: #333; }
        .table th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1>DTU Portal Database Contents</h1>
        
        <?php
        // List of tables to display
        $tables = [
            'roles',
            'departments',
            'user',
            'complaint_categories',
            'complaint_subcategories',
            'complaint_status_types',
            'priority_levels',
            'complaints',
            'complaint_details',
            'complaint_history'
        ];
        
        try {
            foreach ($tables as $table) {
                displayTable($pdo, $table);
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 