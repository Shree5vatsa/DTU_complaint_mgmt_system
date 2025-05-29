<?php
// Set error reporting
error_reporting(-1);
ini_set('display_errors', 1);

header('Content-Type: application/json');

require_once '../includes/db_config.php';
require_once '../includes/auth.php';

// Initialize Auth class
$auth = new Auth($pdo);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get user details
$user = $auth->getCurrentUser();
if (!$user) {
    $auth->logout();
    http_response_code(401);
    echo json_encode(['error' => 'Invalid user session']);
    exit();
}

// Validate category_id
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
if ($category_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid category ID']);
    exit();
}

try {
    // Get subcategories for the given category
    $stmt = $pdo->prepare("
        SELECT id, name, description 
        FROM complaint_subcategories 
        WHERE category_id = ? 
        ORDER BY name
    ");
    $stmt->execute([$category_id]);
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($subcategories);
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
} 