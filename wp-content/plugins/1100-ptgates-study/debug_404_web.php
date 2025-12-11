<?php
define('WP_USE_THEMES', false);
require_once('wp-load.php');

global $wpdb;

echo "=== Distinct Subjects in ptgates_categories ===\n";
$subjects = $wpdb->get_col("SELECT DISTINCT subject FROM ptgates_categories ORDER BY subject");
if ($subjects) {
    foreach ($subjects as $s) {
        echo "- " . $s . "\n";
    }
} else {
    echo "No subjects found or table error.\n";
}

echo "\n=== Testing LegacyRepo for '해부생리' ===\n";
$args = [
    'subject' => '해부생리',
    'limit' => 5,
    'random' => true,
    'exam_session_min' => 1000
];

// Enable query logging if possible, or just print last query after
$questions = \PTG\Platform\LegacyRepo::get_questions_with_categories($args);
echo "Count: " . count($questions) . "\n";
echo "Last Query: " . $wpdb->last_query . "\n";

echo "\n=== Testing LegacyRepo for '해부생리학' (Exact Match?) ===\n";
$args['subject'] = '해부생리학';
$questions = \PTG\Platform\LegacyRepo::get_questions_with_categories($args);
echo "Count: " . count($questions) . "\n";
echo "Last Query: " . $wpdb->last_query . "\n";
