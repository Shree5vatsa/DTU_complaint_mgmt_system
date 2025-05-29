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
    
    // Get only main categories (COE, ECE, CE, Library, Hostel, Campus Security)
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM complaint_categories c 
        ORDER BY 
            CASE WHEN c.category_name = 'Other' THEN 1 ELSE 0 END,
            c.category_name
    ");
    $stmt->execute();
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
                // Insert complaint
                $stmt = $pdo->prepare("
                    INSERT INTO complaints (
                        id, user_id, category_id, sub_category_id,
                        title, description, status_id, priority_id,
                        date_created, last_updated
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, 
                        (SELECT id FROM complaint_status_types WHERE status_name = 'pending'),
                        ?,
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
                    $priority_id
                ]);
                
                // Add initial history entry
                $stmt = $pdo->prepare("
                    INSERT INTO complaint_history (
                        complaint_id, status_id, comments, updated_by, timestamp
                    ) VALUES (
                        ?, 
                        (SELECT id FROM complaint_status_types WHERE status_name = 'pending'),
                        'Complaint submitted',
                        ?,
                        CURRENT_TIMESTAMP
                    )
                ");
                $stmt->execute([$complaint_id, $user['id']]);
                
                $pdo->commit();
                header("Location: view_complaint.php?id=$complaint_id&success=1");
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log($e->getMessage());
                $error = "Failed to submit complaint. Please try again.";
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