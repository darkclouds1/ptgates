<?php
/**
 * Plugin Name: 5180-ptgates-mock-results (PTGates Mock Exam Results)
 * Description: 모의고사 결과 저장 및 분석 플러그인.
 * Version: 1.0.0
 * Author: PTGates
 * Requires Plugins: 0000-ptgates-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PTG_Mock_Results {

    private static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook( __FILE__, [ $this, 'install_tables' ] );
        add_action( 'plugins_loaded', [ $this, 'check_version' ] );
        add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
        add_action( 'init', [ $this, 'register_shortcode' ] );

        // AJAX for Result Summary
        add_action( 'wp_ajax_ptg_mock_result_summary', [ $this, 'ajax_get_result_summary' ] );
        // AJAX for Delete
        add_action( 'wp_ajax_ptg_mock_delete_result', [ $this, 'ajax_delete_result' ] );
    }

    public function check_version() {
        if ( get_option( 'ptg_mock_results_version' ) !== '1.0.4' ) {
            $this->install_tables();
            update_option( 'ptg_mock_results_version', '1.0.4' );
        }
    }

    public function ajax_get_result_summary() {
        if ( ! is_user_logged_in() ) {
            wp_die( '로그인이 필요합니다.' );
        }

        $result_id = isset($_POST['result_id']) ? intval($_POST['result_id']) : 0;
        if ( $result_id <= 0 ) {
            wp_die( '잘못된 요청입니다.' );
        }

        // Fetch Data
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';
        if ( class_exists( '\PTG\Mock\Results\API' ) ) {
            $data = \PTG\Mock\Results\API::get_mock_result_full_data( $result_id );
            
            if ( ! $data || empty($data['history']) ) {
                echo '<p>결과를 찾을 수 없습니다.</p>';
            } else {
                $history = $data['history'];
                $subjects = $data['subjects'];
                // Include Template Part
                include plugin_dir_path( __FILE__ ) . 'templates/part-result-summary.php';
            }
        }
        wp_die();
    }

    public function ajax_delete_result() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( '로그인이 필요합니다.', 401 );
        }

        $result_id = isset($_POST['result_id']) ? intval($_POST['result_id']) : 0;
        if ( $result_id <= 0 ) {
            wp_send_json_error( '잘못된 요청입니다.', 400 );
        }

        require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';
        if ( class_exists( '\PTG\Mock\Results\API' ) ) {
            $deleted = \PTG\Mock\Results\API::delete_mock_history( $result_id, get_current_user_id() );
            if ( is_wp_error( $deleted ) ) {
                wp_send_json_error( $deleted->get_error_message(), 500 );
            } else {
                wp_send_json_success( '삭제되었습니다.' );
            }
        }
        wp_die();
    }

    public function install_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 1. 모의고사 응시 이력 (History)
        $table_history = 'ptgates_mock_history';
        $sql_history = "CREATE TABLE $table_history (
            history_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            session_code int(11) NOT NULL COMMENT '회차 코드 (예: 1001)',
            course_no varchar(10) DEFAULT NULL COMMENT '교시 (1교시, 2교시)',
            exam_type varchar(20) NOT NULL DEFAULT 'mock' COMMENT 'mock | actual',
            start_time datetime DEFAULT CURRENT_TIMESTAMP,
            end_time datetime DEFAULT NULL,
            total_score int(11) DEFAULT 0,
            is_pass tinyint(1) DEFAULT 0 COMMENT '합격 여부',
            answers_json longtext DEFAULT NULL COMMENT '상세 답안 JSON',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (history_id),
            KEY idx_user_session (user_id, session_code)
        ) $charset_collate;";

        // 2. 모의고사 상세 결과 (Results - 과목별)
        $table_results = 'ptgates_mock_results'; // 과목별 점수 저장? 아니면 문항별? 
        // 기획서 내용: "과목별 점수 및 과락 여부"
        
        $sql_results = "CREATE TABLE $table_results (
            result_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            history_id bigint(20) unsigned NOT NULL,
            subject_name varchar(100) NOT NULL,
            score int(11) DEFAULT 0,
            question_count int(11) DEFAULT 0,
            correct_count int(11) DEFAULT 0,
            is_fail tinyint(1) DEFAULT 0 COMMENT '과락 여부',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (result_id),
            KEY idx_history (history_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_history );
        dbDelta( $sql_results );
    }

    public function init_rest_api() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';
        if ( class_exists( '\PTG\Mock\Results\API' ) ) {
            \PTG\Mock\Results\API::register_routes();
        }
    }

    public function register_shortcode() {
        add_shortcode( 'ptg_mock_results', [ $this, 'render_shortcode' ] );
    }

    public function render_shortcode( $atts ) {
        $result_id = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;
        
        ob_start();
        if ( $result_id > 0 ) {
            // API 클래스 로드 (프론트엔드에서 헬퍼로 사용)
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';
            
            // 상세 보기
            include plugin_dir_path( __FILE__ ) . 'templates/mock-results-detail.php';
        } else {
            // 목록 보기
            include plugin_dir_path( __FILE__ ) . 'templates/mock-results-list.php';
        }
        return ob_get_clean();
    }
}

PTG_Mock_Results::get_instance();
