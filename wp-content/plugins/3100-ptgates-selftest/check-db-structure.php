<?php
/**
 * DB Table Structure Checker
 * Shows actual table names and structure for 4-feature persistence
 */

require_once('../../../wp-load.php');

if (!is_user_logged_in()) {
    wp_die('Please log in first.');
}

global $wpdb;

echo "<h1>PTGates DB Table Structure Check</h1>";
echo "<h2>Checking tables for 4-feature toolbar persistence...</h2>";

// Get all ptgates tables
$tables = $wpdb->get_results("SHOW TABLES LIKE 'ptgates_%'", ARRAY_N);

echo "<h3>Found Tables:</h3>";
echo "<ul>";
foreach ($tables as $table) {
    echo "<li><strong>" . $table[0] . "</strong></li>";
}
echo "</ul>";

// Check specific tables for our features
$feature_tables = [
    'Bookmark/Review (Shared)' => 'ptgates_user_states',
    'Memo' => 'ptgates_user_memos', 
    'Flashcard' => 'ptgates_flashcards'
];

echo "<hr>";
echo "<h2>Feature-Specific Table Details:</h2>";

foreach ($feature_tables as $feature => $table_name) {
    echo "<h3>{$feature}: {$table_name}</h3>";
    
    // Check if table exists
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
    
    if ($exists) {
        echo "<p style='color: green;'>✅ Table exists</p>";
        
        // Show structure
        $structure = $wpdb->get_results("DESCRIBE {$table_name}");
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($structure as $field) {
            echo "<tr>";
            echo "<td>{$field->Field}</td>";
            echo "<td>{$field->Type}</td>";
            echo "<td>{$field->Null}</td>";
            echo "<td>{$field->Key}</td>";
            echo "<td>{$field->Default}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show sample count
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        echo "<p>Total records: <strong>{$count}</strong></p>";
    } else {
        echo "<p style='color: red;'>❌ Table does not exist!</p>";
    }
    echo "<hr>";
}

// Show user info
$user = wp_get_current_user();
echo "<h2>Current Test User:</h2>";
echo "<p>Username: <strong>{$user->user_login}</strong></p>";
echo "<p>User ID: <strong>{$user->ID}</strong></p>";
echo "<p>Display Name: <strong>{$user->display_name}</strong></p>";

// Get a sample question ID
$sample_question = $wpdb->get_row("SELECT question_id, content FROM ptgates_questions ORDER BY RAND() LIMIT 1");
if ($sample_question) {
    echo "<h2>Sample Question for Testing:</h2>";
    echo "<p>Question ID: <strong>{$sample_question->question_id}</strong></p>";
    echo "<p>Content (first 100 chars): " . substr(strip_tags($sample_question->content), 0, 100) . "...</p>";
    
    echo "<h3>Suggested Test SQL Queries:</h3>";
    echo "<pre>";
    echo "-- Bookmark/Review Check:\n";
    echo "SELECT * FROM ptgates_user_states \n";
    echo "WHERE user_id = {$user->ID} AND question_id = {$sample_question->question_id};\n\n";
    
    echo "-- Memo Check:\n";
    echo "SELECT * FROM ptgates_user_memos \n";
    echo "WHERE user_id = {$user->ID} AND question_id = {$sample_question->question_id};\n\n";
    
    echo "-- Flashcard Check:\n";
    echo "SELECT * FROM ptgates_flashcards \n";
    echo "WHERE user_id = {$user->ID} AND source_id = {$sample_question->question_id};\n";
    echo "</pre>";
}
