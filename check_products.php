<?php
require_once dirname(__FILE__) . '/wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'ptgates_products';

echo "Checking table: $table_name (Prefix: {$wpdb->prefix})\n";

if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
    echo "Table does NOT exist.\n";
    
    // Check without prefix just in case
    if ($wpdb->get_var("SHOW TABLES LIKE 'ptgates_products'") == 'ptgates_products') {
        echo "However, 'ptgates_products' (no prefix) DOES exist.\n";
    }
} else {
    echo "Table exists.\n";
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "Total rows: $count\n";
    
    $results = $wpdb->get_results("SELECT * FROM $table_name");
    foreach ($results as $row) {
        echo "ID: {$row->id}, Code: {$row->product_code}, Active: {$row->is_active}, Title: {$row->title}\n";
    }
}
