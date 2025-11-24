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

        // 로그인 시 멤버십 체크 및 생성
        add_action('wp_login', array($this, 'check_and_create_member_on_login'), 10, 2);

        // Trial 만료 체크 크론 이벤트 등록
        if (!wp_next_scheduled('ptg_daily_trial_check')) {
            wp_schedule_event(time(), 'daily', 'ptg_daily_trial_check');
        }
        add_action('ptg_daily_trial_check', array($this, 'check_trial_expiration'));
        
        // Ultimate Member 커스텀 로그인/로그아웃 페이지 적용 (인증 UI 일관성)
        add_filter('login_url', array($this, 'custom_login_url'), 10, 2);
        add_filter('logout_url', array($this, 'custom_logout_url'), 10, 2);
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
        
        // 공통 문제 보기 컴포넌트 CSS
        wp_enqueue_style(
            'ptg-question-viewer-style',
            PTG_PLATFORM_PLUGIN_URL . 'assets/css/question-viewer.css',
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
                if (function_exists('ptg_error_log_kst')) {
                    ptg_error_log_kst('[PTG Platform] ⚠️ quiz-ui.js 파일이 존재하지 않음: ' . $quiz_ui_js_path);
                } else {
                    error_log('[PTG Platform] ⚠️ quiz-ui.js 파일이 존재하지 않음: ' . $quiz_ui_js_path);
                }
            }
        } else {
            wp_enqueue_script(
                'ptg-quiz-ui-script',
                $quiz_ui_js_url,
                array('ptg-platform-script'),
                PTG_PLATFORM_VERSION,
                true
            );
        }
        
        // 공통 문제 보기 컴포넌트 JavaScript
        $question_viewer_js_url = PTG_PLATFORM_PLUGIN_URL . 'assets/js/question-viewer.js';
        $question_viewer_js_path = PTG_PLATFORM_PLUGIN_DIR . 'assets/js/question-viewer.js';
        
        if (file_exists($question_viewer_js_path)) {
            wp_enqueue_script(
                'ptg-question-viewer-script',
                $question_viewer_js_url,
                array('ptg-platform-script', 'jquery'),
                PTG_PLATFORM_VERSION,
                true
            );
        }
        
        // REST API 엔드포인트 정보를 JS에 전달
        wp_localize_script('ptg-platform-script', 'ptgPlatform', array(
            'restUrl' => rest_url('/'),  // WordPress REST API 기본 URL
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'timezone' => wp_timezone_string() // Asia/Seoul
        ));
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
            'ptgates_user_states',
            'ptgates_user_drawings',
            'ptgates_user_member',
            'ptgates_billing_history',
            'ptgates_organization',
            'ptgates_org_member_link',
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
                    // 정상 케이스는 로그를 남기지 않음
                } else {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst('[PTG Platform] ⚠️ 필수 테이블이 생성되지 않음: ' . $table_name);
                    } else {
                        error_log('[PTG Platform] ⚠️ 필수 테이블이 생성되지 않음: ' . $table_name);
                    }
                    if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                        if (function_exists('ptg_error_log_kst')) {
                            ptg_error_log_kst('[PTG Platform] SQL 오류: ' . $wpdb->last_error);
                            ptg_error_log_kst('[PTG Platform] 마지막 쿼리: ' . $wpdb->last_query);
                        } else {
                            error_log('[PTG Platform] SQL 오류: ' . $wpdb->last_error);
                            error_log('[PTG Platform] 마지막 쿼리: ' . $wpdb->last_query);
                        }
                    }
                }
            }
        }
    }

    /**
     * 로그인 시 멤버십 레코드 확인 및 생성
     * 
     * @param string $user_login 사용자 로그인명
     * @param WP_User $user 사용자 객체
     */
    public function check_and_create_member_on_login($user_login, $user) {
        if (!isset($user->ID)) {
            return;
        }

        $user_id = $user->ID;

        // Repo 클래스가 로드되었는지 확인
        if (!class_exists('\PTG\Platform\Repo')) {
            return;
        }

        // 레코드 존재 여부 확인
        $existing = \PTG\Platform\Repo::find_one('ptgates_user_member', array('user_id' => $user_id));

        if (!$existing) {
            $defaults = self::get_default_limits('trial');
            
            // 레코드가 없으면 생성 (기본값: trial)
            $result = \PTG\Platform\Repo::insert(
                'ptgates_user_member',
                array(
                    'user_id' => $user_id,
                    'membership_source' => 'individual',
                    'member_grade' => 'trial', // 기본 등급
                    'billing_status' => 'active',
                    'billing_expiry_date' => date('Y-m-d H:i:s', strtotime('+7 days')), // 7일 체험
                    'exam_count_used' => 0,
                    'exam_count_total' => $defaults['exam_count_total'],
                    'study_count_used' => 0,
                    'study_count_total' => $defaults['study_count_total'],
                    'is_active' => 1
                )
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                if ($result) {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst("[PTG Platform] 사용자(ID: {$user_id})의 멤버십 레코드가 자동 생성되었습니다.");
                    } else {
                        error_log("[PTG Platform] 사용자(ID: {$user_id})의 멤버십 레코드가 자동 생성되었습니다.");
                    }
                } else {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst("[PTG Platform] 사용자(ID: {$user_id})의 멤버십 레코드 생성 실패.");
                    } else {
                        error_log("[PTG Platform] 사용자(ID: {$user_id})의 멤버십 레코드 생성 실패.");
                    }
                }
            }
        } else {
            // 이미 존재하면 마지막 로그인 시간 업데이트 (선택 사항)
            \PTG\Platform\Repo::update(
                'ptgates_user_member',
                array('last_login' => current_time('mysql')),
                array('user_id' => $user_id)
            );
        }
    }

    /**
     * Trial 만료 체크 및 Basic 전환 (Cron Job)
     */
    public function check_trial_expiration() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ptgates_user_member';
        
        // 만료된 Trial 사용자 찾기
        // member_grade = 'trial' AND billing_expiry_date < NOW()
        $sql = "UPDATE $table_name 
                SET member_grade = 'basic' 
                WHERE member_grade = 'trial' 
                AND billing_expiry_date < NOW() 
                AND billing_expiry_date IS NOT NULL";
                
        $result = $wpdb->query($sql);
        
        // 정상 케이스는 로그를 남기지 않음
    }

    /**
     * 커스텀 로그인 URL (Ultimate Member 페이지 사용)
     * 
     * @param string $login_url 기본 로그인 URL
     * @param string $redirect 리다이렉트 URL
     * @return string 커스텀 로그인 URL
     */
    public function custom_login_url($login_url, $redirect = '') {
        // Ultimate Member 로그인 페이지 URL
        $custom_login_page = home_url('/login/');
        
        // 리다이렉트 URL이 있으면 쿼리 파라미터로 추가
        if (!empty($redirect)) {
            $custom_login_page = add_query_arg('redirect_to', urlencode($redirect), $custom_login_page);
        }
        
        return $custom_login_page;
    }

    /**
     * 커스텀 로그아웃 URL (Ultimate Member 페이지 사용)
     * 
     * @param string $logout_url 기본 로그아웃 URL
     * @param string $redirect 리다이렉트 URL
     * @return string 커스텀 로그아웃 URL
     */
    public function custom_logout_url($logout_url, $redirect = '') {
        // Ultimate Member 로그아웃 처리 후 홈으로
        $custom_logout_url = home_url('/');
        
        if (!empty($redirect)) {
            $custom_logout_url = add_query_arg('redirect_to', urlencode($redirect), $custom_logout_url);
        }
        
        return $custom_logout_url;
    }

    /**
     * 등급별 기본 한도 값 반환
     * 
     * @param string $grade 등급 (trial, basic, premium)
     * @return array 한도 설정 (exam_count_total, study_count_total)
     */
    public static function get_default_limits($grade) {
        // 나중에 설정 페이지에서 가져오도록 개선 가능
        $defaults = array(
            'trial' => array(
                'exam_count_total' => 5, // 체험판 5회
                'study_count_total' => 100
            ),
            'basic' => array(
                'exam_count_total' => 1, // 누적 1회
                'study_count_total' => 10
            ),
            'premium' => array(
                'exam_count_total' => -1, // 무제한
                'study_count_total' => -1
            ),
            'pt_admin' => array(
                'exam_count_total' => -1,
                'study_count_total' => -1
            )
        );
        
        return isset($defaults[$grade]) ? $defaults[$grade] : $defaults['basic'];
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
        if (function_exists('ptg_error_log_kst')) {
            ptg_error_log_kst('[PTG Platform] 퀴즈 UI 템플릿을 찾을 수 없음: ' . $template_path);
        } else {
            error_log('[PTG Platform] 퀴즈 UI 템플릿을 찾을 수 없음: ' . $template_path);
        }
    }
}
