<?php
/**
 * AJAX: Upload Thumbnail
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

// Handle file upload
if (!isset($_FILES['thumbnail'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$upload_dir = '../thumbnails/';

// Create directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$file = $_FILES['thumbnail'];
$filename = basename($file['name']);
$target_path = $upload_dir . $filename;

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$file_type = mime_content_type($file['tmp_name']);

if (!in_array($file_type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.']);
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 2MB.']);
    exit;
}

if (file_exists($target_path)) {
    echo json_encode(['success' => false, 'error' => 'File already exists. Please rename the file or delete the existing one first.']);
    exit;
}

if (move_uploaded_file($file['tmp_name'], $target_path)) {
    echo json_encode([
        'success' => true,
        'message' => 'Thumbnail uploaded successfully: ' . $filename
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
}
?>
