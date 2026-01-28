<?php
/**
 * AJAX Handler: Submit Restore Game
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

// Get form data
$game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
$host_name = isset($_POST['host_name']) ? trim($_POST['host_name']) : '';
$host_email = isset($_POST['host_email']) ? trim($_POST['host_email']) : '';

// Validate required fields
if (!$game_id || !$host_name) {
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

try {
    // Get game details
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Game not found']);
        exit;
    }
    
    if ($game['is_active'] == 1) {
        echo json_encode(['success' => false, 'error' => 'Game is already active']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Restore game and update host
    $stmt = $db->prepare("UPDATE games SET 
        is_active = 1, 
        host_name = ?, 
        host_email = ?,
        created_by_user_id = ?
        WHERE id = ?");
    
    $stmt->execute([
        $host_name,
        $host_email,
        $current_user ? $current_user['id'] : null,
        $game_id
    ]);
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'game_restored', 
        "Game restored: {$game['name']} (ID: $game_id) by $host_name");
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>