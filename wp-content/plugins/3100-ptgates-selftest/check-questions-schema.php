<?php
/**
 * Quick DB Schema Checker
 */
require_once('../../../wp-load.php');

global $wpdb;

echo "<h1>PTGates Questions Table Schema</h1>";

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE 'ptgates_questions'");

if (!$table_exists) {
    echo "<p style='color: red;'>❌ Table 'ptgates_questions' does not exist!</p>";
    exit;
}

echo "<p style='color: green;'>✅ Table 'ptgates_questions' exists</p>";

// Get table structure
$structure = $wpdb->get_results("DESCRIBE ptgates_questions");

echo "<h2>Table Structure:</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($structure as $field) {
    echo "<tr>";
    echo "<td><strong>{$field->Field}</strong></td>";
    echo "<td>{$field->Type}</td>";
    echo "<td>{$field->Null}</td>";
    echo "<td>{$field->Key}</td>";
    echo "<td>{$field->Default}</td>";
    echo "<td>{$field->Extra}</td>";
    echo "</tr>";
}
echo "</table>";

// Get sample record
echo "<h2>Sample Record:</h2>";
$sample = $wpdb->get_row("SELECT * FROM ptgates_questions LIMIT 1", ARRAY_A);
if ($sample) {
    echo "<pre>";
    print_r($sample);
    echo "</pre>";
} else {
    echo "<p>No records found in table.</p>";
}

// Count total records
$count = $wpdb->get_var("SELECT COUNT(*) FROM ptgates_questions");
echo "<p>Total questions in DB: <strong>{$count}</strong></p>";
