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
            d.code as department_code,
            cc.category_name,
            cs.name as subcategory_name,
            cst.status_name,
            pl.level_name as priority_name,
            assigned.name as assigned_to_name,
            u.role_id as submitter_role_id
        FROM complaints c
        JOIN user u ON c.user_id = u.id
        JOIN complaint_categories cc ON c.category_id = cc.id
        LEFT JOIN departments d ON cc.department_id = d.id
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
            
        case 2: // HOD - can see department complaints and special categories
            $sql .= " AND (cc.department_id = ? OR cc.category_name IN ('Harassment', 'Misbehavior', 'Ragging') OR c.user_id = ?)";
            $params[] = $user['department_id'];
            $params[] = $user['id'];
            break;
            
        case 3: // Warden - can see hostel complaints and special categories
            $sql .= " AND (cc.category_name = 'Hostel' OR cc.category_name IN ('Harassment', 'Misbehavior', 'Ragging') OR c.user_id = ?)";
            $params[] = $user['id'];
            break;
            
        case 4: // Teacher - can see department complaints and special categories
            // Get the teacher's department code
            $stmt = $pdo->prepare("SELECT d.code FROM departments d WHERE d.id = ?");
            $stmt->execute([$user['department_id']]);
            $dept_code = $stmt->fetchColumn();
            
            $sql .= " AND (
                (cc.category_name = ? AND (
                    (cs.name = 'Academic' AND u.role_id = 5) OR  /* Student academic complaints */
                    (u.role_id = 4 AND u.department_id = ?)      /* Teacher complaints from same department */
                )) OR 
                cc.category_name IN ('Harassment', 'Misbehavior', 'Ragging') OR 
                c.user_id = ?
            )";
            $params[] = $dept_code;
            $params[] = $user['department_id'];
            $params[] = $user['id'];
            break;
            
        case 5: // Student - can see all student complaints and special categories
            $sql .= " AND (
                (u.role_id = 5) OR  /* All student complaints */
                cc.category_name IN ('Harassment', 'Misbehavior', 'Ragging') OR
                c.user_id = ?  /* Their own complaints */
            )";
            $params[] = $user['id'];
            break;
            
        default:
            // For any other role, show only their own complaints and special categories
            $sql .= " AND (c.user_id = ? OR cc.category_name IN ('Harassment', 'Misbehavior', 'Ragging'))";
            $params[] = $user['id'];
    }
    
    // Order by most recent first
    $sql .= " ORDER BY c.date_created DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();
    
    // Get statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN cst.status_name = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN cst.status_name = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN cst.status_name = 'resolved' THEN 1 ELSE 0 END) as resolved
        FROM complaints c
        JOIN user u ON c.user_id = u.id
        JOIN complaint_status_types cst ON c.status_id = cst.id
        JOIN complaint_categories cc ON c.category_id = cc.id
        LEFT JOIN complaint_subcategories cs ON c.sub_category_id = cs.id
        LEFT JOIN departments d ON cc.department_id = d.id
        WHERE 1=1
    ";
    
    // Add role-based filters for statistics (using the same logic as above)
    switch ($user['role_id']) {
        case 1: // Administrator - can see all complaints
            break;
            
        case 2: // HOD - can see department complaints and special categories
            $stats_sql .= " AND (cc.department_id = ? OR cc.category_name IN ('Harassment', 'Misbehavior', 'Ragging') OR c.user_id = ?)";
            break;
            
        case 3: // Warden - can see hostel complaints and special categories
            $stats_sql .= " AND (cc.category_name = 'Hostel' OR cc.category_name IN ('Harassment', 'Misbehavior', 'Ragging') OR c.user_id = ?)";
            break;
            
        case 4: // Teacher - can see department complaints and special categories
            $stats_sql .= " AND (
                (cc.category_name = ? AND (
                    (cs.name = 'Academic' AND u.role_id = 5) OR  /* Student academic complaints */
                    (u.role_id = 4 AND u.department_id = ?)      /* Teacher complaints from same department */
                )) OR 
                cc.category_name IN ('Harassment', 'Misbehavior', 'Ragging') OR 
                c.user_id = ?
            )";
            break;
            
        case 5: // Student - can see all student complaints and special categories
            $stats_sql .= " AND (
                (u.role_id = 5) OR  /* All student complaints */
                cc.category_name IN ('Harassment', 'Misbehavior', 'Ragging') OR
                c.user_id = ?  /* Their own complaints */
            )";
            break;
            
        default:
            // For any other role, show only their own complaints and special categories
            $stats_sql .= " AND (c.user_id = ? OR cc.category_name IN ('Harassment', 'Misbehavior', 'Ragging'))";
    }
    
    // Use the same parameters as the main query
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure all stats have a value
    $stats = array_map('intval', $stats);
    
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
