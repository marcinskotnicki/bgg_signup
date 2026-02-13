<?php
/**
 * AJAX Handler: Search BoardGameGeek
 */

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

// Load BGG API helper
require_once '../includes/bgg_api.php';

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get search query
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if (empty($query)) {
    echo json_encode(['success' => false, 'error' => 'Search query required']);
    exit;
}

// Search BGG (no token required for XML API2)
$results = search_bgg_games($db, $query);

if (isset($results['error'])) {
    echo json_encode(['success' => false, 'error' => $results['error']]);
    exit;
}

echo json_encode([
    'success' => true,
    'results' => $results
]);
?>