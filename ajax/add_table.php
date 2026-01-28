<?php
/**
 * AJAX Handler: Add New Table
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

// Get day_id from POST
$day_id = isset($_POST['day_id']) ? intval($_POST['day_id']) : 0;

if (!$day_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid day ID']);
    exit;
}

try {
    // Get event day details
    $stmt = $db->prepare("SELECT * FROM event_days WHERE id = ?");
    $stmt->execute([$day_id]);
    $day = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$day) {
        echo json_encode(['success' => false, 'error' => 'Day not found']);
        exit;
    }
    
    // Count existing tables
    $stmt = $db->prepare("SELECT COUNT(*) FROM tables WHERE event_day_id = ?");
    $stmt->execute([$day_id]);
    $table_count = $stmt->fetchColumn();
    
    // Check if we can add more tables
    if ($table_count >= $day['max_tables']) {
        echo json_encode(['success' => false, 'error' => 'Maximum number of tables reached']);
        exit;
    }
    
    // Get next table number
    $stmt = $db->prepare("SELECT COALESCE(MAX(table_number), 0) + 1 FROM tables WHERE event_day_id = ?");
    $stmt->execute([$day_id]);
    $next_table_number = $stmt->fetchColumn();
    
    // Insert new table
    $stmt = $db->prepare("INSERT INTO tables (event_day_id, table_number) VALUES (?, ?)");
    $stmt->execute([$day_id, $next_table_number]);
    $table_id = $db->lastInsertId();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'table_added', "Added table $next_table_number to day $day_id");
    
    echo json_encode([
        'success' => true,
        'table_id' => $table_id,
        'table_number' => $next_table_number
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>