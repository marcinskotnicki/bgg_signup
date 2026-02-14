/**
 * Timeline Rendering Template
 * Default theme implementation
 */

function initTimeline() {
    const timeline = $('#timeline');
    const startTime = TIMELINE_CONFIG.startTime;
    const endTime = TIMELINE_CONFIG.endTime;
    const extension = TIMELINE_CONFIG.extension;
    
    // Calculate timeline hours
    const start = parseTime(startTime);
    const end = parseTime(endTime);
    const endWithExtension = end + (extension * 60);
    
    // Build hour markers
    const startHour = Math.floor(start / 60);
    const endHour = Math.ceil(endWithExtension / 60);
    
    // Build timeline HTML
    let html = '<div class="timeline-container-inner">';
    
    // Add hour markers header
    html += '<div class="timeline-hours">';
    html += '<div class="timeline-table-label-spacer"></div>'; // Spacer for table labels
    html += '<div class="timeline-hours-bar">';
    
    // Hour markers with vertical lines
    for (let hour = startHour; hour <= endHour; hour++) {
        const hourMinutes = hour * 60;
        const position = ((hourMinutes - start) / (endWithExtension - start)) * 100;
        
        if (position >= 0 && position <= 100) {
            const displayHour = hour % 24;
            const hourStr = displayHour.toString().padStart(2, '0') + ':00';
            html += '<div class="timeline-hour-marker" style="left: ' + position + '%;">' + hourStr + '</div>';
        }
    }
    
    // Add final vertical line at the right edge
    html += '<div class="timeline-hour-marker timeline-final-marker" style="left: 100%;"><span style="visibility: hidden;">_</span></div>';
    html += '</div>'; // timeline-hours-bar
    html += '</div>'; // timeline-hours
    
    html += '<div class="timeline-grid">';
    
    // Add hour background stripes (including the final hour)
    html += '<div class="timeline-hour-stripes">';
    for (let hour = startHour; hour <= endHour; hour++) {
        const hourStart = hour * 60;
        const hourEnd = (hour + 1) * 60;
        const leftPos = Math.max(0, ((hourStart - start) / (endWithExtension - start)) * 100);
        const rightPos = Math.min(100, ((hourEnd - start) / (endWithExtension - start)) * 100);
        const width = rightPos - leftPos;
        
        // Skip if this column would be outside bounds
        if (width <= 0 || leftPos >= 100) continue;
        
        const isEven = (hour - startHour) % 2 === 0;
        const className = isEven ? 'timeline-hour-stripe-even' : 'timeline-hour-stripe-odd';
        
        html += '<div class="' + className + '" style="left: ' + leftPos + '%; width: ' + width + '%;"></div>';
    }
    
    html += '</div>'; // timeline-hour-stripes
    
    // Add table rows with games
    TIMELINE_CONFIG.tables.forEach(function(table) {
        html += '<div class="timeline-row">';
        html += '<div class="timeline-table-label">' + TIMELINE_CONFIG.tableLabel + ' ' + table.table_number + '</div>';
        html += '<div class="timeline-games">';
        
        table.games.forEach(function(game) {
            const gameStart = parseTime(game.start_time);
            const gameWidth = game.play_time;
            const leftPos = ((gameStart - start) / (endWithExtension - start)) * 100;
            const widthPercent = (gameWidth / (endWithExtension - start)) * 100;
            
            html += '<div class="timeline-game" data-game-id="' + game.id + '" style="' + 
                'left: ' + leftPos + '%; ' +
                'width: ' + widthPercent + '%;' +
                '">';
            html += '<span class="timeline-game-name">' + escapeHtml(game.name) + '</span>';
            html += '<span class="timeline-game-players">(' + TIMELINE_CONFIG.playersLabel + ': ' + game.active_players + '/' + game.max_players + ')</span>';
            html += '<span class="timeline-game-time">' + game.start_time + ' - ' + game.end_time + '</span>';
            html += '</div>';
        });
        
        html += '</div>'; // timeline-games
        html += '</div>'; // timeline-row
    });
    
    html += '</div>'; // timeline-grid
    html += '</div>'; // timeline-container-inner
    
    timeline.html(html);
}

// Parse time string to minutes since midnight
function parseTime(timeStr) {
    const parts = timeStr.split(':');
    return parseInt(parts[0]) * 60 + parseInt(parts[1]);
}

// Escape HTML for safe display
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
