<?php
require_once 'includes/http_helper.php';
require_once 'includes/log_helper.php';
require_once 'includes/file_helper.php';
require_once 'includes/timeline_helper.php';
require_once 'includes/schema_helper.php';

echo "All helpers loaded successfully!\n";
echo "fetch_url exists: " . (function_exists('fetch_url') ? 'YES' : 'NO') . "\n";
echo "add_log exists: " . (function_exists('add_log') ? 'YES' : 'NO') . "\n";
echo "time_to_minutes exists: " . (function_exists('time_to_minutes') ? 'YES' : 'NO') . "\n";