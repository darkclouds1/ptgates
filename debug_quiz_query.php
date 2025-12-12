<?php
define('WP_USE_THEMES', false);
// Load WordPress environment
require_once __DIR__ . '/wp-load.php';

global $wpdb;

echo "Checking '사진 자료형' in 'ptgates_categories'...\n";

// Check in 'subject' column
$count_subject = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM ptgates_categories WHERE subject LIKE %s", 
    '%사진 자료형%'
));
echo "Found in 'subject' column: $count_subject rows\n";

// Check in 'subject_category' column
$count_category = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM ptgates_categories WHERE subject_category LIKE %s", 
    '%사진 자료형%'
));
echo "Found in 'subject_category' column: $count_category rows\n";

// Check sample data
$sample = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM ptgates_categories WHERE subject_category LIKE %s LIMIT 1", 
    '%사진 자료형%'
));

if ($sample) {
    echo "\nSample Row (found by subject_category):\n";
    print_r($sample);
} else {
    echo "\nNo sample found by subject_category search.\n";
}

echo "\nChecking '실기' in 'subject' column...\n";
$count_silgi = $wpdb->get_var("SELECT COUNT(*) FROM ptgates_categories WHERE subject LIKE '%실기%'");
echo "Found '실기': $count_silgi rows\n";
