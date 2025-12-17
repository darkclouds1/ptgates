<?php

namespace Monolith\KakaoLoginKit;

/**
 * Plugin Loader Class
 * 
 * Orchestrates all modules (Admin, Public, Auth, Social).
 */
class Loader {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->plugin_name = 'wp-kakao-login-kit';
        $this->version = WPKLK_VERSION;

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        // Core Auth Logic
        require_once WPKLK_PLUGIN_DIR . 'includes/class-kakao-login-kit-auth.php';
        
        // Social Logic (Kakao)
        require_once WPKLK_PLUGIN_DIR . 'includes/class-kakao-login-kit-social.php';

        // Admin Settings
        require_once WPKLK_PLUGIN_DIR . 'admin/class-kakao-login-kit-admin.php';

        // Public Facing (Shortcodes)
        require_once WPKLK_PLUGIN_DIR . 'public/class-kakao-login-kit-public.php';
    }

    private function define_admin_hooks() {
        $plugin_admin = new Admin( $this->plugin_name, $this->version );
        add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
        add_action( 'admin_init', array( $plugin_admin, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
    }

    private function define_public_hooks() {
        $plugin_public = new Public_Facing( $this->plugin_name, $this->version );
        $plugin_auth   = new Auth();
        $plugin_social = new Social();

        // Enqueue Styles
        add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_styles' ) );

        // Shortcodes
        add_shortcode( 'wp_kakao_login', array( $plugin_public, 'render_login_form' ) );
        add_shortcode( 'wp_kakao_signup', array( $plugin_public, 'render_signup_form' ) );
        add_shortcode( 'wp_kakao_account', array( $plugin_public, 'render_account_form' ) );
        add_shortcode( 'wp_kakao_verify_email', array( $plugin_public, 'render_verify_email' ) );

        // Auth Actions (POST Handlers)
        add_action( 'init', array( $plugin_auth, 'handle_signup' ) );
        add_action( 'init', array( $plugin_auth, 'handle_login' ) );
        add_action( 'init', array( $plugin_auth, 'handle_verify_email' ) ); // Handle GET verification
        add_action( 'init', array( $plugin_auth, 'handle_logout' ) ); // Custom logout handler
        
        // Account Actions
        add_action( 'init', array( $plugin_auth, 'handle_profile_update' ) );
        add_action( 'init', array( $plugin_auth, 'handle_password_change' ) );
        add_action( 'init', array( $plugin_auth, 'handle_account_delete' ) );

        // Auth Filters
        add_filter( 'authenticate', array( $plugin_auth, 'check_email_verified' ), 20, 3 );

        // Social Actions (REST API)
        add_action( 'rest_api_init', array( $plugin_social, 'register_routes' ) );
    }

    public function run() {
        // Hooks are registered in constructor
    }
}
