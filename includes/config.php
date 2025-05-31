<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Database Configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', 'dtu_portal');
}

if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// Application settings
define('SITE_NAME', 'DTU Complaint Portal');
define('SITE_URL', 'http://localhost/DTU_complaint_mgmt_system');  // Updated to correct path
define('UPLOAD_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);  // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

// Email settings (configure based on your SMTP server)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@dtu.ac.in');
define('SMTP_PASS', 'your-email-password');
define('SMTP_FROM', 'noreply@dtu.ac.in');

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

try {
    // First try to create the database if it doesn't exist
    $temp_pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Now connect to the database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// Common functions
function sanitize_output($buffer) {
    return htmlspecialchars($buffer, ENT_QUOTES, 'UTF-8');
}

// Set default timezone
date_default_timezone_set('Asia/Kolkata'); // For DTU (Delhi) 