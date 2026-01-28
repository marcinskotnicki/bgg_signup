<?php
/**
 * AJAX Handler: Get BGG Game Details
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

// Get game ID
$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;

if (!$game_id) {
    echo json_encode(['success' => false, 'error' => 'Game ID required']);
    exit;
}

// Get game details from BGG
$details = get_bgg_game_details($db, $game_id);

if (!$details) {
    echo json_encode(['success' => false, 'error' => 'Game not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'game' => $details
]);
?>