<?php
/**
 * Plugin Name: 5100-ptgates-dashboard (PTGates Dashboard)
 * Plugin URI: https://ptgates.com
 * Description: PTGates 개인 대시보드 - 학습 현황, 오늘의 할 일, 프리미엄 상태 표시.
 * Version: 0.1.0
 * Author: PTGates
 * Author URI: https://ptgates.com
 * License: GPL v2 or later
 * Text Domain: ptgates-dashboard
 * Requires Plugins: 0000-ptgates-platform
 * Requires PHP: 8.1
 * 
 * Shortcodes: [ptg_dashboard], [ptg_bookmarks]
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PTG_DASHBOARD_VERSION', '0.1.0');
define('PTG_DASHBOARD_PATH', plugin_dir_path(__FILE__));
define('PTG_DASHBOARD_URL', plugin_dir_url(__FILE__));

class PTG_Dashboard {
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
        if (!class_exists('\PTG\Dashboard\API')) {
            $rest_api_file = PTG_DASHBOARD_PATH . 'includes/class-api.php';
            if (file_exists($rest_api_file) && is_readable($rest_api_file)) {
                require_once $rest_api_file;
            }
        }
    }

    public function init() {
        if (!class_exists('PTG\Platform\Repo')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>PTGates Dashboard requires 0000-ptgates-platform plugin.</p></div>';
            });
            return;
        }

        add_shortcode('ptg_dashboard', [$this, 'render_shortcode']);
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
     * 1200-ptgates-quiz와 동일한 패턴 사용
     */
    public function init_rest_api() {
        if (class_exists('\PTG\Dashboard\API')) {
            // 1200-ptgates-quiz와 동일하게 직접 register_routes 호출
            \PTG\Dashboard\API::register_routes();
        }
    }

    /**
     * 인덱스 추가 (admin_init 훅에서 호출)
     */
    public function maybe_add_indexes() {
        if (class_exists('\PTG\Dashboard\API') && method_exists('\PTG\Dashboard\API', 'maybe_add_indexes')) {
            \PTG\Dashboard\API::maybe_add_indexes();
        }
    }

    public function enqueue_assets() {
        // CSS는 템플릿 파일에 인라인 스타일로 포함되어 있으므로 별도 enqueue 불필요
        // JS는 템플릿에서 인라인 로더로 처리
        
        // 혹시 다른 곳에서 로드되는 CSS를 방지하기 위해 dequeue
        wp_dequeue_style('ptg-dashboard-style');
        wp_deregister_style('ptg-dashboard-style');
    }

    /**
     * 대시보드 페이지 URL 가져오기
     * 
     * @return string Dashboard 페이지 URL
     */
    public static function get_dashboard_url() {
        // 1. 옵션에 저장된 대시보드 페이지 ID 확인
        $dashboard_page_id = get_option( 'ptg_dashboard_page_id' );
        
        // 2. 옵션에 저장된 ID가 있으면 유효성 검사
        if ( $dashboard_page_id ) {
            $page = get_post( $dashboard_page_id );
            // 페이지가 존재하고 publish 상태이며 숏코드가 있는지 확인
            if ( $page && $page->post_status === 'publish' && has_shortcode( $page->post_content, 'ptg_dashboard' ) ) {
                $url = get_permalink( $dashboard_page_id );
                if ( $url ) {
                    return rtrim( $url, '/' );
                }
            } else {
                // 유효하지 않은 ID면 옵션 삭제
                delete_option( 'ptg_dashboard_page_id' );
                $dashboard_page_id = null;
            }
        }
        
        // 3. 옵션이 없거나 유효하지 않으면 [ptg_dashboard] 숏코드가 있는 페이지 찾기
        if ( ! $dashboard_page_id ) {
            // WP_Query를 사용하여 더 확실하게 찾기
            $query = new \WP_Query( array(
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => -1, // 모든 페이지
                'fields'         => 'ids', // ID만 가져오기 (성능 최적화)
            ) );
            
            if ( $query->have_posts() ) {
                foreach ( $query->posts as $page_id ) {
                    $page = get_post( $page_id );
                    if ( $page && has_shortcode( $page->post_content, 'ptg_dashboard' ) ) {
                        $dashboard_page_id = $page_id;
                        // 찾은 페이지 ID를 옵션에 저장 (캐시)
                        update_option( 'ptg_dashboard_page_id', $dashboard_page_id );
                        break;
                    }
                }
            }
            wp_reset_postdata();
        }
        
        // 4. 페이지 ID가 있으면 URL 반환
        if ( $dashboard_page_id ) {
            $url = get_permalink( $dashboard_page_id );
            if ( $url ) {
                // trailing slash 제거 (쿼리 파라미터 추가 시 문제 방지)
                return rtrim( $url, '/' );
            }
        }
        
        // 5. 찾지 못한 경우 fallback URL 반환
        return home_url( '/' );
    }

    /**
     * Quiz 페이지 URL 가져오기
     * 
     * @return string Quiz 페이지 URL
     */
    public static function get_quiz_url() {
        // 1. 옵션에 저장된 Quiz 페이지 ID 확인
        $quiz_page_id = get_option( 'ptg_quiz_page_id' );
        
        // 2. 옵션에 저장된 ID가 있으면 유효성 검사
        if ( $quiz_page_id ) {
            $page = get_post( $quiz_page_id );
            // 페이지가 존재하고 publish 상태이며 숏코드가 있는지 확인
            if ( $page && $page->post_status === 'publish' && has_shortcode( $page->post_content, 'ptg_quiz' ) ) {
                $url = get_permalink( $quiz_page_id );
                if ( $url ) {
                    return rtrim( $url, '/' );
                }
            } else {
                // 유효하지 않은 ID면 옵션 삭제
                delete_option( 'ptg_quiz_page_id' );
                $quiz_page_id = null;
            }
        }
        
        // 3. 옵션이 없거나 유효하지 않으면 [ptg_quiz] 숏코드가 있는 페이지 찾기
        if ( ! $quiz_page_id ) {
            // WP_Query를 사용하여 더 확실하게 찾기
            $query = new \WP_Query( array(
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => -1, // 모든 페이지
                'fields'         => 'ids', // ID만 가져오기 (성능 최적화)
            ) );
            
            if ( $query->have_posts() ) {
                foreach ( $query->posts as $page_id ) {
                    $page = get_post( $page_id );
                    if ( $page && has_shortcode( $page->post_content, 'ptg_quiz' ) ) {
                        $quiz_page_id = $page_id;
                        // 찾은 페이지 ID를 옵션에 저장 (캐시)
                        update_option( 'ptg_quiz_page_id', $quiz_page_id );
                        break;
                    }
                }
            }
            wp_reset_postdata();
        }
        
        // 4. 페이지 ID가 있으면 URL 반환
        if ( $quiz_page_id ) {
            $url = get_permalink( $quiz_page_id );
            if ( $url ) {
                // trailing slash 제거 (쿼리 파라미터 추가 시 문제 방지)
                return rtrim( $url, '/' );
            }
        }
        
        // 5. 찾지 못한 경우 fallback URL 반환
        // 실제 Quiz 페이지 URL이 /ptg_quiz/인 경우를 대비
        return home_url( '/ptg_quiz/' );
    }

    /**
     * Study 페이지 URL 가져오기
     * 
     * @return string Study 페이지 URL
     */
    public static function get_study_url() {
        // 1. 옵션에 저장된 Study 페이지 ID 확인
        $study_page_id = get_option( 'ptg_study_page_id' );
        
        // 2. 옵션에 저장된 ID가 있으면 유효성 검사
        if ( $study_page_id ) {
            $page = get_post( $study_page_id );
            // 페이지가 존재하고 publish 상태이며 숏코드가 있는지 확인
            if ( $page && $page->post_status === 'publish' && has_shortcode( $page->post_content, 'ptg_study' ) ) {
                $url = get_permalink( $study_page_id );
                if ( $url ) {
                    return rtrim( $url, '/' );
                }
            } else {
                // 유효하지 않은 ID면 옵션 삭제
                delete_option( 'ptg_study_page_id' );
                $study_page_id = null;
            }
        }
        
        // 3. 옵션이 없거나 유효하지 않으면 [ptg_study] 숏코드가 있는 페이지 찾기
        if ( ! $study_page_id ) {
            // WP_Query를 사용하여 더 확실하게 찾기
            $query = new \WP_Query( array(
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => -1, // 모든 페이지
                'fields'         => 'ids', // ID만 가져오기 (성능 최적화)
            ) );
            
            if ( $query->have_posts() ) {
                foreach ( $query->posts as $page_id ) {
                    $page = get_post( $page_id );
                    if ( $page && has_shortcode( $page->post_content, 'ptg_study' ) ) {
                        $study_page_id = $page_id;
                        // 찾은 페이지 ID를 옵션에 저장 (캐시)
                        update_option( 'ptg_study_page_id', $study_page_id );
                        break;
                    }
                }
            }
            wp_reset_postdata();
        }
        
        // 4. 페이지 ID가 있으면 URL 반환
        if ( $study_page_id ) {
            $url = get_permalink( $study_page_id );
            if ( $url ) {
                // trailing slash 제거 (쿼리 파라미터 추가 시 문제 방지)
                return rtrim( $url, '/' );
            }
        }
        
        // 5. 찾지 못한 경우 fallback URL 반환
        // 실제 Study 페이지 URL이 /ptg_study/인 경우를 대비
        return home_url( '/ptg_study/' );
    }

    public function render_shortcode($atts) {
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'dashboard';

        ob_start();
        
        if ($view === 'membership') {
            include PTG_DASHBOARD_PATH . 'templates/membership-template.php';
        } else {
            include PTG_DASHBOARD_PATH . 'templates/dashboard-template.php';
        }
        
        return ob_get_clean();
    }


}

PTG_Dashboard::get_instance();





