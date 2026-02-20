<?php
/**
 * AJAX Handler: Add Comment Form
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

// Check if comments are restricted to logged-in users
if ($config['restrict_comments'] && !$current_user) {
    die(t('login_required_to_comment'));
}

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

if (!$game) {
    die('Game not found');
}

// Get existing comments
$stmt = $db->prepare("SELECT * FROM comments WHERE game_id = ? ORDER BY created_at DESC");
$stmt->execute([$game_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-fill user data if logged in
$default_name = $current_user ? $current_user['name'] : '';
$default_email = $current_user ? $current_user['email'] : '';
?>

<div class="add-comment-form">
    <h2><?php echo t('add_comment'); ?></h2>
    
    <div class="game-info">
        <h3><?php echo htmlspecialchars($game['name']); ?></h3>
        <p><?php echo htmlspecialchars($game['event_name']); ?> - <?php echo $game['start_time']; ?></p>
    </div>
    
    <?php if (!empty($comments)): ?>
    <div class="existing-comments">
        <h3><?php echo t('existing_comments'); ?>:</h3>
        <?php foreach ($comments as $comment): ?>
        <div class="comment-item">
            <div class="comment-author">
                <strong><?php echo htmlspecialchars($comment['author_name']); ?></strong>
                <span class="comment-date"><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></span>
            </div>
            <div class="comment-text">
                <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <form id="add-comment-form">
        <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
        
        <!-- Author Name -->
        <div class="form-group">
            <label><?php echo t('your_name'); ?>: <span class="required">*</span></label>
            <input type="text" name="author_name" class="form-control" value="<?php echo htmlspecialchars($default_name); ?>" required>
        </div>
        
        <!-- Author Email -->
        <div class="form-group">
            <label><?php echo t('your_email'); ?>:</label>
            <input type="email" name="author_email" class="form-control" value="<?php echo htmlspecialchars($default_email); ?>">
        </div>
        
        <!-- Comment -->
        <div class="form-group">
            <label><?php echo t('comment'); ?>: <span class="required">*</span></label>
            <textarea name="comment" class="form-control" rows="4" required></textarea>
        </div>
        
        <?php if ($config['use_captcha']): ?>
        <div class="form-group">
            <!-- Simple math CAPTCHA -->
            <?php
            $num1 = rand(1, 10);
            $num2 = rand(1, 10);
            $_SESSION['captcha_answer'] = $num1 + $num2;
            ?>
            <label><?php echo t('captcha_question', ['num1' => $num1, 'num2' => $num2]); ?>: <span class="required">*</span></label>
            <input type="number" name="captcha" class="form-control" required>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="button" onclick="closeModal()" class="btn btn-secondary"><?php echo t('cancel'); ?></button>
            <button type="submit" id="submit-comment" class="btn btn-primary" disabled><?php echo t('post_comment'); ?></button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Validate form and enable/disable submit button
    function validateForm() {
        const requiredFields = $('#add-comment-form').find('[required]');
        let allFilled = true;
        
        requiredFields.each(function() {
            if (!$(this).val()) {
                allFilled = false;
                return false;
            }
        });
        
        $('#submit-comment').prop('disabled', !allFilled);
    }
    
    // Monitor all form inputs
    $('#add-comment-form').on('input change', 'input, textarea', validateForm);
    
    // Initial validation
    validateForm();
    
    // Form submission
    $('#add-comment-form').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $('#submit-comment').prop('disabled', true).text('<?php echo t('saving'); ?>...');
        
        $.ajax({
            url: '../ajax/add_comment_submit.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    closeModal();
                    // Force page reload with cache busting
                    window.location.href = window.location.href.split('?')[0] + '?' + new Date().getTime();
                } else {
                    alert(response.error || '<?php echo t('error_occurred'); ?>');
                    $('#submit-comment').prop('disabled', false).text('<?php echo t('post_comment'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('=== ADD COMMENT ERROR DEBUG ===');
                console.error('Status:', status);
                console.error('HTTP Status Code:', xhr.status);
                console.error('Response Text:', xhr.responseText);
                
                let errorMsg = '<?php echo t('error_occurred'); ?>';
                
                if (status === 'timeout') {
                    errorMsg = 'Request timed out. Please try again.';
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.error || errorMsg;
                    } catch (e) {
                        // Try to extract JSON from response
                        const jsonMatch = xhr.responseText.match(/\{[\s\S]*\}/);
                        if (jsonMatch) {
                            try {
                                const response = JSON.parse(jsonMatch[0]);
                                if (response.success) {
                                    console.warn('SUCCESS despite PHP warnings!');
                                    closeModal();
                                    window.location.href = window.location.href.split('?')[0] + '?' + new Date().getTime();
                                    return;
                                }
                                errorMsg = response.error || errorMsg;
                            } catch (e2) {
                                console.error('Could not parse extracted JSON:', e2);
                            }
                        }
                        errorMsg = 'Server error. Check browser console (F12) for details.';
                    }
                }
                
                alert(errorMsg);
                $('#submit-comment').prop('disabled', false).text('<?php echo t('post_comment'); ?>');
            }
        });
    });
});
</script>