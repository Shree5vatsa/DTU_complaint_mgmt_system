<?php
// Set error reporting
error_reporting(-1);
ini_set('display_errors', 1);

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
    $auth->logout();
    header('Location: login.php');
    exit();
}

// Get complaint ID from URL
$complaint_id = $_GET['id'] ?? '';
if (empty($complaint_id)) {
    header('Location: index.php');
    exit();
}

try {
    // Get complaint details
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.name as submitted_by,
            u.email as submitted_by_email,
            d.name as department_name,
            cc.category_name,
            cs.name as subcategory_name,
            cst.status_name,
            pl.level_name as priority_name,
            assigned.name as assigned_to_name,
            assigned.email as assigned_to_email,
            cd.resolution_comments,
            cd.internal_notes
        FROM complaints c
        JOIN user u ON c.user_id = u.id
        JOIN complaint_categories cc ON c.category_id = cc.id
        JOIN departments d ON cc.department_id = d.id
        LEFT JOIN complaint_subcategories cs ON c.sub_category_id = cs.id
        JOIN complaint_status_types cst ON c.status_id = cst.id
        JOIN priority_levels pl ON c.priority_id = pl.id
        LEFT JOIN user assigned ON c.assigned_to = assigned.id
        LEFT JOIN complaint_details cd ON c.id = cd.complaint_id
        WHERE c.id = ?
    ");
    
    $stmt->execute([$complaint_id]);
    $complaint = $stmt->fetch();
    
    if (!$complaint) {
        header('Location: index.php');
        exit();
    }
    
    // Get all status types for dropdown
    $stmt = $pdo->prepare("SELECT id, status_name FROM complaint_status_types ORDER BY id");
    $stmt->execute();
    $status_types = $stmt->fetchAll();
    
    // Check if user has permission to view this complaint
    $hasPermission = false;
    switch ($user['role_id']) {
        case 1: // Administrator - can view all complaints
            $hasPermission = true;
            break;
            
        case 2: // HOD - can view department complaints
            $hasPermission = ($complaint['department_id'] == $user['department_id']);
            break;
            
        case 3: // Warden - can view hostel complaints
            $hasPermission = ($complaint['category_name'] == 'Hostel');
            break;
            
        case 4: // Teacher - can view department complaints
            $hasPermission = ($complaint['department_id'] == $user['department_id']);
            break;
            
        case 5: // Student - can only view their own complaints
            $hasPermission = ($complaint['user_id'] == $user['id']);
            break;
            
        default:
            $hasPermission = ($complaint['user_id'] == $user['id']);
    }
    
    if (!$hasPermission) {
        header('Location: index.php?error=permission_denied');
        exit();
    }
    
    // Get complaint history
    $stmt = $pdo->prepare("
        SELECT 
            ch.*,
            u.name as updated_by_name,
            cst.status_name
        FROM complaint_history ch
        JOIN user u ON ch.updated_by = u.id
        JOIN complaint_status_types cst ON ch.status_id = cst.id
        WHERE ch.complaint_id = ?
        ORDER BY ch.timestamp DESC
    ");
    $stmt->execute([$complaint_id]);
    $history = $stmt->fetchAll();
    
    // Handle status update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_POST['action'] === 'update_status' && $auth->hasPermission('resolve_complaint')) {
            $new_status = $_POST['status'];
            $comments = $_POST['comments'];
            
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Update complaint status
                $stmt = $pdo->prepare("UPDATE complaints SET status_id = ? WHERE id = ?");
                $stmt->execute([$new_status, $complaint_id]);
                
                // Add to history
                $stmt = $pdo->prepare("
                    INSERT INTO complaint_history (complaint_id, status_id, comments, updated_by)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$complaint_id, $new_status, $comments, $user['id']]);
                
                // If resolved, update resolution comments
                if ($new_status == 3) { // Assuming 3 is 'resolved' status
                    $stmt = $pdo->prepare("
                        INSERT INTO complaint_details (complaint_id, resolution_comments)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE resolution_comments = ?
                    ");
                    $stmt->execute([$complaint_id, $comments, $comments]);
                }
                
                $pdo->commit();
                header("Location: view_complaint.php?id=$complaint_id&success=1");
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log($e->getMessage());
                header("Location: view_complaint.php?id=$complaint_id&error=1");
                exit();
            }
        }
    }
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: index.php?error=system_error');
    exit();
}

// Include header
include 'includes/header.php';
?>

<div class="container">
    <div class="complaint-header">
        <h1><?php echo htmlspecialchars($complaint['title']); ?></h1>
        <div class="complaint-id">
            ID: <?php echo htmlspecialchars($complaint['id']); ?>
            <span class="status-badges">
                <span class="status-badge <?php echo $complaint['status_name']; ?>">
                    <?php echo ucfirst($complaint['status_name']); ?>
                </span>
                <span class="priority-badge <?php echo strtolower($complaint['priority_name']); ?>">
                    <?php echo ucfirst($complaint['priority_name']); ?>
                </span>
            </span>
        </div>
    </div>

    <div class="complaint-details-grid">
        <div class="complaint-info">
            <h2>Complaint Details</h2>
            <div class="info-row">
                <span class="info-label">Department:</span>
                <span class="info-value"><?php echo htmlspecialchars($complaint['department_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Category:</span>
                <span class="info-value"><?php echo htmlspecialchars($complaint['category_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Submitted By:</span>
                <span class="info-value"><?php echo htmlspecialchars($complaint['submitted_by']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date Submitted:</span>
                <span class="info-value"><?php echo date('d M Y H:i', strtotime($complaint['date_created'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Last Updated:</span>
                <span class="info-value"><?php echo date('d M Y H:i', strtotime($complaint['last_updated'])); ?></span>
            </div>
        </div>

        <div class="complaint-description">
            <h2>Description</h2>
            <div class="description-content"><?php echo nl2br(htmlspecialchars(trim($complaint['description']))); ?></div>
        </div>
    </div>

    <?php if ($auth->hasPermission('update_complaint_status')): ?>
    <div class="update-status">
        <h2>Update Status</h2>
        <form class="status-form" method="POST">
            <select name="status_id" class="form-control" required>
                <option value="">Select Status</option>
                <?php foreach ($status_types as $status): ?>
                    <option value="<?php echo $status['id']; ?>">
                        <?php echo ucfirst($status['status_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <textarea name="comments" class="form-control" placeholder="Add comments..." required></textarea>
            <button type="submit" class="btn btn-primary">Update Status</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="timeline">
        <h2>History</h2>
        <?php foreach ($history as $index => $entry): ?>
            <div class="timeline-item">
                <div class="timeline-badge <?php echo $entry['status_name']; ?>">
                    <i class="fas fa-circle"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span>
                            <strong><?php echo ucfirst($entry['status_name']); ?></strong>
                        </span>
                        <span>
                            <?php echo date('d M Y H:i', strtotime($entry['timestamp'])); ?>
                        </span>
                    </div>
                    <div class="timeline-body">
                        <?php echo htmlspecialchars($entry['comments']); ?>
                        <div class="text-muted mt-1">
                            <small>Updated by <?php echo htmlspecialchars($entry['updated_by_name']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 