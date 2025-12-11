<?php
define('WP_USE_THEMES', false);
require_once('wp-load.php');
echo "Log file: " . ini_get('error_log') . "\n";
error_log('Test error log entry from antigravity');
trigger_error('Test trigger error from antigravity', E_USER_WARNING);
echo "Done.\n";
