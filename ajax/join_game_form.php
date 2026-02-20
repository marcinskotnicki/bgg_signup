<?php
/**
 * AJAX Handler: Join Game Form
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

// Get game ID and reserve flag
$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;
$is_reserve = isset($_GET['is_reserve']) && $_GET['is_reserve'] == '1';

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

// Pre-fill user data if logged in
$default_name = $current_user ? $current_user['name'] : '';
$default_email = $current_user ? $current_user['email'] : '';
?>

<div class="join-game-form">
    <h2><?php echo $is_reserve ? t('join_reserve') : t('join_game'); ?></h2>
    
    <div class="game-info">
        <h3><?php echo htmlspecialchars($game['name']); ?></h3>
        <p><?php echo htmlspecialchars($game['event_name']); ?> - <?php echo $game['start_time']; ?></p>
    </div>
    
    <?php if ($config['add_player_message']): ?>
        <div class="form-message">
            <?php echo nl2br(htmlspecialchars($config['add_player_message'])); ?>
        </div>
    <?php endif; ?>
    
    <form id="join-game-form">
        <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
        <input type="hidden" name="is_reserve" value="<?php echo $is_reserve ? '1' : '0'; ?>">
        
        <!-- Player Name -->
        <div class="form-group">
            <label><?php echo t('player_name'); ?>: <span class="required">*</span></label>
            <input type="text" name="player_name" class="form-control" value="<?php echo htmlspecialchars($default_name); ?>" required>
        </div>
        
        <!-- Player Email -->
        <div class="form-group">
            <label><?php echo t('player_email'); ?>:<?php if ($config['require_emails']): ?> <span class="required">*</span><?php endif; ?></label>
            <input type="email" name="player_email" class="form-control" value="<?php echo htmlspecialchars($default_email); ?>" <?php echo $config['require_emails'] ? 'required' : ''; ?>>
        </div>
        
        <!-- Knows Rules -->
        <div class="form-group">
            <label><?php echo t('knows_rules'); ?>: <span class="required">*</span></label>
            <select name="knows_rules" class="form-control" required>
                <option value=""><?php echo t('select_option'); ?></option>
                <option value="yes"><?php echo t('knows_rules_yes'); ?></option>
                <option value="somewhat"><?php echo t('knows_rules_somewhat'); ?></option>
                <option value="no"><?php echo t('knows_rules_no'); ?></option>
            </select>
        </div>
        
        <!-- Comment -->
        <div class="form-group">
            <label><?php echo t('additional_comment'); ?>:</label>
            <textarea name="comment" class="form-control" rows="3"></textarea>
        </div>
        
        <div class="form-actions">
            <button type="button" onclick="closeModal()" class="btn btn-secondary"><?php echo t('cancel'); ?></button>
            <div id="submit-wrapper" style="display: inline-block; cursor: pointer;">
                <button type="submit" id="submit-join" class="btn btn-primary" disabled><?php echo t('sign_up'); ?></button>
            </div>
        </div>
    </form>
</div>


<script>
$(document).ready(function() {
    // Validate form and enable/disable submit button
    function validateForm() {
        const requiredFields = $('#join-game-form').find('[required]');
        let allFilled = true;
        
        requiredFields.each(function() {
            if (!$(this).val()) {
                allFilled = false;
                return false;
            }
        });
        
        $('#submit-join').prop('disabled', !allFilled);
    }
    
    // Highlight missing required fields
    function highlightMissingFields() {
        const requiredFields = $('#join-game-form').find('[required]');
        let firstEmpty = null;
        
        console.log('Found', requiredFields.length, 'required fields');
        
        requiredFields.each(function() {
            const $field = $(this);
            const $formGroup = $field.closest('.form-group');
            const fieldValue = $field.val();
            
            console.log('Field:', $field.attr('name'), 'Value:', fieldValue, 'Empty:', !fieldValue);
            
            if (!$field.val()) {
                // Add error class
                $formGroup.addClass('has-error');
                $field.addClass('error-field');
                console.log('Added error classes to:', $field.attr('name'));
                
                // Remember first empty field to focus
                if (!firstEmpty) {
                    firstEmpty = $field;
                }
            } else {
                // Remove error class
                $formGroup.removeClass('has-error');
                $field.removeClass('error-field');
            }
        });
        
        // Focus first empty field
        if (firstEmpty) {
            console.log('Focusing field:', firstEmpty.attr('name'));
            firstEmpty.focus();
        }
        
        return firstEmpty === null;
    }
    
    // Remove error styling when user starts filling field
    $('#join-game-form').on('input change', 'input, select, textarea', function() {
        const $field = $(this);
        const $formGroup = $field.closest('.form-group');
        
        if ($field.val()) {
            $formGroup.removeClass('has-error');
            $field.removeClass('error-field');
        }
        
        validateForm();
    });
    
    // Monitor all form inputs
    $('#join-game-form').on('input change', 'input, select, textarea', validateForm);
    
    // Initial validation
    validateForm();
    
    // Hover on submit button when disabled - highlight missing fields
    $(document).on('mouseenter', '#submit-wrapper', function(e) {
        if ($('#submit-join').is(':disabled')) {
            highlightMissingFields();
        }
    });
    
    // Clear highlights when mouse leaves (optional - you can remove this if you want them to stay)
    $(document).on('mouseleave', '#submit-wrapper', function(e) {
        if ($('#submit-join').is(':disabled')) {
            // Optional: clear highlights
            // $('.form-group').removeClass('has-error');
            // $('.form-control').removeClass('error-field');
        }
    });
    
    // Form submission
    $('#join-game-form').submit(function(e) {
        e.preventDefault();
        
        // Final validation with highlighting
        if (!highlightMissingFields()) {
            return false;
        }
        
        const formData = $(this).serialize();
        
        $('#submit-join').prop('disabled', true).text('<?php echo t('saving'); ?>...');
        
        $.ajax({
            url: '../ajax/join_game_submit.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (response.success) {
                    closeModal();
                    // Force page reload with cache busting
                    window.location.href = window.location.href.split('?')[0] + '?' + new Date().getTime();
                } else {
                    alert(response.error || '<?php echo t('error_occurred'); ?>');
                    $('#submit-join').prop('disabled', false).text('<?php echo t('sign_up'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('=== JOIN GAME ERROR DEBUG ===');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('HTTP Status Code:', xhr.status);
                console.error('Response Text Length:', xhr.responseText.length);
                console.error('Response Text:', xhr.responseText);
                console.error('First 200 chars:', xhr.responseText.substring(0, 200));
                console.error('Last 200 chars:', xhr.responseText.substring(xhr.responseText.length - 200));
                
                let errorMsg = '<?php echo t('error_occurred'); ?>';
                
                if (status === 'timeout') {
                    errorMsg = 'Request timed out. Please try again.';
                } else if (xhr.responseText) {
                    // Try to parse as JSON
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('Parsed JSON successfully:', response);
                        errorMsg = response.error || errorMsg;
                    } catch (e) {
                        console.error('JSON parse failed:', e);
                        
                        // Try to extract JSON from response (in case there are PHP warnings before it)
                        const jsonMatch = xhr.responseText.match(/\{[\s\S]*\}/);
                        if (jsonMatch) {
                            console.log('Found JSON in response:', jsonMatch[0]);
                            try {
                                const response = JSON.parse(jsonMatch[0]);
                                console.log('Extracted JSON:', response);
                                
                                if (response.success) {
                                    // Actually succeeded! There were just warnings/notices before JSON
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
                $('#submit-join').prop('disabled', false).text('<?php echo t('sign_up'); ?>');
            }
        });
    });
});
</script>