<?php
/**
 * Plugin Name: PTGates MyNote
 * Description: PTGates 마이노트 - 문제, 이론, 메모 통합 저장 및 관리 허브
 * Version: 1.0.0
 * Author: PTGates
 * Requires Plugins: 0000-ptgates-platform
 * 
 * Shortcode: [ptg_mynote]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PTG_MyNote_Plugin {

	private static $instance = null;

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
		add_action( 'init', [ $this, 'register_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function init_rest_api() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';

		if ( class_exists( '\PTG\MyNote\API' ) ) {
			\PTG\MyNote\API::register_routes();
		}
	}

	public function register_shortcode() {
		add_shortcode( 'ptg_mynote', [ $this, 'render_shortcode' ] );
	}

	public function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ptg_mynote' ) ) {
			// CSS (Placeholder for future use)
			// wp_enqueue_style(...)
		}
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'type' => 'all', // all, question, theory, custom
			],
			$atts,
			'ptg_mynote'
		);

		$plugin_url   = plugin_dir_url( __FILE__ );
		$platform_url = plugins_url( '0000-ptgates-platform/assets/js/platform.js' );
		$mynote_js    = $plugin_url . 'assets/js/mynote.js';
		$rest_url     = esc_url_raw( rest_url( 'ptg-mynote/v1/' ) );
		$nonce        = wp_create_nonce( 'wp_rest' );
		$user_id      = get_current_user_id();

		// Inline Loader Script
		$loader_script = sprintf(
			'<script id="ptg-mynote-loader">
			(function(d){
				var cfg=d.defaultView||window;
				cfg.ptgMyNote=cfg.ptgMyNote||{};
				cfg.ptgMyNote.restUrl=%1$s;
				cfg.ptgMyNote.nonce=%2$s;
				
				var queue=[
					{check:function(){return typeof cfg.PTGPlatform!=="undefined";},url:%3$s},
					{check:function(){return typeof cfg.PTGMyNote!=="undefined";},url:%4$s}
				];
				
				function load(i){
					if(i>=queue.length)return;
					var item=queue[i];
					if(item.check()){load(i+1);return;}
					var existing=d.querySelector(\'script[data-ptg-src="\'+item.url+\'"]\');
					if(existing){existing.addEventListener("load",function(){load(i+1);});return;}
					var s=d.createElement("script");
					s.src=item.url;
					s.async=false;
					s.setAttribute("data-ptg-src",item.url);
					s.onload=function(){load(i+1);};
					(d.head||d.body||d.documentElement).appendChild(s);
				}
				if(d.readyState==="loading"){d.addEventListener("DOMContentLoaded",function(){load(0);});}else{load(0);}
			})(document);
			</script>',
			wp_json_encode( $rest_url ),
			wp_json_encode( $nonce ),
			wp_json_encode( $platform_url ),
			wp_json_encode( $mynote_js )
		);

		ob_start();
		
		// Include template
		$template_path = plugin_dir_path( __FILE__ ) . 'templates/mynote-template.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div id="ptg-mynote-app">Template not found.</div>';
		}

		echo $loader_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return ob_get_clean();
	}
}

PTG_MyNote_Plugin::get_instance();
