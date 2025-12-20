<?php
// Load WordPress environment
require_once('../../../wp-load.php');

global $wpdb;
$table_categories = 'ptgates_categories';

// 1. Inspect Column Types
echo "<h2>Column Types</h2>";
$cols = $wpdb->get_results("SHOW COLUMNS FROM $table_categories");
echo "<pre>";
foreach($cols as $col) {
    echo $col->Field . " (" . $col->Type . ")\n";
}
echo "</pre>";

// 2. Sample Data for Session 1001
echo "<h2>Sample Data (Session >= 1000)</h2>";
$rows = $wpdb->get_results("SELECT * FROM $table_categories WHERE exam_session >= 1000 LIMIT 5");
echo "<pre>";
print_r($rows);
echo "</pre>";

// 3. Check for specific question if known (or just check count of '1교시')
echo "<h2>Count of '1교시' vs '1'</h2>";
$count_1 = $wpdb->get_var("SELECT COUNT(*) FROM $table_categories WHERE exam_course = '1'");
$count_1_str = $wpdb->get_var("SELECT COUNT(*) FROM $table_categories WHERE exam_course = '1교시'");

echo "Count '1': $count_1\n";
echo "Count '1교시': $count_1_str\n";

// 4. Check actual query logic
echo "<h2>Test Query Logic (1001, 1교시)</h2>";
$session_code = 1001;
$exam_course = '1교시';
$sql = "SELECT COUNT(*) FROM $table_categories WHERE exam_session = %d AND exam_course = %s";
$result = $wpdb->get_var($wpdb->prepare($sql, $session_code, $exam_course));
echo "Matches for session 1001 AND course '1교시': $result\n";
