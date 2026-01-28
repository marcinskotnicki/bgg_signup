<?php
/**
 * AJAX Handler: Submit Join Game
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
if ($config['allow_logged_in'] === 'required_all' && !$current_user) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

// Get form data
$game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
$is_reserve = isset($_POST['is_reserve']) && $_POST['is_reserve'] == '1';
$player_name = isset($_POST['player_name']) ? trim($_POST['player_name']) : '';
$player_email = isset($_POST['player_email']) ? trim($_POST['player_email']) : '';
$knows_rules = isset($_POST['knows_rules']) ? $_POST['knows_rules'] : '';
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Validate required fields
if (!$game_id || !$player_name || !$knows_rules) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate email if required
if ($config['require_emails'] && empty($player_email)) {
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}

if ($player_email && !filter_var($player_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
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
    
    // Check if game is active
    if ($game['is_active'] == 0) {
        echo json_encode(['success' => false, 'error' => 'This game is not active']);
        exit;
    }
    
    // Count active players
    $stmt = $db->prepare("SELECT COUNT(*) FROM players WHERE game_id = ? AND is_reserve = 0");
    $stmt->execute([$game_id]);
    $active_count = $stmt->fetchColumn();
    
    // Determine if should join as reserve
    $join_as_reserve = $is_reserve || ($active_count >= $game['max_players']);
    
    // Get next position
    $stmt = $db->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM players WHERE game_id = ? AND is_reserve = ?");
    $stmt->execute([$game_id, $join_as_reserve ? 1 : 0]);
    $position = $stmt->fetchColumn();
    
    $db->beginTransaction();
    
    // Insert player
    $stmt = $db->prepare("INSERT INTO players (
        game_id, player_name, player_email, knows_rules, 
        comment, is_reserve, position, user_id, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    
    $stmt->execute([
        $game_id, $player_name, $player_email, $knows_rules,
        $comment, $join_as_reserve ? 1 : 0, $position,
        $current_user ? $current_user['id'] : null
    ]);
    
    $player_id = $db->lastInsertId();
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'player_joined', 
        "Player $player_name joined game: {$game['name']} (ID: $game_id)" . ($join_as_reserve ? " as reserve" : ""));
    
    // Send email notification
    email_player_joined($db, $game_id, $player_name, $join_as_reserve);
    
    echo json_encode([
        'success' => true,
        'player_id' => $player_id,
        'is_reserve' => $join_as_reserve
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>