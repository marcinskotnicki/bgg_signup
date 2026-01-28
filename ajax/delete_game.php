<?php
/**
 * AJAX Handler: Delete Game
 */

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

// Load auth helper
require_once '../includes/auth.php';

// Load email helper
require_once '../includes/email.php';

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

// Get game ID
$game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;

if (!$game_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid game ID']);
    exit;
}

try {
    // Get game details
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Game not found']);
        exit;
    }
    
    // Check permissions
    $can_delete = false;
    
    if ($current_user) {
        // Admin or game creator
        if ($current_user['is_admin'] == 1 || ($game['created_by_user_id'] && $game['created_by_user_id'] == $current_user['id'])) {
            $can_delete = true;
        }
    }
    
    // If no created_by_user_id, anyone can delete (but may require verification)
    if (!$game['created_by_user_id']) {
        // TODO: Implement verification if needed
        $can_delete = true;
    }
    
    if (!$can_delete) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Check if full deletion is allowed
    if ($config['allow_full_deletion']) {
        // User can choose - for now, default to soft delete
        // (Full delete option would be in a separate handler or with a parameter)
        
        // Soft delete - mark as inactive
        $stmt = $db->prepare("UPDATE games SET is_active = 0 WHERE id = ?");
        $stmt->execute([$game_id]);
        
        $deletion_type = 'soft';
    } else {
        // Only soft delete allowed
        $stmt = $db->prepare("UPDATE games SET is_active = 0 WHERE id = ?");
        $stmt->execute([$game_id]);
        
        $deletion_type = 'soft';
    }
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'game_deleted', 
        "Game deleted ($deletion_type): {$game['name']} (ID: $game_id)");
    
    // Send email notification
    email_game_deleted($db, $game_id);
    
    echo json_encode([
        'success' => true,
        'deletion_type' => $deletion_type
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>