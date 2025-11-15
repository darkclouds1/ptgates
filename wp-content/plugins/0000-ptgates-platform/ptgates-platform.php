<?php
/**
 * Plugin Name: PTGates Platform
 * Plugin URI: https://ptgates.com
 * Description: PTGates 플랫폼 코어 - 공통 DB 스키마, 권한, 유틸리티, 컴포넌트 제공. 모든 모듈의 필수 의존성.
 * Version: 1.0.1
 * Author: PTGates
 * Author URI: https://ptgates.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ptgates-platform
 * Domain Path: /languages
 * Requires Plugins: 
 * Requires PHP: 8.1
 * Requires at least: 6.0
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('PTG_PLATFORM_VERSION', '1.0.2');
define('PTG_PLATFORM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PTG_PLATFORM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PTG_PLATFORM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * 플러그인 활성화 시 실행
 */
function ptg_platform_activate() {
    require_once PTG_PLATFORM_PLUGIN_DIR . 'includes/class-migration.php';
    PTG\Platform\Migration::run_migrations();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'ptg_platform_activate');

/**
 * 플러그인 비활성화 시 실행
 */
function ptg_platform_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'ptg_platform_deactivate');

/**
 * 플러그인 언인스톨 시 실행
 * 주의: 플랫폼 전용 테이블만 삭제 (기존 3개 테이블 절대 삭제 금지)
 */
function ptg_platform_uninstall() {
    // 사용자 확인 필요하므로 uninstall.php에서 처리
    // 여기서는 실제 삭제하지 않음
}
register_uninstall_hook(__FILE__, 'ptg_platform_uninstall');

/**
 * 플러그인 메인 클래스
 */
class PTG_Platform {
    
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
        $this->load_dependencies();
        
