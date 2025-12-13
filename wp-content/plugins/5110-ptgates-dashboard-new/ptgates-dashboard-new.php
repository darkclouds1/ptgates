<?php
/**
 * Plugin Name: 5110-ptgates-dashboard-new (PTGates Dashboard New)
 * Plugin URI: https://ptgates.com
 * Description: PTGates 개인 대시보드 (New) - UX/UI 및 성능 개선용 놀이터.
 * Version: 0.1.0
 * Author: PTGates
 * Author URI: https://ptgates.com
 * License: GPL v2 or later
 * Text Domain: ptgates-dashboard-new
 * Requires Plugins: 0000-ptgates-platform
 * Requires PHP: 8.1
 * 
 * Shortcodes: [ptg_dashboard_new]
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PTG_DASHBOARD_NEW_VERSION', '0.1.0');
define('PTG_DASHBOARD_NEW_PATH', plugin_dir_path(__FILE__));
define('PTG_DASHBOARD_NEW_URL', plugin_dir_url(__FILE__));

class PTG_DashboardNew {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // API 클래스 미리 로드
        $this->load_api_class();
        // REST API 초기화
        $this->boot_rest_api();
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * API 클래스를 로드합니다.
     */
    private function load_api_class() {
        if (!class_exists('\PTG\DashboardNew\API')) {
            $rest_api_file = PTG_DASHBOARD_NEW_PATH . 'includes/class-api.php';
            if (file_exists($rest_api_file) && is_readable($rest_api_file)) {
                require_once $rest_api_file;
            }
        }
    }

    public function init() {
        if (!class_exists('PTG\Platform\Repo')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>PTGates Dashboard (New) requires 0000-ptgates-platform plugin.</p></div>';
            });
            return;
        }

        add_shortcode('ptg_dashboard_new', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Custom Logout Handler
        add_action('init', [$this, 'handle_custom_logout']);
    }

    public function handle_custom_logout() {
        if (isset($_GET['ptg_action']) && $_GET['ptg_action'] === 'logout') {
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'ptg_logout')) {
                wp_logout();
                wp_safe_redirect(home_url());
                exit;
            }
        }
    }

    private function boot_rest_api() {
        // REST API 초기화를 rest_api_init 훅에서 직접 수행
        add_action('rest_api_init', [$this, 'init_rest_api'], 10);
        // 인덱스 추가는 admin_init에서 수행
        add_action('admin_init', [$this, 'maybe_add_indexes']);
    }

    /**
     * REST API를 초기화합니다.
     */
    public function init_rest_api() {
        if (class_exists('\PTG\DashboardNew\API')) {
            \PTG\DashboardNew\API::register_routes();
        }
    }

    /**
     * 인덱스 추가 (admin_init 훅에서 호출)
     */
    public function maybe_add_indexes() {
        if (class_exists('\PTG\DashboardNew\API') && method_exists('\PTG\DashboardNew\API', 'maybe_add_indexes')) {
            \PTG\DashboardNew\API::maybe_add_indexes();
        }
    }

    public function enqueue_assets() {
        // CSS는 템플릿 파일에 인라인 스타일로 포함되어 있으므로 별도 enqueue 불필요
        // JS는 템플릿에서 인라인 로더로 처리
        
        // 혹시 다른 곳에서 로드되는 CSS를 방지하기 위해 dequeue
        wp_dequeue_style('ptg-dashboard-new-style');
        wp_deregister_style('ptg-dashboard-new-style');
    }

    /**
     * 대시보드 페이지 URL 가져오기
     */
    public static function get_dashboard_url() {
        $dashboard_page_id = get_option( 'ptg_dashboard_new_page_id' );
        
        if ( $dashboard_page_id ) {
            $page = get_post( $dashboard_page_id );
            if ( $page && $page->post_status === 'publish' && has_shortcode( $page->post_content, 'ptg_dashboard_new' ) ) {
                $url = get_permalink( $dashboard_page_id );
                if ( $url ) {
                    return rtrim( $url, '/' );
                }
            } else {
                delete_option( 'ptg_dashboard_new_page_id' );
                $dashboard_page_id = null;
            }
        }
        
        if ( ! $dashboard_page_id ) {
            $query = new \WP_Query( array(
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ) );
            
            if ( $query->have_posts() ) {
                foreach ( $query->posts as $page_id ) {
                    $page = get_post( $page_id );
                    if ( $page && has_shortcode( $page->post_content, 'ptg_dashboard_new' ) ) {
                        $dashboard_page_id = $page_id;
                        update_option( 'ptg_dashboard_new_page_id', $dashboard_page_id );
                        break;
                    }
                }
            }
            wp_reset_postdata();
        }
        
        if ( $dashboard_page_id ) {
            $url = get_permalink( $dashboard_page_id );
            if ( $url ) {
                return rtrim( $url, '/' );
            }
        }
        
        return home_url( '/' );
    }

    /**
     * Quiz 페이지 URL 가져오기
     * (기존 옵션 유지 - 퀴즈 플러그인은 공유되므로)
     */
    public static function get_quiz_url() {
        // ... (이전과 동일한 로직, Quiz URL은 공유됨)
        $quiz_page_id = get_option( 'ptg_quiz_page_id' );
        
        // ... 생략 (이전 함수 내용 재사용하되 여기서는 간략화 안함, 전체 코드 삽입 필요)
        // 편의상 복사해서 넣음
        
        if ( $quiz_page_id ) {
            $page = get_post( $quiz_page_id );
            if ( $page && $page->post_status === 'publish' && has_shortcode( $page->post_content, 'ptg_quiz' ) ) {
                $url = get_permalink( $quiz_page_id );
                if ( $url ) return rtrim( $url, '/' );
            } else {
                delete_option( 'ptg_quiz_page_id' );
                $quiz_page_id = null;
            }
        }
        
        if ( ! $quiz_page_id ) {
            $query = new \WP_Query( array(
                'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
            ) );
            
            if ( $query->have_posts() ) {
                foreach ( $query->posts as $page_id ) {
                    $page = get_post( $page_id );
                    if ( $page && has_shortcode( $page->post_content, 'ptg_quiz' ) ) {
                        $quiz_page_id = $page_id;
                        update_option( 'ptg_quiz_page_id', $quiz_page_id );
                        break;
                    }
                }
            }
            wp_reset_postdata();
        }
        
        if ( $quiz_page_id ) {
            $url = get_permalink( $quiz_page_id );
            if ( $url ) return rtrim( $url, '/' );
        }
        
        return home_url( '/ptg_quiz/' );
    }

    /**
     * Study 페이지 URL 가져오기
     * (기존 옵션 유지 - 스터디 플러그인은 공유되므로)
     */
    public static function get_study_url() {
        $study_page_id = get_option( 'ptg_study_page_id' );
        
        if ( $study_page_id ) {
            $page = get_post( $study_page_id );
            if ( $page && $page->post_status === 'publish' && has_shortcode( $page->post_content, 'ptg_study' ) ) {
                $url = get_permalink( $study_page_id );
                if ( $url ) return rtrim( $url, '/' );
            } else {
                delete_option( 'ptg_study_page_id' );
                $study_page_id = null;
            }
        }
        
        if ( ! $study_page_id ) {
            $query = new \WP_Query( array(
                'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
            ) );
            
            if ( $query->have_posts() ) {
                foreach ( $query->posts as $page_id ) {
                    $page = get_post( $page_id );
                    if ( $page && has_shortcode( $page->post_content, 'ptg_study' ) ) {
                        $study_page_id = $page_id;
                        update_option( 'ptg_study_page_id', $study_page_id );
                        break;
                    }
                }
            }
            wp_reset_postdata();
        }
        
        if ( $study_page_id ) {
            $url = get_permalink( $study_page_id );
            if ( $url ) return rtrim( $url, '/' );
        }
        
        return home_url( '/ptg_study/' );
    }

    public function render_shortcode($atts) {
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'dashboard';

        ob_start();
        
        if ($view === 'membership') {
            include PTG_DASHBOARD_NEW_PATH . 'templates/membership-template.php';
        } else {
            include PTG_DASHBOARD_NEW_PATH . 'templates/dashboard-template.php';
        }
        
        return ob_get_clean();
    }
}

PTG_DashboardNew::get_instance();





