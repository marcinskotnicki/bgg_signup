<?php
/**
 * AJAX Handler: Private Message Form
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

// Check if private messages are enabled
if (!$config['allow_private_messages']) {
    die('Private messages are disabled');
}

// Get current user
$current_user = get_current_user($db);

// Get parameters
$player_id = isset($_GET['player_id']) ? intval($_GET['player_id']) : 0;
$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;

$recipient_name = '';
$recipient_email = '';
$message_context = '';

if ($player_id) {
    // Message to a specific player
    $stmt = $db->prepare("
        SELECT p.player_name, p.player_email, g.name as game_name, e.name as event_name
        FROM players p
        JOIN games g ON p.game_id = g.id
        JOIN tables t ON g.table_id = t.id
        JOIN event_days ed ON t.event_day_id = ed.id
        JOIN events e ON ed.event_id = e.id
        WHERE p.id = ?
    ");
    $stmt->execute([$player_id]);
    $player_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player_data) {
        die('Player not found');
    }
    
    $recipient_name = $player_data['player_name'];
    $recipient_email = $player_data['player_email'];
    $message_context = "{$player_data['event_name']}: {$player_data['game_name']}";
    
} elseif ($game_id) {
    // Message to all players in a game
    $stmt = $db->prepare("
        SELECT g.name as game_name, e.name as event_name
        FROM games g
        JOIN tables t ON g.table_id = t.id
        JOIN event_days ed ON t.event_day_id = ed.id
        JOIN events e ON ed.event_id = e.id
        WHERE g.id = ?
    ");
    $stmt->execute([$game_id]);
    $game_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game_data) {
        die('Game not found');
    }
    
    // Get player count
    $stmt = $db->prepare("SELECT COUNT(*) FROM players WHERE game_id = ?");
    $stmt->execute([$game_id]);
    $player_count = $stmt->fetchColumn();
    
    $recipient_name = t('all_players');
    $message_context = "{$game_data['event_name']}: {$game_data['game_name']} ({$player_count} " . t('players') . ")";
    
} else {
    die('Invalid parameters');
}

// Pre-fill with user data if logged in
$sender_name = $current_user ? $current_user['name'] : '';
$sender_email = $current_user ? $current_user['email'] : '';
?>

<div class="private-message-form-container">
    <h3><?php echo t('send_private_message'); ?></h3>
    
    <div class="message-info">
        <p><strong><?php echo t('to'); ?>:</strong> <?php echo htmlspecialchars($recipient_name); ?></p>
        <p><strong><?php echo t('regarding'); ?>:</strong> <?php echo htmlspecialchars($message_context); ?></p>
    </div>
    
    <form id="private-message-form" class="modal-form">
        <input type="hidden" name="player_id" value="<?php echo $player_id; ?>">
        <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
        <input type="hidden" name="context" value="<?php echo htmlspecialchars($message_context); ?>">
        
        <div class="form-group">
            <label><?php echo t('your_name'); ?>: <span class="required">*</span></label>
            <input type="text" name="sender_name" class="form-control" value="<?php echo htmlspecialchars($sender_name); ?>" required <?php echo $current_user ? 'readonly' : ''; ?>>
        </div>
        
        <div class="form-group">
            <label><?php echo t('your_email'); ?>: <span class="required">*</span></label>
            <input type="email" name="sender_email" class="form-control" value="<?php echo htmlspecialchars($sender_email); ?>" required <?php echo $current_user ? 'readonly' : ''; ?>>
            <small><?php echo t('reply_to_address'); ?></small>
        </div>
        
        <div class="form-group">
            <label><?php echo t('message'); ?>: <span class="required">*</span></label>
            <textarea name="message" class="form-control" rows="6" required></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" id="submit-message" class="btn btn-primary">
                <?php echo t('send_message'); ?>
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeModal()">
                <?php echo t('cancel'); ?>
            </button>
        </div>
    </form>
</div>

<style>
.private-message-form-container {
    padding: 20px;
}

.private-message-form-container h3 {
    margin-top: 0;
    color: #2c3e50;
    font-size: 20px;
}

.message-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    border-left: 4px solid #3498db;
}

.message-info p {
    margin: 5px 0;
}

.modal-form .form-group {
    margin-bottom: 20px;
}

.modal-form .form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #2c3e50;
}

.modal-form .form-group .required {
    color: #e74c3c;
}

.modal-form .form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
    font-family: inherit;
}

.modal-form textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.modal-form .form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.modal-form .form-control[readonly] {
    background: #f5f5f5;
    cursor: not-allowed;
}

.modal-form small {
    display: block;
    margin-top: 5px;
    color: #7f8c8d;
    font-size: 12px;
}

.modal-form .form-actions {
    margin-top: 25px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.modal-form .btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
    transition: background 0.3s;
}

.modal-form .btn-primary {
    background: #27ae60;
    color: white;
}

.modal-form .btn-primary:hover {
    background: #229954;
}

.modal-form .btn-primary:disabled {
    background: #95a5a6;
    cursor: not-allowed;
}

.modal-form .btn-secondary {
    background: #95a5a6;
    color: white;
}

.modal-form .btn-secondary:hover {
    background: #7f8c8d;
}
</style>

<script>
$(document).ready(function() {
    $('#private-message-form').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $('#submit-message').prop('disabled', true).text('<?php echo t('sending'); ?>...');
        
        $.ajax({
            url: '../ajax/send_private_message.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    alert('<?php echo t('message_sent_successfully'); ?>');
                    closeModal();
                } else {
                    alert(response.error || '<?php echo t('error_occurred'); ?>');
                    $('#submit-message').prop('disabled', false).text('<?php echo t('send_message'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('=== PRIVATE MESSAGE ERROR ===');
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                let errorMsg = '<?php echo t('error_occurred'); ?>';
                if (status === 'timeout') {
                    errorMsg = 'Request timed out. Please try again.';
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.error || errorMsg;
                    } catch (e) {
                        errorMsg = 'Server error. Check console (F12) for details.';
                    }
                }
                
                alert(errorMsg);
                $('#submit-message').prop('disabled', false).text('<?php echo t('send_message'); ?>');
            }
        });
    });
});
</script>
