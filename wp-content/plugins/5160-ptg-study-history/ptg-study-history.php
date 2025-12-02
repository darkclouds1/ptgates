<?php
/**
 * Plugin Name: 5160 ptGates Study History
 * Description: 과목별 학습 기록(Study/Quiz)을 표시하는 플러그인입니다.
 * Version: 1.0.0
 * Author: Antigravity
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PTG_STUDY_HISTORY_VERSION', '1.0.0');
define('PTG_STUDY_HISTORY_PATH', plugin_dir_path(__FILE__));
define('PTG_STUDY_HISTORY_URL', plugin_dir_url(__FILE__));

require_once PTG_STUDY_HISTORY_PATH . 'includes/class-api.php';

use PTG\StudyHistory\API;

// API 등록
add_action('rest_api_init', [API::class, 'register_routes']);

// 숏코드 등록
add_shortcode('ptg_study_history', function() {
    ob_start();
    include PTG_STUDY_HISTORY_PATH . 'templates/study-history-template.php';
    return ob_get_clean();
});
