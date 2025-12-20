<?php
require_once('../../../wp-load.php');
global $wpdb;

echo "<h2>ptgates_subject (Limit 20)</h2>";
$rows = $wpdb->get_results("SELECT * FROM ptgates_subject LIMIT 20");
echo "<pre>";
print_r($rows);
echo "</pre>";

echo "<h2>Check Mapping for '해부생리'</h2>";
$check = $wpdb->get_row("SELECT * FROM ptgates_subject WHERE subcategory = '해부생리'");
print_r($check);
