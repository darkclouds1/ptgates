<?php
require_once('../../../wp-load.php');

global $wpdb;

function get_columns($table) {
    global $wpdb;
    return $wpdb->get_results("SHOW COLUMNS FROM $table");
}

$tables = ['ptgates_categories', 'ptgates_subject_config'];
$output = [];

foreach ($tables as $table) {
    $output[$table] = get_columns($table);
}

header('Content-Type: application/json');
echo json_encode($output, JSON_PRETTY_PRINT);
