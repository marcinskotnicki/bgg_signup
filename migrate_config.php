<?php
/**
 * Config Migration Script
 * Converts old allow_full_deletion to new deletion_mode
 */

$config_file = 'config.php';

if (!file_exists($config_file)) {
    die("Error: config.php not found!");
}

// Read current config
$config_content = file_get_contents($config_file);

// Check if already migrated
if (strpos($config_content, "'deletion_mode'") !== false) {
    echo "Config already migrated to deletion_mode. No changes needed.\n";
    exit;
}

// Check if old setting exists
if (strpos($config_content, "'allow_full_deletion'") === false) {
    echo "Warning: Neither old nor new deletion setting found. Adding deletion_mode...\n";
    // Add deletion_mode before comment settings
    $pattern = "/(\/\/ Comment Settings)/";
    $replacement = "// Game Deletion Options:\n    // 'soft_only' - Only soft delete allowed\n    // 'allow_choice' - Users can choose between hard delete or soft delete\n    // 'hard_only' - Only hard delete allowed (immediate permanent deletion)\n    'deletion_mode' => 'soft_only',\n    \n    $1";
    $config_content = preg_replace($pattern, $replacement, $config_content);
} else {
    // Migrate from old to new
    echo "Migrating from allow_full_deletion to deletion_mode...\n";
    
    // Determine the old value
    if (preg_match("/'allow_full_deletion'\s*=>\s*(true|false)/", $config_content, $matches)) {
        $old_value = $matches[1];
        $new_value = ($old_value === 'true') ? 'allow_choice' : 'soft_only';
        
        echo "Old value: allow_full_deletion = $old_value\n";
        echo "New value: deletion_mode = '$new_value'\n";
    } else {
        echo "Could not detect old value, defaulting to 'soft_only'\n";
        $new_value = 'soft_only';
    }
    
    // Replace the entire section
    $old_pattern = "/\/\/ Game Deletion Options:.*?'allow_full_deletion'\s*=>\s*(true|false),/s";
    $new_section = "// Game Deletion Options:\n    // 'soft_only' - Only soft delete allowed\n    // 'allow_choice' - Users can choose between hard delete or soft delete\n    // 'hard_only' - Only hard delete allowed (immediate permanent deletion)\n    'deletion_mode' => '$new_value',";
    
    $config_content = preg_replace($old_pattern, $new_section, $config_content);
}

// Backup the old config
$backup_file = 'config.php.backup_' . date('Y-m-d_His');
copy($config_file, $backup_file);
echo "Backup created: $backup_file\n";

// Write new config
file_put_contents($config_file, $config_content);

echo "\nMigration complete!\n";
echo "Your config.php has been updated.\n";
echo "Please refresh your admin panel.\n";
?>
