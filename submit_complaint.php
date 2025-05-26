<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/init.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_role = getUserRole($user_id);

// Check if user has permission to create complaints
if (!hasPermission($user_id, 'create_complaint')) {
    header('Location: index.php?error=permission_denied');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $subcategory_id = (int)$_POST['subcategory_id'];
    $priority_id = (int)$_POST['priority_id'];
    
    $errors = [];
    
    // Validate inputs
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    if ($category_id <= 0) {
        $errors[] = "Please select a category";
    }
    
    // Process file upload if present
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = [
            'image/jpeg',
            'image/png',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $upload_errors = validateFileUpload($_FILES['attachment'], $allowed_types, $max_size);
        if (!empty($upload_errors)) {
            $errors = array_merge($errors, $upload_errors);
        } else {
            $upload_dir = __DIR__ . '/uploads/complaints';
            $filename = moveUploadedFile($_FILES['attachment'], $upload_dir);
            
            if ($filename === false) {
                $errors[] = "Failed to upload file";
            } else {
                $attachment_path = 'uploads/complaints/' . $filename;
            }
        }
    }
    
    // If no errors, insert complaint
    if (empty($errors)) {
        $complaint_id = generateComplaintId();
        $status_id = getInitialStatusId();
        
        $stmt = $pdo->prepare("
            INSERT INTO complaints (
                id, user_id, category_id, sub_category_id, 
                title, description, status_id, priority_id, 
                date_created
            ) VALUES (
                ?, ?, ?, ?, 
                ?, ?, ?, ?,
                CURRENT_TIMESTAMP
            )
        ");
        
        try {
            $pdo->beginTransaction();
            
            $stmt->execute([
                $complaint_id, $user_id, $category_id, $subcategory_id,
                $title, $description, $status_id, $priority_id
            ]);
            
            // Insert attachment if present
            if ($attachment_path) {
                $stmt = $pdo->prepare("
                    INSERT INTO complaint_attachments (
                        complaint_id, file_name, file_path
                    ) VALUES (?, ?, ?)
                ");
                $stmt->execute([$complaint_id, basename($attachment_path), $attachment_path]);
            }
            
            // Create initial workflow entry
            $stmt = $pdo->prepare("
                INSERT INTO complaint_workflow (
                    complaint_id, from_status_id, to_status_id,
                    from_role_id, to_role_id
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $complaint_id,
                $status_id,
                $status_id,
                $user_role['id'],
                getAssignedRoleId($category_id)
            ]);
            
            $pdo->commit();
            header('Location: view_complaint.php?id=' . $complaint_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Failed to submit complaint. Please try again.";
            // Log the error
            error_log($e->getMessage());
        }
    }
}

// Get categories for dropdown
$stmt = $pdo->prepare("
    SELECT c.id, c.category_name, d.name as department_name 
    FROM complaint_categories c
    JOIN departments d ON c.department_id = d.id
    WHERE c.is_active = 1
    ORDER BY d.name, c.category_name
");
$stmt->execute();
$categories = $stmt->fetchAll();

// Get priority levels
$stmt = $pdo->prepare("SELECT * FROM priority_levels ORDER BY id");
$stmt->execute();
$priority_levels = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint - DTU Complaint Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/jquery.min.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <h1>Submit New Complaint</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="complaint-form">
            <div class="form-group">
                <label for="title">Complaint Title *</label>
                <input type="text" id="title" name="title" required 
                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                       class="form-control">
            </div>
            
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select id="category_id" name="category_id" required class="form-control">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"
                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['department_name'] . ' - ' . $category['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="subcategory_id">Subcategory</label>
                <select id="subcategory_id" name="subcategory_id" class="form-control">
                    <option value="">Select Subcategory</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="priority_id">Priority Level *</label>
                <select id="priority_id" name="priority_id" required class="form-control">
                    <?php foreach ($priority_levels as $priority): ?>
                        <option value="<?php echo $priority['id']; ?>"
                                <?php echo (isset($_POST['priority_id']) && $_POST['priority_id'] == $priority['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($priority['level_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description" required 
                          class="form-control" rows="5"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="attachment">Attachment</label>
                <input type="file" id="attachment" name="attachment" class="form-control">
                <small class="form-text text-muted">Max file size: 5MB. Allowed types: JPG, PNG, PDF, DOC</small>
            </div>
            
            <button type="submit" class="btn btn-primary">Submit Complaint</button>
        </form>
    </div>
    
    <script>
    $(document).ready(function() {
        // Load subcategories when category is selected
        $('#category_id').change(function() {
            var categoryId = $(this).val();
            var $subcategorySelect = $('#subcategory_id');
            
            $subcategorySelect.prop('disabled', true);
            
            if (categoryId) {
                $.ajax({
                    url: 'ajax/get_subcategories.php',
                    type: 'POST',
                    data: {category_id: categoryId},
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.success) {
                            $subcategorySelect.html(response.html);
                            if (response.count > 0) {
                                $subcategorySelect.prop('required', true);
                            }
                        } else {
                            showError('Failed to load subcategories');
                        }
                    },
                    error: function(xhr) {
                        var message = 'Failed to load subcategories';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            message = xhr.responseJSON.error;
                        }
                        showError(message);
                    },
                    complete: function() {
                        $subcategorySelect.prop('disabled', false);
                    }
                });
            } else {
                $subcategorySelect.html('<option value="">Select Subcategory</option>');
                $subcategorySelect.prop('required', false);
                $subcategorySelect.prop('disabled', false);
            }
        });
        
        function showError(message) {
            var $alert = $('<div class="alert alert-danger"></div>')
                .text(message)
                .hide();
            
            $('.complaint-form').prepend($alert);
            $alert.slideDown();
            
            setTimeout(function() {
                $alert.slideUp(function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // File upload preview and validation
        $('#attachment').change(function() {
            var $input = $(this);
            var $label = $input.next('.form-text');
            var maxSize = 5 * 1024 * 1024; // 5MB
            var allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            
            if (this.files && this.files[0]) {
                var file = this.files[0];
                
                // Check file size
                if (file.size > maxSize) {
                    showError('File is too large (maximum 5MB)');
                    $input.val('');
                    return;
                }
                
                // Check file type
                if (!allowedTypes.includes(file.type)) {
                    showError('Invalid file type. Allowed types: JPG, PNG, PDF, DOC, DOCX');
                    $input.val('');
                    return;
                }
                
                // Show file name
                $label.text('Selected file: ' + file.name);
            }
        });
    });
    </script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html> 