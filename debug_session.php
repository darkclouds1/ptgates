<?php
require_once('wp-load.php');
global $wpdb;

echo "Checking ptgates_subject...\n";

// Check columns
$rows = $wpdb->get_results("SHOW COLUMNS FROM ptgates_subject");
foreach ($rows as $row) {
    echo $row->Field . "\n";
}

echo "\nData:\n";
$data = $wpdb->get_results("SELECT * FROM ptgates_subject");
print_r($data);
