<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

function sendError($message, $code = 400) {
    http_response_code($code);
    die(json_encode(['error' => $message]));
}

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    sendError('Invalid request method', 403);
}

// Check if user is logged in
if (!isLoggedIn()) {
    sendError('Unauthorized', 401);
}

// Validate input
if (!isset($_POST['category_id']) || !is_numeric($_POST['category_id'])) {
    sendError('Invalid category ID');
}

$category_id = (int)$_POST['category_id'];

// Get subcategories for the selected category
try {
    $stmt = $pdo->prepare("
        SELECT id, name, description 
        FROM complaint_subcategories 
        WHERE category_id = ? 
        ORDER BY name
    ");
    
    $stmt->execute([$category_id]);
    $subcategories = $stmt->fetchAll();
    
    // Generate HTML options
    $html = '<option value="">Select Subcategory</option>';
    foreach ($subcategories as $subcategory) {
        $html .= sprintf(
            '<option value="%d">%s</option>',
            $subcategory['id'],
            htmlspecialchars($subcategory['name'], ENT_QUOTES, 'UTF-8')
        );
    }
    
    die(json_encode([
        'success' => true,
        'html' => $html,
        'count' => count($subcategories)
    ]));
    
} catch (PDOException $e) {
    error_log("Database Error in get_subcategories.php: " . $e->getMessage());
    sendError('Failed to fetch subcategories', 500);
} 