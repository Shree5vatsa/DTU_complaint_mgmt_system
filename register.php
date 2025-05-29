<?php
require_once 'includes/db_config.php';
require_once 'includes/auth.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize Auth class
$auth = new Auth($pdo);

// Check if already logged in
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Get roles from database
try {
    $stmt = $pdo->query("SELECT id, role_name FROM roles WHERE role_name != 'Administrator' ORDER BY role_level");
    $roles = $stmt->fetchAll();
    
    // Get departments
    $stmt = $pdo->query("SELECT id, name, code FROM departments ORDER BY name");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role_id = filter_var($_POST['role_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $department_id = filter_var($_POST['department_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $roll_number = trim($_POST['roll_number'] ?? '');
    $gender = $_POST['gender'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($role_id)) {
        $error = 'All required fields must be filled out.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[a-zA-Z\s]{2,100}$/', $name)) {
        $error = 'Name should only contain letters and spaces (2-100 characters).';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/@dtu\.ac\.in$/', $email)) {
        $error = 'Please use your DTU email address (@dtu.ac.in).';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'This email is already registered.';
            } else {
                // Validate roll number format for students
                $role_stmt = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
                $role_stmt->execute([$role_id]);
                $role = $role_stmt->fetch();
                
                if ($role['role_name'] === 'Student') {
                    if (empty($roll_number) || !preg_match('/^2K\d{2}\/(CO|IT|ECE|ME|CE)\/\d{3}$/', $roll_number)) {
                        $error = 'Invalid roll number format. Use format: 2KYY/BRANCH/XXX (e.g., 2K21/CO/123)';
                    }
                }
                
                if (empty($error)) {
                    // Create user
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO user (name, email, password, role_id, department_id, roll_number, status, image, gender)
                        VALUES (?, ?, ?, ?, ?, ?, 'active', 'default.jpg', ?)
                    ");
                    
                    if ($stmt->execute([$name, $email, $hash, $role_id, $department_id, $roll_number, $gender])) {
                        $success = 'Registration successful! You can now login.';
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - DTU Complaint Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a237e;
            --secondary-color: #0d47a1;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .register-container {
            background: rgba(255, 255, 255, 0.95);
            max-width: 800px;
            width: 100%;
            margin: 2rem auto;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo-container {
            width: 120px;
            height: 120px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            padding: 5px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-container img {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            background-color: white;
            padding: 5px;
        }
        
        .register-header h2 {
            color: var(--primary-color);
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .register-header p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(26, 35, 126, 0.15);
        }
        
        .form-label {
            color: #444;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-size: 1.1rem;
            font-weight: 500;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .btn-outline-secondary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 10px;
            padding: 0.75rem;
            font-size: 1.1rem;
            font-weight: 500;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s ease;
            background: transparent;
        }
        
        .btn-outline-secondary:hover {
            background: rgba(26, 35, 126, 0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 1.5rem;
            padding: 1rem;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            border-color: #a5d6a7;
            color: #2e7d32;
        }
        
        .alert-danger {
            background-color: #fde8e8;
            border-color: #f5c2c7;
            color: #842029;
        }
        
        .form-text {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .register-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
        
        #studentFields {
            background: rgba(26, 35, 126, 0.05);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid rgba(26, 35, 126, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-header">
                <div class="logo-container">
                    <img src="dtulogo.png" alt="DTU Logo" class="img-fluid">
                </div>
                <h2>Register New Account</h2>
                <p>Create your DTU Complaint Portal account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i> Proceed to Login
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" required
                               pattern="[a-zA-Z\s]{2,100}"
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        <div class="invalid-feedback">
                            Please enter your full name (letters and spaces only).
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">DTU Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               pattern="[a-zA-Z0-9._%+-]+@dtu\.ac\.in$"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <div class="invalid-feedback">
                            Please enter a valid DTU email address (@dtu.ac.in).
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   required minlength="6">
                            <div class="invalid-feedback">
                                Password must be at least 6 characters long.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" required>
                            <div class="invalid-feedback">
                                Please confirm your password.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role_id" class="form-label">Role</label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role['id']); ?>"
                                            <?php echo (isset($_POST['role_id']) && $_POST['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select your role.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select your gender.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="department_id" class="form-label">Department</label>
                        <select class="form-select" id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['id']); ?>"
                                        <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select your department.
                        </div>
                    </div>
                    
                    <div id="studentFields" class="mb-3">
                        <label for="roll_number" class="form-label">Roll Number</label>
                        <input type="text" class="form-control" id="roll_number" name="roll_number"
                               pattern="2K\d{2}/(CO|IT|ECE|ME|CE)/\d{3}"
                               value="<?php echo isset($_POST['roll_number']) ? htmlspecialchars($_POST['roll_number']) : ''; ?>">
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Format: 2KYY/BRANCH/XXX (e.g., 2K21/CO/123)
                        </div>
                        <div class="invalid-feedback">
                            Please enter a valid roll number.
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i> Register Account
                        </button>
                        <a href="login.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Login
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
        
        // Show/hide student fields based on role selection
        document.getElementById('role_id').addEventListener('change', function() {
            var roleSelect = this;
            var studentFields = document.getElementById('studentFields');
            var rollNumberInput = document.getElementById('roll_number');
            
            // Get the selected option's text
            var selectedRole = roleSelect.options[roleSelect.selectedIndex].text;
            
            if (selectedRole === 'Student') {
                studentFields.style.display = 'block';
                rollNumberInput.required = true;
            } else {
                studentFields.style.display = 'none';
                rollNumberInput.required = false;
                rollNumberInput.value = '';
            }
        });
    </script>
</body>
</html> 