<?php
/**
 * Plugin Name: 6100-ptGates Members
 * Description: Lightweight member management (Signup, Login, Account) using wp_mail
 * Version: 1.0.0
 * Author: PTGates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define Constants
define( 'PTG_MEMBERS_VERSION', '1.0.0' );
define( 'PTG_MEMBERS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PTG_MEMBERS_URL', plugin_dir_url( __FILE__ ) );

// Include Classes
require_once PTG_MEMBERS_PATH . 'includes/class-members-activator.php';
require_once PTG_MEMBERS_PATH . 'includes/class-members-auth.php';
require_once PTG_MEMBERS_PATH . 'includes/class-members-shortcodes.php';
require_once PTG_MEMBERS_PATH . 'includes/class-members-settings.php';

// Initialize Plugin
function ptg_members_init() {
    $auth = new PTG_Members_Auth();
    $auth->init();

    $settings = new PTG_Members_Settings();
    $settings->init();

    $shortcodes = new PTG_Members_Shortcodes( $auth );
    $shortcodes->init();

    // [Fix] Force Register Kakao Routes if Platform Class exists (Rescue for 404)
    add_action( 'rest_api_init', function() {
        if ( class_exists( '\PTG\Platform\KakaoAuth' ) ) {
            register_rest_route( 'ptg/v1', '/auth/kakao/start', array(
                'methods' => 'GET',
                'callback' => array( '\PTG\Platform\KakaoAuth', 'handle_start' ),
                'permission_callback' => '__return_true',
            ) );
            register_rest_route( 'ptg/v1', '/auth/kakao/callback', array(
                'methods' => 'GET',
                'callback' => array( '\PTG\Platform\KakaoAuth', 'handle_callback' ),
                'permission_callback' => '__return_true',
            ) );
        }
    }, 20 ); // Priority 20 to override others if needed
}

// Flush rewrites on init for now to ensure routes work immediately
add_action( 'init', function() {
    // Check if flush is needed (optional, or just do it once)
    if ( ! get_option( 'ptg_members_routes_flushed' ) ) {
        flush_rewrite_rules();
        update_option( 'ptg_members_routes_flushed', 1 );
    }
} );
add_action( 'plugins_loaded', 'ptg_members_init' );

// Enqueue Assets
function ptg_members_enqueue_assets() {
    wp_enqueue_style( 'ptg-members-css', PTG_MEMBERS_URL . 'assets/css/members.css', [], PTG_MEMBERS_VERSION );
    wp_enqueue_script( 'ptg-members-js', PTG_MEMBERS_URL . 'assets/js/members.js', ['jquery'], PTG_MEMBERS_VERSION, true );
    wp_enqueue_style( 'dashicons' ); 
}
add_action( 'wp_enqueue_scripts', 'ptg_members_enqueue_assets' );

// Activation Hook
register_activation_hook( __FILE__, [ 'PTG_Members_Activator', 'activate' ] );

// Temporary Migration: Force update pages to use new shortcodes
add_action( 'init', function() {
    if ( get_option( 'ptg_members_shortcodes_fixed_v1' ) ) {
        return;
    }

    $pages = [
        'login'   => '[ptg_login]',
        'signup'  => '[ptg_signup]',
        'account' => '[ptg_account]',
    ];

    foreach ( $pages as $slug => $shortcode ) {
        $page = get_page_by_path( $slug );
        if ( $page ) {
            // Only update if it contains old shortcode or is empty, or just force it for now to ensure styling
            $updated = wp_update_post( [
                'ID'           => $page->ID,
                'post_content' => $shortcode,
            ] );
        }
    }
    
    update_option( 'ptg_members_shortcodes_fixed_v1', true );
});
