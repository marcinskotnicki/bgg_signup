<?php
/**
 * AJAX Handler: Submit Create Poll
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

// Check login requirements
if (($config['allow_logged_in'] === 'required_games' || $config['allow_logged_in'] === 'required_all') && !$current_user) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

// Get poll data
$poll_data_json = isset($_POST['poll_data']) ? $_POST['poll_data'] : '';
$poll_data = json_decode($poll_data_json, true);

if (!$poll_data || !isset($poll_data['table_id']) || !isset($poll_data['creator_name']) || !isset($poll_data['options'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid poll data']);
    exit;
}

$table_id = intval($poll_data['table_id']);
$creator_name = trim($poll_data['creator_name']);
$creator_email = isset($poll_data['creator_email']) ? trim($poll_data['creator_email']) : '';
$start_time = isset($poll_data['start_time']) ? trim($poll_data['start_time']) : '';
$options = $poll_data['options'];

// Validate
if (!$table_id || !$creator_name || count($options) < 2) {
    echo json_encode(['success' => false, 'error' => 'Poll must have at least 2 options']);
    exit;
}

if ($config['require_emails'] && empty($creator_email)) {
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}

if (empty($start_time)) {
    echo json_encode(['success' => false, 'error' => 'Start time is required']);
    exit;
}

try {
    // Verify table exists
    $stmt = $db->prepare("SELECT * FROM tables WHERE id = ?");
    $stmt->execute([$table_id]);
    $table = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$table) {
        echo json_encode(['success' => false, 'error' => 'Table not found']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Create poll
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
    
    $poll_id = $db->lastInsertId();
    
    // Create poll options
    foreach ($options as $option) {
        $stmt = $db->prepare("INSERT INTO poll_options (
            poll_id, bgg_id, bgg_url, game_name, thumbnail, 
            vote_threshold, display_order, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        
        $stmt->execute([
            $poll_id,
            $option['bgg_id'] ? intval($option['bgg_id']) : null,
            $option['bgg_url'] ?? null,
            $option['game_name'],
            $option['thumbnail'] ?? null,
            intval($option['vote_threshold']),
            intval($option['display_order'])
        ]);
    }
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'poll_created', 
        "Poll created by $creator_name with " . count($options) . " options on table $table_id");
    
    echo json_encode([
        'success' => true,
        'poll_id' => $poll_id
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>