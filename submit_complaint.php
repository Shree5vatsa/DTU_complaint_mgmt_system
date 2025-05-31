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

// Check if user has permission to create complaints
if (!$auth->hasPermission('create_complaint')) {
    header('Location: index.php?error=permission_denied');
    exit();
}

try {
    // Get departments for dropdown
    $stmt = $pdo->prepare("SELECT * FROM departments ORDER BY name");
    $stmt->execute();
    $departments = $stmt->fetchAll();
    
    // Get categories based on user role
    $sql = "SELECT c.* FROM complaint_categories c WHERE 1=1";
    $params = [];
    
    // Handle category visibility based on user role
    switch ($user['role_name']) {
        case 'Student':
            // Students see their department's academic category and special categories
            $sql .= " AND (c.department_id = ? OR c.category_name IN ('Harassment', 'Misbehavior', 'Ragging', 'Hostel', 'Library', 'Other'))";
            $params[] = $user['department_id'];
            break;
            
        case 'HOD':
            // HODs see their department category and special categories
            $sql .= " AND (c.department_id = ? OR c.category_name IN ('Harassment', 'Misbehavior', 'Ragging', 'Library', 'Other'))";
            $params[] = $user['department_id'];
            break;
            
        case 'Teacher':
            // Teachers see their department category and special categories
            $sql .= " AND (c.department_id = ? OR c.category_name IN ('Harassment', 'Misbehavior', 'Ragging', 'Library', 'Other'))";
            $params[] = $user['department_id'];
            break;
            
        case 'Warden':
            // Wardens see hostel category and special categories
            $sql .= " AND (c.category_name IN ('Hostel', 'Harassment', 'Misbehavior', 'Ragging', 'Library', 'Other'))";
            break;
            
        default:
            // Administrators and others see all categories
            break;
    }
    
    $sql .= " ORDER BY CASE WHEN c.category_name = 'Other' THEN 1 ELSE 0 END, c.category_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
    
    // Get priority levels
    $stmt = $pdo->prepare("SELECT * FROM priority_levels ORDER BY id");
    $stmt->execute();
    $priority_levels = $stmt->fetchAll();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $category_id = (int)$_POST['category_id'];
        $subcategory_id = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $priority_id = (int)$_POST['priority_id'];
        
        // Validate required fields
        if (empty($title) || empty($description) || $category_id <= 0 || $priority_id <= 0) {
            $error = "Please fill in all required fields.";
        } else {
            // Generate unique complaint ID
            $complaint_id = uniqid('COMP');
            
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Get the pending status ID
                $stmt = $pdo->prepare("SELECT id FROM complaint_status_types WHERE status_name = 'pending'");
                $stmt->execute();
                $pending_status = $stmt->fetchColumn();
                
                if (!$pending_status) {
                    throw new Exception("Could not find pending status");
                }
                
                // For HOD and Teacher roles, automatically set their department's category
                if (in_array($user['role_name'], ['HOD', 'Teacher']) && $user['department_id']) {
                    // Get the selected category info
                    $stmt = $pdo->prepare("
                        SELECT category_name 
                        FROM complaint_categories 
                        WHERE id = ?
                    ");
                    $stmt->execute([$category_id]);
                    $selected_category = $stmt->fetchColumn();
                    
                    // Only override if it's not a special category
                    if (!in_array($selected_category, ['Library', 'Harassment', 'Misbehavior', 'Ragging', 'Other'])) {
                        $stmt = $pdo->prepare("
                            SELECT id FROM complaint_categories 
                            WHERE department_id = ?
                            LIMIT 1
                        ");
                        $stmt->execute([$user['department_id']]);
                        $dept_category = $stmt->fetchColumn();
                        if ($dept_category) {
                            $category_id = $dept_category;
                        }
                    }
                }
                
                // For Warden role, automatically set Hostel category
                if ($user['role_name'] === 'Warden') {
                    // Get the selected category info
                    $stmt = $pdo->prepare("
                        SELECT category_name 
                        FROM complaint_categories 
                        WHERE id = ?
                    ");
                    $stmt->execute([$category_id]);
                    $selected_category = $stmt->fetchColumn();
                    
                    // Only override if it's not a special category
                    if (!in_array($selected_category, ['Library', 'Harassment', 'Misbehavior', 'Ragging', 'Other'])) {
                        $stmt = $pdo->prepare("
                            SELECT id FROM complaint_categories 
                            WHERE category_name = 'Hostel'
                            LIMIT 1
                        ");
                        $stmt->execute();
                        $hostel_category = $stmt->fetchColumn();
                        if ($hostel_category) {
                            $category_id = $hostel_category;
                        }
                    }
                }
                
                // Insert complaint
                $stmt = $pdo->prepare("
                    INSERT INTO complaints (
                        id, user_id, category_id, sub_category_id,
                        title, description, status_id, priority_id,
                        date_created, last_updated
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?,
                        CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )
                ");
                
                $stmt->execute([
                    $complaint_id,
                    $user['id'],
                    $category_id,
                    $subcategory_id,
                    $title,
                    $description,
                    $pending_status,
                    $priority_id
                ]);
                
                // Add initial history entry
                $stmt = $pdo->prepare("
                    INSERT INTO complaint_history (
                        complaint_id, status_id, comments, updated_by, timestamp
                    ) VALUES (
                        ?, ?, ?, ?, CURRENT_TIMESTAMP
                    )
                ");
                $stmt->execute([$complaint_id, $pending_status, 'Complaint submitted', $user['id']]);
                
                // Auto-assign complaint based on category
                if ($category_id) {
                    // Get category information
                    $stmt = $pdo->prepare("
                        SELECT cc.department_id, cc.category_name, d.name as dept_name
                        FROM complaint_categories cc
                        LEFT JOIN departments d ON cc.department_id = d.id
                        WHERE cc.id = ?
                    ");
                    $stmt->execute([$category_id]);
                    $category_info = $stmt->fetch();
                    
                    if ($category_info) {
                        $assignee_id = null;
                        
                        // For special categories (Harassment, Misbehavior, Ragging)
                        if (in_array($category_info['category_name'], ['Harassment', 'Misbehavior', 'Ragging'])) {
                            // Assign to Warden if it's a hostel student, otherwise to HOD
                            if ($user['department_id'] && $user['role_name'] === 'Student') {
                                $stmt = $pdo->prepare("
                                    SELECT u.id 
                                    FROM user u
                                    JOIN roles r ON u.role_id = r.id
                                    WHERE u.department_id = ? 
                                    AND r.role_name = 'HOD'
                                    LIMIT 1
                                ");
                                $stmt->execute([$user['department_id']]);
                                $assignee_id = $stmt->fetchColumn();
                            }
                        }
                        // For department-specific complaints
                        elseif ($category_info['department_id']) {
                            $stmt = $pdo->prepare("
                                SELECT u.id 
                                FROM user u
                                JOIN roles r ON u.role_id = r.id
                                WHERE u.department_id = ? 
                                AND r.role_name = 'HOD'
                                LIMIT 1
                            ");
                            $stmt->execute([$category_info['department_id']]);
                            $assignee_id = $stmt->fetchColumn();
                        }
                        // For hostel complaints
                        elseif ($category_info['category_name'] === 'Hostel') {
                            $stmt = $pdo->prepare("
                                SELECT u.id 
                                FROM user u
                                JOIN roles r ON u.role_id = r.id
                                WHERE r.role_name = 'Warden'
                                LIMIT 1
                            ");
                            $stmt->execute();
                            $assignee_id = $stmt->fetchColumn();
                        }
                        // For library complaints
                        elseif ($category_info['category_name'] === 'Library') {
                            // For Library complaints, we don't need to set department_id as it's handled in the view
                            
                            // Assign to Library admin if exists, otherwise to system admin
                            $stmt = $pdo->prepare("
                                SELECT u.id 
                                FROM user u
                                JOIN roles r ON u.role_id = r.id
                                WHERE r.role_name = 'Library Admin'
                                LIMIT 1
                            ");
                            $stmt->execute();
                            $assignee_id = $stmt->fetchColumn();
                            
                            if (!$assignee_id) {
                                // If no Library Admin, assign to system admin
                                $stmt = $pdo->prepare("
                                    SELECT u.id 
                                    FROM user u
                                    JOIN roles r ON u.role_id = r.id
                                    WHERE r.role_name = 'Administrator'
                                    LIMIT 1
                                ");
                                $stmt->execute();
                                $assignee_id = $stmt->fetchColumn();
                            }
                            
                            // Set department_id to NULL for Library complaints
                            $stmt = $pdo->prepare("
                                UPDATE complaints 
                                SET department_id = NULL 
                                WHERE id = ?
                            ");
                            $stmt->execute([$complaint_id]);
                        }
                        
                        // Update assignee if found
                        if ($assignee_id) {
                            $stmt = $pdo->prepare("UPDATE complaints SET assigned_to = ? WHERE id = ?");
                            $stmt->execute([$assignee_id, $complaint_id]);
                        }
                    }
                }
                
                $pdo->commit();
                header("Location: view_complaint.php?id=" . urlencode($complaint_id) . "&success=created");
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log($e->getMessage());
                $error = "An error occurred while submitting your complaint. Please try again.";
            }
        }
    }
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = "A system error occurred. Please try again later.";
}

// Include header
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="submit-complaint">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title mb-4">Submit New Complaint</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="complaint-form needs-validation" novalidate>
                    <div class="form-group mb-4">
                        <label for="title" class="form-label">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" class="form-control" 
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                               required>
                        <div class="form-text">Provide a clear, concise title for your complaint</div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category_id" class="form-label">Category <span class="required">*</span></label>
                                <select id="category_id" name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"
                                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select your department or service area</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="subcategory_id" class="form-label">Subcategory</label>
                                <select id="subcategory_id" name="subcategory_id" class="form-select">
                                    <option value="">First Select a Category</option>
                                </select>
                                <div class="form-text">Select the specific area of concern (if applicable)</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="priority_id" class="form-label">Priority <span class="required">*</span></label>
                        <select id="priority_id" name="priority_id" class="form-select" required>
                            <option value="">Select Priority</option>
                            <?php foreach ($priority_levels as $priority): ?>
                                <option value="<?php echo $priority['id']; ?>"
                                        <?php echo (isset($_POST['priority_id']) && $_POST['priority_id'] == $priority['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($priority['level_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Choose the urgency level of your complaint</div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="description" class="form-label">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" class="form-control" rows="6" required><?php 
                            echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; 
                        ?></textarea>
                        <div class="form-text">Provide detailed information about your complaint</div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Complaint
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
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

.submit-complaint {
    max-width: 800px;
    margin: 0 auto;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border-radius: 12px;
    transition: var(--transition);
}

.card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.card-title {
    color: var(--primary-color);
    font-weight: 600;
    font-size: 1.75rem;
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

.form-text {
    color: var(--text-secondary);
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.required {
    color: #dc3545;
    font-weight: bold;
}

.btn {
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    border-radius: 8px;
    transition: var(--transition);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border: none;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.btn-outline-secondary {
    border: 2px solid var(--text-secondary);
    color: var(--text-secondary);
}

.btn-outline-secondary:hover {
    background: var(--text-secondary);
    color: white;
    transform: translateY(-2px);
}

.alert {
    border-radius: 8px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border: none;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .card {
        border-radius: 8px;
    }
    
    .form-actions {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .btn {
        width: 100%;
    }
    
    .ms-2 {
        margin-left: 0 !important;
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
})()

// JavaScript for dynamic subcategories
document.getElementById('category_id').addEventListener('change', function() {
    var categoryId = this.value;
    var subcategorySelect = document.getElementById('subcategory_id');
    
    // Clear current options
    subcategorySelect.innerHTML = '<option value="">Select Subcategory (Optional)</option>';
    
    if (categoryId) {
        // Fetch subcategories via AJAX
        fetch('ajax/get_subcategories.php?category_id=' + categoryId)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    subcategorySelect.disabled = false;
                    data.forEach(function(subcategory) {
                        var option = document.createElement('option');
                        option.value = subcategory.id;
                        option.textContent = subcategory.name;
                        if (subcategory.description) {
                            option.title = subcategory.description;
                        }
                        subcategorySelect.appendChild(option);
                    });
                } else {
                    subcategorySelect.disabled = true;
                    subcategorySelect.innerHTML = '<option value="">No subcategories available</option>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                subcategorySelect.disabled = true;
                subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
            });
    } else {
        subcategorySelect.disabled = true;
        subcategorySelect.innerHTML = '<option value="">First Select a Category</option>';
    }
});

// Initialize subcategory if category is pre-selected
window.addEventListener('load', function() {
    var categoryId = document.getElementById('category_id').value;
    if (categoryId) {
        document.getElementById('category_id').dispatchEvent(new Event('change'));
    }
});
</script>

<?php include 'includes/footer.php'; ?> 