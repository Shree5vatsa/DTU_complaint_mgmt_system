<?php
// Create required directories if they don't exist
$required_dirs = [
    'uploads',
    'uploads/complaints',
    'logs',
    'assets',
    'assets/css',
    'assets/js',
    'assets/img'
];

foreach ($required_dirs as $dir) {
    $full_path = __DIR__ . '/../' . $dir;
    if (!file_exists($full_path)) {
        mkdir($full_path, 0777, true);
        
        // Create .htaccess for uploads directory to prevent direct access
        if (strpos($dir, 'uploads') === 0) {
            $htaccess = $full_path . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Options -Indexes\nDeny from all");
            }
        }
    }
}

// Create .gitignore if it doesn't exist
$gitignore = __DIR__ . '/../.gitignore';
if (!file_exists($gitignore)) {
    $ignore_content = <<<EOT
/logs/*
/uploads/*
.env
.DS_Store
Thumbs.db
EOT;
    file_put_contents($gitignore, $ignore_content);
}

// Create robots.txt if it doesn't exist
$robots = __DIR__ . '/../robots.txt';
if (!file_exists($robots)) {
    $robots_content = <<<EOT
User-agent: *
Disallow: /uploads/
Disallow: /includes/
Disallow: /logs/
EOT;
    file_put_contents($robots, $robots_content);
}

// Function to validate file upload
function validateFileUpload($file, $allowed_types = [], $max_size = 5242880) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = "File is too large";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = "File was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errors[] = "Missing temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errors[] = "Failed to write file to disk";
                break;
            default:
                $errors[] = "Unknown upload error";
        }
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = "File is too large (maximum " . ($max_size / 1024 / 1024) . "MB)";
    }
    
    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    if (!empty($allowed_types) && !in_array($mime_type, $allowed_types)) {
        $errors[] = "Invalid file type";
    }
    
    // Additional security checks
    if (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = "Invalid upload attempt";
    }
    
    return $errors;
}

// Function to safely move uploaded file
function moveUploadedFile($file, $destination, $filename = null) {
    if ($filename === null) {
        $filename = basename($file['name']);
    }
    
    // Sanitize filename
    $filename = preg_replace("/[^a-zA-Z0-9._-]/", "", $filename);
    $filename = strtolower($filename);
    
    // Ensure unique filename
    $path_parts = pathinfo($filename);
    $counter = 1;
    while (file_exists($destination . '/' . $filename)) {
        $filename = $path_parts['filename'] . '_' . $counter . '.' . $path_parts['extension'];
        $counter++;
    }
    
    $full_path = $destination . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $full_path)) {
        return false;
    }
    
    // Set correct permissions
    chmod($full_path, 0644);
    
    return $filename;
} 