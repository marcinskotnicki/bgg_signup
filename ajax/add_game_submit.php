<?php
/**
 * AJAX Handler: Submit Add Game
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

// Check login requirements
if (($config['allow_logged_in'] === 'required_games' || $config['allow_logged_in'] === 'required_all') && !$current_user) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

// Get form data
$table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 0;
$bgg_id = isset($_POST['bgg_id']) && $_POST['bgg_id'] !== '' ? intval($_POST['bgg_id']) : null;
$bgg_url = isset($_POST['bgg_url']) ? $_POST['bgg_url'] : null;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$thumbnail = isset($_POST['thumbnail']) ? $_POST['thumbnail'] : '';
$play_time = isset($_POST['play_time']) ? intval($_POST['play_time']) : 0;
$min_players = isset($_POST['min_players']) ? intval($_POST['min_players']) : 0;
$max_players = isset($_POST['max_players']) ? intval($_POST['max_players']) : 0;
$difficulty = isset($_POST['difficulty']) ? floatval($_POST['difficulty']) : 0;
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$language = isset($_POST['language']) ? $_POST['language'] : '';
$rules_explanation = isset($_POST['rules_explanation']) ? $_POST['rules_explanation'] : '';
$host_name = isset($_POST['host_name']) ? trim($_POST['host_name']) : '';
$host_email = isset($_POST['host_email']) ? trim($_POST['host_email']) : '';
$initial_comment = isset($_POST['initial_comment']) ? trim($_POST['initial_comment']) : '';
$join_as_player = isset($_POST['join_as_player']) && $_POST['join_as_player'] == '1';

// Validate required fields
if (!$table_id || !$name || !$play_time || !$min_players || !$max_players || !$start_time || !$language || !$rules_explanation || !$host_name) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate email if required
if ($config['require_emails'] && empty($host_email)) {
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}

if ($host_email && !filter_var($host_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

// Validate player counts
if ($min_players > $max_players) {
    echo json_encode(['success' => false, 'error' => 'Minimum players cannot be greater than maximum players']);
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
    
    // Insert game
    $stmt = $db->prepare("INSERT INTO games (
        table_id, bgg_id, bgg_url, name, thumbnail, play_time, 
        min_players, max_players, difficulty, start_time, 
        host_name, host_email, language, rules_explanation, 
        initial_comment, is_active, created_by_user_id, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, CURRENT_TIMESTAMP)");
    
    $stmt->execute([
        $table_id, $bgg_id, $bgg_url, $name, $thumbnail, $play_time,
        $min_players, $max_players, $difficulty, $start_time,
        $host_name, $host_email, $language, $rules_explanation,
        $initial_comment, $current_user ? $current_user['id'] : null
    ]);
    
    $game_id = $db->lastInsertId();
    
    // If user wants to join as first player
    if ($join_as_player) {
        $stmt = $db->prepare("INSERT INTO players (
            game_id, player_name, player_email, knows_rules, 
            comment, is_reserve, position, user_id, created_at
        ) VALUES (?, ?, ?, ?, ?, 0, 1, ?, CURRENT_TIMESTAMP)");
        
        $stmt->execute([
            $game_id, $host_name, $host_email, 'yes', 
            '', $current_user ? $current_user['id'] : null
        ]);
    }
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'game_added', "Added game: $name (ID: $game_id) to table $table_id");
    
    echo json_encode([
        'success' => true,
        'game_id' => $game_id
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>