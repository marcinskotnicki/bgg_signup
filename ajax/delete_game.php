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
    
    // Determine deletion type based on config
    $deletion_mode = $config['deletion_mode'];
    $deletion_type = 'soft'; // default
    
    if ($deletion_mode === 'hard_only') {
        // Hard delete only - permanently delete
        $deletion_type = 'hard';
        
    } elseif ($deletion_mode === 'allow_choice') {
        // User can choose - check parameter
        $delete_type_param = isset($_POST['delete_type']) ? $_POST['delete_type'] : 'soft';
        $deletion_type = ($delete_type_param === 'hard') ? 'hard' : 'soft';
        
    } else {
        // soft_only - always soft delete
        $deletion_type = 'soft';
    }
    
    if ($deletion_type === 'hard') {
        // Permanently delete game
        $stmt = $db->prepare("DELETE FROM games WHERE id = ?");
        $stmt->execute([$game_id]);
    } else {
        // Soft delete - mark as inactive
        $stmt = $db->prepare("UPDATE games SET is_active = 0 WHERE id = ?");
        $stmt->execute([$game_id]);
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