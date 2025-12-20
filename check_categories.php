<?php
require_once('wp-load.php');
global $wpdb;

// Check ptgates_categories structure and sample data
$table = $wpdb->prefix . 'ptgates_categories';
echo "Table: $table\n";

$columns = $wpdb->get_results("DESCRIBE $table");
foreach ($columns as $col) {
    echo $col->Field . " (" . $col->Type . ")\n";
}

echo "\nSample Data (first 10 rows):\n";
$rows = $wpdb->get_results("SELECT * FROM $table LIMIT 10");
foreach ($rows as $row) {
    print_r($row);
}

// Check for values between 1001 and 1999 in any column
echo "\nChecking for values between 1001 and 1999 in subject_category or other fields:\n";
// Assuming subject_category might be the one, or maybe just a general search
$query = "SELECT * FROM $table WHERE subject_category BETWEEN '1001' AND '1999' LIMIT 5";
$results = $wpdb->get_results($query);
print_r($results);

// Also check distinct subject_category values
echo "\nDistinct subject_category values:\n";
$cats = $wpdb->get_col("SELECT DISTINCT subject_category FROM $table LIMIT 20");
print_r($cats);
