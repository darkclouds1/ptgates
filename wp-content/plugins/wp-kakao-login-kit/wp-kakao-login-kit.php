<?php
/**
 * Plugin Name:       WP Kakao Login Kit
 * Plugin URI:        https://uloca.net/simple-kakao
 * Description:       Simple & Lightweight Kakao Login/Auth Kit for WordPress.
 * Version:           1.0.0
 * Author:            Monolith
 * Author URI:        https://uloca.net
 * Text Domain:       wp-kakao-login-kit
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Validating Dependencies
 */
// Define Constants
define( 'WPKLK_VERSION', '1.0.0' );
define( 'WPKLK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPKLK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPKLK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-kakao-login-kit-loader.php';

/**
 * Begins execution of the plugin.
 */
function run_wp_kakao_login_kit() {
	$plugin = new Monolith\KakaoLoginKit\Loader();
	$plugin->run();
}
run_wp_kakao_login_kit();