        // 스타일 및 스크립트 등록 (높은 우선순위로 실행)
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 5);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // REST API 네임스페이스 등록 (공통 유틸리티)
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // 핵심 테이블이 없으면 자동으로 마이그레이션 실행
        add_action('init', array($this, 'ensure_database_schema'), 0);
        
        // 관리자 페이지에서 마이그레이션 수동 실행 (디버깅용)
        add_action('admin_init', array($this, 'maybe_run_migration_manually'));
        
        // 디버깅: 플러그인 초기화 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Platform] 플러그인 초기화 완료 - 훅 등록됨');
        }
    }
    
    /**
     * 필수 파일 로드
     */
    private function load_dependencies() {
        require_once PTG_PLATFORM_PLUGIN_DIR . 'includes/class-migration.php';
        require_once PTG_PLATFORM_PLUGIN_DIR . 'includes/class-repo.php';
        require_once PTG_PLATFORM_PLUGIN_DIR . 'includes/class-legacy-repo.php'; // 기존 테이블 접근용
        require_once PTG_PLATFORM_PLUGIN_DIR . 'includes/class-permissions.php';
        require_once PTG_PLATFORM_PLUGIN_DIR . 'includes/class-rest.php';
    }
    
    /**
     * 스타일 및 스크립트 로드 (프론트엔드)
     */
    public function enqueue_scripts() {
        // 디버깅: 스크립트 로드 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Platform] enqueue_scripts 호출됨');
        }
        
        // 플랫폼 공통 CSS
        wp_enqueue_style(
            'ptg-platform-style',
            PTG_PLATFORM_PLUGIN_URL . 'assets/css/platform.css',
            array(),
            PTG_PLATFORM_VERSION
        );
        
        // 공통 퀴즈 UI CSS
        wp_enqueue_style(
            'ptg-quiz-ui-style',
            PTG_PLATFORM_PLUGIN_URL . 'assets/css/quiz-ui.css',
            array('ptg-platform-style'),
            PTG_PLATFORM_VERSION
        );
        
        // 플랫폼 공통 JavaScript
        wp_enqueue_script(
            'ptg-platform-script',
            PTG_PLATFORM_PLUGIN_URL . 'assets/js/platform.js',
            array('jquery'),
            PTG_PLATFORM_VERSION,
            true
        );
        
        // 공통 퀴즈 UI JavaScript (항상 로드)
        // URL 생성 및 검증
        $quiz_ui_js_url = PTG_PLATFORM_PLUGIN_URL . 'assets/js/quiz-ui.js';
        $quiz_ui_js_path = PTG_PLATFORM_PLUGIN_DIR . 'assets/js/quiz-ui.js';
        
        // 파일 존재 확인
        if (!file_exists($quiz_ui_js_path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Platform] ⚠️ quiz-ui.js 파일이 존재하지 않음: ' . $quiz_ui_js_path);
            }
        } else {
            wp_enqueue_script(
                'ptg-quiz-ui-script',
                $quiz_ui_js_url,
                array('ptg-platform-script'),
                PTG_PLATFORM_VERSION,
                true
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Platform] quiz-ui.js 등록 완료: ' . $quiz_ui_js_url);
            }
        }
        
        // REST API 엔드포인트 정보를 JS에 전달
        wp_localize_script('ptg-platform-script', 'ptgPlatform', array(
            'restUrl' => rest_url('/'),  // WordPress REST API 기본 URL
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'timezone' => wp_timezone_string() // Asia/Seoul
        ));
        
        // 디버깅: 스크립트 등록 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Platform] 스크립트 등록 완료: ptg-platform-script, ptg-quiz-ui-script');
            error_log('[PTG Platform] quiz-ui.js URL: ' . PTG_PLATFORM_PLUGIN_URL . 'assets/js/quiz-ui.js');
        }
    }
    
    /**
     * 관리자 스타일 및 스크립트 로드
     */
    public function enqueue_admin_scripts() {
        wp_enqueue_style(
            'ptg-platform-admin-style',
            PTG_PLATFORM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PTG_PLATFORM_VERSION
        );
    }
    
    /**
     * REST API 라우트 등록 (공통 유틸리티용)
     */
    public function register_rest_routes() {
        // 플랫폼 코어는 공통 유틸리티만 제공
        // 각 모듈이 자체 REST API를 가짐
    }

    /**
     * 관리자 페이지에서 마이그레이션 수동 실행 (디버깅용)
     * URL 파라미터: ?ptg_run_migration=1
     */
    public function maybe_run_migration_manually() {
        // 관리자 권한 확인
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // URL 파라미터 확인
        if (!isset($_GET['ptg_run_migration']) || $_GET['ptg_run_migration'] !== '1') {
            return;
        }
        
        // nonce 확인 (보안)
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ptg_run_migration')) {
            wp_die('보안 검증 실패', '오류', array('response' => 403));
        }
        
        // 마이그레이션 실행
        \PTG\Platform\Migration::run_migrations();
        
        // 리다이렉트 (파라미터 제거)
        wp_redirect(remove_query_arg(array('ptg_run_migration', '_wpnonce')));
        exit;
    }
    
    /**
     * 필수 데이터베이스 테이블이 없으면 마이그레이션을 다시 실행합니다.
     */
    public function ensure_database_schema() {
        global $wpdb;

        $required_tables = array(
            $wpdb->prefix . 'ptgates_user_states',
            $wpdb->prefix . 'ptgates_user_notes',
            $wpdb->prefix . 'ptgates_user_drawings',
        );

        $missing = false;
        foreach ($required_tables as $table_name) {
            $existing = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
            if ($existing !== $table_name) {
                $missing = true;
                break;
            }
        }

        if ($missing) {
            // 마이그레이션 실행
            \PTG\Platform\Migration::run_migrations();

            // 재확인 및 결과 로깅
            foreach ($required_tables as $table_name) {
                $existing = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
                if ($existing === $table_name) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[PTG Platform] ✓ 테이블 생성 성공: ' . $table_name);
                    }
                } else {
                    error_log('[PTG Platform] ⚠️ 필수 테이블이 생성되지 않음: ' . $table_name);
                    if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                        error_log('[PTG Platform] SQL 오류: ' . $wpdb->last_error);
                        error_log('[PTG Platform] 마지막 쿼리: ' . $wpdb->last_query);
                    }
                }
            }
        }
    }
}

// 플러그인 초기화
PTG_Platform::get_instance();

/**
 * 플랫폼 코어 활성화 확인 헬퍼 함수
 * 다른 모듈에서 사용 가능
 */
function ptg_platform_is_active() {
    return class_exists('PTG_Platform');
}

/**
 * 공통 퀴즈 UI 템플릿 로드
 * 
 * @param array $args 템플릿 인자
 */
function ptg_quiz_ui_template($args = array()) {
    $template_path = PTG_PLATFORM_PLUGIN_DIR . 'templates/quiz-ui.php';
    if (file_exists($template_path)) {
        include $template_path;
    } else {
        error_log('[PTG Platform] 퀴즈 UI 템플릿을 찾을 수 없음: ' . $template_path);
    }
}

