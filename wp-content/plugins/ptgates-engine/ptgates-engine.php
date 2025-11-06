<?php
/**
 * Plugin Name: PTGates Learning Engine
 * Plugin URI: https://ptgates.com
 * Description: 물리치료사 국가고시 문제 학습 시스템 (REST API 기반). 숏코드: [ptgates_quiz] 또는 [ptgates_quiz year="2024" subject="해부학" limit="20"]
 * Version: 1.0.20
 * Author: PTGates
 * Author URI: https://ptgates.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ptgates-engine
 * Domain Path: /languages
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('PTGATES_ENGINE_VERSION', '1.0.20');
define('PTGATES_ENGINE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PTGATES_ENGINE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 플러그인 활성화 시 실행
 */
function ptgates_engine_activate() {
    require_once PTGATES_ENGINE_PLUGIN_DIR . 'includes/class-ptg-db.php';
    PTG_DB::create_tables();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'ptgates_engine_activate');

/**
 * 플러그인 비활성화 시 실행
 */
function ptgates_engine_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'ptgates_engine_deactivate');

/**
 * 플러그인 메인 클래스
 */
class PTGates_Engine {
    
    /**
     * 싱글톤 인스턴스
     */
    private static $instance = null;
    
    /**
     * 싱글톤 인스턴스 반환
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 생성자
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * 훅 초기화
     */
    private function init_hooks() {
        // 필수 파일 포함
        require_once PTGATES_ENGINE_PLUGIN_DIR . 'includes/class-ptg-db.php';
        require_once PTGATES_ENGINE_PLUGIN_DIR . 'includes/class-ptg-api.php';
        require_once PTGATES_ENGINE_PLUGIN_DIR . 'includes/class-ptg-logger.php';
        
        // REST API 등록
        add_action('rest_api_init', array('PTG_API', 'register_routes'));
        
        // Shortcode 등록
        add_shortcode('ptgates_quiz', array($this, 'render_quiz_shortcode'));
        
        // 스타일 및 스크립트 등록
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * 스타일 및 스크립트 로드
     */
    public function enqueue_scripts() {
        // CSS
        wp_enqueue_style(
            'ptgates-engine-style',
            PTGATES_ENGINE_PLUGIN_URL . 'assets/css/style.css',
            array(),
            PTGATES_ENGINE_VERSION
        );
        
        // JavaScript (모듈 방식)
        wp_enqueue_script(
            'ptgates-engine-main',
            PTGATES_ENGINE_PLUGIN_URL . 'assets/js/ptg-main.js',
            array(),
            PTGATES_ENGINE_VERSION,
            true
        );
        
        wp_enqueue_script(
            'ptgates-engine-ui',
            PTGATES_ENGINE_PLUGIN_URL . 'assets/js/ptg-ui.js',
            array(),
            PTGATES_ENGINE_VERSION,
            true
        );
        
        wp_enqueue_script(
            'ptgates-engine-timer',
            PTGATES_ENGINE_PLUGIN_URL . 'assets/js/ptg-timer.js',
            array(),
            PTGATES_ENGINE_VERSION,
            true
        );
        
        // REST API 엔드포인트 정보를 JS에 전달
        wp_localize_script('ptgates-engine-main', 'ptgatesAPI', array(
            'restUrl' => rest_url('ptgates/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id()
        ));
    }
    
    /**
     * 퀴즈 Shortcode 렌더링
     */
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'year' => '',
            'subject' => '',
            'limit' => '10'
        ), $atts, 'ptgates_quiz');
        
        ob_start();
        include PTGATES_ENGINE_PLUGIN_DIR . 'templates/quiz-template.php';
        return ob_get_clean();
    }
}

// 플러그인 초기화
PTGates_Engine::get_instance();
