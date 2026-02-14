<?php
/**
 * Config Checker & Fixer
 * Diagnoses and fixes issues with config.php
 */

echo "<h1>Config.php Diagnostic Tool</h1>";
echo "<style>body{font-family:Arial;max-width:900px;margin:20px auto;padding:20px;}pre{background:#f5f5f5;padding:15px;overflow-x:auto;}.error{color:red;}.success{color:green;}.warning{color:orange;}</style>";

$config_file = 'config.php';

// Check if file exists
if (!file_exists($config_file)) {
    echo "<p class='error'>❌ config.php not found!</p>";
    exit;
}

echo "<h2>Step 1: File Check</h2>";
echo "<p class='success'>✓ config.php exists</p>";

// Check if readable
if (!is_readable($config_file)) {
    echo "<p class='error'>❌ config.php is not readable!</p>";
    exit;
}
echo "<p class='success'>✓ config.php is readable</p>";

// Check if writable
if (!is_writable($config_file)) {
    echo "<p class='warning'>⚠ config.php is not writable! chmod 666 config.php</p>";
} else {
    echo "<p class='success'>✓ config.php is writable</p>";
}

// Try to load it
echo "<h2>Step 2: Syntax Check</h2>";
try {
    $config = require $config_file;
    if (!is_array($config)) {
        echo "<p class='error'>❌ config.php doesn't return an array!</p>";
        exit;
    }
    echo "<p class='success'>✓ config.php syntax is valid</p>";
    echo "<p class='success'>✓ config.php returns an array</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ config.php has syntax error: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Show backup info
    if (file_exists($config_file . '.backup')) {
        echo "<h3>Backup Available</h3>";
        echo "<p>You can restore from backup by running:</p>";
        echo "<pre>cp config.php.backup config.php</pre>";
        echo "<form method='POST'><button type='submit' name='restore_backup'>Restore from Backup</button></form>";
    }
    exit;
}

// Check for old vs new deletion setting
echo "<h2>Step 3: Deletion Setting Check</h2>";
if (isset($config['deletion_mode'])) {
    echo "<p class='success'>✓ Using NEW deletion_mode: <strong>{$config['deletion_mode']}</strong></p>";
} elseif (isset($config['allow_full_deletion'])) {
    $old_value = $config['allow_full_deletion'] ? 'true' : 'false';
    echo "<p class='warning'>⚠ Using OLD allow_full_deletion: <strong>{$old_value}</strong></p>";
    echo "<p>This needs to be migrated to the new deletion_mode setting.</p>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='migrate_deletion'>Migrate to New Format</button>";
    echo "</form>";
} else {
    echo "<p class='error'>❌ No deletion setting found!</p>";
}

// Check for other common settings
echo "<h2>Step 4: Settings Check</h2>";
$required_settings = [
    'venue_name', 'default_language', 'allow_logged_in', 
    'send_emails', 'active_template'
];

foreach ($required_settings as $setting) {
    if (isset($config[$setting])) {
        $value = is_bool($config[$setting]) ? ($config[$setting] ? 'true' : 'false') : $config[$setting];
        echo "<p class='success'>✓ {$setting}: {$value}</p>";
    } else {
        echo "<p class='warning'>⚠ Missing: {$setting}</p>";
    }
}

// Show all settings
echo "<h2>Step 5: All Settings</h2>";
echo "<pre>";
foreach ($config as $key => $value) {
    if (is_bool($value)) {
        $display = $value ? 'true' : 'false';
    } else {
        $display = is_string($value) ? "'{$value}'" : $value;
    }
    echo "$key => $display\n";
}
echo "</pre>";

// Handle restore backup
if (isset($_POST['restore_backup'])) {
    if (file_exists($config_file . '.backup')) {
        copy($config_file . '.backup', $config_file);
        echo "<p class='success'>✓ Restored from backup! <a href='?'>Refresh</a></p>";
    } else {
        echo "<p class='error'>❌ Backup file not found!</p>";
    }
}

// Handle migration
if (isset($_POST['migrate_deletion'])) {
    $config_content = file_get_contents($config_file);
    
    // Backup first
    copy($config_file, $config_file . '.backup_' . date('Y-m-d_His'));
    
    // Get the old value
    $new_value = $config['allow_full_deletion'] ? 'allow_choice' : 'soft_only';
    
    // Replace
    $old_pattern = "/(\/\/ Game Deletion Options:.*?)'allow_full_deletion'\s*=>\s*(true|false),/s";
    $new_section = "$1'deletion_mode' => '{$new_value}',";
    $config_content = preg_replace($old_pattern, $new_section, $config_content);
    
    file_put_contents($config_file, $config_content);
    
    echo "<p class='success'>✓ Migration complete! <a href='?'>Refresh</a></p>";
    exit;
}

echo "<h2>Summary</h2>";
if (isset($config['deletion_mode'])) {
    echo "<p class='success'>✓ Your config.php is up-to-date and working!</p>";
} else {
    echo "<p class='warning'>⚠ Your config.php needs migration (click button above)</p>";
}

echo "<hr>";
echo "<p><a href='admin.php'>← Back to Admin Panel</a></p>";
?>
