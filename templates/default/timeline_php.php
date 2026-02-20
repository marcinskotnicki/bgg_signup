<?php
/**
 * Enhanced Timeline Template - PHP Rendered
 * 
 * Features:
 * - Shows soft-deleted games (25% opacity, red color)
 * - Shows polls (different color, 120min duration)
 * - Mobile-friendly with horizontal scroll
 * - Click to scroll with glow highlight
 * - Bold/highlighted player count when full
 * 
 * Variables expected:
 * - $selected_day: Current day data
 * - $tables_with_games: Array of tables with their games and polls
 * - $config: Configuration array
 */

// Load timeline helper
require_once __DIR__ . '/../../includes/timeline_helper.php';

if (empty($selected_day) || empty($tables_with_games)) {
    echo '<p>' . t('no_games_yet') . '</p>';
    return;
}

// Calculate timeline parameters
$start_time = $selected_day['start_time'];
$end_time = $selected_day['end_time'];
$extension = $config['timeline_extension'];

$start_minutes = time_to_minutes($start_time);
$end_minutes = time_to_minutes($end_time);
$end_with_extension = $end_minutes + ($extension * 60);
$actual_end = $end_minutes + (($extension + 1) * 60); // Extra hour for overflow

// Calculate hours for markers
$start_hour = floor($start_minutes / 60);
$end_hour = ceil($end_with_extension / 60);
?>

