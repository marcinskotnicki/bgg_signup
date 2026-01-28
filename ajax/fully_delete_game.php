<?php
/**
 * AJAX Handler: Fully Delete Game (Permanent)
 */

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

// Load auth helper
require_once '../includes/auth.php';

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get current user
$current_user = get_current_user($db);

// Only admins can fully delete
if (!$current_user || $current_user['is_admin'] != 1) {
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

// Get game ID
$game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;

if (!$game_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid game ID']);
    exit;
}

try {
    // Get game details for logging
    $stmt = $db->prepare("SELECT name FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Game not found']);
        exit;
    }
    
    $game_name = $game['name'];
    
    $db->beginTransaction();
    
    // Delete players (cascade should handle this, but being explicit)
    $stmt = $db->prepare("DELETE FROM players WHERE game_id = ?");
    $stmt->execute([$game_id]);
    
    // Delete comments
    $stmt = $db->prepare("DELETE FROM comments WHERE game_id = ?");
    $stmt->execute([$game_id]);
    
    // Delete game
    $stmt = $db->prepare("DELETE FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user['id'], 'game_fully_deleted', 
        "Game permanently deleted: $game_name (ID: $game_id)");
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>