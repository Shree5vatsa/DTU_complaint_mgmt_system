<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in for protected pages
$public_pages = ['login.php', 'register.php', 'forgot_password.php', 'reset_password.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if (!in_array($current_page, $public_pages) && !isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user details if logged in
$current_user = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTU Complaint Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/jquery.min.js"></script>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <a href="index.php">DTU Complaint Portal</a>
        </div>
        
        <?php if (isLoggedIn()): ?>
            <div class="navbar-menu">
                <a href="index.php">Dashboard</a>
                
                <?php if (hasPermission($_SESSION['user_id'], 'create_complaint')): ?>
                    <a href="submit_complaint.php">Submit Complaint</a>
                <?php endif; ?>
                
                <?php if (hasPermission($_SESSION['user_id'], 'view_analytics')): ?>
                    <a href="analytics.php">Analytics</a>
                <?php endif; ?>
                
                <div class="dropdown">
                    <button class="dropdown-toggle">
                        <?php echo htmlspecialchars($current_user['name']); ?>
                    </button>
                    <div class="dropdown-menu">
                        <a href="profile.php">Profile</a>
                        <a href="change_password.php">Change Password</a>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </nav>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <?php
            $error_message = '';
            switch ($_GET['error']) {
                case 'permission_denied':
                    $error_message = 'You do not have permission to access this page.';
                    break;
                case 'invalid_complaint':
                    $error_message = 'Invalid complaint ID.';
                    break;
                case 'complaint_not_found':
                    $error_message = 'Complaint not found.';
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
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            $success_message = '';
            switch ($_GET['success']) {
                case 'complaint_submitted':
                    $success_message = 'Complaint submitted successfully.';
                    break;
                case 'status_updated':
                    $success_message = 'Status updated successfully.';
                    break;
                case 'password_changed':
                    $success_message = 'Password changed successfully.';
                    break;
                default:
                    $success_message = 'Operation completed successfully.';
            }
            echo htmlspecialchars($success_message);
            ?>
        </div>
    <?php endif; ?> 