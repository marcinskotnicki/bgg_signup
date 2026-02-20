<?php
/**
 * Timeline Helper Functions
 * 
 * Consolidates duplicate time_to_minutes() implementations from:
 * - templates/default/timeline_php.php
 * - templates/classic/timeline_php.php
 * - templates/timeline_php.php
 * 
 * Use: require_once __DIR__ . '/../../includes/timeline_helper.php';
 */

/**
 * Convert time string to minutes since midnight
 * 
 * @param string $time Time in HH:MM format (e.g., "14:30")
 * @return int Minutes since midnight
 */
function time_to_minutes($time) {
    list($hours, $minutes) = explode(':', $time);
    return ($hours * 60) + $minutes;
}

/**
 * Convert minutes since midnight to time string
 * 
 * @param int $minutes Minutes since midnight
 * @return string Time in HH:MM format
 */
function minutes_to_time($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $mins);
}

/**
 * Calculate overlap between two time ranges
 * 
 * @param string $start1 Start time of first range (HH:MM)
 * @param string $end1 End time of first range (HH:MM)
 * @param string $start2 Start time of second range (HH:MM)
 * @param string $end2 End time of second range (HH:MM)
 * @return int Minutes of overlap (0 if no overlap)
 */
function calculate_time_overlap($start1, $end1, $start2, $end2) {
    $start1_min = time_to_minutes($start1);
    $end1_min = time_to_minutes($end1);
    $start2_min = time_to_minutes($start2);
    $end2_min = time_to_minutes($end2);
    
    // No overlap
    if ($end1_min <= $start2_min || $end2_min <= $start1_min) {
        return 0;
    }
    
    // Calculate overlap
    $overlap_start = max($start1_min, $start2_min);
    $overlap_end = min($end1_min, $end2_min);
    
    return $overlap_end - $overlap_start;
}

/**
 * Check if two time ranges overlap
 * 
 * @param string $start1 Start time of first range (HH:MM)
 * @param string $end1 End time of first range (HH:MM)
 * @param string $start2 Start time of second range (HH:MM)
 * @param string $end2 End time of second range (HH:MM)
 * @return bool True if ranges overlap
 */
function times_overlap($start1, $end1, $start2, $end2) {
    return calculate_time_overlap($start1, $end1, $start2, $end2) > 0;
}
