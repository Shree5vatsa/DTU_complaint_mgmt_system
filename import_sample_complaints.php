<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db_config.php';

try {
    // Disable foreign key checks temporarily
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Sample complaints data
    $complaints = [
        [
            'id' => 'COMP001',
            'user_email' => 'rahul.kumar@dtu.ac.in',
            'category' => 'COE',
            'subcategory' => 'Labs',
            'title' => 'Computer Lab PCs Not Working',
            'description' => 'In Lab 3, computers numbered 12, 14, and 15 are not booting up properly. This is affecting our practical sessions.',
            'status' => 'pending',
            'priority' => 'high',
            'assigned_to_email' => 'hod.coe@dtu.ac.in'
        ],
        [
            'id' => 'COMP002',
            'user_email' => 'sarah.wilson@dtu.ac.in',
            'category' => 'Hostel',
            'subcategory' => 'Infrastructure',
            'title' => 'Water Supply Issue in Girls Hostel',
            'description' => 'Block C of girls hostel is facing irregular water supply for the past 3 days.',
            'status' => 'in_progress',
            'priority' => 'high',
            'assigned_to_email' => 'admin@dtu.ac.in'
        ],
        [
            'id' => 'COMP003',
            'user_email' => 'ramesh.chandra@dtu.ac.in',
            'category' => 'COE',
            'subcategory' => 'Academic',
            'title' => 'Projector Not Working in Room 304',
            'description' => 'The projector in Room 304 is not functioning properly, affecting the delivery of lectures.',
            'status' => 'in_progress',
            'priority' => 'medium',
            'assigned_to_email' => 'admin@dtu.ac.in'
        ],
        [
            'id' => 'COMP004',
            'user_email' => 'hod.ece@dtu.ac.in',
            'category' => 'ECE',
            'subcategory' => 'Labs',
            'title' => 'Requirement for New Lab Equipment',
            'description' => 'The Digital Signal Processing lab requires new oscilloscopes and signal generators for better practical training.',
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to_email' => 'admin@dtu.ac.in'
        ]
    ];

    // First, clear existing sample complaints
    $pdo->exec("DELETE FROM complaint_history WHERE complaint_id IN ('COMP001', 'COMP002', 'COMP003', 'COMP004')");
    $pdo->exec("DELETE FROM complaint_details WHERE complaint_id IN ('COMP001', 'COMP002', 'COMP003', 'COMP004')");
    $pdo->exec("DELETE FROM complaints WHERE id IN ('COMP001', 'COMP002', 'COMP003', 'COMP004')");

    foreach ($complaints as $complaint) {
        // Get user ID
        $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ?");
        $stmt->execute([$complaint['user_email']]);
        $userId = $stmt->fetchColumn();

        // Get category ID
        $stmt = $pdo->prepare("SELECT id FROM complaint_categories WHERE category_name = ?");
        $stmt->execute([$complaint['category']]);
        $categoryId = $stmt->fetchColumn();

        // Get subcategory ID
        $stmt = $pdo->prepare("
            SELECT cs.id 
            FROM complaint_subcategories cs
            JOIN complaint_categories c ON cs.category_id = c.id
            WHERE c.category_name = ? AND cs.name = ?
        ");
        $stmt->execute([$complaint['category'], $complaint['subcategory']]);
        $subcategoryId = $stmt->fetchColumn();

        // Get status ID
        $stmt = $pdo->prepare("SELECT id FROM complaint_status_types WHERE status_name = ?");
        $stmt->execute([$complaint['status']]);
        $statusId = $stmt->fetchColumn();

        // Get priority ID
        $stmt = $pdo->prepare("SELECT id FROM priority_levels WHERE level_name = ?");
        $stmt->execute([$complaint['priority']]);
        $priorityId = $stmt->fetchColumn();

        // Get assigned_to ID
        $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ?");
        $stmt->execute([$complaint['assigned_to_email']]);
        $assignedToId = $stmt->fetchColumn();

        // Insert complaint
        $stmt = $pdo->prepare("
            INSERT INTO complaints (
                id, user_id, category_id, sub_category_id, title, 
                description, status_id, priority_id, assigned_to,
                date_created, last_updated
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        
        $stmt->execute([
            $complaint['id'],
            $userId,
            $categoryId,
            $subcategoryId,
            $complaint['title'],
            $complaint['description'],
            $statusId,
            $priorityId,
            $assignedToId
        ]);

        // Add complaint details
        $stmt = $pdo->prepare("
            INSERT INTO complaint_details (
                complaint_id, 
                resolution_comments, 
                internal_notes
            ) VALUES (?, NULL, ?)
        ");
        
        $internalNotes = '';
        switch ($complaint['id']) {
            case 'COMP001':
                $internalNotes = 'IT team has been notified for immediate inspection';
                break;
            case 'COMP002':
                $internalNotes = 'Requires coordination with Delhi Jal Board';
                break;
            case 'COMP003':
                $internalNotes = 'New projector requisition might be needed';
                break;
            case 'COMP004':
                $internalNotes = 'Budget approval pending from finance department';
                break;
        }
        
        $stmt->execute([$complaint['id'], $internalNotes]);

        // Add complaint history
        $stmt = $pdo->prepare("
            INSERT INTO complaint_history (
                complaint_id, 
                status_id, 
                comments, 
                updated_by
            ) VALUES (?, ?, ?, ?)
        ");
        
        $historyComment = '';
        switch ($complaint['id']) {
            case 'COMP001':
                $historyComment = 'Complaint registered and assigned to HOD';
                break;
            case 'COMP002':
                $historyComment = 'Maintenance team has been deployed';
                break;
            case 'COMP003':
                $historyComment = 'Technical team inspection scheduled';
                break;
            case 'COMP004':
                $historyComment = 'Under review by administration';
                break;
        }
        
        $stmt->execute([
            $complaint['id'],
            $statusId,
            $historyComment,
            1 // Admin user ID
        ]);
    }

    // Re-enable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "Sample complaints have been successfully imported!\n";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
} 