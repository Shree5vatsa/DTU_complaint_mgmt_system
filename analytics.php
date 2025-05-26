<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if user is logged in and has analytics permission
if (!isLoggedIn() || !hasPermission($_SESSION['user_id'], 'view_analytics')) {
    header('Location: index.php?error=permission_denied');
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_role = getUserRole($user_id);

try {
    // Get overall statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_complaints,
            SUM(CASE WHEN status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'pending') THEN 1 ELSE 0 END) as pending_complaints,
            SUM(CASE WHEN status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress') THEN 1 ELSE 0 END) as in_progress_complaints,
            SUM(CASE WHEN status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'resolved') THEN 1 ELSE 0 END) as resolved_complaints,
            AVG(TIMESTAMPDIFF(HOUR, date_created, IFNULL(last_updated, CURRENT_TIMESTAMP))) as avg_resolution_time
        FROM complaints
    ");
    $stmt->execute();
    $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get department-wise complaint distribution
    $dept_stats = $pdo->query("
        SELECT 
            d.name as department,
            COUNT(*) as total_complaints,
            SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'resolved') THEN 1 ELSE 0 END) as resolved_complaints,
            SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'pending') THEN 1 ELSE 0 END) as pending_complaints,
            SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress') THEN 1 ELSE 0 END) as in_progress_complaints,
            AVG(TIMESTAMPDIFF(HOUR, c.date_created, IFNULL(c.last_updated, CURRENT_TIMESTAMP))) as avg_resolution_time
        FROM complaints c
        JOIN complaint_categories cc ON c.category_id = cc.id
        JOIN departments d ON cc.department_id = d.id
        GROUP BY d.name
        ORDER BY total_complaints DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get priority distribution
    $priority_stats = $pdo->query("
        SELECT 
            pl.level_name,
            COUNT(*) as count,
            AVG(TIMESTAMPDIFF(HOUR, c.date_created, IFNULL(c.last_updated, CURRENT_TIMESTAMP))) as avg_resolution_time
        FROM complaints c
        JOIN priority_levels pl ON c.priority_id = pl.id
        GROUP BY pl.level_name
        ORDER BY pl.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get monthly trends
    $monthly_trends = $pdo->query("
        SELECT 
            DATE_FORMAT(date_created, '%Y-%m') as month,
            COUNT(*) as total_complaints,
            SUM(CASE WHEN status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'resolved') THEN 1 ELSE 0 END) as resolved_complaints
        FROM complaints
        WHERE date_created >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_created, '%Y-%m')
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get top categories with most complaints
    $top_categories = $pdo->query("
        SELECT 
            cc.category_name,
            COUNT(*) as complaint_count,
            d.name as department
        FROM complaints c
        JOIN complaint_categories cc ON c.category_id = cc.id
        JOIN departments d ON cc.department_id = d.id
        GROUP BY cc.id
        ORDER BY complaint_count DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: index.php?error=system_error');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - DTU Complaint Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
            font-size: 1rem;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .priority-high { background-color: #fee2e2; }
        .priority-medium { background-color: #fef3c7; }
        .priority-low { background-color: #ecfdf5; }
        .status-pending { background-color: #fef3c7; }
        .status-progress { background-color: #dbeafe; }
        .status-resolved { background-color: #dcfce7; }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="h2 mb-4">Analytics Dashboard</h1>
            </div>
        </div>

        <!-- Overview Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card bg-white">
                    <div class="stat-label"><i class="bi bi-clipboard-data"></i> Total Complaints</div>
                    <div class="stat-number text-primary"><?php echo number_format($overall_stats['total_complaints']); ?></div>
                    <div class="stat-trend">
                        All time complaints
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card status-pending">
                    <div class="stat-label"><i class="bi bi-hourglass-split"></i> Pending</div>
                    <div class="stat-number text-warning"><?php echo number_format($overall_stats['pending_complaints']); ?></div>
                    <div class="stat-trend">
                        Awaiting action
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card status-progress">
                    <div class="stat-label"><i class="bi bi-arrow-repeat"></i> In Progress</div>
                    <div class="stat-number text-info"><?php echo number_format($overall_stats['in_progress_complaints']); ?></div>
                    <div class="stat-trend">
                        Being processed
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card status-resolved">
                    <div class="stat-label"><i class="bi bi-check-circle"></i> Resolved</div>
                    <div class="stat-number text-success"><?php echo number_format($overall_stats['resolved_complaints']); ?></div>
                    <div class="stat-trend">
                        Successfully completed
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Monthly Trends Chart -->
            <div class="col-md-8">
                <div class="chart-container">
                    <h3 class="h5 mb-4">Monthly Complaint Trends</h3>
                    <canvas id="monthlyTrendsChart"></canvas>
                </div>
            </div>
            <!-- Priority Distribution Chart -->
            <div class="col-md-4">
                <div class="chart-container">
                    <h3 class="h5 mb-4">Complaints by Priority</h3>
                    <canvas id="priorityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Department Statistics -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="chart-container">
                    <h3 class="h5 mb-4">Department-wise Statistics</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Total</th>
                                    <th>Pending</th>
                                    <th>In Progress</th>
                                    <th>Resolved</th>
                                    <th>Avg. Resolution Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dept_stats as $dept): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                    <td><?php echo number_format($dept['total_complaints']); ?></td>
                                    <td>
                                        <span class="badge bg-warning">
                                            <?php echo number_format($dept['pending_complaints']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo number_format($dept['in_progress_complaints']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">
                                            <?php echo number_format($dept['resolved_complaints']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $hours = round($dept['avg_resolution_time']);
                                        echo $hours > 24 ? round($hours/24) . ' days' : $hours . ' hours';
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Categories and Priority Stats -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="chart-container">
                    <h3 class="h5 mb-4">Top Complaint Categories</h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Department</th>
                                    <th>Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['department']); ?></td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo number_format($category['complaint_count']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h3 class="h5 mb-4">Resolution Time by Priority</h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Priority</th>
                                    <th>Complaints</th>
                                    <th>Avg. Resolution Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($priority_stats as $priority): ?>
                                <tr class="priority-<?php echo strtolower($priority['level_name']); ?>">
                                    <td>
                                        <?php echo ucfirst(htmlspecialchars($priority['level_name'])); ?>
                                    </td>
                                    <td><?php echo number_format($priority['count']); ?></td>
                                    <td>
                                        <?php 
                                        $hours = round($priority['avg_resolution_time']);
                                        echo $hours > 24 ? round($hours/24) . ' days' : $hours . ' hours';
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Monthly Trends Chart
        const monthlyData = <?php echo json_encode($monthly_trends); ?>;
        new Chart(document.getElementById('monthlyTrendsChart'), {
            type: 'line',
            data: {
                labels: monthlyData.map(row => row.month),
                datasets: [{
                    label: 'Total Complaints',
                    data: monthlyData.map(row => row.total_complaints),
                    borderColor: '#3b82f6',
                    tension: 0.1
                }, {
                    label: 'Resolved Complaints',
                    data: monthlyData.map(row => row.resolved_complaints),
                    borderColor: '#22c55e',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Complaint Trends (Last 6 Months)'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Priority Distribution Chart
        const priorityData = <?php echo json_encode($priority_stats); ?>;
        new Chart(document.getElementById('priorityChart'), {
            type: 'doughnut',
            data: {
                labels: priorityData.map(row => row.level_name),
                datasets: [{
                    data: priorityData.map(row => row.count),
                    backgroundColor: [
                        '#fee2e2', // High
                        '#fef3c7', // Medium
                        '#ecfdf5'  // Low
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html> 