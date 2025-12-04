<?php
// Load WordPress
require_once 'e:/proj/ptgates.com/wp-load.php';

// Load the class if not already loaded
if (!class_exists('\PTG\Quiz\Subjects')) {
    require_once 'e:/proj/ptgates.com/wp-content/plugins/0000-ptgates-platform/includes/class-subjects.php';
}

use PTG\Quiz\Subjects;

echo "Testing Subjects::get_subject_from_subsubject...\n";
$test_subs = ['해부생리학', '운동학', '의료법', 'NonExistent'];

foreach ($test_subs as $sub) {
    $parent = Subjects::get_subject_from_subsubject($sub);
    echo "Sub: $sub -> Parent: " . ($parent ?? 'NULL') . "\n";
}

echo "\nChecking DB content...\n";
global $wpdb;
$results = $wpdb->get_results("SELECT DISTINCT subject, subject_category FROM ptgates_categories LIMIT 20");
foreach ($results as $row) {
    echo "DB Sub: {$row->subject} -> DB Parent: {$row->subject_category}\n";
}

echo "\nChecking for inconsistencies...\n";
// Check if any sub-subject has multiple parents
$dupes = $wpdb->get_results("
    SELECT subject, COUNT(DISTINCT subject_category) as cnt 
    FROM ptgates_categories 
    WHERE subject_category IS NOT NULL AND subject_category != ''
    GROUP BY subject 
    HAVING cnt > 1
");

if ($dupes) {
    echo "WARNING: Found sub-subjects with multiple parents:\n";
    print_r($dupes);
} else {
    echo "All sub-subjects have unique parents in DB.\n";
}
