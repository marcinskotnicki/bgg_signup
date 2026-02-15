<?php
/**
 * AJAX: Delete Thumbnail
 */

header('Content-Type: application/json');

// Check admin access
session_start();
require_once '../includes/auth.php';
require_once '../config.php';

try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$current_user = get_current_user($db);
if (!$current_user || !$current_user['is_admin']) {
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

// Handle file deletion
if (!isset($_POST['filename'])) {
    echo json_encode(['success' => false, 'error' => 'No filename provided']);
    exit;
}

$filename = $_POST['filename'];
$file_path = '../thumbnails/' . basename($filename); // Sanitize

if (!file_exists($file_path)) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

if (unlink($file_path)) {
    echo json_encode([
        'success' => true,
        'message' => 'Thumbnail deleted successfully: ' . $filename
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to delete thumbnail']);
}
?>
