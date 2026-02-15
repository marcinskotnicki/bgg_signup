<?php
/**
 * Single Game Display Template
 * 
 * Variables expected:
 * - $game: Game data array
 * - $players: Array of players signed up for this game
 * - $current_user: Current logged in user (or null)
 * - $config: Configuration array
 * - $show_edit_delete: Whether to show edit/delete buttons
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

// If game was added without login, anyone can modify (but needs verification)
if (!$game['created_by_user_id']) {
    $can_modify = true;
}

// Format difficulty
$difficulty_info = format_difficulty($game['difficulty']);

// Determine if game is inactive (soft deleted)
$is_inactive = $game['is_active'] == 0;
?>

<li class="gameitem <?php echo $is_inactive ? 'inactive' : ''; ?>" 
    data-name="<?php echo htmlspecialchars($game['name']); ?>" 
    data-time="<?php echo htmlspecialchars($game['start_time']); ?>" 
    data-weight="<?php echo $game['difficulty']; ?>"
    data-game-id="<?php echo $game['id']; ?>">
    
    <div class="table">
        <a name="game_<?php echo $game['id']; ?>" id="game_<?php echo $game['id']; ?>"></a>
        
        <!-- Game Number -->
        <div class="game-number-badge"><?php echo $game_number; ?></div>
        
        <!-- Game Thumbnail -->
        <div class="game_thumbnail">
            <?php if ($game['thumbnail']): ?>
                <img src="<?php echo htmlspecialchars($game['thumbnail']); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>">
            <?php else: ?>
                <div class="no-thumbnail">?</div>
            <?php endif; ?>
        </div>
        
        <!-- Game Name -->
        <div class="game_name">
            <?php if ($game['bgg_url']): ?>
                <a href="<?php echo htmlspecialchars($game['bgg_url']); ?>" target="_blank">
                    <?php echo htmlspecialchars($game['name']); ?>
                </a>
            <?php else: ?>
                <?php echo htmlspecialchars($game['name']); ?>
            <?php endif; ?>
            
            <?php if ($config['allow_private_messages'] && !$is_inactive): ?>
                <span class="mail-icon" data-game-id="<?php echo $game['id']; ?>" title="<?php echo t('send_message_to_all_players'); ?>">
                    ✉️
                </span>
            <?php endif; ?>
        </div>
        
        <!-- Game Difficulty/Weight -->
        <div class="game_weight" title="<?php echo t('difficulty'); ?>: <?php echo number_format($game['difficulty'], 2); ?>">
            <div class="weight-bar">
                <div class="weight-fill" style="width: <?php echo $difficulty_info['percentage']; ?>%; background-color: <?php echo $difficulty_info['color']; ?>;"></div>
            </div>
            <span class="weight-value"><?php echo number_format($game['difficulty'], 2); ?></span>
        </div>
        
        <!-- Player Count -->
        <div class="game_players">
            <?php echo $game['min_players']; ?> - <?php echo $game['max_players']; ?>
        </div>
        
        <!-- Start Time -->
        <div class="game_start" title="<?php echo t('play_time'); ?>: <?php echo $game['play_time']; ?> <?php echo t('minutes'); ?>">
            <?php echo htmlspecialchars($game['start_time']); ?>
        </div>
        
        <!-- Host -->
        <div class="game_who">
            <?php echo htmlspecialchars($game['host_name']); ?>
        </div>
        
        <!-- Language -->
        <div class="game_language">
            <?php 
            if ($game['language'] === 'independent') {
                echo t('language_independent');
            } else {
                echo htmlspecialchars($game['language']);
            }
            ?>
        </div>
        
        <!-- Rules Explanation -->
        <div class="game_rules">
            <?php 
            if ($game['rules_explanation'] === 'explained') {
                echo t('rules_will_be_explained');
            } else {
                echo t('rules_knowledge_required');
            }
            ?>
        </div>
        
        <!-- Initial Comment -->
        <?php if ($game['initial_comment']): ?>
        <div class="game_initial_comment">
            <?php echo nl2br(htmlspecialchars($game['initial_comment'])); ?>
        </div>
        <?php endif; ?>
        
        <!-- Comments Section -->
        <div class="game_comment">
        </div>
    </div>
    
    <!-- Players List -->
    <ul class="players">
        <?php 
        $player_num = 1;
        
        // Show active players
        foreach ($active_players as $player): 
        ?>
        <li class="player-slot">
            <span class="numer"><?php echo t('player'); ?> <?php echo $player_num; ?>: </span>
            <span class="name"><?php echo htmlspecialchars($player['player_name']); ?></span>
            
            <?php if ($config['allow_private_messages'] && $player['player_email'] && !$is_inactive): ?>
                <span class="mail-icon" data-player-id="<?php echo $player['id']; ?>" title="<?php echo t('send_private_message'); ?>">
                    ✉️
                </span>
            <?php endif; ?>
            
            <?php if ($player['knows_rules']): ?>
                <span class="rules rules_<?php echo $player['knows_rules']; ?>">
                    <?php 
                    if ($player['knows_rules'] === 'yes') {
                        echo ' (' . t('knows_rules_yes') . ')';
                    } elseif ($player['knows_rules'] === 'no') {
                        echo ' (' . t('knows_rules_no') . ')';
                    } elseif ($player['knows_rules'] === 'somewhat') {
                        echo ' (' . t('knows_rules_somewhat') . ')';
                    }
                    ?>
                </span>
            <?php endif; ?>
            
            <?php if ($player['comment']): ?>
                <span class="player_comment"> - <?php echo htmlspecialchars($player['comment']); ?></span>
            <?php endif; ?>
            
            <!-- Delete/Resign button -->
            <?php
            $can_delete_player = false;
            if ($current_user) {
                if ($current_user['is_admin'] == 1) {
                    $can_delete_player = true;
                } elseif ($player['user_id'] && $player['user_id'] == $current_user['id']) {
                    $can_delete_player = true;
                }
            }
            if (!$player['user_id']) {
                $can_delete_player = true; // Anyone can delete if no user_id (needs verification)
            }
            ?>
            
            <?php if ($can_delete_player && !$is_inactive): ?>
                <span class="delete delete_player">
                    <a href="#" class="resign-btn" data-player-id="<?php echo $player['id']; ?>" data-game-id="<?php echo $game['id']; ?>">
                        <?php echo t('resign'); ?>
                    </a>
                </span>
            <?php endif; ?>
        </li>
        <?php 
        $player_num++;
        endforeach; 
        ?>
        
        <!-- Empty slots -->
        <?php 
        $empty_slots = $game['max_players'] - count($active_players);
        for ($i = 0; $i < $empty_slots; $i++): 
        ?>
        <li class="player-slot empty">
            <span class="numer"><?php echo t('player'); ?> <?php echo $player_num; ?>: </span>
            <?php if (!$is_inactive): ?>
                <a href="#" class="join-game-btn" data-game-id="<?php echo $game['id']; ?>">
                    <?php echo t('join_game'); ?>
                </a>
            <?php else: ?>
                <span class="empty-slot">-</span>
            <?php endif; ?>
        </li>
        <?php 
        $player_num++;
        endfor; 
        ?>
        
        <!-- Reserve List -->
        <?php if ($config['allow_reserve_list'] && !$is_inactive): ?>
            <?php if (count($reserve_players) > 0): ?>
                <li class="reserve-header">
                    <strong><?php echo t('reserve_list'); ?>:</strong>
                </li>
                <?php foreach ($reserve_players as $reserve_player): ?>
                <li class="player-slot reserve">
                    <span class="name"><?php echo htmlspecialchars($reserve_player['player_name']); ?></span>
                    
                    <?php if ($config['allow_private_messages'] && $reserve_player['player_email']): ?>
                        <span class="mail-icon" data-player-id="<?php echo $reserve_player['id']; ?>" title="<?php echo t('send_private_message'); ?>">
                            ✉️
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($reserve_player['comment']): ?>
                        <span class="player_comment"> - <?php echo htmlspecialchars($reserve_player['comment']); ?></span>
                    <?php endif; ?>
                    
                    <?php
                    $can_delete_reserve = false;
                    if ($current_user) {
                        if ($current_user['is_admin'] == 1) {
                            $can_delete_reserve = true;
                        } elseif ($reserve_player['user_id'] && $reserve_player['user_id'] == $current_user['id']) {
                            $can_delete_reserve = true;
                        }
                    }
                    if (!$reserve_player['user_id']) {
                        $can_delete_reserve = true;
                    }
                    ?>
                    
                    <?php if ($can_delete_reserve): ?>
                        <span class="delete delete_player">
                            <a href="#" class="resign-btn" data-player-id="<?php echo $reserve_player['id']; ?>" data-game-id="<?php echo $game['id']; ?>">
                                <?php echo t('resign'); ?>
                            </a>
                        </span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if ($is_full): ?>
                <li class="add_reserve reserve">
                    <a href="#" class="join-reserve-btn" data-game-id="<?php echo $game['id']; ?>">
                        <?php echo t('join_reserve'); ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endif; ?>
    </ul>
    
    <!-- Edit/Delete Buttons -->
    <?php if ($can_modify && !$is_inactive): ?>
    <div class="delete delete_game">
        <a href="#" class="delete_link delete-game-btn" data-game-id="<?php echo $game['id']; ?>">
            <?php echo t('delete'); ?>
        </a>
        <a href="#" class="edit_link edit-game-btn" data-game-id="<?php echo $game['id']; ?>">
            <?php echo t('edit'); ?>
        </a>
    </div>
    <?php endif; ?>
    
    <!-- Restore button for inactive games -->
    <?php if ($is_inactive): ?>
    <div class="restore_game">
        <a href="#" class="restore-game-btn" data-game-id="<?php echo $game['id']; ?>">
            <?php echo t('restore'); ?>
        </a>
        <?php if ($current_user && $current_user['is_admin'] == 1): ?>
            <a href="#" class="fully-delete-btn" data-game-id="<?php echo $game['id']; ?>">
                <?php echo t('fully_delete'); ?>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Comments Section -->
    <?php if (!empty($game['comments']) || !$is_inactive): ?>
    <div class="game-comments-section">
        <h4 class="comments-header">
            <?php echo t('comments'); ?> 
            <?php if (!empty($game['comments'])): ?>
                <span class="comment-count">(<?php echo count($game['comments']); ?>)</span>
            <?php endif; ?>
        </h4>
        
        <?php if (!empty($game['comments'])): ?>
            <div class="comments-list">
                <?php foreach ($game['comments'] as $comment): ?>
                    <div class="comment-item">
                        <div class="comment-header">
                            <span class="comment-author"><?php echo htmlspecialchars($comment['author_name']); ?></span>
                            <span class="comment-time"><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></span>
                        </div>
                        <div class="comment-text">
                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$is_inactive): ?>
            <div class="add-comment-btn-container">
                <button class="btn-add-comment" data-game-id="<?php echo $game['id']; ?>">
                    <?php echo t('add_comment'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</li>