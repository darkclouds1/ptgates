<?php
define('WP_USE_THEMES', false);
require_once 'e:\proj\ptgates.com\wp-load.php';

global $wpdb;

echo "Checking ptgates_exam_course_config:\n";
$courses = $wpdb->get_results("SELECT * FROM ptgates_exam_course_config");
print_r($courses);

echo "\nChecking ptgates_subject_config (exam_course):\n";
$subject_courses = $wpdb->get_col("SELECT DISTINCT exam_course FROM ptgates_subject_config");
print_r($subject_courses);

echo "\nChecking PTG\Quiz\Subjects::get_sessions():\n";
if (class_exists('PTG\Quiz\Subjects')) {
    print_r(\PTG\Quiz\Subjects::get_sessions());
} else {
    echo "Class PTG\Quiz\Subjects not found.\n";
}
