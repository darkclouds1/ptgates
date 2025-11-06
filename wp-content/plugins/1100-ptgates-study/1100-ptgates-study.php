<?php
/**
 * PTGates Study - 모듈 스캐폴드
 *
 * Lightweight scaffolding for study module.
 * Requires: 0000-ptgates-platform
 */

/**
 * Plugin bootstrap
 */
class PTG_Study_Plugin {
    const VERSION = '0.1.0';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Activation hooks
        register_activation_hook(__FILE__, [ $this, 'activate' ]);
        register_deactivation_hook(__FILE__, [ $this, 'deactivate' ]);

        // Init
        add_action('init', [ $this, 'init_plugin' ]);
        // Shortcode for frontend
        add_shortcode('ptg_study', [ $this, 'render_shortcode' ]);
        // REST routes will be registered by API class
        add_action('rest_api_init', [ 'PTG\Study\Study_API', 'register_routes' ]);
    }

    public function activate() {
        // Flush rewrite rules on activation if needed
        flush_rewrite_rules();
    }
    public function deactivate() {
        flush_rewrite_rules();
    }

    public function init_plugin() {
        // Namespace setup if needed
        // No heavy init in scaffold
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([], $atts, 'ptg_study');
        return '<div id="ptg-study-container">PTGStudy placeholder</div>';
    }
}

// Initialize plugin
PTG_Study_Plugin::get_instance();


