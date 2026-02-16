<?php
/**
 * AJAX Handler: Submit Create Poll
 */

// Start output buffering to catch any warnings/notices
ob_start();

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

// Load translations
require_once '../includes/translations.php';

// Load auth helper
require_once '../includes/auth.php';

/**
 * Send JSON response with clean output buffer
 */
function send_json($data) {
    // Clear output buffer if it exists
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($data);
    exit;
}

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    send_json(['success' => false, 'error' => 'Database connection failed']);
}

// Get current user
$current_user = get_current_user($db);

// Check login requirements
if (($config['allow_logged_in'] === 'required_games' || $config['allow_logged_in'] === 'required_all') && !$current_user) {
    send_json(['success' => false, 'error' => 'Login required']);
}

// Get poll data
$poll_data_json = isset($_POST['poll_data']) ? $_POST['poll_data'] : '';
$poll_data = json_decode($poll_data_json, true);

if (!$poll_data || !isset($poll_data['table_id']) || !isset($poll_data['creator_name']) || !isset($poll_data['options'])) {
    send_json(['success' => false, 'error' => 'Invalid poll data']);
}

$table_id = intval($poll_data['table_id']);
$creator_name = trim($poll_data['creator_name']);
$creator_email = isset($poll_data['creator_email']) ? trim($poll_data['creator_email']) : '';
$comment = isset($poll_data['comment']) ? trim($poll_data['comment']) : '';
$start_time = isset($poll_data['start_time']) ? trim($poll_data['start_time']) : '';
$options = $poll_data['options'];

// Validate
if (!$table_id || !$creator_name || count($options) < 2) {
    send_json(['success' => false, 'error' => 'Poll must have at least 2 options']);
}

if ($config['require_emails'] && empty($creator_email)) {
    send_json(['success' => false, 'error' => 'Email is required']);
}

if (empty($start_time)) {
    send_json(['success' => false, 'error' => 'Start time is required']);
}

try {
    // Verify table exists
    $stmt = $db->prepare("SELECT * FROM tables WHERE id = ?");
    $stmt->execute([$table_id]);
    $table = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$table) {
        send_json(['success' => false, 'error' => 'Table not found']);
    }
    
    $db->beginTransaction();
    
    // Check if comment column exists (for backward compatibility)
    try {
        $columns = $db->query("PRAGMA table_info(polls)")->fetchAll(PDO::FETCH_ASSOC);
        $has_comment = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'comment') {
                $has_comment = true;
                break;
            }
        }
    } catch (Exception $e) {
        $has_comment = false;
    }
    
    // Create poll (with or without comment depending on schema)
    if ($has_comment) {
        $stmt = $db->prepare("INSERT INTO polls (
            table_id, creator_name, creator_email, comment, created_by_user_id, start_time, is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)");
        
        $stmt->execute([
            $table_id,
            $creator_name,
            $creator_email,
            $comment,
            $current_user ? $current_user['id'] : null,
            $start_time
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO polls (
            table_id, creator_name, creator_email, created_by_user_id, start_time, is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)");
        
        $stmt->execute([
            $table_id,
            $creator_name,
            $creator_email,
            $current_user ? $current_user['id'] : null,
            $start_time
        ]);
    }
    
    $poll_id = $db->lastInsertId();
    
    // Create poll options
    foreach ($options as $option) {
        $stmt = $db->prepare("INSERT INTO poll_options (
            poll_id, bgg_id, bgg_url, game_name, thumbnail, 
            play_time, min_players, max_players, difficulty,
            vote_threshold, display_order, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        
        $stmt->execute([
            $poll_id,
            $option['bgg_id'] ? intval($option['bgg_id']) : null,
            $option['bgg_url'] ?? null,
            $option['game_name'],
            $option['thumbnail'] ?? null,
            isset($option['play_time']) && $option['play_time'] ? intval($option['play_time']) : null,
            isset($option['min_players']) && $option['min_players'] ? intval($option['min_players']) : null,
            isset($option['max_players']) && $option['max_players'] ? intval($option['max_players']) : null,
            isset($option['difficulty']) && $option['difficulty'] ? floatval($option['difficulty']) : null,
            intval($option['vote_threshold']),
            intval($option['display_order'])
        ]);
    }
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'poll_created', 
        "Poll created by $creator_name with " . count($options) . " options on table $table_id");
    
    send_json([
        'success' => true,
        'poll_id' => $poll_id
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    send_json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>