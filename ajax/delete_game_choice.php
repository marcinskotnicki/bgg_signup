<?php
/**
 * Delete Game Choice Dialog
 * Shows options for soft/hard delete if config allows
 */

// Load configuration
$config = require_once '../config.php';

// Load translation system
require_once '../includes/translations.php';

// Get game ID
$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;

if (!$game_id) {
    die('Invalid game ID');
}

// Check deletion mode from config
$deletion_mode = isset($config['deletion_mode']) ? $config['deletion_mode'] : 'soft_only';
?>

<div class="delete-choice-dialog">
    <h3><?php echo t('delete_game'); ?></h3>
    
    <?php if ($deletion_mode === 'allow_choice'): ?>
        <p><?php echo t('choose_deletion_type'); ?></p>
        
        <form id="delete-game-choice-form">
            <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
            
            <div class="delete-option">
                <label>
                    <input type="radio" name="delete_type" value="soft" checked>
                    <strong><?php echo t('soft_delete'); ?></strong>
                    <p><?php echo t('soft_delete_description'); ?></p>
                </label>
            </div>
            
            <div class="delete-option">
                <label>
                    <input type="radio" name="delete_type" value="hard">
                    <strong><?php echo t('hard_delete'); ?></strong>
                    <p><?php echo t('hard_delete_description'); ?></p>
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-danger"><?php echo t('confirm_delete'); ?></button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()"><?php echo t('cancel'); ?></button>
            </div>
        </form>
        
        <script>
        $('#delete-game-choice-form').submit(function(e) {
            e.preventDefault();
            
            const formData = $(this).serialize();
            
            $.post('ajax/delete_game.php', formData, function(response) {
                if (response.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert(response.error || '<?php echo t('error_occurred'); ?>');
                }
            }, 'json');
        });
        </script>
    <?php else: ?>
        <!-- Simple confirmation - mode is either soft_only or hard_only -->
        <p>
            <?php 
            if ($deletion_mode === 'hard_only') {
                echo t('confirm_hard_delete_only');
            } else {
                echo t('confirm_soft_delete_only');
            }
            ?>
        </p>
        
        <form id="delete-game-simple-form">
            <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
            <input type="hidden" name="delete_type" value="<?php echo $deletion_mode === 'hard_only' ? 'hard' : 'soft'; ?>">
            
            <div class="form-actions">
                <button type="submit" class="btn btn-danger"><?php echo t('confirm_delete'); ?></button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()"><?php echo t('cancel'); ?></button>
            </div>
        </form>
        
        <script>
        $('#delete-game-simple-form').submit(function(e) {
            e.preventDefault();
            
            const formData = $(this).serialize();
            
            $.post('ajax/delete_game.php', formData, function(response) {
                if (response.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert(response.error || '<?php echo t('error_occurred'); ?>');
                }
            }, 'json');
        });
        </script>
    <?php endif; ?>
</div>

