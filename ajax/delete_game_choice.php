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

<style>
.delete-choice-dialog {
    max-width: 500px;
}

.delete-choice-dialog h3 {
    margin-top: 0;
    color: #e74c3c;
}

.delete-option {
    margin: 15px 0;
    padding: 15px;
    border: 2px solid #34495e;
    border-radius: 4px;
    cursor: pointer;
}

.delete-option:hover {
    border-color: #3498db;
    background: rgba(52, 152, 219, 0.1);
}

.delete-option label {
    cursor: pointer;
    display: block;
    margin: 0;
}

.delete-option input[type="radio"] {
    margin-right: 10px;
}

.delete-option p {
    margin: 5px 0 0 25px;
    font-size: 13px;
    color: #bdc3c7;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
}
</style>