<!-- Mobile-friendly scrollable wrapper -->
<div class="timeline-scroll-container">
<div class="timeline-container-inner">
    <!-- Hour markers header -->
    <div class="timeline-hours">
        <div class="timeline-table-label-spacer"></div>
        <div class="timeline-hours-bar">
            <?php for ($hour = $start_hour; $hour <= $end_hour; $hour++): ?>
                <?php
                $hour_minutes = $hour * 60;
                $position = (($hour_minutes - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                
                if ($position >= 0 && $position <= 100):
                    $display_hour = $hour % 24;
                    $hour_str = str_pad($display_hour, 2, '0', STR_PAD_LEFT) . ':00';
                ?>
                    <div class="timeline-hour-marker" style="left: <?php echo $position; ?>%;">
                        <?php echo $hour_str; ?>
                    </div>
                <?php endif; ?>
            <?php endfor; ?>
            
            <!-- Final marker at right edge -->
            <div class="timeline-hour-marker timeline-final-marker" style="left: 100%;">
                <span style="visibility: hidden;">_</span>
            </div>
        </div>
    </div>
    
    <!-- Timeline grid -->
    <div class="timeline-grid">
        <!-- Hour background stripes -->
        <div class="timeline-hour-stripes">
            <?php for ($hour = $start_hour; $hour <= $end_hour; $hour++): ?>
                <?php
                $hour_minutes = $hour * 60;
                $next_hour_minutes = ($hour + 1) * 60;
                $stripe_start = (($hour_minutes - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                $stripe_end = (($next_hour_minutes - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                $stripe_width = $stripe_end - $stripe_start;
                
                if ($stripe_start < 100 && $stripe_end > 0):
                    $is_even = ($hour % 2 == 0);
                ?>
                    <div class="timeline-hour-stripe <?php echo $is_even ? 'even' : 'odd'; ?>" 
                         style="left: <?php echo max(0, $stripe_start); ?>%; width: <?php echo min(100 - max(0, $stripe_start), $stripe_width); ?>%;"></div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        
        <!-- Table rows with games and polls -->
        <?php foreach ($tables_with_games as $table_data): ?>
            <?php
            // First pass: Calculate positions and max height needed for BOTH games and polls
            $item_positions = [];
            $max_offset = 0;
            
            // Process ALL games (including soft-deleted)
            foreach ($table_data['games'] as $game) {
                $game_start = time_to_minutes($game['start_time']);
                $game_end = $game_start + $game['play_time'];
                
                $game_start_pos = (($game_start - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                $game_end_pos = (($game_end - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                
                $display_left = max(0, $game_start_pos);
                $display_right = min(100, $game_end_pos);
                $display_width = $display_right - $display_left;
                
                if ($display_right > 0 && $display_left < 100 && $display_width > 0) {
                    $vertical_offset = 0;
                    foreach ($item_positions as $pos) {
                        if ($game_start_pos < $pos['end'] && $game_end_pos > $pos['start']) {
                            $vertical_offset = max($vertical_offset, $pos['offset'] + 1);
                        }
                    }
                    
                    $max_offset = max($max_offset, $vertical_offset);
                    
                    $item_positions[] = [
                        'start' => $game_start_pos,
                        'end' => $game_end_pos,
                        'offset' => $vertical_offset
                    ];
                }
            }
            
            // Process polls (assume 120 minutes each)
            if (!empty($table_data['polls'])) {
                foreach ($table_data['polls'] as $poll) {
                    // Get poll start time or use table start
                    $poll_start_str = !empty($poll['start_time']) ? $poll['start_time'] : $selected_day['start_time'];
                    $poll_start = time_to_minutes($poll_start_str);
                    $poll_end = $poll_start + 120; // Assume 120 minutes for polls
                    
                    $poll_start_pos = (($poll_start - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                    $poll_end_pos = (($poll_end - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                    
                    $display_left = max(0, $poll_start_pos);
                    $display_right = min(100, $poll_end_pos);
                    $display_width = $display_right - $display_left;
                    
                    if ($display_right > 0 && $display_left < 100 && $display_width > 0) {
                        $vertical_offset = 0;
                        foreach ($item_positions as $pos) {
                            if ($poll_start_pos < $pos['end'] && $poll_end_pos > $pos['start']) {
                                $vertical_offset = max($vertical_offset, $pos['offset'] + 1);
                            }
                        }
                        
                        $max_offset = max($max_offset, $vertical_offset);
                        
                        $item_positions[] = [
                            'start' => $poll_start_pos,
                            'end' => $poll_end_pos,
                            'offset' => $vertical_offset
                        ];
                    }
                }
            }
            
            // Calculate required height
            $required_height = 10 + ($max_offset * 60) + 50 + 10;
            ?>
            
            <div class="timeline-row">
                <!-- Table label -->
                <div class="timeline-table-label">
                    <?php echo t('table'); ?> <?php echo $table_data['table']['table_number']; ?>
                </div>
                
                <!-- Games track with dynamic height -->
                <div class="timeline-games" style="min-height: <?php echo $required_height; ?>px;">
                    <?php 
                    // Second pass: Render ALL games and polls
                    $item_positions = [];
                    
                    // Render games (including soft-deleted)
                    foreach ($table_data['games'] as $game): 
                        $game_start = time_to_minutes($game['start_time']);
                        $game_end = $game_start + $game['play_time'];
                        
                        $game_start_pos = (($game_start - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                        $game_end_pos = (($game_end - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                        
                        $display_left = max(0, $game_start_pos);
                        $display_right = min(100, $game_end_pos);
                        $display_width = $display_right - $display_left;
                        
                        if ($display_right > 0 && $display_left < 100 && $display_width > 0):
                            // Count active players
                            $active_players = count(array_filter($game['players'], function($p) { 
                                return $p['is_reserve'] == 0; 
                            }));
                            
                            // Determine game state
                            $is_full = $active_players >= $game['max_players'];
                            $is_deleted = $game['is_active'] == 0;
                            $fill_class = $is_full ? 'timeline-game-full' : 'timeline-game-open';
                            $deleted_class = $is_deleted ? 'timeline-game-deleted' : '';
                            
                            // Check for overlaps
                            $vertical_offset = 0;
                            foreach ($item_positions as $pos) {
                                if ($game_start_pos < $pos['end'] && $game_end_pos > $pos['start']) {
                                    $vertical_offset = max($vertical_offset, $pos['offset'] + 1);
                                }
                            }
                            
                            $item_positions[] = [
                                'start' => $game_start_pos,
                                'end' => $game_end_pos,
                                'offset' => $vertical_offset
                            ];
                            
                            $top_position = 10 + ($vertical_offset * 60);
                            $player_count_class = $is_full ? 'player-count-full' : '';
                        ?>
                            <div class="timeline-game timeline-item-game <?php echo $fill_class; ?> <?php echo $deleted_class; ?>" 
                                 data-game-id="<?php echo $game['id']; ?>"
                                 data-item-type="game"
                                 style="left: <?php echo $display_left; ?>%; width: <?php echo $display_width; ?>%; top: <?php echo $top_position; ?>px;"
                                 title="<?php echo htmlspecialchars($game['name']); ?> (<?php echo $active_players; ?>/<?php echo $game['max_players']; ?>)<?php echo $is_deleted ? ' - ' . t('deleted') : ''; ?>">
                                <div class="timeline-game-content">
                                    <div class="timeline-game-name"><?php echo htmlspecialchars($game['name']); ?></div>
                                    <div class="timeline-game-players <?php echo $player_count_class; ?>"><?php echo $active_players; ?>/<?php echo $game['max_players']; ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php 
                    // Render polls
                    if (!empty($table_data['polls'])):
                        foreach ($table_data['polls'] as $poll):
                            $poll_start_str = !empty($poll['start_time']) ? $poll['start_time'] : $selected_day['start_time'];
                            $poll_start = time_to_minutes($poll_start_str);
                            $poll_end = $poll_start + 120;
                            
                            $poll_start_pos = (($poll_start - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                            $poll_end_pos = (($poll_end - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                            
                            $display_left = max(0, $poll_start_pos);
                            $display_right = min(100, $poll_end_pos);
                            $display_width = $display_right - $display_left;
                            
                            if ($display_right > 0 && $display_left < 100 && $display_width > 0):
                                // Count total votes
                                $total_votes = 0;
                                if (!empty($poll['options'])) {
                                    foreach ($poll['options'] as $option) {
                                        $total_votes += $option['vote_count'];
                                    }
                                }
                                
                                // Check for overlaps
                                $vertical_offset = 0;
                                foreach ($item_positions as $pos) {
                                    if ($poll_start_pos < $pos['end'] && $poll_end_pos > $pos['start']) {
                                        $vertical_offset = max($vertical_offset, $pos['offset'] + 1);
                                    }
                                }
                                
                                $item_positions[] = [
                                    'start' => $poll_start_pos,
                                    'end' => $poll_end_pos,
                                    'offset' => $vertical_offset
                                ];
                                
                                $top_position = 10 + ($vertical_offset * 60);
                            ?>
                                <div class="timeline-game timeline-item-poll" 
                                     data-poll-id="<?php echo $poll['id']; ?>"
                                     data-item-type="poll"
                                     style="left: <?php echo $display_left; ?>%; width: <?php echo $display_width; %>%; top: <?php echo $top_position; ?>px;"
                                     title="<?php echo t('game_poll'); ?> (<?php echo $total_votes; ?> <?php echo t('votes'); ?>)">
                                    <div class="timeline-game-content">
                                        <div class="timeline-game-name">📊 <?php echo t('game_poll'); ?></div>
                                        <div class="timeline-game-players"><?php echo $total_votes; ?> <?php echo t('votes'); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</div> <!-- Close timeline-scroll-container -->

<script>
// Enhanced timeline click handling
$(document).on('click', '.timeline-game', function() {
    const itemType = $(this).data('item-type');
    const gameId = $(this).data('game-id');
    const pollId = $(this).data('poll-id');
    
    if (itemType === 'game' && gameId) {
        // Find the game card (not the timeline item)
        const gameCard = $('.game-card[data-game-id="' + gameId + '"]').first();
        if (gameCard.length) {
            // Scroll to game
            $('html, body').animate({
                scrollTop: gameCard.offset().top - 100
            }, 500);
            
            // Add glow highlight
            gameCard.addClass('timeline-highlight-glow');
            setTimeout(function() {
                gameCard.removeClass('timeline-highlight-glow');
            }, 3000);
        }
    } else if (itemType === 'poll' && pollId) {
        // Find the poll card (not the timeline item)
        const pollCard = $('.poll-container[data-poll-id="' + pollId + '"], .poll-card[data-poll-id="' + pollId + '"]').first();
        if (pollCard.length) {
            // Scroll to poll
            $('html, body').animate({
                scrollTop: pollCard.offset().top - 100
            }, 500);
            
            // Add glow highlight
            pollCard.addClass('timeline-highlight-glow');
            setTimeout(function() {
                pollCard.removeClass('timeline-highlight-glow');
            }, 3000);
        }
    }
});
</script>
