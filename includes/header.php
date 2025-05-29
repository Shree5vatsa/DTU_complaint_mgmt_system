<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';
$auth = new Auth($pdo);

// Ensure user is logged in for protected pages
$public_pages = ['login.php', 'register.php', 'forgot_password.php', 'reset_password.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if (!in_array($current_page, $public_pages) && !$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user details if logged in
$current_user = null;
if ($auth->isLoggedIn()) {
    $current_user = $auth->getCurrentUser();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTU Complaint Portal</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-clipboard-list mr-2"></i>
                DTU Complaint Portal
            </a>
            
            <?php if ($auth->isLoggedIn()): ?>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                                <i class="fas fa-home mr-1"></i> Dashboard
                            </a>
                        </li>
                        
                        <?php if ($auth->hasPermission('create_complaint')): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page === 'submit_complaint.php' ? 'active' : ''; ?>" href="submit_complaint.php">
                                    <i class="fas fa-plus-circle mr-1"></i> Submit Complaint
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($current_user['role_id'] === 1): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>" href="analytics.php">
                                    <i class="fas fa-chart-bar mr-1"></i> Analytics
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                               data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-user-circle mr-1"></i>
                                <?php 
                                $user = $auth->getCurrentUser();
                                echo htmlspecialchars($user['name']); 
                                ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-id-card mr-2"></i> Profile
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="container mt-3">
            <div class="alert alert-danger">
                <?php
                $error_message = '';
                switch ($_GET['error']) {
                    case 'permission_denied':
                        $error_message = 'You do not have permission to access this resource.';
                        break;
                    case 'system_error':
                        $error_message = 'A system error occurred. Please try again later.';
                        break;
                    default:
                        $error_message = 'An error occurred.';
                }
                echo htmlspecialchars($error_message);
                ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="container mt-3">
            <div class="alert alert-success">
                <?php
                $success_message = '';
                switch ($_GET['success']) {
                    case 'complaint_submitted':
                        $success_message = 'Your complaint has been submitted successfully.';
                        break;
                    case 'status_updated':
                        $success_message = 'Complaint status has been updated successfully.';
                        break;
                    default:
                        $success_message = 'Operation completed successfully.';
                }
                echo htmlspecialchars($success_message);
                ?>
            </div>
        </div>
    <?php endif; ?> 
</body>
</html> 