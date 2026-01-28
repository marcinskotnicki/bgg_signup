<?php
/**
 * AJAX Handler: Add Game Form
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

// Get table ID
$table_id = isset($_GET['table_id']) ? intval($_GET['table_id']) : 0;

if (!$table_id) {
    die('Invalid table ID');
}

// Get table and day info
$stmt = $db->prepare("SELECT t.*, ed.start_time, ed.end_time 
                      FROM tables t 
                      JOIN event_days ed ON t.event_day_id = ed.id 
                      WHERE t.id = ?");
$stmt->execute([$table_id]);
$table = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$table) {
    die('Table not found');
}

// Get last game on this table to calculate default start time
$stmt = $db->prepare("SELECT start_time, play_time FROM games WHERE table_id = ? ORDER BY start_time DESC LIMIT 1");
$stmt->execute([$table_id]);
$last_game = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate default start time
if ($last_game) {
    $last_start = strtotime($last_game['start_time']);
    $default_start_time = date('H:i', $last_start + ($last_game['play_time'] * 60));
} else {
    $default_start_time = $table['start_time'];
}

// Get custom thumbnails
$custom_thumbnails = get_custom_thumbnails();

// Pre-fill user data if logged in
$default_name = $current_user ? $current_user['name'] : '';
$default_email = $current_user ? $current_user['email'] : '';
?>

<div class="add-game-form">
    <h2><?php echo t('add_game'); ?></h2>
    
    <?php if ($config['add_game_message']): ?>
        <div class="form-message">
            <?php echo nl2br(htmlspecialchars($config['add_game_message'])); ?>
        </div>
    <?php endif; ?>
    
    <!-- Step 1: Search or Manual -->
    <div id="game-search-step" class="form-step">
        <h3><?php echo t('search_or_add_manually'); ?></h3>
        
        <div class="search-section">
            <input type="text" id="bgg-search-input" placeholder="<?php echo t('search_game'); ?>" class="form-control">
            <button type="button" id="bgg-search-btn" class="btn btn-primary"><?php echo t('search_bgg'); ?></button>
            <button type="button" id="add-manual-btn" class="btn btn-secondary"><?php echo t('add_game_manual'); ?></button>
        </div>
        
        <div id="search-results" class="search-results" style="display: none;">
            <!-- Results will be loaded here -->
        </div>
    </div>
    
    <!-- Step 2: Game Details Form -->
    <div id="game-details-step" class="form-step" style="display: none;">
        <h3><?php echo t('game_details'); ?></h3>
        
        <form id="add-game-form">
            <input type="hidden" name="table_id" value="<?php echo $table_id; ?>">
            <input type="hidden" id="bgg_id" name="bgg_id" value="">
            <input type="hidden" id="bgg_url" name="bgg_url" value="">
            <input type="hidden" id="thumbnail" name="thumbnail" value="">
            
            <!-- Game Name -->
            <div class="form-group">
                <label><?php echo t('game_name'); ?>: <span class="required">*</span></label>
                <input type="text" id="game_name" name="name" class="form-control" required>
            </div>
            
            <!-- Thumbnail (for manual games) -->
            <div id="thumbnail-selector" class="form-group" style="display: none;">
                <label><?php echo t('select_thumbnail'); ?>:</label>
                <div class="thumbnail-grid">
                    <?php foreach ($custom_thumbnails as $thumb): ?>
                        <div class="thumbnail-option" data-thumbnail="thumbnails/<?php echo $thumb; ?>">
                            <img src="thumbnails/<?php echo $thumb; ?>" alt="Thumbnail">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Play Time -->
            <div class="form-group">
                <label><?php echo t('play_time'); ?> (<?php echo t('minutes'); ?>): <span class="required">*</span></label>
                <input type="number" id="play_time" name="play_time" class="form-control" min="1" required>
            </div>
            
            <!-- Min Players -->
            <div class="form-group">
                <label><?php echo t('min_players'); ?>: <span class="required">*</span></label>
                <input type="number" id="min_players" name="min_players" class="form-control" min="1" required>
            </div>
            
            <!-- Max Players -->
            <div class="form-group">
                <label><?php echo t('max_players'); ?>: <span class="required">*</span></label>
                <input type="number" id="max_players" name="max_players" class="form-control" min="1" required>
            </div>
            
            <!-- Difficulty -->
            <div class="form-group">
                <label><?php echo t('difficulty'); ?> (1-5): <span class="required">*</span></label>
                <input type="number" id="difficulty" name="difficulty" class="form-control" min="0" max="5" step="0.01" value="2.5" required>
            </div>
            
            <!-- Start Time -->
            <div class="form-group">
                <label><?php echo t('start_time'); ?>: <span class="required">*</span></label>
                <input type="time" id="start_time" name="start_time" class="form-control" value="<?php echo $default_start_time; ?>" required>
            </div>
            
            <!-- Language -->
            <div class="form-group">
                <label><?php echo t('language'); ?>: <span class="required">*</span></label>
                <select id="language" name="language" class="form-control" required>
                    <option value="independent"><?php echo t('language_independent'); ?></option>
                    <option value="English">English</option>
                    <option value="Polish">Polski</option>
                    <option value="German">Deutsch</option>
                    <option value="French">Français</option>
                </select>
            </div>
            
            <!-- Rules Explanation -->
            <div class="form-group">
                <label><?php echo t('rules_explanation'); ?>: <span class="required">*</span></label>
                <select id="rules_explanation" name="rules_explanation" class="form-control" required>
                    <option value="explained"><?php echo t('rules_will_be_explained'); ?></option>
                    <option value="required"><?php echo t('rules_knowledge_required'); ?></option>
                </select>
            </div>
            
            <!-- Host Name -->
            <div class="form-group">
                <label><?php echo t('host_name'); ?>: <span class="required">*</span></label>
                <input type="text" id="host_name" name="host_name" class="form-control" value="<?php echo htmlspecialchars($default_name); ?>" required>
            </div>
            
            <!-- Host Email -->
            <div class="form-group">
                <label><?php echo t('host_email'); ?>:<?php if ($config['require_emails']): ?> <span class="required">*</span><?php endif; ?></label>
                <input type="email" id="host_email" name="host_email" class="form-control" value="<?php echo htmlspecialchars($default_email); ?>" <?php echo $config['require_emails'] ? 'required' : ''; ?>>
            </div>
            
            <!-- Initial Comment -->
            <div class="form-group">
                <label><?php echo t('additional_comment'); ?>:</label>
                <textarea id="initial_comment" name="initial_comment" class="form-control" rows="3"></textarea>
            </div>
            
            <!-- Join as First Player -->
            <div class="form-group">
                <label>
                    <input type="checkbox" id="join_as_player" name="join_as_player" value="1" checked>
                    <?php echo t('join_as_first_player'); ?>
                </label>
            </div>
            
            <div class="form-actions">
                <button type="button" id="back-to-search" class="btn btn-secondary"><?php echo t('back'); ?></button>
                <button type="submit" id="submit-game" class="btn btn-primary" disabled><?php echo t('add_game'); ?></button>
            </div>
        </form>
    </div>
</div>

<style>
.add-game-form {
    max-width: 600px;
}

.form-message {
    background: #e3f2fd;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    border-left: 4px solid #2196f3;
}

.search-section {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

#bgg-search-input {
    flex: 1;
}

.search-results {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f8f9fa;
}

.search-result-item {
    padding: 15px;
    border-bottom: 1px solid #ddd;
    cursor: pointer;
    transition: background 0.2s;
}

.search-result-item:hover {
    background: #e9ecef;
}

.search-result-item:last-child {
    border-bottom: none;
}

.result-name {
    font-weight: bold;
    font-size: 16px;
    color: #2c3e50;
}

.result-year {
    color: #7f8c8d;
    font-size: 14px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
}

.required {
    color: #e74c3c;
}

.thumbnail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 10px;
}

.thumbnail-option {
    cursor: pointer;
    border: 3px solid transparent;
    border-radius: 4px;
    overflow: hidden;
    transition: border-color 0.2s;
}

.thumbnail-option:hover {
    border-color: #3498db;
}

.thumbnail-option.selected {
    border-color: #27ae60;
}

.thumbnail-option img {
    width: 100%;
    height: 80px;
    object-fit: cover;
    display: block;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    transition: background 0.3s;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #2980b9;
}

.btn-primary:disabled {
    background: #bdc3c7;
    cursor: not-allowed;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
}

.loading {
    text-align: center;
    padding: 20px;
    color: #7f8c8d;
}
</style>

<script>
$(document).ready(function() {
    let selectedBggId = null;
    let isManualAdd = false;
    
    // Validate form and enable/disable submit button
    function validateForm() {
        const requiredFields = $('#add-game-form').find('[required]');
        let allFilled = true;
        
        requiredFields.each(function() {
            if (!$(this).val()) {
                allFilled = false;
                return false;
            }
        });
        
        $('#submit-game').prop('disabled', !allFilled);
    }
    
    // Monitor all form inputs
    $('#add-game-form').on('input change', 'input, select, textarea', validateForm);
    
    // BGG Search
    $('#bgg-search-btn').click(function() {
        const query = $('#bgg-search-input').val().trim();
        
        if (!query) {
            alert('<?php echo t('enter_game_name'); ?>');
            return;
        }
        
        $('#search-results').html('<div class="loading"><?php echo t('loading'); ?>...</div>').show();
        
        $.get('../ajax/search_bgg.php', { query: query }, function(response) {
            if (response.success) {
                displaySearchResults(response.results);
            } else {
                $('#search-results').html('<div class="loading">' + response.error + '</div>');
            }
        });
    });
    
    // Enter key in search
    $('#bgg-search-input').keypress(function(e) {
        if (e.which === 13) {
            $('#bgg-search-btn').click();
        }
    });
    
    // Display search results
    function displaySearchResults(results) {
        if (results.length === 0) {
            $('#search-results').html('<div class="loading"><?php echo t('no_results_found'); ?></div>');
            return;
        }
        
        let html = '';
        results.forEach(function(game) {
            html += '<div class="search-result-item" data-game-id="' + game.id + '">';
            html += '<div class="result-name">' + game.name + '</div>';
            if (game.year) {
                html += '<div class="result-year">(' + game.year + ')</div>';
            }
            html += '</div>';
        });
        
        $('#search-results').html(html);
    }
    
    // Select game from search results
    $(document).on('click', '.search-result-item', function() {
        const gameId = $(this).data('game-id');
        loadGameDetails(gameId);
    });
    
    // Load game details from BGG
    function loadGameDetails(gameId) {
        $('#search-results').html('<div class="loading"><?php echo t('loading_game_details'); ?>...</div>');
        
        $.get('../ajax/get_bgg_game.php', { game_id: gameId }, function(response) {
            if (response.success) {
                fillGameForm(response.game, false);
                showDetailsStep();
            } else {
                alert(response.error);
            }
        });
    }
    
    // Manual add button
    $('#add-manual-btn').click(function() {
        isManualAdd = true;
        fillGameForm({}, true);
        showDetailsStep();
    });
    
    // Fill form with game data
    function fillGameForm(game, manual) {
        if (!manual) {
            // BGG game
            $('#bgg_id').val(game.id);
            $('#bgg_url').val(game.url);
            $('#thumbnail').val(game.thumbnail);
            $('#game_name').val(game.name).prop('readonly', true);
            $('#play_time').val(game.play_time);
            $('#min_players').val(game.min_players);
            $('#max_players').val(game.max_players);
            $('#difficulty').val(game.difficulty.toFixed(2));
            $('#thumbnail-selector').hide();
        } else {
            // Manual game
            $('#bgg_id').val('');
            $('#bgg_url').val('');
            $('#thumbnail').val('');
            $('#game_name').val('').prop('readonly', false);
            $('#thumbnail-selector').show();
        }
        
        validateForm();
    }
    
    // Show details step
    function showDetailsStep() {
        $('#game-search-step').hide();
        $('#game-details-step').show();
    }
    
    // Back to search
    $('#back-to-search').click(function() {
        $('#game-details-step').hide();
        $('#game-search-step').show();
        $('#search-results').empty().hide();
        $('#bgg-search-input').val('');
        isManualAdd = false;
    });
    
    // Thumbnail selection
    $(document).on('click', '.thumbnail-option', function() {
        $('.thumbnail-option').removeClass('selected');
        $(this).addClass('selected');
        $('#thumbnail').val($(this).data('thumbnail'));
    });
    
    // Form submission
    $('#add-game-form').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $('#submit-game').prop('disabled', true).text('<?php echo t('saving'); ?>...');
        
        $.post('../ajax/add_game_submit.php', formData, function(response) {
            if (response.success) {
                closeModal();
                location.reload();
            } else {
                alert(response.error || '<?php echo t('error_occurred'); ?>');
                $('#submit-game').prop('disabled', false).text('<?php echo t('add_game'); ?>');
            }
        });
    });
});
</script>