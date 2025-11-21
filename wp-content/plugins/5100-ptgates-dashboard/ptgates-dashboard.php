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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Dashboard] Bootstrapping REST API');
        }

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

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Dashboard] API::boot() missing, fallback hook registered');
                }
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

    public function render_shortcode($atts) {
        // CSS는 템플릿 파일에 인라인 스타일로 포함되어 있음
        ob_start();
        include PTG_DASHBOARD_PATH . 'templates/dashboard-template.php';
        return ob_get_clean();
    }
}

PTG_Dashboard::get_instance();





