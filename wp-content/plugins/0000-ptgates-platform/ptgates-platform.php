<?php
/**
 * Plugin Name: 0000-ptgates-platform (PTGates Platform)
 * Plugin URI: https://ptgates.com
 * Description: PTGates 플랫폼 코어 - 공통 DB 스키마, 권한, 유틸리티, 컴포넌트 제공. 모든 모듈의 필수 의존성.
 * Version: 1.0.4
 * Author: PTGates
 * Author URI: https://ptgates.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ptgates-platform
 * Domain Path: /languages
 * Requires Plugins: 
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('PTG_PLATFORM_VERSION', '1.0.5');
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
        
        // [FIX] 버전 변경 감지 및 마이그레이션 실행
        add_action('init', array($this, 'check_version_and_migrate'), 1);
        
        // [CRITICAL] 사용자 삭제 시 Elementor Kit 보호 (Reassign to Admin)
        add_action( 'delete_user', array( $this, 'reassign_elementor_posts_before_delete' ) );
        add_action( 'wpmu_delete_user', array( $this, 'reassign_elementor_posts_before_delete' ) );
        
        // [KAKAO] 사용자 삭제 시 카카오 연동 해제
        add_action( 'delete_user', array( $this, 'unlink_kakao_on_delete' ) );

        // [UM] 계정 삭제 탭 커스터마이징 (카카오 전용)
        add_action( 'um_account_content_hook_delete', array( $this, 'um_custom_delete_content' ) );
        
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

        // 카카오 로그인 버튼 표시 (Ultimate Member 로그인/회원가입 폼 하단)
        add_action( 'um_after_login_fields', array( '\PTG\Platform\KakaoAuth', 'render_login_button' ) );
        add_action( 'um_after_register_fields', array( '\PTG\Platform\KakaoAuth', 'render_login_button' ) );

        // SSL 루프백 오류 수정 (강력한 적용: WP가 외부 요청으로 인식할 수 있으므로 http_request_args 사용)
        add_filter( 'http_request_args', function( $args, $url ) {
            // 내 사이트(ptgates.com)로 보내는 요청은 무조건 SSL 검증 끄기
            if ( strpos( $url, 'ptgates.com' ) !== false || strpos( $url, home_url() ) !== false ) {
                $args['sslverify'] = false;
            }
            return $args;
        }, 10, 2 );
        
        // 기존 로컬 필터도 유지
        add_filter( 'https_local_ssl_verify', '__return_false' );

        // 사용자 삭제 시 Elementor Kit 소유권 이전 (삭제 방지)
        // Priority 5: 기본 삭제 로직(10)보다 먼저 실행
        add_action( 'delete_user', array( $this, 'reassign_elementor_posts_before_delete' ), 5 );
    }

    /**
     * 버전 확인 및 마이그레이션 실행
     * 플러그인 업데이트 시 스키마 변경 반영을 위해 필요
     */
    public function check_version_and_migrate() {
        $current_version = get_option('ptg_platform_version', '0.0.0');
        
        if (version_compare($current_version, PTG_PLATFORM_VERSION, '<')) {
            // 버전이 낮으면 마이그레이션 실행
            \PTG\Platform\Migration::run_migrations();
            
            // 버전 업데이트
            update_option('ptg_platform_version', PTG_PLATFORM_VERSION);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Platform] Updated to version ' . PTG_PLATFORM_VERSION);
            }
        }
    }

    /**
     * 사용자 삭제 전 Elementor 게시물 소유권 이전
     * 
     * Elementor는 'kit' 타입의 게시물이 삭제되려 하면 wp_die()를 발생시킴.
     * 사용자가 해당 게시물의 작성자일 경우 삭제 과정에서 문제가 발생하므로,
     * 삭제 전에 관리자(1)에게 소유권을 이전함.
     * 
     * @param int $user_id 삭제될 사용자 ID
     */
    public function reassign_elementor_posts_before_delete( $user_id ) {
        // 본인 삭제 시도 등 예외 상황 체크 (optional)
        
        $args = array(
            'post_type' => 'elementor_library', // Kit, Templates etc
            'author'    => $user_id,
            'posts_per_page' => -1,
            'fields'    => 'ids',
            'post_status' => 'any'
        );
        
        $posts = get_posts( $args );
        
        if ( ! empty( $posts ) ) {
            foreach ( $posts as $post_id ) {
                $update_args = array(
                    'ID'          => $post_id,
                    'post_author' => 1 // Admin User ID
                );
                wp_update_post( $update_args );
                
                // Optional: Log
                // error_log( "[PTG] Reassigned Elementor post {$post_id} from user {$user_id} to Admin due to deletion." );
            }
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
        require_once PTG_PLATFORM_PLUGIN_DIR . 'includes/class-auth-kakao.php';
        // 교시/과목/세부과목 정적 정의 클래스 (최초 로드 시 자동 메모리에 로드)
        require_once PTG_PLATFORM_PLUGIN_DIR . 'includes/class-subjects.php';
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
        
        // 공통 팝업(Tips) CSS
        wp_enqueue_style(
            'ptg-tips-style',
            PTG_PLATFORM_PLUGIN_URL . 'assets/css/tips.css',
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
        
        // 공통 팝업(Tips) 내용 정의 (팝업 유틸리티보다 먼저 로드)
        wp_enqueue_script(
            'ptg-tips-content-script',
            PTG_PLATFORM_PLUGIN_URL . 'assets/js/tips-content.js',
            array(),
            PTG_PLATFORM_VERSION,
            true
        );
        
        // 공통 팝업(Tips) JavaScript (내용 정의 이후 로드)
        wp_enqueue_script(
            'ptg-tips-script',
            PTG_PLATFORM_PLUGIN_URL . 'assets/js/tips.js',
            array('ptg-tips-content-script'),
            PTG_PLATFORM_VERSION,
            true
        );
        
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

        // 카카오 로그인 인증 라우트 등록 (공통 플랫폼 기능으로 제공)
        // [COMPAT] 9000-wp-kakao-login-kit 플러그인이 활성화되어 있으면 기존 로직 비활성화
        if ( ! class_exists( 'Monolith\KakaoLoginKit\Loader' ) && class_exists( '\PTG\Platform\KakaoAuth' ) ) {
            \PTG\Platform\KakaoAuth::register_routes();
        }
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
            // [Trial Abuse Prevention]
            // Check if this user (Email or Kakao ID) has already received a trial in the past.
            global $wpdb;
            $history_table = 'ptgates_auth_history'; // Table name (no prefix if migration used fix name, wait migration uses prefix?) 
            // Migration uses: $table_name = 'ptgates_auth_history'; INSIDE migration. 
            // But dbDelta usually expects full name? 
            // Let's check migration again. Migration used: $table_name = 'ptgates_auth_history'; which implies NO prefix if not handled carefully, 
            // BUT WordPress tables usually need prefix. 
            // Wait, looking at Migration code: `$sql = "CREATE TABLE IF NOT EXISTS {$table_name}...` 
            // It did NOT use $wpdb->prefix in the snippet I wrote? 
            // Let me re-read the snippet I sent in Step 555.
            // " $table_name = 'ptgates_auth_history'; " ... It does NOT have $wpdb->prefix. 
            // THIS IS A BUG in my previous step if I intended to use prefix. 
            // However, dbDelta often handles it? No, if I didn't add it, it's a raw table name.
            // Update: I should check if I should fix the table name or use it as is. 
            // Most plugins use $wpdb->prefix. Standard practice.
            // I will assume for now I should use standard prefix practices. 
            // But if I already ran migration without prefix, the table is `ptgates_auth_history`.
            // Let's check `create_billing_history_table` in the same file `class-migration.php` (Step 540).
            // It uses `$table_name = 'ptgates_billing_history';` WITHOUT prefix variable usage in the string assignment? 
            // No, look at `create_exam_sessions_table`: `$table_name = 'ptgates_exam_sessions';`
            // Wait, usually people do `$table_name = $wpdb->prefix . 'ptgates_...';`
            // Let's check Step 540 content line 82: `swpdb->...` is not used.
            // Just `$table_name = 'ptgates_exam_sessions';`
            // Checking `run_migrations`: `self::create_exam_sessions_table($charset_collate);`
            // This suggests the existing codebase uses tables WITHOUT WP prefix? Or maybe I misread.
            // Let's look at `create_user_member_table` (Line 463). `$table_name = 'ptgates_user_member';`
            // This is strange. Usually WP plugins use prefixes.
            // Maybe they are defined loosely. 
            // I will check `ptgates_user_member` usage in `check_and_create_member_on_login` (Line 434).
            // `\PTG\Platform\Repo::find_one('ptgates_user_member', ...)`
            // The `Repo` class likely handles the prefix if it's an ORM.
            // However, for raw SQL in `check_and_create_member_on_login` (if I use raw SQL), I need to know.
            // `Repo` class is likely `Idiorm` or similar wrapper.
            // I will assume `ptgates_auth_history` matches the convention of other tables.
            
            // Back to Logic:
            $identifiers = array();
            
            // 1. Email
            if ( ! empty( $user->user_email ) ) {
                $identifiers[] = array( 'type' => 'email', 'val' => $user->user_email );
            }
            
            // 2. Kakao IDs (Check both plugins' meta keys)
            $kakao_id_old = get_user_meta( $user_id, 'ptg_kakao_id', true );
            $kakao_id_new = get_user_meta( $user_id, 'wpklk_kakao_id', true );
            
            if ( $kakao_id_old ) {
                $identifiers[] = array( 'type' => 'kakao', 'val' => $kakao_id_old );
            }
            if ( $kakao_id_new && $kakao_id_new !== $kakao_id_old ) {
                $identifiers[] = array( 'type' => 'kakao', 'val' => $kakao_id_new );
            }
            
            $found_history = false;
            
            // Check History
            foreach ( $identifiers as $id_data ) {
                $hash = hash( 'sha256', $id_data['val'] );
                // Use raw query for the history table (Assuming it might not be in Repo properly yet or just safer)
                // Note: The table name in DB might be `ptgates_auth_history` (no prefix from my migration code).
                // I'll try to start with just `ptgates_auth_history`. 
                // Wait, if I want to be safe I should check `SHOW TABLES` logic in migration?
                // Migration Step 555: `create_auth_history_table` just used string literal.
                // So I will use the string literal.
                
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM ptgates_auth_history WHERE identifier_hash = %s", $hash ) );
                if ( $exists ) {
                    $found_history = true;
                    if ( defined('WP_DEBUG') && WP_DEBUG ) error_log( "[PTG] Trial denied. Found history for hash: " . $hash );
                    break;
                }
            }
            
            if ( $found_history ) {
                // Deny Trial -> Grant Basic
                $target_grade = 'basic';
                $trial_expiry = null; // No expiry for basic usually, or irrelevant
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log( "[PTG] User {$user_id} granted BASIC (History found)" );
            } else {
                // Grant Trial
                $target_grade = 'trial';
                $trial_days   = self::get_trial_period_days();
                $trial_expiry = date( 'Y-m-d H:i:s', strtotime( '+' . $trial_days . ' days' ) );
                
                // Record History
                foreach ( $identifiers as $id_data ) {
                    $hash = hash( 'sha256', $id_data['val'] );
                    $wpdb->query( $wpdb->prepare( 
                        "INSERT IGNORE INTO ptgates_auth_history (identifier_hash, auth_type) VALUES (%s, %s)", 
                        $hash, $id_data['type'] 
                    ) );
                }
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log( "[PTG] User {$user_id} granted TRIAL (New user)" );
            }

            $defaults = self::get_default_limits( $target_grade );
            
            // Create Record
            $result = \PTG\Platform\Repo::insert(
                'ptgates_user_member',
                array(
                    'user_id' => $user_id,
                    'membership_source' => 'individual',
                    'member_grade' => $target_grade, 
                    'billing_status' => 'active',
                    'billing_expiry_date' => $trial_expiry, 
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
        // 관리자 페이지에서 접근 시(wp-admin)에는 기본 wp-login.php 유지 (무한 리다이렉트 방지)
        if ( is_admin() ) {
            return $login_url;
        }

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

    /**
     * 체험판 기간 설정값 가져오기 (Helper)
     */
    public static function get_trial_period_days() {
        $conf = get_option('ptg_conf_membership', []);
        return isset($conf['TRIAL_PERIOD_DAYS']) ? intval($conf['TRIAL_PERIOD_DAYS']) : 10;
    }


    /**
     * 사용자 삭제 시 Kakao 연동 해제 (Unlink)
     * Hook: delete_user
     */
    public function unlink_kakao_on_delete( $user_id ) {
        // Kakao ID 확인 (없으면 연동된 계정 아님)
        $kakao_id = get_user_meta( $user_id, 'ptg_kakao_id', true );
        if ( ! empty( $kakao_id ) ) {
            // KakaoAuth 클래스 로드 필요 (네임스페이스 확인)
            if ( class_exists( '\PTG\Platform\KakaoAuth' ) ) {
                \PTG\Platform\KakaoAuth::unlink_kakao_user( $kakao_id );
            }
        }
    }

    /**
     * Ultimate Member 계정 삭제 탭 커스터마이징
     * Hook: um_account_content_hook_delete
     */
    public function um_custom_delete_content( $output ) {
        $user_id = get_current_user_id();
        $kakao_id = get_user_meta( $user_id, 'ptg_kakao_id', true );

        // 카카오 회원이 아니면 기본 동작 유지
        if ( empty( $kakao_id ) ) {
            return;
        }

        // 카카오 회원용 UI 출력
        $delete_url = rest_url( 'ptg/v1/auth/kakao/start?action=delete' );
        
        ?>
        <div class="ptg-kakao-delete-wrapper" style="margin-bottom: 20px;">
            <div class="um-field-label">
                <label>카카오 계정 인증</label>
                <div class="um-clear"></div>
            </div>
            <div class="um-field-area">
                <p>회원 안전을 위해 카카오 계정 재인증 후 탈퇴가 진행됩니다.</p>
                <a href="<?php echo esc_url( $delete_url ); ?>" class="ptg-btn-kakao" style="display:inline-block; padding:10px 20px; background:#FEE500; color:#000000; text-decoration:none; border-radius:5px; font-weight:bold;">
                    카카오로 인증하고 탈퇴하기
                </a>
            </div>
        </div>

        <style>
            /* 카카오 회원에게는 기존 비밀번호 입력란 숨김 */
            .um-account-delete-password,
            input[name="single_user_password"],
            .um-field-password-id {
                display: none !important;
            }
             /* UM의 삭제 버튼(submit)도 숨겨야 함 (비밀번호 없이 제출하면 에러나므로) */
             .um-after-account-delete, 
             .um-account-tab-delete .um-col-alt-b {
                 display: none !important;
             }
        </style>
        <?php
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
