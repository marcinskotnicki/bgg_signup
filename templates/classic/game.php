<?php
/**
 * Classic Template - Single Game Display
 * 
 * Variables expected:
 * - $game: Game data array
 * - $players: Array of players signed up for this game
 * - $current_user: Current logged in user (or null)
 * - $config: Configuration array
 * - $game_number: Game number on table
 */

// Calculate if game is full
$is_full = count($players) >= $game['max_players'];

// Separate active players from reserve
$active_players = array_filter($players, function($p) { return $p['is_reserve'] == 0; });
$reserve_players = array_filter($players, function($p) { return $p['is_reserve'] == 1; });

// Check if current user can edit/delete this game
$can_modify = false;
if ($current_user) {
    if ($current_user['is_admin'] == 1) {
        $can_modify = true;
    } elseif ($game['created_by_user_id'] && $game['created_by_user_id'] == $current_user['id']) {
        $can_modify = true;
    }
}

if (!$game['created_by_user_id']) {
    $can_modify = true;
}

// Format difficulty
$difficulty_info = format_difficulty($game['difficulty']);

// Determine if game is inactive
$is_inactive = $game['is_active'] == 0;

// Get comments
$stmt = $db->prepare("SELECT * FROM comments WHERE game_id = ? ORDER BY created_at ASC");
$stmt->execute([$game['id']]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="game-card <?php echo $is_inactive ? 'game-inactive' : ''; ?>" data-game-id="<?php echo $game['id']; ?>">
    <a name="game_<?php echo $game['id']; ?>" id="game_<?php echo $game['id']; ?>"></a>
    
    <!-- Game Number Badge -->
    <div class="game-number"><?php echo $game_number; ?></div>
    
    <div class="game-layout">
        <!-- Left: Game Info -->
        <div class="game-info">
            <div class="game-header">
                <?php if ($game['thumbnail']): ?>
                    <img src="<?php echo htmlspecialchars($game['thumbnail']); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>" class="game-thumb">
                <?php endif; ?>
                
                <div class="game-title-section">
                    <h3 class="game-title">
                        <?php if ($game['bgg_url']): ?>
                            <a href="<?php echo htmlspecialchars($game['bgg_url']); ?>" target="_blank">
                                <?php echo htmlspecialchars($game['name']); ?>
                            </a>
                        <?php else: ?>
                            <?php echo htmlspecialchars($game['name']); ?>
                        <?php endif; ?>
                    </h3>
                    <div class="game-time">
                        ⏱ <?php echo $game['start_time']; ?> • <?php echo $game['play_time']; ?> <?php echo t('minutes'); ?>
                    </div>
                </div>
            </div>
            
            <div class="game-meta">
                <span class="meta-item">
                    👥 <?php echo $game['min_players']; ?>-<?php echo $game['max_players']; ?>
                </span>
                <span class="meta-item">
                    🗣 <?php 
                    // Handle both old and new database values
                    $lang = $game['language'];
                    if ($lang === 'independent' || $lang === 'language_independent') {
                        echo t('language_independent');
                    } elseif ($lang === 'en') {
                        echo 'English';
                    } elseif ($lang === 'pl') {
                        echo 'Polski';
                    } else {
                        echo htmlspecialchars($lang);
                    }
                    ?>
                </span>
                <span class="meta-item" title="<?php echo t('difficulty'); ?>: <?php echo number_format($game['difficulty'], 1); ?>">
                    ⚙️ <?php echo number_format($game['difficulty'], 1); ?>/5
                </span>
                <span class="meta-item">
                    📋 <?php 
                    // Handle both old and new database values
                    $rules = $game['rules_explanation'];
                    if ($rules === 'explained' || $rules === 'will_explain') {
                        echo t('rules_will_be_explained');
                    } elseif ($rules === 'required' || $rules === 'knowledge_required') {
                        echo t('rules_knowledge_required');
                    } else {
                        echo htmlspecialchars($rules);
                    }
                    ?>
                </span>
            </div>
            
            <div class="game-host">
                <?php echo t('host'); ?>: <strong><?php echo htmlspecialchars($game['host_name']); ?></strong>
            </div>
            
            <?php if ($game['initial_comment']): ?>
                <div class="game-description">
                    <?php echo nl2br(htmlspecialchars($game['initial_comment'])); ?>
                </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <?php if (!$is_inactive): ?>
                <div class="game-actions">
                    <?php if ($can_modify): ?>
                        <button class="btn-sm btn-edit" onclick="editGame(<?php echo $game['id']; ?>)">✏️ <?php echo t('edit'); ?></button>
                        <button class="btn-sm btn-delete" onclick="deleteGame(<?php echo $game['id']; ?>)">🗑️ <?php echo t('delete'); ?></button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Inactive game actions -->
                <div class="game-actions">
                    <button class="btn-sm btn-restore" onclick="restoreGame(<?php echo $game['id']; ?>)">
                        ↩️ <?php echo t('restore'); ?>
                    </button>
                    <?php if (isset($config['allow_full_deletion']) && ($config['allow_full_deletion'] === 'yes' || $config['allow_full_deletion'] === true)): ?>
                        <button class="btn-sm btn-delete-permanent" onclick="fullyDeleteGame(<?php echo $game['id']; ?>)">
                            🗑️ <?php echo t('fully_delete'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Right: Players -->
        <div class="game-players">
            <div class="players-header">
                <?php echo t('players'); ?> (<?php echo count($active_players); ?>/<?php echo $game['max_players']; ?>)
            </div>
            
            <div class="players-list">
                <?php foreach ($active_players as $index => $player): ?>
                    <div class="player-item">
                        <span class="player-number"><?php echo $index + 1; ?>.</span>
                        <span class="player-name"><?php echo htmlspecialchars($player['player_name']); ?></span>
                        <?php if ($player['knows_rules'] === 'yes'): ?>
                            <span class="player-badge">✓</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$is_full && !$is_inactive): ?>
                    <button class="btn-sm btn-join" onclick="joinGame(<?php echo $game['id']; ?>, 0)">
                        + <?php echo t('join_game'); ?>
                    </button>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($reserve_players) && $config['allow_reserve_list']): ?>
                <div class="reserve-header"><?php echo t('reserve_list'); ?></div>
                <div class="reserve-list">
                    <?php foreach ($reserve_players as $player): ?>
                        <div class="player-item reserve">
                            <span class="player-name"><?php echo htmlspecialchars($player['player_name']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($is_full && !$is_inactive && $config['allow_reserve_list']): ?>
                <button class="btn-sm btn-reserve" onclick="joinGame(<?php echo $game['id']; ?>, 1)">
                    + <?php echo t('join_reserve'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Comments Section -->
    <?php if (!empty($comments) || !$is_inactive): ?>
        <div class="game-comments">
            <?php if (!empty($comments)): ?>
                <div class="comments-list">
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment-item">
                            <strong><?php echo htmlspecialchars($comment['author_name']); ?>:</strong>
                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$is_inactive): ?>
                <button class="btn-sm btn-comment" onclick="addComment(<?php echo $game['id']; ?>)">
                    💬 <?php echo t('add_comment'); ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
