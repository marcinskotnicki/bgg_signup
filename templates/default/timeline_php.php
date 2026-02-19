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
            <?php
            // First pass: Calculate positions and max height needed
            $game_positions = [];
            $max_offset = 0;
            
            foreach ($table_data['games'] as $game) {
                // Skip soft-deleted games in timeline
                if ($game['is_active'] == 0) continue;
                
                // Calculate game positioning
                $game_start = time_to_minutes($game['start_time']);
                $game_end = $game_start + $game['play_time'];
                
                $game_start_pos = (($game_start - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                $game_end_pos = (($game_end - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                
                // Clip to visible range [0, 100]
                $display_left = max(0, $game_start_pos);
                $display_right = min(100, $game_end_pos);
                $display_width = $display_right - $display_left;
                
                // Only process if visible in timeline
                if ($display_right > 0 && $display_left < 100 && $display_width > 0) {
                    // Check for overlaps with previous games
                    $vertical_offset = 0;
                    foreach ($game_positions as $pos) {
                        if ($game_start_pos < $pos['end'] && $game_end_pos > $pos['start']) {
                            $vertical_offset = max($vertical_offset, $pos['offset'] + 1);
                        }
                    }
                    
                    // Track max offset for height calculation
                    $max_offset = max($max_offset, $vertical_offset);
                    
                    // Store this game's position
                    $game_positions[] = [
                        'start' => $game_start_pos,
                        'end' => $game_end_pos,
                        'offset' => $vertical_offset
                    ];
                }
            }
            
            // Calculate required height: base padding + (max_offset * 60px spacing) + 50px game height + base padding
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
                    // Second pass: Render games with calculated positions
                    $game_positions = [];
                    
                    foreach ($table_data['games'] as $game): 
                        // Skip soft-deleted games in timeline
                        if ($game['is_active'] == 0) continue;
                        
                        // Calculate game positioning
                        $game_start = time_to_minutes($game['start_time']);
                        $game_end = $game_start + $game['play_time'];
                        
                        $game_start_pos = (($game_start - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                        $game_end_pos = (($game_end - $start_minutes) / ($actual_end - $start_minutes)) * 100;
                        
                        // Clip to visible range [0, 100]
                        $display_left = max(0, $game_start_pos);
                        $display_right = min(100, $game_end_pos);
                        $display_width = $display_right - $display_left;
                        
                        // Only show if visible in timeline
                        if ($display_right > 0 && $display_left < 100 && $display_width > 0):
                            // Count active players
                            $active_players = count(array_filter($game['players'], function($p) { 
                                return $p['is_reserve'] == 0; 
                            }));
                            
                            // Determine if game is full
                            $is_full = $active_players >= $game['max_players'];
                            $fill_class = $is_full ? 'timeline-game-full' : 'timeline-game-open';
                            
                            // Check for overlaps with previous games
                            $vertical_offset = 0;
                            foreach ($game_positions as $pos) {
                                // Check if this game overlaps with existing game
                                if ($game_start_pos < $pos['end'] && $game_end_pos > $pos['start']) {
                                    // Overlaps! Move down
                                    $vertical_offset = max($vertical_offset, $pos['offset'] + 1);
                                }
                            }
                            
                            // Store this game's position for future overlap checks
                            $game_positions[] = [
                                'start' => $game_start_pos,
                                'end' => $game_end_pos,
                                'offset' => $vertical_offset
                            ];
                            
                            // Calculate top position (60px per row)
                            $top_position = 10 + ($vertical_offset * 60);
                        ?>
                            <div class="timeline-game <?php echo $fill_class; ?>" 
                                 data-game-id="<?php echo $game['id']; ?>"
                                 style="left: <?php echo $display_left; ?>%; width: <?php echo $display_width; ?>%; top: <?php echo $top_position; ?>px;"
                                 title="<?php echo htmlspecialchars($game['name']); ?> (<?php echo $active_players; ?>/<?php echo $game['max_players']; ?>)">
                                <div class="timeline-game-content">
                                    <div class="timeline-game-name"><?php echo htmlspecialchars($game['name']); ?></div>
                                    <div class="timeline-game-players"><?php echo $active_players; ?>/<?php echo $game['max_players']; ?></div>
                                </div>
                            </div>
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
});
</script>
