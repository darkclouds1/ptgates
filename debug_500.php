<?php
define('WP_USE_THEMES', false);
require_once('wp-load.php');

// Simulate the API call logic
$course_id = urlencode('해부생리');
$limit = 5;
$random = true;
$offset = 0;
$user_id = 1; // Assuming admin user

echo "Simulating request for course: " . urldecode($course_id) . "\n";

try {
    // 1. Check Subjects class
    if ( ! class_exists( '\\PTG\\Quiz\\Subjects' ) ) {
        echo "Loading Subjects class...\n";
        $platform_subjects_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/includes/class-subjects.php';
        if ( file_exists( $platform_subjects_file ) ) {
            require_once $platform_subjects_file;
        } else {
            $quiz_subjects_file = WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php';
            if ( file_exists( $quiz_subjects_file ) ) {
                require_once $quiz_subjects_file;
            }
        }
    }
    
    if (class_exists('\\PTG\\Quiz\\Subjects')) {
        echo "Subjects class loaded.\n";
    } else {
        echo "Subjects class NOT loaded.\n";
    }

    // 2. Simulate logic in get_course_detail (Subject Selection Mode)
    $subject = urldecode($course_id);
    
    // Check Normalizer
    if (class_exists('Normalizer')) {
        echo "Normalizer exists.\n";
        $needle = \Normalizer::normalize($subject, \Normalizer::FORM_C);
    } else {
        echo "Normalizer NOT found.\n";
        $needle = $subject;
    }
    
    echo "Needle: $needle\n";

    // 3. Call LegacyRepo
    echo "Calling LegacyRepo::get_questions_with_categories...\n";
    
    $args = [
        'subject'          => $subject,
        'limit'            => 5,
        'offset'           => 0,
        'exam_session_min' => 1000,
        'random'           => true,
        // 'smart_random_user_id' => $user_id, // Uncomment to test smart random
    ];

    $questions = \PTG\Platform\LegacyRepo::get_questions_with_categories($args);
    
    echo "Questions found: " . count($questions) . "\n";
    if (!empty($questions)) {
        echo "First question ID: " . $questions[0]['question_id'] . "\n";
    }

} catch (Throwable $e) {
    echo "Caught Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
