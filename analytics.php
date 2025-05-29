<?php
// Set error reporting
error_reporting(-1);
ini_set('display_errors', 1);

require_once 'includes/db_config.php';
require_once 'includes/auth.php';

// Initialize Auth class
$auth = new Auth($pdo);

// Check if user is logged in and has permission
if (!$auth->isLoggedIn() || !$auth->hasPermission('view_analytics')) {
    header('Location: index.php?error=permission_denied');
    exit();
}

// Get user details
$user = $auth->getCurrentUser();
if (!$user) {
    $auth->logout();
    header('Location: login.php');
    exit();
}

try {
    // Get complaint statistics by department
    $stmt = $pdo->prepare("
        SELECT 
            d.name as department,
            COUNT(*) as total_complaints,
            SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'pending') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress') THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'resolved') THEN 1 ELSE 0 END) as resolved
        FROM complaints c
        JOIN complaint_categories cc ON c.category_id = cc.id
        JOIN departments d ON cc.department_id = d.id
        GROUP BY d.id, d.name
        ORDER BY total_complaints DESC
    ");
    $stmt->execute();
    $department_stats = $stmt->fetchAll();
    
    // Get unresolved complaints by priority
    $stmt = $pdo->prepare("
        SELECT 
            pl.level_name as priority,
            COUNT(*) as count
        FROM complaints c
        JOIN priority_levels pl ON c.priority_id = pl.id
        WHERE c.status_id IN (
            SELECT id FROM complaint_status_types 
            WHERE status_name IN ('pending', 'in_progress')
            AND status_name != 'rejected'
        )
        GROUP BY pl.id, pl.level_name
        ORDER BY pl.id
    ");
    $stmt->execute();
    $unresolved_by_priority = $stmt->fetchAll();
    
    // Get complaint statistics by category
    $stmt = $pdo->prepare("
        SELECT 
            cc.category_name,
            COUNT(*) as total_complaints,
            SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'pending') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress') THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'resolved') THEN 1 ELSE 0 END) as resolved
        FROM complaints c
        JOIN complaint_categories cc ON c.category_id = cc.id
        GROUP BY cc.id, cc.category_name
        ORDER BY total_complaints DESC
    ");
    $stmt->execute();
    $category_stats = $stmt->fetchAll();
    
    // Get complaint trends over time (hourly)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(date_created, '%Y-%m-%d %H:00:00') as complaint_hour,
            COUNT(*) as total_complaints
        FROM complaints
        WHERE date_created >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY DATE_FORMAT(date_created, '%Y-%m-%d %H:00:00')
        ORDER BY complaint_hour DESC
        LIMIT 24
    ");
    $stmt->execute();
    $trends = $stmt->fetchAll();
    
    // Get resolution time statistics
    $stmt = $pdo->prepare("
        SELECT 
            d.name as department,
            AVG(TIMESTAMPDIFF(HOUR, c.date_created, c.last_updated)) as avg_resolution_time
        FROM complaints c
        JOIN complaint_categories cc ON c.category_id = cc.id
        JOIN departments d ON cc.department_id = d.id
        WHERE c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'resolved')
        AND c.last_updated IS NOT NULL
        AND c.date_created IS NOT NULL
        GROUP BY d.id, d.name
        HAVING avg_resolution_time IS NOT NULL
        ORDER BY avg_resolution_time DESC
    ");
    
    // Debug output
    echo "<!-- Debug: SQL Query -->\n";
    $stmt->execute();
    $resolution_stats = $stmt->fetchAll();
    echo "<!-- Debug: Resolution Stats\n";
    var_dump($resolution_stats);
    echo "\n-->";
    
    // Add sample resolved complaints if none exist
    if (empty($resolution_stats)) {
        // Insert some resolved complaints
        $stmt = $pdo->prepare("
            INSERT INTO complaints (
                id, user_id, category_id, sub_category_id, title, description,
                status_id, priority_id, assigned_to, date_created, last_updated
            ) VALUES 
            (
                'COMP005',
                (SELECT id FROM user WHERE email = 'rahul.kumar@dtu.ac.in'),
                (SELECT c.id FROM complaint_categories c WHERE c.category_name = 'COE'),
                (SELECT cs.id FROM complaint_subcategories cs 
                JOIN complaint_categories c ON cs.category_id = c.id 
                WHERE c.category_name = 'COE' AND cs.name = 'Labs'),
                'Printer Not Working in Lab 2',
                'The printer in Lab 2 is not responding to print commands.',
                (SELECT id FROM complaint_status_types WHERE status_name = 'resolved'),
                (SELECT id FROM priority_levels WHERE level_name = 'medium'),
                (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'),
                DATE_SUB(NOW(), INTERVAL 48 HOUR),
                DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ),
            (
                'COMP006',
                (SELECT id FROM user WHERE email = 'sarah2021.wilson@dtu.ac.in'),
                (SELECT c.id FROM complaint_categories c WHERE c.category_name = 'Hostel'),
                (SELECT cs.id FROM complaint_subcategories cs 
                JOIN complaint_categories c ON cs.category_id = c.id 
                WHERE c.category_name = 'Hostel' AND cs.name = 'Infrastructure'),
                'Common Room AC Not Working',
                'The air conditioner in the girls hostel common room is not cooling properly.',
                (SELECT id FROM complaint_status_types WHERE status_name = 'resolved'),
                (SELECT id FROM priority_levels WHERE level_name = 'high'),
                (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'),
                DATE_SUB(NOW(), INTERVAL 72 HOUR),
                DATE_SUB(NOW(), INTERVAL 36 HOUR)
            )
        ");
        $stmt->execute();

        // Add complaint details for resolved complaints
        $stmt = $pdo->prepare("
            INSERT INTO complaint_details (complaint_id, resolution_comments, internal_notes) VALUES 
            ('COMP005', 'Printer cartridge replaced and test prints confirmed working', 'Hardware team resolved the issue'),
            ('COMP006', 'AC serviced and cooling restored to normal', 'External AC technician called for repairs')
        ");
        $stmt->execute();

        // Add complaint history for resolved complaints
        $stmt = $pdo->prepare("
            INSERT INTO complaint_history (complaint_id, status_id, comments, updated_by, timestamp) VALUES 
            ('COMP005', 
             (SELECT id FROM complaint_status_types WHERE status_name = 'resolved'),
             'Issue resolved - Printer is now working',
             (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'),
             DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ),
            ('COMP006',
             (SELECT id FROM complaint_status_types WHERE status_name = 'resolved'),
             'AC repaired and functioning normally',
             (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'),
             DATE_SUB(NOW(), INTERVAL 36 HOUR)
            )
        ");
        $stmt->execute();

        // Refresh resolution stats query
        $stmt = $pdo->prepare("
            SELECT 
                d.name as department,
                AVG(TIMESTAMPDIFF(HOUR, c.date_created, c.last_updated)) as avg_resolution_time
            FROM complaints c
            JOIN complaint_categories cc ON c.category_id = cc.id
            JOIN departments d ON cc.department_id = d.id
            WHERE c.status_id = (SELECT id FROM complaint_status_types WHERE status_name = 'resolved')
            AND c.last_updated IS NOT NULL
            AND c.date_created IS NOT NULL
            GROUP BY d.id, d.name
            HAVING avg_resolution_time IS NOT NULL
            ORDER BY avg_resolution_time DESC
        ");
        $stmt->execute();
        $resolution_stats = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: index.php?error=system_error');
    exit();
}

// Include header
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="analytics-dashboard">
        <div class="page-header mb-4">
            <h2 class="page-title">Analytics Dashboard</h2>
            <p class="text-muted">Comprehensive overview of complaint statistics and trends</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card analytics-card h-100">
                    <div class="card-body">
                        <h3 class="card-title">Complaints by Department</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Total</th>
                                        <th>Pending</th>
                                        <th>In Progress</th>
                                        <th>Resolved</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($department_stats as $stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['department']); ?></td>
                                            <td><strong><?php echo number_format($stat['total_complaints']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?php echo number_format($stat['pending']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark">
                                                    <?php echo number_format($stat['in_progress']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?php echo number_format($stat['resolved']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card analytics-card h-100">
                    <div class="card-body">
                        <h3 class="card-title">Complaints by Category</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Total</th>
                                        <th>Pending</th>
                                        <th>In Progress</th>
                                        <th>Resolved</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($category_stats as $stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['category_name']); ?></td>
                                            <td><strong><?php echo number_format($stat['total_complaints']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?php echo number_format($stat['pending']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark">
                                                    <?php echo number_format($stat['in_progress']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?php echo number_format($stat['resolved']); ?>
                                                </span>
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
        
        <div class="row g-4 mt-4">
            <div class="col-md-6">
                <div class="card analytics-card h-100">
                    <div class="card-body">
                        <h3 class="card-title">Complaint Trends</h3>
                        <p class="text-muted">Last 24 Hours</p>
                        <div class="chart-container">
                            <canvas id="trendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card analytics-card h-100">
                    <div class="card-body">
                        <h3 class="card-title">Unresolved Complaints</h3>
                        <p class="text-muted">By Priority Level</p>
                        <div class="chart-container">
                            <canvas id="priorityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-4">
            <div class="col-12">
                <div class="card analytics-card">
                    <div class="card-body">
                        <h3 class="card-title">Resolution Time Analysis</h3>
                        <p class="text-muted">Average time taken to resolve complaints by department</p>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Average Resolution Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resolution_stats as $stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['department']); ?></td>
                                            <td>
                                                <?php 
                                                // Debug: Show raw value
                                                echo '<!-- RAW: ' . var_export($stat['avg_resolution_time'], true) . ' -->';
                                                if (!is_null($stat['avg_resolution_time'])) {
                                                    // Always show as hours (1 decimal place)
                                                    echo '<strong>' . round($stat['avg_resolution_time'], 1) . '</strong> hours';
                                                } else {
                                                    echo "<span class='text-muted'>No resolved complaints</span>";
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($resolution_stats)): ?>
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">
                                                No resolution time data available
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #1a237e;
    --secondary-color: #0d47a1;
    --text-primary: #333;
    --text-secondary: #666;
    --bg-light: #f8f9fa;
    --transition: all 0.3s ease;
}

.page-header {
    text-align: center;
    margin-bottom: 2rem;
}

.page-title {
    color: var(--primary-color);
    font-weight: 600;
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.analytics-card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border-radius: 12px;
    transition: var(--transition);
}

.analytics-card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.card-title {
    color: var(--primary-color);
    font-weight: 600;
    font-size: 1.25rem;
    margin-bottom: 1rem;
}

.table {
    margin-bottom: 0;
}

.table thead th {
    background: var(--bg-light);
    border-bottom: 2px solid #dee2e6;
    color: var(--text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    padding: 1rem;
}

.table tbody tr {
    transition: var(--transition);
}

.table tbody tr:hover {
    background-color: rgba(26, 35, 126, 0.05);
}

.table td {
    padding: 1rem;
    vertical-align: middle;
}

.badge {
    padding: 0.5em 1em;
    font-weight: 500;
    border-radius: 6px;
}

.chart-container {
    position: relative;
    height: 300px !important;
    width: 100%;
    margin-top: 1rem;
}

.text-muted {
    color: var(--text-secondary) !important;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .analytics-card {
        margin-bottom: 1rem;
    }
    
    .table {
        font-size: 0.875rem;
    }
    
    .badge {
        font-size: 0.75rem;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Prepare data for trends chart
var dates = <?php echo json_encode(array_column($trends, 'complaint_hour')); ?>;
var counts = <?php echo json_encode(array_column($trends, 'total_complaints')); ?>;

// Create an array of all hours in the last 24 hours
var now = new Date();
var allHours = [];
var hourlyData = new Map();

// Initialize hourly data with 0 counts
for (let i = 23; i >= 0; i--) {
    let hour = new Date(now - i * 3600000);
    hour.setMinutes(0, 0, 0);
    allHours.push(hour.toISOString());
    hourlyData.set(hour.toISOString().slice(0, 13), 0);
}

// Fill in actual complaint counts
dates.forEach((date, index) => {
    let hourKey = new Date(date).toISOString().slice(0, 13);
    hourlyData.set(hourKey, counts[index]);
});

// Prepare final datasets
var formattedDates = allHours.map(date => 
    new Date(date).toLocaleTimeString([], { 
        hour: '2-digit',
        minute: '2-digit'
    })
);

var formattedCounts = allHours.map(hour => 
    hourlyData.get(hour.slice(0, 13)) || 0
);

// Create trends chart
var ctx = document.getElementById('trendsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: formattedDates,
        datasets: [{
            label: 'Number of Complaints',
            data: formattedCounts,
            borderColor: '#1a237e',
            backgroundColor: 'rgba(26, 35, 126, 0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: '#1a237e',
            pointBorderColor: '#1a237e'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)',
                    drawBorder: false
                },
                ticks: {
                    stepSize: 1,
                    font: {
                        size: 11
                    },
                    padding: 10
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    maxRotation: 45,
                    minRotation: 45,
                    font: {
                        size: 11
                    },
                    padding: 10,
                    autoSkip: true,
                    maxTicksLimit: 12
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'white',
                titleColor: '#666',
                bodyColor: '#333',
                borderColor: '#e1e1e1',
                borderWidth: 1,
                padding: 12,
                displayColors: false,
                callbacks: {
                    title: function(context) {
                        return allHours[context[0].dataIndex];
                    }
                }
            }
        }
    }
});

// Prepare data for priority pie chart
var priorities = <?php echo json_encode(array_column($unresolved_by_priority, 'priority')); ?>;
var priorityCounts = <?php echo json_encode(array_column($unresolved_by_priority, 'count')); ?>;

// Create priority pie chart
var priorityCtx = document.getElementById('priorityChart').getContext('2d');
new Chart(priorityCtx, {
    type: 'doughnut',
    data: {
        labels: priorities,
        datasets: [{
            data: priorityCounts,
            backgroundColor: [
                '#28a745',  // Low - Green
                '#ffc107',  // Medium - Yellow
                '#dc3545'   // High - Red
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    padding: 20,
                    font: {
                        size: 12
                    },
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            tooltip: {
                backgroundColor: 'white',
                titleColor: '#666',
                bodyColor: '#333',
                borderColor: '#e1e1e1',
                borderWidth: 1,
                padding: 12,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.raw || 0;
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?> 