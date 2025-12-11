<?php
require_once('wp-load.php');

global $wpdb;

echo "<h2>Subject Config (New Menu)</h2>";
$config_subjects = $wpdb->get_results("SELECT DISTINCT subject FROM ptgates_subject_config ORDER BY subject", ARRAY_A);
foreach ($config_subjects as $row) {
    echo $row['subject'] . "<br>";
}

echo "<h2>Categories (Question Data)</h2>";
$category_subjects = $wpdb->get_results("SELECT DISTINCT subject FROM ptgates_categories ORDER BY subject", ARRAY_A);
foreach ($category_subjects as $row) {
    echo $row['subject'] . "<br>";
}
