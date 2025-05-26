<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_role = getUserRole($user_id);

// Validate complaint ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?error=invalid_complaint');
    exit();
}

$complaint_id = $_GET['id'];

// Get complaint details with joins
$stmt = $pdo->prepare("
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
    WHERE c.id = ?
");

try {
    $stmt->execute([$complaint_id]);
    $complaint = $stmt->fetch();
    
    if (!$complaint) {
        header('Location: index.php?error=complaint_not_found');
        exit();
    }
    
    // Check if user has permission to view this complaint
    if (!hasPermission($user_id, 'view_all_complaints') && 
        !hasPermission($user_id, 'view_department_complaints') && 
        $complaint['user_id'] != $user_id) {
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
    
    // Get attachments
    $stmt = $pdo->prepare("
        SELECT * FROM complaint_attachments 
        WHERE complaint_id = ?
        ORDER BY upload_date DESC
    ");
    $stmt->execute([$complaint_id]);
    $attachments = $stmt->fetchAll();
    
    // Handle status update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'update_status' && hasPermission($user_id, 'resolve_complaint')) {
            $new_status_id = (int)$_POST['new_status_id'];
            $comments = trim($_POST['comments']);
            
            if ($new_status_id > 0) {
                $pdo->beginTransaction();
                
                try {
                    // Update complaint status
                    $stmt = $pdo->prepare("
                        UPDATE complaints 
                        SET status_id = ?, last_updated = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_status_id, $complaint_id]);
                    
                    // Add to history
                    $stmt = $pdo->prepare("
                        INSERT INTO complaint_history (
                            complaint_id, status_id, comments, updated_by
                        ) VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$complaint_id, $new_status_id, $comments, $user_id]);
                    
                    $pdo->commit();
                    header('Location: view_complaint.php?id=' . $complaint_id . '&success=status_updated');
                    exit();
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $update_error = "Failed to update status. Please try again.";
                    error_log($e->getMessage());
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: index.php?error=system_error');
    exit();
}

// Get available status types for update
$stmt = $pdo->prepare("SELECT * FROM complaint_status_types ORDER BY id");
$stmt->execute();
$status_types = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Complaint - DTU Complaint Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="complaint-header">
            <h1>Complaint Details</h1>
            <div class="status-badge <?php echo strtolower($complaint['status_name']); ?>">
                <?php echo htmlspecialchars($complaint['status_name']); ?>
            </div>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                Status updated successfully.
            </div>
        <?php endif; ?>
        
        <?php if (isset($update_error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($update_error); ?>
            </div>
        <?php endif; ?>
        
        <div class="complaint-details">
            <div class="detail-row">
                <label>Complaint ID:</label>
                <span><?php echo htmlspecialchars($complaint['id']); ?></span>
            </div>
            
            <div class="detail-row">
                <label>Title:</label>
                <span><?php echo htmlspecialchars($complaint['title']); ?></span>
            </div>
            
            <div class="detail-row">
                <label>Department:</label>
                <span><?php echo htmlspecialchars($complaint['department_name']); ?></span>
            </div>
            
            <div class="detail-row">
                <label>Category:</label>
                <span><?php echo htmlspecialchars($complaint['category_name']); ?></span>
            </div>
            
            <?php if ($complaint['subcategory_name']): ?>
            <div class="detail-row">
                <label>Subcategory:</label>
                <span><?php echo htmlspecialchars($complaint['subcategory_name']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="detail-row">
                <label>Priority:</label>
                <span class="priority-badge <?php echo strtolower($complaint['priority_name']); ?>">
                    <?php echo htmlspecialchars($complaint['priority_name']); ?>
                </span>
            </div>
            
            <div class="detail-row">
                <label>Submitted By:</label>
                <span><?php echo htmlspecialchars($complaint['submitted_by']); ?></span>
            </div>
            
            <div class="detail-row">
                <label>Submitted On:</label>
                <span><?php echo date('d M Y H:i', strtotime($complaint['date_created'])); ?></span>
            </div>
            
            <?php if ($complaint['assigned_to_name']): ?>
            <div class="detail-row">
                <label>Assigned To:</label>
                <span><?php echo htmlspecialchars($complaint['assigned_to_name']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="detail-row">
                <label>Description:</label>
                <div class="description-box">
                    <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
                </div>
            </div>
            
            <?php if (!empty($attachments)): ?>
            <div class="detail-row">
                <label>Attachments:</label>
                <div class="attachments-list">
                    <?php foreach ($attachments as $attachment): ?>
                        <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                           target="_blank" class="attachment-link">
                            <?php echo htmlspecialchars($attachment['file_name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (hasPermission($user_id, 'resolve_complaint')): ?>
        <div class="update-status-section">
            <h2>Update Status</h2>
            <form method="POST" class="status-form">
                <input type="hidden" name="action" value="update_status">
                
                <div class="form-group">
                    <label for="new_status_id">New Status:</label>
                    <select name="new_status_id" id="new_status_id" required class="form-control">
                        <option value="">Select Status</option>
                        <?php foreach ($status_types as $status): ?>
                            <option value="<?php echo $status['id']; ?>">
                                <?php echo htmlspecialchars($status['status_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="comments">Comments:</label>
                    <textarea name="comments" id="comments" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Update Status</button>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="complaint-history">
            <h2>Complaint History</h2>
            <?php if (!empty($history)): ?>
                <div class="timeline">
                    <?php foreach ($history as $entry): ?>
                        <div class="timeline-item">
                            <div class="timeline-badge <?php echo strtolower($entry['status_name']); ?>">
                                <?php echo htmlspecialchars($entry['status_name']); ?>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    Updated by <?php echo htmlspecialchars($entry['updated_by_name']); ?>
                                    on <?php echo date('d M Y H:i', strtotime($entry['timestamp'])); ?>
                                </div>
                                <?php if ($entry['comments']): ?>
                                    <div class="timeline-body">
                                        <?php echo nl2br(htmlspecialchars($entry['comments'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No history available.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html> 