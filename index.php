<?php
/**
 * Complaint Management System
 */

// Set error reporting
error_reporting(-1);
ini_set('display_errors', 1);

// Start the session
session_start();

// Include required files
require_once 'includes/db_config.php';
require_once 'includes/auth.php';

// Initialize Auth class
$auth = new Auth($pdo);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user details
$user = $auth->getCurrentUser();
if (!$user) {
    // If we can't get user details, log them out
    $auth->logout();
    header('Location: login.php');
    exit();
}

try {
    // Get complaints based on user role and permissions
    $params = [];
    $sql = "
        SELECT 
            c.*,
            u.name as submitted_by,
            d.name as department_name,
            cc.category_name,
            cs.name as subcategory_name,
            cst.status_name,
            pl.level_name as priority_name,
            assigned.name as assigned_to_name
        FROM complaints c
        JOIN user u ON c.user_id = u.id
        JOIN complaint_categories cc ON c.category_id = cc.id
        JOIN departments d ON cc.department_id = d.id
        LEFT JOIN complaint_subcategories cs ON c.sub_category_id = cs.id
        JOIN complaint_status_types cst ON c.status_id = cst.id
        JOIN priority_levels pl ON c.priority_id = pl.id
        LEFT JOIN user assigned ON c.assigned_to = assigned.id
        WHERE 1=1
    ";
    
    // Add role-based filters
    switch ($user['role_id']) {
        case 1: // Administrator - can see all complaints
            break;
            
        case 2: // HOD - can see department complaints
            $sql .= " AND cc.department_id = ?";
            $params[] = $user['department_id'];
            break;
            
        case 3: // Warden - can see hostel complaints
            $sql .= " AND cc.category_name = 'Hostel'";
            break;
            
        case 4: // Teacher - can see department complaints
            $sql .= " AND cc.department_id = ?";
            $params[] = $user['department_id'];
            break;
            
        case 5: // Student - can only see their own complaints
            $sql .= " AND c.user_id = ?";
            $params[] = $user['id'];
            break;
            
        default:
            // For any other role, show only their own complaints
            $sql .= " AND c.user_id = ?";
            $params[] = $user['id'];
    }
    
    $sql .= " ORDER BY c.date_created DESC LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();
    
    // Get statistics
    $stats = [
        'total' => 0,
        'pending' => 0,
        'in_progress' => 0,
        'resolved' => 0
    ];
    
    foreach ($complaints as $complaint) {
        $stats['total']++;
        $stats[$complaint['status_name']]++;
    }
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: index.php?error=system_error');
    exit();
}
?>
<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="dashboard-header">
        <h1>Dashboard</h1>
        <?php if ($auth->hasPermission('create_complaint')): ?>
            <a href="submit_complaint.php" class="btn btn-primary">Submit New Complaint</a>
        <?php endif; ?>
    </div>
    
    <div class="stats-cards">
        <div class="stat-card total">
            <h3>Total Complaints</h3>
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
        </div>
        
        <div class="stat-card pending">
            <h3>Pending</h3>
            <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
        </div>
        
        <div class="stat-card in-progress">
            <h3>In Progress</h3>
            <div class="stat-number"><?php echo number_format($stats['in_progress']); ?></div>
        </div>
        
        <div class="stat-card resolved">
            <h3>Resolved</h3>
            <div class="stat-number"><?php echo number_format($stats['resolved']); ?></div>
        </div>
    </div>
    
    <div class="complaints-list">
        <h2>Recent Complaints</h2>
        
        <?php if (empty($complaints)): ?>
            <div class="alert alert-info">No complaints found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Department</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Submitted By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $complaint): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($complaint['id']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['title']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['department_name']); ?></td>
                                <td>
                                    <?php 
                                    echo htmlspecialchars($complaint['category_name']);
                                    if ($complaint['subcategory_name']) {
                                        echo ' - ' . htmlspecialchars($complaint['subcategory_name']);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($complaint['status_name']); ?>">
                                        <?php echo htmlspecialchars($complaint['status_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="priority-badge <?php echo strtolower($complaint['priority_name']); ?>">
                                        <?php echo htmlspecialchars($complaint['priority_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($complaint['submitted_by']); ?></td>
                                <td><?php echo date('d M Y', strtotime($complaint['date_created'])); ?></td>
                                <td>
                                    <a href="view_complaint.php?id=<?php echo urlencode($complaint['id']); ?>" 
                                       class="btn btn-sm btn-info">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
