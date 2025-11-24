<?php
/**
 * Plugin Name: PTGates Dashboard
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
 * Shortcode: [ptg_dashboard]
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
        $this->boot_rest_api();
        add_action('plugins_loaded', [$this, 'init']);
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
    }

    private function boot_rest_api() {
        if (!class_exists('\PTG\Dashboard\API')) {
            require_once PTG_DASHBOARD_PATH . 'includes/class-api.php';
        }

        if (class_exists('\PTG\Dashboard\API')) {
            if (method_exists('\PTG\Dashboard\API', 'boot')) {
                \PTG\Dashboard\API::boot();
            } else {
                add_action('rest_api_init', function() {
                    if (class_exists('\PTG\Dashboard\API')) {
                        $api = new \PTG\Dashboard\API();
                        if (method_exists($api, 'register_routes')) {
                            $api->register_routes();
                        } elseif (method_exists('\PTG\Dashboard\API', 'register_routes')) {
                            // 최후의 수단으로 정적 호출 시도 (구버전 호환)
                            \PTG\Dashboard\API::register_routes();
                        }
                    }
                }, 10);
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Dashboard] Failed to load API class');
            }
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
        // CSS는 템플릿 파일에 인라인 스타일로 포함되어 있음
        ob_start();
        include PTG_DASHBOARD_PATH . 'templates/dashboard-template.php';
        return ob_get_clean();
    }
}

PTG_Dashboard::get_instance();





