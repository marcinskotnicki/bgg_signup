<?php
/**
 * AJAX Handler: Vote Form
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

// Get poll option ID
$option_id = isset($_GET['option_id']) ? intval($_GET['option_id']) : 0;
$poll_id = isset($_GET['poll_id']) ? intval($_GET['poll_id']) : 0;

if (!$option_id || !$poll_id) {
    die('Invalid parameters');
}

// Get poll option details
$stmt = $db->prepare("SELECT po.*, p.creator_name 
                      FROM poll_options po 
                      JOIN polls p ON po.poll_id = p.id 
                      WHERE po.id = ?");
$stmt->execute([$option_id]);
$option = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$option) {
    die('Poll option not found');
}

// Get poll details
$stmt = $db->prepare("SELECT * FROM polls WHERE id = ?");
$stmt->execute([$poll_id]);
$poll = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$poll || $poll['is_active'] == 0) {
    die('Poll is not active');
}

// Pre-fill with user data if logged in
$voter_name = $current_user ? $current_user['name'] : '';
$voter_email = $current_user ? $current_user['email'] : '';
?>

<div class="vote-form-container">
    <h3><?php echo t('vote_for_game'); ?>: <?php echo htmlspecialchars($option['game_name']); ?></h3>
    
    <p class="vote-info">
        <?php echo t('vote_threshold'); ?>: <strong><?php echo $option['vote_threshold']; ?> <?php echo t('votes'); ?></strong>
    </p>
    
    <form id="vote-form" class="modal-form">
        <input type="hidden" name="option_id" value="<?php echo $option_id; ?>">
        <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">
        
        <div class="form-group">
            <label><?php echo t('your_name'); ?>: <span class="required">*</span></label>
            <input type="text" name="voter_name" value="<?php echo htmlspecialchars($voter_name); ?>" required <?php echo $current_user ? 'readonly' : ''; ?>>
        </div>
        
        <div class="form-group">
            <label><?php echo t('your_email'); ?>: <span class="required">*</span></label>
            <input type="email" name="voter_email" value="<?php echo htmlspecialchars($voter_email); ?>" required <?php echo $current_user ? 'readonly' : ''; ?>>
            <small><?php echo t('email_notification_on_poll_close'); ?></small>
        </div>
        
        <div class="form-actions">
            <button type="submit" id="submit-vote" class="btn-primary">
                <?php echo t('submit_vote'); ?>
            </button>
            <button type="button" class="btn-secondary" onclick="closeModal()">
                <?php echo t('cancel'); ?>
            </button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    $('#vote-form').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $('#submit-vote').prop('disabled', true).text('<?php echo t('saving'); ?>...');
        
        $.ajax({
            url: '../ajax/vote_submit.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    closeModal();
                    window.location.href = window.location.href.split('?')[0] + '?' + new Date().getTime();
                } else {
                    alert(response.error || '<?php echo t('error_occurred'); ?>');
                    $('#submit-vote').prop('disabled', false).text('<?php echo t('submit_vote'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('=== VOTE SUBMIT ERROR ===');
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
                        const jsonMatch = xhr.responseText.match(/\{[\s\S]*\}/);
                        if (jsonMatch) {
                            try {
                                const response = JSON.parse(jsonMatch[0]);
                                if (response.success) {
                                    closeModal();
                                    window.location.href = window.location.href.split('?')[0] + '?' + new Date().getTime();
                                    return;
                                }
                                errorMsg = response.error || errorMsg;
                            } catch (e2) {
                                console.error('Parse error:', e2);
                            }
                        }
                        errorMsg = 'Server error. Check console (F12) for details.';
                    }
                }
                
                alert(errorMsg);
                $('#submit-vote').prop('disabled', false).text('<?php echo t('submit_vote'); ?>');
            }
        });
    });
});
</script>
