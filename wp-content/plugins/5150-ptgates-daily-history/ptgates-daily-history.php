<?php
/**
 * Plugin Name: 5150 ptGates Daily History
 * Description: 일별 학습 기록 (Study/Quiz) 히스토리 플러그인
 * Version: 1.0.0
 * Author: ptGates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PTG_DAILY_HISTORY_PATH', plugin_dir_path( __FILE__ ) );
define( 'PTG_DAILY_HISTORY_URL', plugin_dir_url( __FILE__ ) );

// Include API Class
require_once PTG_DAILY_HISTORY_PATH . 'includes/class-api.php';

class PTG_Daily_History {

    public function __construct() {
        add_shortcode( 'ptg_daily_history', [ $this, 'render_shortcode' ] );
        add_action( 'rest_api_init', [ '\PTG\DailyHistory\API', 'register_routes' ] );
    }

    public function render_shortcode() {
        ob_start();
        include PTG_DAILY_HISTORY_PATH . 'templates/daily-history-template.php';
        return ob_get_clean();
    }
}

new PTG_Daily_History();
