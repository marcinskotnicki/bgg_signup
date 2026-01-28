<?php
/**
 * AJAX Handler: Resign from Game
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

// Get player ID and game ID
$player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
$game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;

if (!$player_id || !$game_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Get player details
    $stmt = $db->prepare("SELECT * FROM players WHERE id = ? AND game_id = ?");
    $stmt->execute([$player_id, $game_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player) {
        echo json_encode(['success' => false, 'error' => 'Player not found']);
        exit;
    }
    
    // Check permissions
    $can_delete = false;
    
    if ($current_user) {
        // Admin or player's own signup
        if ($current_user['is_admin'] == 1 || ($player['user_id'] && $player['user_id'] == $current_user['id'])) {
            $can_delete = true;
        }
    }
    
    // If no user_id, anyone can delete (but may require verification based on config)
    if (!$player['user_id']) {
        // TODO: Implement verification if needed
        $can_delete = true;
    }
    
    if (!$can_delete) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    
    $db->beginTransaction();
    
    $was_reserve = $player['is_reserve'] == 1;
    $player_name = $player['player_name'];
    
    // Delete player
    $stmt = $db->prepare("DELETE FROM players WHERE id = ?");
    $stmt->execute([$player_id]);
    
    // If player was active (not reserve), promote first reserve player
    if (!$was_reserve && $config['allow_reserve_list']) {
        $stmt = $db->prepare("SELECT * FROM players WHERE game_id = ? AND is_reserve = 1 ORDER BY position ASC LIMIT 1");
        $stmt->execute([$game_id]);
        $first_reserve = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($first_reserve) {
            // Promote to active
            $stmt = $db->prepare("UPDATE players SET is_reserve = 0, position = ? WHERE id = ?");
            $stmt->execute([$player['position'], $first_reserve['id']]);
            
            // Send promotion email
            email_player_promoted($db, $first_reserve['id'], $game_id);
            
            // Reorder remaining reserve players
            $stmt = $db->prepare("UPDATE players SET position = position - 1 WHERE game_id = ? AND is_reserve = 1 AND position > ?");
            $stmt->execute([$game_id, $first_reserve['position']]);
        } else {
            // No reserves, just reorder active players
            $stmt = $db->prepare("UPDATE players SET position = position - 1 WHERE game_id = ? AND is_reserve = 0 AND position > ?");
            $stmt->execute([$game_id, $player['position']]);
        }
    } else {
        // Was reserve, just reorder reserve list
        $stmt = $db->prepare("UPDATE players SET position = position - 1 WHERE game_id = ? AND is_reserve = 1 AND position > ?");
        $stmt->execute([$game_id, $player['position']]);
    }
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'player_resigned', 
        "Player $player_name resigned from game ID: $game_id");
    
    // Send email notification
    email_player_resigned($db, $game_id, $player_name);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>