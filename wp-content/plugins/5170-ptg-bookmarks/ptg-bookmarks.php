<?php
/**
 * Plugin Name: 5170-ptg-bookmarks (PTGates Bookmarks)
 * Plugin URI: https://ptgates.com
 * Description: PTGates 북마크 기능 - 북마크된 문제 목록 조회 및 풀이.
 * Version: 0.1.0
 * Author: PTGates
 * Author URI: https://ptgates.com
 * License: GPL v2 or later
 * Text Domain: ptg-bookmarks
 * Requires Plugins: 0000-ptgates-platform
 * Requires PHP: 8.1
 * 
 * Shortcodes: [ptg_bookmarks]
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PTG_BOOKMARKS_VERSION', '0.1.0');
define('PTG_BOOKMARKS_PATH', plugin_dir_path(__FILE__));
define('PTG_BOOKMARKS_URL', plugin_dir_url(__FILE__));

class PTG_Bookmarks {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_api_class();
        $this->boot_rest_api();
        add_action('plugins_loaded', [$this, 'init']);
    }

    private function load_api_class() {
        if (!class_exists('\PTG\Bookmarks\API')) {
            $rest_api_file = PTG_BOOKMARKS_PATH . 'includes/class-api.php';
            if (file_exists($rest_api_file) && is_readable($rest_api_file)) {
                require_once $rest_api_file;
            }
        }
    }

    public function init() {
        if (!class_exists('PTG\Platform\Repo')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>PTGates Bookmarks requires 0000-ptgates-platform plugin.</p></div>';
            });
            return;
        }

        add_shortcode('ptg_bookmarks', [$this, 'render_shortcode']);
    }

    private function boot_rest_api() {
        add_action('rest_api_init', [$this, 'init_rest_api'], 10);
    }

    public function init_rest_api() {
        if (class_exists('\PTG\Bookmarks\API')) {
            \PTG\Bookmarks\API::register_routes();
        }
    }

    public function render_shortcode($atts) {
        ob_start();
        include PTG_BOOKMARKS_PATH . 'templates/bookmarks-template.php';
        return ob_get_clean();
    }
}

PTG_Bookmarks::get_instance();
