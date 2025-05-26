<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get user role details
 */
function getUserRole($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT r.* 
            FROM user u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND u.status = 'active'
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error in getUserRole: " . $e->getMessage());
        return false;
    }
}

/**
 * Authenticate user
 */
function authenticateUser($email, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.role_name, r.role_level
            FROM user u
            JOIN roles r ON u.role_id = r.id
            WHERE u.email = ? AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        error_log("Login attempt - Email: " . $email);
        if ($user) {
            error_log("User found in database");
            error_log("Stored hash: " . $user['password']);
            error_log("Password verification result: " . (password_verify($password, $user['password']) ? 'true' : 'false'));
            
            if (password_verify($password, $user['password'])) {
                error_log("Password verified successfully");
                return $user;
            }
        } else {
            error_log("No user found with email: " . $email);
        }
        return false;
    } catch (PDOException $e) {
        error_log("Error in authenticateUser: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has specific permission
 */
function hasPermission($user_id, $permission) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT r.role_level
            FROM user u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND u.status = 'active'
        ");
        $stmt->execute([$user_id]);
        $role = $stmt->fetch();
        
        // Admin has all permissions
        if ($role && $role['role_level'] >= 100) {
            return true;
        }
        
        // Basic permissions based on role level
        switch($permission) {
            case 'view_all_complaints':
                return $role && $role['role_level'] >= 50;
            case 'create_complaint':
                return $role && $role['role_level'] >= 10;
            case 'manage_complaints':
                return $role && $role['role_level'] >= 50;
            default:
                return false;
        }
    } catch (PDOException $e) {
        error_log("Error in hasPermission: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate unique complaint ID
 */
function generateComplaintId() {
    $prefix = 'CMP';
    $timestamp = date('YmdHis');
    $random = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4);
    return $prefix . $timestamp . $random;
}

/**
 * Get initial status ID for new complaints
 */
function getInitialStatusId() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id 
        FROM complaint_status_types 
        WHERE status_name = 'pending'
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    return $result ? $result['id'] : null;
}

/**
 * Get role ID that should be assigned to handle complaints of given category
 */
function getAssignedRoleId($category_id) {
    global $pdo;
    
    // First check if category belongs to hostel
    $stmt = $pdo->prepare("
        SELECT d.id as dept_id
        FROM complaint_categories cc
        JOIN departments d ON cc.department_id = d.id
        WHERE cc.id = ? AND d.code = 'HST'
    ");
    $stmt->execute([$category_id]);
    $dept = $stmt->fetch();
    
    // If hostel complaint, assign to Warden
    if ($dept) {
        $role_name = 'Warden';
    } else {
        // Otherwise assign to HOD
        $role_name = 'HOD';
    }
    
    // Get role ID
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = ?");
    $stmt->execute([$role_name]);
    $role = $stmt->fetch();
    
    return $role ? $role['id'] : null;
} 