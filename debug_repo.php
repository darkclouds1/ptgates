<?php
define('WP_USE_THEMES', false);
require_once('wp-load.php');

$subject = '해부생리학';
$count = \PTG\Platform\LegacyRepo::count_questions_with_categories(['subject' => $subject]);
echo "Subject: $subject, Count: $count\n";

$cat = '물리치료 기초';
$count_cat = \PTG\Platform\LegacyRepo::count_questions_with_categories(['subject_category' => $cat]);
echo "Category: $cat, Count: $count_cat\n";

// Check if Subjects class is loaded and what map it returns
if (class_exists('\PTG\Quiz\Subjects')) {
    echo "Subjects class exists.\n";
    $map = \PTG\Quiz\Subjects::get_map();
    echo "Map keys: " . implode(', ', array_keys($map)) . "\n";
    if (isset($map[1]['subjects']['물리치료 기초'])) {
        echo "물리치료 기초 found in Map.\n";
        print_r($map[1]['subjects']['물리치료 기초']);
    } else {
        echo "물리치료 기초 NOT found in Map.\n";
    }
} else {
    echo "Subjects class NOT found.\n";
}
