<?php
require_once 'includes/db_config.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

session_start();
$auth = new Auth($pdo);
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user = $auth->getCurrentUser();
$complaint_id = $_POST['id'] ?? '';
if (!$complaint_id) {
    echo json_encode(['success' => false, 'error' => 'No complaint ID provided']);
    exit();
}

// Fetch complaint owner
$stmt = $pdo->prepare('SELECT user_id FROM complaints WHERE id = ?');
$stmt->execute([$complaint_id]);
$complaint = $stmt->fetch();
if (!$complaint) {
    echo json_encode(['success' => false, 'error' => 'Complaint not found']);
    exit();
}

$isOwner = ($complaint['user_id'] == $user['id']);
$isAdmin = ($user['role_id'] == 1);
if (!$isOwner && !$isAdmin) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

try {
    $pdo->beginTransaction();
    // Delete related data
    $stmt = $pdo->prepare('DELETE FROM complaint_attachments WHERE complaint_id = ?');
    $stmt->execute([$complaint_id]);
    $stmt = $pdo->prepare('DELETE FROM complaint_details WHERE complaint_id = ?');
    $stmt->execute([$complaint_id]);
    $stmt = $pdo->prepare('DELETE FROM complaint_history WHERE complaint_id = ?');
    $stmt->execute([$complaint_id]);
    // Delete complaint
    $stmt = $pdo->prepare('DELETE FROM complaints WHERE id = ?');
    $stmt->execute([$complaint_id]);
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Delete failed']);
} 