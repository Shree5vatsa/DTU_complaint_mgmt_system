<?php
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

$success_message = '';
$error_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $gender = $_POST['gender'] ?? '';
        
        // Validate input
        if (empty($name)) {
            $error_message = 'Name cannot be empty.';
        } elseif (!preg_match('/^[a-zA-Z .\'-]{2,100}$/', $name)) {
            $error_message = 'Name should only contain letters, spaces, dots, apostrophes, and hyphens (2-100 characters).';
        } elseif (!empty($new_password)) {
            // Password change requested
            if (empty($current_password)) {
                $error_message = 'Current password is required to set a new password.';
            } elseif (strlen($new_password) < 6) {
                $error_message = 'New password must be at least 6 characters long.';
            } elseif ($new_password !== $confirm_password) {
                $error_message = 'New passwords do not match.';
            } else {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM user WHERE id = ?");
                $stmt->execute([$user['id']]);
                $current_hash = $stmt->fetchColumn();
                
                if (!password_verify($current_password, $current_hash)) {
                    $error_message = 'Current password is incorrect.';
                }
            }
        }
        
        // Update profile if no errors
        if (empty($error_message)) {
            try {
                $pdo->beginTransaction();
                
                // Update name
                $stmt = $pdo->prepare("UPDATE user SET name = ?, gender = ? WHERE id = ?");
                $stmt->execute([$name, $gender, $user['id']]);
                
                // Update password if provided
                if (!empty($new_password)) {
                    $hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE id = ?");
                    $stmt->execute([$hash, $user['id']]);
                }
                
                $pdo->commit();
                $success_message = 'Profile updated successfully.';
                
                // Update session
                $_SESSION['user_name'] = $name;
                
                // Refresh user data after update
                $stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
                $stmt->execute([$user['id']]);
                $user = $stmt->fetch();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = 'Failed to update profile. Please try again.';
                error_log($e->getMessage());
            }
        }
    }
    
    // Handle profile picture upload
    elseif ($_POST['action'] === 'update_picture' && isset($_FILES['profile_picture'])) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'Failed to upload file.';
        } elseif (!in_array($file['type'], $allowed_types)) {
            $error_message = 'Invalid file type. Only JPG, PNG and GIF are allowed.';
        } elseif ($file['size'] > $max_size) {
            $error_message = 'File is too large. Maximum size is 5MB.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $user['id'] . '_' . time() . '.' . $ext;
            $upload_path = 'uploads/profile_pictures/' . $new_filename;
            
            // Create directory if it doesn't exist
            if (!file_exists('uploads/profile_pictures')) {
                mkdir('uploads/profile_pictures', 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Update database
                try {
                    $stmt = $pdo->prepare("UPDATE user SET image = ? WHERE id = ?");
                    $stmt->execute([$new_filename, $user['id']]);
                    $success_message = 'Profile picture updated successfully.';
                } catch (PDOException $e) {
                    $error_message = 'Failed to update profile picture in database.';
                    error_log($e->getMessage());
                }
            } else {
                $error_message = 'Failed to save uploaded file.';
            }
        }
    }
}

// Get user's complaints
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            cc.category_name,
            cs.name as subcategory_name,
            cst.status_name,
            pl.level_name as priority_name
        FROM complaints c
        JOIN complaint_categories cc ON c.category_id = cc.id
        LEFT JOIN complaint_subcategories cs ON c.sub_category_id = cs.id
        JOIN complaint_status_types cst ON c.status_id = cst.id
        JOIN priority_levels pl ON c.priority_id = pl.id
        WHERE c.user_id = ?
        ORDER BY c.date_created DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $recent_complaints = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $recent_complaints = [];
}

