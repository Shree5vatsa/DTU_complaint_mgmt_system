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

// Define categories that are visible to all users
$public_categories = ['Harassment', 'Misbehavior', 'Ragging'];

try {
    // Get complaint details
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.name as submitted_by,
            u.email as submitted_by_email,
            CASE 
                WHEN cc.category_name = 'Library' THEN 'Library'
                WHEN cc.category_name = 'Hostel' THEN 'Hostel'
                WHEN cc.category_name IN ('Harassment', 'Misbehavior', 'Ragging') THEN cc.category_name
                ELSE d.name 
            END as department_name,
            cc.category_name,
            cc.department_id,
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
        LEFT JOIN departments d ON cc.department_id = d.id
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
    
    // First check if it's a special category - these are visible to everyone
    if (in_array($complaint['category_name'], $public_categories)) {
        $hasPermission = true;
    } 
    // Then check role-specific permissions
    else {
        switch ($user['role_id']) {
            case 1: // Administrator - can view all complaints
                $hasPermission = true;
                break;
                
            case 2: // HOD - can see department complaints and their own
                $hasPermission = ($complaint['department_id'] && $complaint['department_id'] == $user['department_id']) || 
                               ($complaint['user_id'] == $user['id']) ||
                               in_array($complaint['category_name'], ['Library', 'Harassment', 'Misbehavior', 'Ragging']);
                break;
                
            case 3: // Warden - can see hostel complaints and their own
                $hasPermission = ($complaint['category_name'] == 'Hostel') || 
                               ($complaint['user_id'] == $user['id']) ||
                               in_array($complaint['category_name'], ['Library', 'Harassment', 'Misbehavior', 'Ragging']);
                break;
                
            case 4: // Teacher - can see department academic complaints from students and their own
                // Check if complaint was submitted by a student
                $stmt = $pdo->prepare("
                    SELECT 1 FROM user 
                    WHERE id = ? AND role_id = 5
                    LIMIT 1
                ");
                $stmt->execute([$complaint['user_id']]);
                $isStudentComplaint = $stmt->fetchColumn() > 0;

                // Get the department code for the complaint's category
                $stmt = $pdo->prepare("
                    SELECT d.code 
                    FROM complaint_categories cc
                    LEFT JOIN departments d ON cc.department_id = d.id
                    WHERE cc.id = ?
                ");
                $stmt->execute([$complaint['category_id']]);
                $departmentCode = $stmt->fetchColumn();

                // Check if complaint is from a teacher in same department
                $stmt = $pdo->prepare("
                    SELECT 1 FROM user 
                    WHERE id = ? AND role_id = 4 AND department_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$complaint['user_id'], $user['department_id']]);
                $isTeacherFromSameDept = $stmt->fetchColumn() > 0;
                
                $hasPermission = (
                    // Can view academic complaints from their department
                    ($complaint['department_id'] == $user['department_id'] && 
                     $departmentCode && 
                     $complaint['subcategory_name'] == 'Academic' && 
                     $isStudentComplaint)
                    ) || 
                    // Can view complaints from teachers in same department
                    $isTeacherFromSameDept ||
                    // Can view their own complaints
                    ($complaint['user_id'] == $user['id']) ||
                    // Can view special categories
                    in_array($complaint['category_name'], ['Library', 'Harassment', 'Misbehavior', 'Ragging']);
                break;
                
            case 5: // Student - can see all student complaints and special categories
                // Check if complaint was submitted by a student
                $stmt = $pdo->prepare("
                    SELECT 1 FROM user 
                    WHERE id = ? AND role_id = 5
                    LIMIT 1
                ");
                $stmt->execute([$complaint['user_id']]);
                $isStudentComplaint = $stmt->fetchColumn() > 0;

                $hasPermission = (
                    $isStudentComplaint || // Can see all student complaints
                    $complaint['user_id'] == $user['id'] || // Can see their own complaints
                    in_array($complaint['category_name'], $public_categories) // Can see special categories
                );
                break;
                
            default:
                // For any other role, show only their own complaints and special categories
                $hasPermission = (
                    $complaint['user_id'] == $user['id'] || 
                    in_array($complaint['category_name'], $public_categories)
                );
                break;
        }
    }
    
    if (!$hasPermission) {
        header('Location: index.php?error=permission_denied');
        exit();
    }
    
    // Check if user has permission to update status
    $canUpdateStatus = false;
    
    // No user can update their own complaints
    if ($complaint['user_id'] != $user['id']) {
        switch ($user['role_id']) {
            case 1: // Administrator - can update all complaints
                $canUpdateStatus = true;
                break;
                
            case 2: // HOD - can update department complaints and special categories
                $canUpdateStatus = 
                    // Department complaints
                    ($complaint['department_id'] && $complaint['department_id'] == $user['department_id']) ||
                    // Special categories
                    in_array($complaint['category_name'], ['Harassment', 'Misbehavior', 'Ragging']);
                break;
                
            case 3: // Warden - can update hostel complaints and special categories
                $canUpdateStatus = 
                    $complaint['category_name'] == 'Hostel' ||
                    in_array($complaint['category_name'], ['Harassment', 'Misbehavior', 'Ragging']);
                break;
                
            case 4: // Teacher - can only update academic complaints from students in their department
                // Get the department code for the complaint's category
                $stmt = $pdo->prepare("
                    SELECT d.code 
                    FROM complaint_categories cc
                    JOIN departments d ON cc.department_id = d.id
                    WHERE cc.id = ?
                ");
                $stmt->execute([$complaint['category_id']]);
                $departmentCode = $stmt->fetchColumn();
                
                // Check if complaint was submitted by a student
                $stmt = $pdo->prepare("
                    SELECT 1 FROM user 
                    WHERE id = ? AND role_id = 5
                    LIMIT 1
                ");
                $stmt->execute([$complaint['user_id']]);
                $isStudentComplaint = $stmt->fetchColumn() > 0;
                
                $canUpdateStatus = 
                    $complaint['department_id'] == $user['department_id'] && 
                    $departmentCode && // Ensure it's a department category
                    $complaint['subcategory_name'] == 'Academic' &&
                    $isStudentComplaint;
                break;
                
            case 5: // Students cannot update any complaints
                $canUpdateStatus = false;
                break;
                
            default: // Students and others cannot update any complaints
                $canUpdateStatus = false;
        }
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
        // Check if user has permission to update this complaint
        if (!$canUpdateStatus) {
            header("Location: view_complaint.php?id=$complaint_id&error=permission_denied");
            exit();
        }
        
        $new_status = $_POST['status_id'];
        $comments = $_POST['comments'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Update complaint status and last_updated timestamp
            $stmt = $pdo->prepare("UPDATE complaints SET status_id = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$new_status, $complaint_id]);
            
            // Add to history
            $stmt = $pdo->prepare("
                INSERT INTO complaint_history (complaint_id, status_id, comments, updated_by, timestamp)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
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
            header("Location: view_complaint.php?id=$complaint_id&success=status_updated");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error updating complaint status: " . $e->getMessage());
            header("Location: view_complaint.php?id=$complaint_id&error=update_failed");
            exit();
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
            <?php if ($complaint['subcategory_name']): ?>
            <div class="info-row">
                <span class="info-label">Subcategory:</span>
                <span class="info-value"><?php echo htmlspecialchars($complaint['subcategory_name']); ?></span>
            </div>
            <?php endif; ?>
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

    <?php if ($canUpdateStatus): ?>
    <div class="update-status">
        <h2>Update Status</h2>
        <form class="status-form" method="POST" action="">
            <input type="hidden" name="action" value="update_status">
            <select name="status_id" class="form-control" required>
                <option value="">Select Status</option>
                <?php foreach ($status_types as $status): ?>
                    <option value="<?php echo $status['id']; ?>" <?php echo ($complaint['status_id'] == $status['id']) ? 'selected' : ''; ?>>
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