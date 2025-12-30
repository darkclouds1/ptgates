<?php
/**
 * Plugin Name: 4100-ptgates-reviewer (PTGates Reviewer)
 * Description: PTGates 복습 스케줄러 - 오답/북마크/취약개념 기반 자동 복습 추천 및 "오늘의 학습" 생성
 * Version: 1.0.0
 * Author: PTGates
 * Requires Plugins: 0000-ptgates-platform
 * 
 * Shortcode: [ptg_review]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PTG_Reviewer_Plugin {

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
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-scheduler.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';

		if ( class_exists( '\PTG\Reviewer\API' ) ) {
			\PTG\Reviewer\API::register_routes();
		}
	}

	public function register_shortcode() {
		add_shortcode( 'ptg_review', [ $this, 'render_shortcode' ] );
	}

	public function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ptg_review' ) ) {
			// CSS (if needed in future, currently none specified in plan but good to have placeholder)
			// CSS
			wp_enqueue_style(
				'ptg-reviewer-style',
				plugin_dir_url( __FILE__ ) . 'assets/css/reviewer.css',
				[],
				'1.0.0'
			);

			// JS Loader is handled inline in shortcode for module independence, 
			// but we can register the script file to be available.
		}
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'mode' => 'today', // today, list, etc.
			],
			$atts,
			'ptg_review'
		);

		$plugin_url   = plugin_dir_url( __FILE__ );
		$platform_url = plugins_url( '0000-ptgates-platform/assets/js/platform.js' );
		$reviewer_js  = $plugin_url . 'assets/js/reviewer.js';
		$rest_url     = esc_url_raw( rest_url( 'ptg-review/v1/' ) );
		$nonce        = wp_create_nonce( 'wp_rest' );
		$user_id      = get_current_user_id();
        
        // 대시보드 URL 가져오기
        $dashboard_url = home_url('/');
        if (class_exists('PTG_Dashboard')) {
            $dashboard_url = PTG_Dashboard::get_dashboard_url();
        }

		// Inline Loader Script (following project guidelines)
		$loader_script = sprintf(
			'<script id="ptg-reviewer-loader">
			(function(d){
				var cfg=d.defaultView||window;
				cfg.ptgReviewer=cfg.ptgReviewer||{};
				cfg.ptgReviewer.restUrl=%1$s;
				cfg.ptgReviewer.nonce=%2$s;
                cfg.ptgReviewer.dashboardUrl=%5$s;
				
				var queue=[
					{check:function(){return typeof cfg.PTGPlatform!=="undefined";},url:%3$s},
					{check:function(){return typeof cfg.PTGReviewer!=="undefined";},url:%4$s}
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
			wp_json_encode( $reviewer_js ),
            wp_json_encode( $dashboard_url )
		);

		ob_start();
		?>
		<div id="ptg-reviewer-app" class="ptg-reviewer-container" data-mode="<?php echo esc_attr( $atts['mode'] ); ?>">
			<div class="ptg-loading">복습 스케줄을 불러오는 중...</div>
		</div>
		<?php
		echo $loader_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return ob_get_clean();
	}
}

PTG_Reviewer_Plugin::get_instance();
