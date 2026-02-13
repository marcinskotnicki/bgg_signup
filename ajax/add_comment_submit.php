<?php
/**
 * AJAX Handler: Submit Add Comment
 */

session_start();

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

// Check if comments are restricted
if ($config['restrict_comments'] && !$current_user) {
    echo json_encode(['success' => false, 'error' => 'Login required to comment']);
    exit;
}

// Get form data
$game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
$author_name = isset($_POST['author_name']) ? trim($_POST['author_name']) : '';
$author_email = isset($_POST['author_email']) ? trim($_POST['author_email']) : '';
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Validate CAPTCHA if enabled
if ($config['use_captcha']) {
    $captcha_answer = isset($_POST['captcha']) ? intval($_POST['captcha']) : 0;
    $expected_answer = isset($_SESSION['captcha_answer']) ? intval($_SESSION['captcha_answer']) : 0;
    
    if ($captcha_answer !== $expected_answer) {
        echo json_encode(['success' => false, 'error' => 'Incorrect CAPTCHA answer']);
        exit;
    }
    
    // Clear captcha from session
    unset($_SESSION['captcha_answer']);
}

// Validate required fields
if (!$game_id || !$author_name || !$comment) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate email if provided
if ($author_email && !filter_var($author_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

try {
    // Verify game exists
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Game not found']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Insert comment
    $stmt = $db->prepare("INSERT INTO comments (
        game_id, author_name, author_email, comment, user_id, created_at
    ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    
    $stmt->execute([
        $game_id,
        $author_name,
        $author_email,
        $comment,
        $current_user ? $current_user['id'] : null
    ]);
    
    $comment_id = $db->lastInsertId();
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'comment_added', 
        "Comment added to game: {$game['name']} (ID: $game_id) by $author_name");
    
    // Send email notification to all players (wrapped in try-catch so email failures don't break response)
    try {
        email_comment_added($db, $game_id, $author_name, $comment);
    } catch (Exception $e) {
        error_log("Email sending failed in add_comment_submit: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'comment_id' => $comment_id
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("add_comment_submit error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("add_comment_submit unexpected error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred']);
}
// Note: Closing ?> tag omitted to prevent whitespace output issues