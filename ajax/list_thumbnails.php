<?php
/**
 * AJAX: List Thumbnails
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

// Get thumbnails
$thumbnails = [];
$thumbnail_dir = '../thumbnails/';

if (is_dir($thumbnail_dir)) {
    $files = scandir($thumbnail_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir($thumbnail_dir . $file)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $thumbnails[] = $file;
            }
        }
    }
    sort($thumbnails);
}

echo json_encode([
    'success' => true,
    'thumbnails' => $thumbnails
]);
?>
