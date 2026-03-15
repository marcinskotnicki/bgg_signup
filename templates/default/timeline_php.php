<?php
/**
 * Timeline Template - PHP Rendered
 * 
 * Variables expected:
 * - $selected_day: Current day data
 * - $tables_with_games: Array of tables with their games
 * - $config: Configuration array
 */

if (empty($selected_day) || empty($tables_with_games)) {
    echo '<p>' . t('no_games_yet') . '</p>';
    return;
}

// Calculate timeline parameters
$start_time = $selected_day['start_time'];
$end_time = $selected_day['end_time'];
$extension = $config['timeline_extension'];

// Parse times to minutes
function time_to_minutes($time) {
    list($h, $m) = explode(':', $time);
    return (int)$h * 60 + (int)$m;
}

$start_minutes = time_to_minutes($start_time);
$end_minutes = time_to_minutes($end_time);
$end_with_extension = $end_minutes + ($extension * 60);
$actual_end = $end_minutes + (($extension + 1) * 60); // Extra hour for overflow

// Calculate hours for markers
$start_hour = floor($start_minutes / 60);
$end_hour = ceil($end_with_extension / 60);
?>

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
        
        <!-- Table rows with games -->
        <?php foreach ($tables_with_games as $table_data): ?>
            <div class="timeline-row">
                <!-- Table label -->
                <div class="timeline-table-label">
                    <?php echo t('table'); ?> <?php echo $table_data['table']['table_number']; ?>
                </div>
                
                <!-- Games track -->
                <div class="timeline-track">
                    <?php 
                    // Combine games and polls for timeline
                    $timeline_items = [];
                    
                    // Add games
                    foreach ($table_data['games'] as $game) {
                        $timeline_items[] = [
                            'type' => 'game',
                            'data' => $game,
                            'name' => $game['name'],
                            'start_time' => $game['start_time'],
                            'duration' => $game['play_time'],
                            'id' => $game['id']
                        ];
                    }
                    
                    // Add polls (fixed 120 minute duration)
                    foreach ($table_data['polls'] as $poll) {
                        if ($poll['start_time']) {
                            $timeline_items[] = [
                                'type' => 'poll',
                                'data' => $poll,
                                'name' => t('game_poll'),
                                'start_time' => $poll['start_time'],
                                'duration' => 120, // Fixed 2-hour duration
                                'id' => $poll['id']
                            ];
                        }
                    }
                    
                    // Sort by start time
                    usort($timeline_items, function($a, $b) {
                        return strcmp($a['start_time'], $b['start_time']);
                    });
                    ?>
                    
                    <?php foreach ($timeline_items as $item): ?>
                        <?php
                        // Calculate positioning
                        $item_start = time_to_minutes($item['start_time']);
                        $item_end = $item_start + $item['duration'];
                        
                        $item_start_pos = (($item_start - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                        $item_end_pos = (($item_end - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                        $item_width = $item_end_pos - $item_start_pos;
                        
                        // Only show if visible in timeline
                        if ($item_end_pos > 0 && $item_start_pos < 100):
                            if ($item['type'] === 'game'):
                                $game = $item['data'];
                                // Count active players
                                $active_players = count(array_filter($game['players'], function($p) { 
                                    return $p['is_reserve'] == 0; 
                                }));
                                
                                // Determine if game is full
                                $is_full = $active_players >= $game['max_players'];
                                $fill_class = $is_full ? 'timeline-game-full' : 'timeline-game-open';
                            ?>
                                <div class="timeline-game <?php echo $fill_class; ?>" 
                                     data-game-id="<?php echo $game['id']; ?>"
                                     style="left: <?php echo max(0, $item_start_pos); ?>%; width: <?php echo min(100 - max(0, $item_start_pos), $item_width); ?>%;"
                                     title="<?php echo htmlspecialchars($game['name']); ?> (<?php echo $active_players; ?>/<?php echo $game['max_players']; ?>)">
                                    <div class="timeline-game-content">
                                        <div class="timeline-game-name"><?php echo htmlspecialchars($game['name']); ?></div>
                                        <div class="timeline-game-players"><?php echo $active_players; ?>/<?php echo $game['max_players']; ?></div>
                                    </div>
                                </div>
                            <?php else: // poll ?>
                                <?php $poll = $item['data']; ?>
                                <div class="timeline-game timeline-poll" 
                                     data-poll-id="<?php echo $poll['id']; ?>"
                                     style="left: <?php echo max(0, $item_start_pos); ?>%; width: <?php echo min(100 - max(0, $item_start_pos), $item_width); ?>%;"
                                     title="<?php echo t('game_poll'); ?>">
                                    <div class="timeline-game-content">
                                        <div class="timeline-game-name"><?php echo t('poll'); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Make timeline games clickable
$(document).on('click', '.timeline-game', function() {
    const gameId = $(this).data('game-id');
    const pollId = $(this).data('poll-id');
    
    if (gameId) {
        // Scroll to the game card
        const gameCard = $('[data-game-id="' + gameId + '"]').not('.timeline-game').first();
        if (gameCard.length) {
            $('html, body').animate({
                scrollTop: gameCard.offset().top - 100
            }, 500);
            
            // Highlight the game briefly
            gameCard.addClass('highlight-flash');
            setTimeout(function() {
                gameCard.removeClass('highlight-flash');
            }, 2000);
        }
    } else if (pollId) {
        // Scroll to the poll
        const pollCard = $('[data-poll-id="' + pollId + '"]').not('.timeline-poll').first();
        if (pollCard.length) {
            $('html, body').animate({
                scrollTop: pollCard.offset().top - 100
            }, 500);
            
            // Highlight the poll briefly
            pollCard.addClass('highlight-flash');
            setTimeout(function() {
                pollCard.removeClass('highlight-flash');
            }, 2000);
        }
    }
});
</script>
