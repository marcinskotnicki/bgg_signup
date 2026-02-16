<?php
/**
 * AJAX Handler: Edit Game Submit
 */

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

// Load translations
require_once '../includes/translations.php';

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

// Get form data
$game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
$game_name = isset($_POST['game_name']) ? trim($_POST['game_name']) : '';
$play_time = isset($_POST['play_time']) ? intval($_POST['play_time']) : 0;
$min_players = isset($_POST['min_players']) ? intval($_POST['min_players']) : 1;
$max_players = isset($_POST['max_players']) ? intval($_POST['max_players']) : 1;
$difficulty = isset($_POST['difficulty']) ? floatval($_POST['difficulty']) : 0;
$start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
$language = isset($_POST['language']) ? trim($_POST['language']) : 'en';
$rules_explanation = isset($_POST['rules_explanation']) ? trim($_POST['rules_explanation']) : 'will_explain';
$thumbnail = isset($_POST['selected_thumbnail']) ? trim($_POST['selected_thumbnail']) : '';
$host_name = isset($_POST['host_name']) ? trim($_POST['host_name']) : '';
$host_email = isset($_POST['host_email']) ? trim($_POST['host_email']) : '';
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Validate required fields
if (!$game_id || !$game_name || !$play_time || !$start_time || !$host_name) {
    echo json_encode(['success' => false, 'error' => 'All required fields must be filled']);
    exit;
}

// Validate email if provided
if ($host_email && !filter_var($host_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

// Validate player counts
if ($min_players < 1 || $max_players < $min_players) {
    echo json_encode(['success' => false, 'error' => 'Invalid player counts']);
    exit;
}

try {
    // Get current game details
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Game not found']);
        exit;
    }
    
    // Check permissions
    $can_edit = false;
    
    if ($current_user) {
        // Admin or game creator
        if ($current_user['is_admin'] == 1 || ($game['created_by_user_id'] && $game['created_by_user_id'] == $current_user['id'])) {
            $can_edit = true;
        }
    }
    
    // If no created_by_user_id, anyone can edit
    if (!$game['created_by_user_id']) {
        $can_edit = true;
    }
    
    if (!$can_edit) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Update game
    $stmt = $db->prepare("UPDATE games SET 
        name = ?,
        play_time = ?,
        min_players = ?,
        max_players = ?,
        difficulty = ?,
        start_time = ?,
        language = ?,
        rules_explanation = ?,
        thumbnail = ?,
        host_name = ?,
        host_email = ?,
        initial_comment = ?
        WHERE id = ?");
    
    $stmt->execute([
        $game_name,
        $play_time,
        $min_players,
        $max_players,
        $difficulty,
        $start_time,
        $language,
        $rules_explanation,
        $thumbnail,
        $host_name,
        $host_email,
        $comment,
        $game_id
    ]);
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'game_edited', 
        "Game edited: $game_name (ID: $game_id)");
    
    // Send email notification to players
    email_game_changed($db, $game_id);
    
    echo json_encode([
        'success' => true,
        'game_id' => $game_id
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
