<?php
/**
 * AJAX Handler: Restore Game Form
 */

// Load configuration
$config = require_once '../config.php';

// Load translation system
require_once '../includes/translations.php';

// Load auth helper
require_once '../includes/auth.php';

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed');
}

// Get current user
$current_user = get_current_user($db);

// Get game ID
$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;

if (!$game_id) {
    die('Invalid game ID');
}

// Get game details
$stmt = $db->prepare("SELECT g.*, e.name as event_name 
                      FROM games g 
                      JOIN tables t ON g.table_id = t.id 
                      JOIN event_days ed ON t.event_day_id = ed.id 
                      JOIN events e ON ed.event_id = e.id 
                      WHERE g.id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game || $game['is_active'] == 1) {
    die('Game not found or already active');
}

// Get all players from this game
$stmt = $db->prepare("SELECT * FROM players WHERE game_id = ? ORDER BY is_reserve ASC, position ASC");
$stmt->execute([$game_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-fill user data if logged in
$default_name = $current_user ? $current_user['name'] : '';
$default_email = $current_user ? $current_user['email'] : '';
?>

<div class="restore-game-form">
    <h2><?php echo t('restore_game'); ?></h2>
    
    <div class="game-info">
        <h3><?php echo htmlspecialchars($game['name']); ?></h3>
        <p><?php echo htmlspecialchars($game['event_name']); ?> - <?php echo $game['start_time']; ?></p>
        <p class="player-count"><?php echo count($players); ?> <?php echo t('players_signed_up'); ?></p>
    </div>
    
    <div class="restore-info">
        <?php echo t('restore_game_info'); ?>
    </div>
    
    <form id="restore-game-form">
        <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
        
        <!-- New Host Name -->
        <div class="form-group">
            <label><?php echo t('your_name'); ?>: <span class="required">*</span></label>
            <input type="text" name="host_name" class="form-control" value="<?php echo htmlspecialchars($default_name); ?>" required>
        </div>
        
        <!-- New Host Email -->
        <div class="form-group">
            <label><?php echo t('your_email'); ?>:<?php if ($config['require_emails']): ?> <span class="required">*</span><?php endif; ?></label>
            <input type="email" name="host_email" class="form-control" value="<?php echo htmlspecialchars($default_email); ?>" <?php echo $config['require_emails'] ? 'required' : ''; ?>>
        </div>
        
        <div class="form-actions">
            <button type="button" onclick="closeModal()" class="btn btn-secondary"><?php echo t('cancel'); ?></button>
            <button type="submit" id="submit-restore" class="btn btn-primary" disabled><?php echo t('restore_game'); ?></button>
        </div>
    </form>
</div>


<script>
$(document).ready(function() {
    // Validate form and enable/disable submit button
    function validateForm() {
        const requiredFields = $('#restore-game-form').find('[required]');
        let allFilled = true;
        
        requiredFields.each(function() {
            if (!$(this).val()) {
                allFilled = false;
                return false;
            }
        });
        
        $('#submit-restore').prop('disabled', !allFilled);
    }
    
    // Monitor all form inputs
    $('#restore-game-form').on('input change', 'input', validateForm);
    
    // Initial validation
    validateForm();
    
    // Form submission
    $('#restore-game-form').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $('#submit-restore').prop('disabled', true).text('<?php echo t('saving'); ?>...');
        
        $.post('../ajax/restore_game_submit.php', formData, function(response) {
            if (response.success) {
                closeModal();
                location.reload();
            } else {
                alert(response.error || '<?php echo t('error_occurred'); ?>');
                $('#submit-restore').prop('disabled', false).text('<?php echo t('restore_game'); ?>');
            }
        });
    });
});
</script>