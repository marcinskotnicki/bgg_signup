<?php
/**
 * restore_game_form.php
 * 
 * PURPOSE:
 * Shows a form asking for email when restoring a deleted game.
 * The person restoring becomes the NEW OWNER.
 * 
 * IMPORTANT:
 * This form collects the restoring person's email so they become
 * the new owner (not the original creator).
 */

session_start();
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/translations.php';

$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;
$current_user = get_current_user();

if (!$game_id) {
    echo '<div class="error">' . t('invalid_game_id') . '</div>';
    exit;
}

// Get game details
$db = get_db_connection();
$stmt = $db->prepare("
    SELECT 
        id,
        name,
        description,
        inactive
    FROM games 
    WHERE id = ?
");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    echo '<div class="error">' . t('game_not_found') . '</div>';
    exit;
}

if ($game['inactive'] != 1) {
    echo '<div class="error">' . t('game_not_deleted') . '</div>';
    exit;
}
?>

<div class="modal-form">
    <h2><?php echo t('restore_game'); ?></h2>
    
    <div class="game-info">
        <strong><?php echo t('game_name'); ?>:</strong> <?php echo htmlspecialchars($game['name']); ?>
    </div>
    
    <?php if ($current_user): ?>
        <!-- ============================================
             LOGGED-IN USER: No email needed
             ============================================
             Logged-in users are authenticated, so we don't
             need their email. They'll become the owner via
             their user account.
        -->
        <div class="info-box">
            <?php echo t('restore_game_logged_in_info'); ?>
        </div>
        
        <form id="restore-game-form" onsubmit="return submitRestoreGame(event, <?php echo $game_id; ?>);">
            <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()"><?php echo t('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo t('restore_game'); ?></button>
            </div>
        </form>
        
    <?php else: ?>
        <!-- ============================================
             NOT LOGGED IN: Ask for email
             ============================================
             When restoring without login, the person's email
             becomes the new owner email. This is important
             because they'll need it to edit/delete later.
        -->
        <div class="info-box">
            <?php echo t('restore_game_ownership_info'); ?>
        </div>
        
        <form id="restore-game-form" onsubmit="return submitRestoreGame(event, <?php echo $game_id; ?>);">
            <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
            
            <div class="form-group">
                <label><?php echo t('your_email'); ?>:</label>
                <input type="email" 
                       name="email" 
                       class="form-control" 
                       placeholder="<?php echo t('enter_your_email'); ?>"
                       <?php echo !empty($config['require_email']) ? 'required' : ''; ?>>
                <small><?php echo t('restore_game_email_help'); ?></small>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()"><?php echo t('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo t('restore_game'); ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
/**
 * submitRestoreGame() - Handle restore form submission
 * 
 * Sends the restore request with the restorer's email (if not logged in)
 * so they become the new owner of the game.
 */
function submitRestoreGame(event, gameId) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    // Send to server
    $.post('ajax/restore_game_submit.php', {
        game_id: formData.get('game_id'),
        email: formData.get('email') || ''
    }, function(response) {
        if (response.success) {
            closeModal();
            // Reload page to show restored game
            location.reload();
        } else {
            alert(response.error || 'Failed to restore game');
        }
    }, 'json').fail(function() {
        alert('Error restoring game. Please try again.');
    });
    
    return false;
}
</script>