// Include header
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="profile-picture-container mb-3">
                        <img src="<?php 
                            if (isset($user['image']) && $user['image'] !== 'default.jpg') {
                                echo 'uploads/profile_pictures/' . htmlspecialchars($user['image']);
                            } else {
                                echo isset($user['gender']) && $user['gender'] === 'Female' ? 'woman.png' : 'man.png';
                            }
                        ?>" alt="Profile Picture" class="profile-picture">
                        
                        <?php if ($auth->hasPermission('update_profile')): ?>
                            <button type="button" class="profile-picture-edit" data-bs-toggle="modal" data-bs-target="#updatePictureModal">
                                <i class="fas fa-camera"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <h5 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h5>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($user['role_name']); ?></p>
                    <p class="text-muted mb-4"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
            
            <!-- Department Information -->
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Department Information</h6>
                    <hr>
                    <p class="mb-0"><strong>Department:</strong> <?php echo htmlspecialchars($user['department_name'] ?? 'Not assigned'); ?></p>
                    <?php if ($user['role_name'] === 'Student' && !empty($user['roll_number'])): ?>
                        <p class="mb-0 mt-2"><strong>Roll Number:</strong> <?php echo htmlspecialchars($user['roll_number']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Profile Details and Edit -->
        <div class="col-lg-8">
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <!-- Edit Profile -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Edit Profile</h5>
                    <hr>
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" 
                                   pattern="[a-zA-Z .'-]{2,100}" required>
                            <div class="invalid-feedback">
                                Please enter your full name (letters, spaces, dots, apostrophes, and hyphens only).
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   readonly disabled>
                            <small class="text-muted">Email cannot be changed</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($user['gender']) && $user['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($user['gender']) && $user['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        
                        <h6 class="mt-4">Change Password</h6>
                        <hr>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       minlength="6">
                                <div class="invalid-feedback">
                                    Password must be at least 6 characters long.
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                <div class="invalid-feedback">
                                    Passwords do not match.
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Complaints -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recent Complaints</h5>
                    <hr>
                    <?php if (empty($recent_complaints)): ?>
                        <p class="text-muted">No complaints submitted yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_complaints as $complaint): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($complaint['id']); ?></td>
                                            <td><?php echo htmlspecialchars($complaint['title']); ?></td>
                                            <td>
                                                <?php 
                                                echo htmlspecialchars($complaint['category_name']);
                                                if ($complaint['subcategory_name']) {
                                                    echo ' - ' . htmlspecialchars($complaint['subcategory_name']);
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo strtolower($complaint['status_name']) === 'pending' ? 'warning' : 
                                                    (strtolower($complaint['status_name']) === 'resolved' ? 'success' : 
                                                    (strtolower($complaint['status_name']) === 'rejected' ? 'danger' : 'info')); ?>">
                                                    <?php echo htmlspecialchars($complaint['status_name']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($complaint['date_created'])); ?></td>
                                            <td>
                                                <a href="view_complaint.php?id=<?php echo $complaint['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <a href="my_complaints.php" class="btn btn-link">View All Complaints</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Profile Picture Modal -->
<div class="modal fade" id="updatePictureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_picture">
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Choose Picture</label>
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                               accept="image/jpeg,image/png,image/gif" required>
                        <div class="form-text">
                            Maximum file size: 5MB<br>
                            Allowed formats: JPG, PNG, GIF
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
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

.container {
    max-width: 1200px;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    transition: var(--transition);
}

.card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.profile-picture-container {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    padding: 5px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    transition: var(--transition);
}

.profile-picture-container:hover {
    transform: scale(1.02);
}

.profile-picture {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    background: white;
    padding: 3px;
    transition: var(--transition);
}

.profile-picture-edit {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background: var(--primary-color);
    border: none;
    border-radius: 50%;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.profile-picture-edit:hover {
    transform: scale(1.1);
    background: var(--secondary-color);
}

.card-title {
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 1.5rem;
}

.form-label {
    color: var(--text-secondary);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    border: 2px solid #e1e1e1;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(26, 35, 126, 0.15);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    transition: var(--transition);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.table {
    border-radius: 8px;
    overflow: hidden;
}

.table thead th {
    background: var(--bg-light);
    border-bottom: 2px solid #dee2e6;
    color: var(--text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
}

.table tbody tr {
    transition: var(--transition);
}

.table tbody tr:hover {
    background-color: rgba(26, 35, 126, 0.05);
}

.badge {
    padding: 0.5em 1em;
    font-weight: 500;
    border-radius: 6px;
}

.badge.bg-warning {
    background-color: #fff3cd !important;
    color: #856404;
}

.badge.bg-success {
    background-color: #d4edda !important;
    color: #155724;
}

.badge.bg-danger {
    background-color: #f8d7da !important;
    color: #721c24;
}

.badge.bg-info {
    background-color: #d1ecf1 !important;
    color: #0c5460;
}

.alert {
    border-radius: 8px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border: none;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
}

.modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.modal-header {
    border-bottom: 2px solid #e9ecef;
    padding: 1.5rem;
}

.modal-footer {
    border-top: 2px solid #e9ecef;
    padding: 1.5rem;
}

@media (max-width: 768px) {
    .container {
        padding-top: 1rem !important;
        padding-bottom: 1rem !important;
    }
    
    .card {
        margin-bottom: 1rem;
    }
    
    .table {
        font-size: 0.875rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
}
</style>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
    
    // Password confirmation validation
    var password = document.getElementById('new_password')
    var confirm = document.getElementById('confirm_password')
    
    function validatePassword() {
        if (password.value != confirm.value) {
            confirm.setCustomValidity("Passwords do not match")
        } else {
            confirm.setCustomValidity('')
        }
    }
    
    password.onchange = validatePassword
    confirm.onkeyup = validatePassword
})()
</script>

<?php include 'includes/footer.php'; ?> 