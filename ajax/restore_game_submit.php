<?php
/**
 * restore_game_submit.php
 * 
 * PURPOSE:
 * Restores a soft-deleted game and transfers ownership to the person restoring it.
 * 
 * CRITICAL SECURITY FIX:
 * When a game is restored, the person restoring becomes the NEW OWNER.
 * We must update:
 * - creator_email → restoring person's email
 * - verification_code → NEW code for the restoring person
 * - created_by_user_id → restoring person's user ID (if logged in)
 * 
 * Without this, the ORIGINAL owner could still edit/delete the restored game!
 * 
 * CALLED BY:
 * - restore_game_form.php form submission
 * - User clicks "Restore" button on a deleted game
 */

session_start();
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/translations.php';

header('Content-Type: application/json');

try {
    $db = get_db_connection();
    
    // Get form data
    $game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
    $restorer_email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    // ============================================
    // VALIDATION: Check game ID
    // ============================================
    if (!$game_id) {
        echo json_encode(['success' => false, 'error' => t('invalid_game_id')]);
        exit;
    }
    
    // ============================================
    // STEP 1: Get the deleted game
    // ============================================
    // We need to verify:
    // - Game exists
    // - Game is deleted (inactive = 1)
    // - Get current owner info (for logging/audit trail)
    $stmt = $db->prepare("
        SELECT 
            id,
            name,
            creator_email as old_creator_email,
            created_by_user_id as old_user_id,
            inactive
        FROM games 
        WHERE id = ?
    ");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        echo json_encode(['success' => false, 'error' => t('game_not_found')]);
        exit;
    }
    
    if ($game['inactive'] != 1) {
        // Game is not deleted - can't restore it
        echo json_encode(['success' => false, 'error' => 'Game is not deleted']);
        exit;
    }
    
    // ============================================
    // STEP 2: Determine new ownership
    // ============================================
    $current_user = get_current_user();
    $new_creator_email = null;
    $new_user_id = null;
    $new_verification_code = null;
    
    if ($current_user) {
        // ============================================
        // LOGGED-IN USER: Transfer ownership to their account
        // ============================================
        // Logged-in users are authenticated, so we trust them
        // Set their user ID as the new owner
        // No email or verification code needed (they're logged in)
        $new_user_id = $current_user['id'];
        $new_creator_email = null;  // Not needed - they're logged in
        $new_verification_code = null;  // Not needed - they're logged in
        
    } else {
        // ============================================
        // NOT LOGGED IN: Use email for ownership
        // ============================================
        
        if ($restorer_email) {
            // Email provided - validate it
            if (!filter_var($restorer_email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => t('invalid_email')]);
                exit;
            }
            
            // Generate NEW verification code for the restoring person
            // This is critical - we don't want the old owner's code!
            $new_verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $new_creator_email = $restorer_email;
            
        } else {
            // ============================================
            // NO EMAIL PROVIDED
            // ============================================
            // User chose not to provide email - this is allowed
            // They can restore the game but won't be able to verify
            // ownership later (unless they remember to edit it soon)
            $new_creator_email = null;
            $new_verification_code = null;
        }
    }
    
    // ============================================
    // STEP 3: Restore game with NEW ownership
    // ============================================
    // This is the critical part - we update:
    // 1. inactive = 0 (restore the game)
    // 2. creator_email = new person's email
    // 3. verification_code = new code
    // 4. created_by_user_id = new person's user ID (if logged in)
    //
    // This transfers complete ownership to the restoring person
    $stmt = $db->prepare("
        UPDATE games 
        SET 
            inactive = 0,
            creator_email = ?,
            verification_code = ?,
            created_by_user_id = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $new_creator_email,
        $new_verification_code,
        $new_user_id,
        $game_id
    ]);
    
    // ============================================
    // STEP 4: Log the ownership transfer (optional but recommended)
    // ============================================
    // For audit trail, you might want to log:
    // - Who was the old owner (email or user_id)
    // - Who is the new owner
    // - When this happened
    // 
    // This could be useful for:
    // - Debugging ownership disputes
    // - Security audits
    // - Understanding game lifecycle
    //
    // Example (if you have an audit_log table):
    // $stmt = $db->prepare("
    //     INSERT INTO audit_log (action, game_id, old_owner, new_owner, timestamp)
    //     VALUES ('game_restored', ?, ?, ?, NOW())
    // ");
    // $stmt->execute([
    //     $game_id,
    //     $game['old_creator_email'] ?: $game['old_user_id'],
    //     $new_creator_email ?: $new_user_id
    // ]);
    
    // ============================================
    // SUCCESS: Game restored with new ownership
    // ============================================
    echo json_encode([
        'success' => true,
        'message' => t('game_restored_successfully'),
        'new_owner' => $current_user ? $current_user['name'] : $new_creator_email,
        'game_name' => $game['name']
    ]);
    
} catch (PDOException $e) {
    error_log('Restore game error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => t('database_error')
    ]);
}
