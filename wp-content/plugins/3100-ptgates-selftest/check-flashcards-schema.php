<?php
/**
 * Flashcards Table Schema Checker
 */
require_once('../../../wp-load.php');

global $wpdb;

echo "<h1>PTGates Flashcards Table Schema</h1>";

// Check both with and without wp_ prefix
$tables_to_check = [
    'ptgates_flashcards',
    'wp_ptgates_flashcards',
    $wpdb->prefix . 'ptgates_flashcards'
];

foreach ($tables_to_check as $table_name) {
    echo "<h2>Checking: {$table_name}</h2>";
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
    
    if ($table_exists) {
        echo "<p style='color: green;'>✅ Table '{$table_name}' exists</p>";
        
        // Get table structure
        $structure = $wpdb->get_results("DESCRIBE {$table_name}");
        
        echo "<h3>Table Structure:</h3>";
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
        echo "<h3>Sample Record:</h3>";
        $sample = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1", ARRAY_A);
        if ($sample) {
            echo "<pre>";
            print_r($sample);
            echo "</pre>";
        } else {
            echo "<p>No records found in table.</p>";
        }
        
        // Count total records
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        echo "<p>Total flashcards in DB: <strong>{$count}</strong></p>";
        
        echo "<hr>";
        break; // Found the table, no need to check others
    } else {
        echo "<p style='color: red;'>❌ Table '{$table_name}' does not exist</p>";
        echo "<hr>";
    }
}

echo "<h2>All Tables in Database:</h2>";
$all_tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
echo "<ul>";
foreach ($all_tables as $table) {
    if (stripos($table[0], 'flashcard') !== false || stripos($table[0], 'ptgates') !== false) {
        echo "<li><strong style='color: blue;'>{$table[0]}</strong></li>";
    }
}
echo "</ul>";
