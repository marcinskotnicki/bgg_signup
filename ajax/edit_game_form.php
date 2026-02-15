<?php
/**
 * AJAX Handler: Edit Game Form
 */

// Load configuration
$config = require_once '../config.php';

// Load translation system
require_once '../includes/translations.php';

// Load auth helper
require_once '../includes/auth.php';

// Load BGG API helper
require_once '../includes/bgg_api.php';

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
$stmt = $db->prepare("SELECT g.*, t.id as table_id, t.table_number, ed.start_time as day_start_time, ed.end_time as day_end_time
                      FROM games g
                      JOIN tables t ON g.table_id = t.id
                      JOIN event_days ed ON t.event_day_id = ed.id
                      WHERE g.id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    die('Game not found');
}

// Check permissions
$can_edit = false;

if ($current_user) {
    // Admin or game creator
    if ($current_user['is_admin'] == 1 || ($game['created_by_user_id'] && $game['created_by_user_id'] == $current_user['id'])) {
        $can_edit = true;
    }
}

// If no created_by_user_id, anyone can edit (but may require verification)
if (!$game['created_by_user_id']) {
    $can_edit = true;
}

if (!$can_edit) {
    die('Permission denied');
}

// Get custom thumbnails (function is in includes/bgg_api.php)
$custom_thumbnails = get_custom_thumbnails();

// Get available languages
$available_languages = [
    'en' => 'English',
    'pl' => 'Polski',
    'language_independent' => t('language_independent')
];
?>

<div class="edit-game-form">
    <h2><?php echo t('edit'); ?> - <?php echo htmlspecialchars($game['name']); ?></h2>
    
    <form id="edit-game-submit-form" method="POST">
        <input type="hidden" name="game_id" value="<?php echo $game_id; ?>">
        
        <!-- Game Name -->
        <div class="form-group">
            <label><?php echo t('game_name'); ?>:</label>
            <input type="text" name="game_name" value="<?php echo htmlspecialchars($game['name']); ?>" required>
        </div>
        
        <!-- Play Time -->
        <div class="form-group">
            <label><?php echo t('play_time'); ?> (<?php echo t('minutes'); ?>):</label>
            <input type="number" name="play_time" value="<?php echo $game['play_time']; ?>" min="1" max="600" required>
        </div>
        
        <!-- Players -->
        <div class="form-row">
            <div class="form-group">
                <label><?php echo t('min_players'); ?>:</label>
                <input type="number" name="min_players" value="<?php echo $game['min_players']; ?>" min="1" max="20" required>
            </div>
            <div class="form-group">
                <label><?php echo t('max_players'); ?>:</label>
                <input type="number" name="max_players" value="<?php echo $game['max_players']; ?>" min="1" max="20" required>
            </div>
        </div>
        
        <!-- Difficulty -->
        <div class="form-group">
            <label><?php echo t('difficulty'); ?> (1-5):</label>
            <input type="number" name="difficulty" value="<?php echo $game['difficulty']; ?>" min="0" max="5" step="0.1">
        </div>
        
        <!-- Start Time -->
        <div class="form-group">
            <label><?php echo t('start_time'); ?>:</label>
            <input type="time" name="start_time" value="<?php echo $game['start_time']; ?>" required>
        </div>
        
        <!-- Language -->
        <div class="form-group">
            <label><?php echo t('language'); ?>:</label>
            <select name="language" required>
                <?php foreach ($available_languages as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo $game['language'] === $code ? 'selected' : ''; ?>>
                        <?php echo $name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Rules Explanation -->
        <div class="form-group">
            <label><?php echo t('rules_explanation'); ?>:</label>
            <select name="rules_explanation" required>
                <option value="will_explain" <?php echo $game['rules_explanation'] === 'will_explain' ? 'selected' : ''; ?>>
                    <?php echo t('rules_will_be_explained'); ?>
                </option>
                <option value="knowledge_required" <?php echo $game['rules_explanation'] === 'knowledge_required' ? 'selected' : ''; ?>>
                    <?php echo t('rules_knowledge_required'); ?>
                </option>
            </select>
        </div>
        
        <!-- Thumbnail -->
        <div class="form-group">
            <label><?php echo t('select_thumbnail'); ?>:</label>
            <div class="thumbnail-selector">
                <?php if ($game['thumbnail']): ?>
                    <div class="thumbnail-option selected" data-thumbnail="<?php echo htmlspecialchars($game['thumbnail']); ?>">
                        <img src="<?php echo htmlspecialchars($game['thumbnail']); ?>" alt="Current thumbnail">
                        <input type="radio" name="thumbnail" value="<?php echo htmlspecialchars($game['thumbnail']); ?>" checked>
                    </div>
                <?php endif; ?>
                
                <?php foreach ($custom_thumbnails as $thumb): ?>
                    <?php 
                    $thumb_url = 'thumbnails/' . $thumb;
                    $is_selected = ($game['thumbnail'] === $thumb_url);
                    ?>
                    <div class="thumbnail-option <?php echo $is_selected ? 'selected' : ''; ?>" data-thumbnail="<?php echo $thumb_url; ?>">
                        <img src="../<?php echo $thumb_url; ?>" alt="<?php echo $thumb; ?>">
                        <input type="radio" name="thumbnail" value="<?php echo $thumb_url; ?>" <?php echo $is_selected ? 'checked' : ''; ?>>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="selected_thumbnail" id="selected_thumbnail" value="<?php echo htmlspecialchars($game['thumbnail']); ?>">
        </div>
        
        <!-- Host Information -->
        <div class="form-group">
            <label><?php echo t('host_name'); ?>:</label>
            <input type="text" name="host_name" value="<?php echo htmlspecialchars($game['host_name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label><?php echo t('host_email'); ?>:</label>
            <input type="email" name="host_email" value="<?php echo htmlspecialchars($game['host_email']); ?>">
        </div>
        
        <!-- Comment -->
        <div class="form-group">
            <label><?php echo t('comment'); ?>:</label>
            <textarea name="comment" rows="3"><?php echo htmlspecialchars($game['initial_comment']); ?></textarea>
        </div>
        
        <!-- Buttons -->
        <div class="form-actions">
            <button type="submit" class="btn-primary"><?php echo t('save_changes'); ?></button>
            <button type="button" class="btn-secondary" onclick="closeModal()"><?php echo t('cancel'); ?></button>
        </div>
    </form>
</div>

<style>
.edit-game-form {
    max-width: 600px;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
}

.thumbnail-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.thumbnail-option {
    position: relative;
    width: 80px;
    height: 80px;
    border: 2px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    overflow: hidden;
}

.thumbnail-option.selected {
    border-color: #4CAF50;
}

.thumbnail-option img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.thumbnail-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.btn-primary {
    background: #4CAF50;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-primary:hover {
    background: #45a049;
}

.btn-secondary {
    background: #757575;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-secondary:hover {
    background: #616161;
}
</style>

<script>
// Thumbnail selection
$('.thumbnail-option').click(function() {
    $('.thumbnail-option').removeClass('selected');
    $(this).addClass('selected');
    $(this).find('input[type="radio"]').prop('checked', true);
    $('#selected_thumbnail').val($(this).data('thumbnail'));
});

// Form submission
$('#edit-game-submit-form').submit(function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'ajax/edit_game_submit.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                closeModal();
                // Force page reload with cache busting
                window.location.href = window.location.href.split('?')[0] + '?' + new Date().getTime();
            } else {
                alert(response.error || '<?php echo t('error_occurred'); ?>');
            }
        },
        error: function() {
            alert('<?php echo t('error_occurred'); ?>');
        }
    });
});
</script>
