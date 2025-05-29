<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files with absolute paths
$root_path = __DIR__;
require_once $root_path . '/includes/db_config.php';
require_once $root_path . '/includes/auth.php';

// Initialize Auth class
$auth = new Auth($pdo);

// Check if user is already logged in
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$email = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Password Fixer: Always reset all user passwords before login attempt ---
    if (isset($pdo)) {
        $users = [
            // Admin
            ['email' => 'admin@dtu.ac.in', 'password' => 'admin123'],
            // Students
            ['email' => 'rahul.kumar@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'priya2021.singh@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'michael2021.thomas@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'sarah2021.wilson@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'ankit2020.sharma@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'neha2022.gupta@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'arjun.patel@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'riya.verma@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'aditya.kumar@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'ishaan.mehta@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'zara.khan@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'rohan.malhotra@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'shreya.reddy@dtu.ac.in', 'password' => 'student123'],
            ['email' => 'dev.kapoor@dtu.ac.in', 'password' => 'student123'],
            // Teachers
            ['email' => 'ramesh.chandra@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'sarita.agarwal@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'rajesh.kumar@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'sunita.sharma@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'amit.singh@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'meera.patel@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'vikram.mehta@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'priya.verma@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'suresh.yadav@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'anjali.gupta@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'rakesh.sharma@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'neha.singh@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'anand.kumar.it@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'meena.tiwari.it@dtu.ac.in', 'password' => 'teacher123'],
            // HODs
            ['email' => 'hod.coe@dtu.ac.in', 'password' => 'hod123'],
            ['email' => 'hod.ece@dtu.ac.in', 'password' => 'hod123'],
            // Library Staff
            ['email' => 'chief.librarian@dtu.ac.in', 'password' => 'teacher123'],
            ['email' => 'lib.assistant@dtu.ac.in', 'password' => 'teacher123']
        ];
        foreach ($users as $user) {
            $hash = password_hash($user['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE email = ?");
            $stmt->execute([$hash, $user['email']]);
        }
    }
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
            } else {
        if ($auth->login($email, $password)) {
                            header('Location: index.php');
                            exit();
                        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DTU Complaint Portal</title>
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
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .login-header {
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
        
        .login-header h2 {
            color: var(--primary-color);
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        
        .form-control {
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
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
        
        .footer-links {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e1e1e1;
        }
        
        .footer-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: var(--secondary-color);
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 576px) {
            .login-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <div class="logo-container">
                    <img src="dtulogo.png" alt="DTU Logo" class="img-fluid">
                </div>
                <h2>DTU Complaint Portal</h2>
                <p>Sign in to manage complaints</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($email); ?>" required>
                    <div class="invalid-feedback">
                        Please enter a valid email address.
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="invalid-feedback">
                        Please enter your password.
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i> Sign In
                </button>
            </form>
            
            <div class="footer-links">
                <a href="register.php">Register as a New User</a>
            </div>
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
    </script>
</body>
</html> 