<?php
define('ABSPATH', __DIR__ . '/');
require_once('wp-content/plugins/1200-ptgates-quiz/includes/class-subjects.php');

echo "Testing Session=0 (All), Subject='물리치료 중재'\n";

$session = 0;
$subject = '물리치료 중재';

// Simulate the Fallback Logic
$subs = [];
echo "Iterating sessions...\n";
foreach (\PTG\Quiz\Subjects::get_sessions() as $sess) {
    echo "Checking Session $sess...\n";
    $s_subs = \PTG\Quiz\Subjects::get_subsubjects($sess, $subject);
    if (!empty($s_subs)) {
        echo "Found in Session $sess: " . implode(', ', $s_subs) . "\n";
        $subs = array_merge($subs, $s_subs);
    } else {
        echo "Not found in Session $sess\n";
    }
}
$subs = array_values(array_unique($subs));

echo "Result: " . print_r($subs, true) . "\n";
