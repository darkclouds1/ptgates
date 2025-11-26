<?php
/**
 * Plugin Name: 3200-ptgates-analytics (PTGates Analytics)
 * Description: PTGates 성적 분석 - 취약 단원 분석, 정답률 통계, 예상 점수 시뮬레이션
 * Version: 1.0.0
 * Author: PTGates
 * X-Requires Plugins: 0000-ptgates-platform
 * 
 * Shortcode: [ptg_analytics]
 */


// Debug Shortcode
add_shortcode( 'ptg_debug', function() {
    return 'PTG Analytics Plugin is ACTIVE';
});

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PTG_Analytics_Plugin {

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
		add_action( 'wp_loaded', [ $this, 'check_shortcode_registration' ] );
	}



	public function check_shortcode_registration() {
		if ( ! shortcode_exists( 'ptg_analytics' ) ) {
			$this->register_shortcode();
		}
	}

	public function init_rest_api() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-analyzer.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';

		if ( class_exists( '\PTG\Analytics\API' ) ) {
			\PTG\Analytics\API::register_routes();
		}
	}

	public function register_shortcode() {
		add_shortcode( 'ptg_analytics', [ $this, 'render_shortcode' ] );
	}

	public function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ptg_analytics' ) ) {
			// CSS (Placeholder)
			// wp_enqueue_style(...)
		}
	}

	public function render_shortcode( $atts ) {
		$plugin_url   = plugin_dir_url( __FILE__ );
		$platform_url = plugins_url( '0000-ptgates-platform/assets/js/platform.js' );
		// Use plugins_url for more reliable URL generation
		$analytics_js = plugins_url( 'assets/js/analytics.js', __FILE__ );
		$rest_url     = esc_url_raw( rest_url( 'ptg-analytics/v1/' ) );
		$nonce        = wp_create_nonce( 'wp_rest' );
		
		// Inline Loader Script
		$loader_script = sprintf(
			'<script id="ptg-analytics-loader">
			(function(d){
				var cfg=d.defaultView||window;
				cfg.ptgAnalytics=cfg.ptgAnalytics||{};
				cfg.ptgAnalytics.restUrl=%1$s;
				cfg.ptgAnalytics.nonce=%2$s;
				
				var queue=[
					{check:function(){return typeof cfg.PTGPlatform!=="undefined";},url:%3$s},
					{check:function(){return typeof cfg.PTGAnalytics!=="undefined";},url:%4$s}
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
			wp_json_encode( $analytics_js )
		);

		ob_start();
		
		// Include template using __DIR__
		$template_path = __DIR__ . '/templates/analytics-template.php';
		
		// Self-repair: If template is missing, try to create it
		if ( ! file_exists( $template_path ) ) {
			if ( ! is_dir( __DIR__ . '/templates' ) ) {
				@mkdir( __DIR__ . '/templates', 0755, true );
			}
			@file_put_contents( $template_path, $default_template );
		}

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div id="ptg-analytics-app">Template not found.</div>';
		}



		echo $loader_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return ob_get_clean();
	}
}

PTG_Analytics_Plugin::get_instance();
