<?php
require_once('wp-load.php');
global $wpdb;
$results = $wpdb->get_results("DESCRIBE ptgates_questions");
print_r($results);
