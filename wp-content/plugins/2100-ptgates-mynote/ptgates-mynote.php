<?php
/**
 * Plugin Name: 2100-ptgates-mynote (PTGates MyNote)
 * Description: PTGates 마이노트 - 문제, 이론, 메모 통합 저장 및 관리 허브
 * Version: 1.0.0
 * Author: PTGates
 * Requires Plugins: 0000-ptgates-platform
 * 
 * Shortcode: [ptg_mynote] 
 * view="list|card" 속성을 추가하거나, 관리자 옵션/URL 파라미터 등으로 모드를 결정
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

	/**
	 * 대시보드 페이지 URL 가져오기
	 * 
	 * @return string 대시보드 페이지 URL
	 */
	public static function get_dashboard_url() {
		// 1. 옵션에 저장된 대시보드 페이지 ID 확인
		$dashboard_page_id = get_option( 'ptg_dashboard_page_id' );
		
		// 2. 옵션이 없으면 [ptg_dashboard] 숏코드가 있는 페이지 찾기
		if ( ! $dashboard_page_id ) {
			$pages = get_pages( array(
				'post_status' => 'publish',
			) );
			
			foreach ( $pages as $page ) {
				if ( has_shortcode( $page->post_content, 'ptg_dashboard' ) ) {
					$dashboard_page_id = $page->ID;
					// 찾은 페이지 ID를 옵션에 저장 (캐시)
					update_option( 'ptg_dashboard_page_id', $dashboard_page_id );
					break;
				}
			}
		}
		
		// 3. 페이지 ID가 있으면 URL 반환
		if ( $dashboard_page_id ) {
			$url = get_permalink( $dashboard_page_id );
			if ( $url ) {
				return $url;
			}
		}
		
		return '#';
	}

	public function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ptg_mynote' ) ) {
			$plugin_url = plugin_dir_url( __FILE__ );
			// 공통 컴포넌트 CSS에 의존하도록 설정 (question-viewer.css가 먼저 로드되도록)
			wp_enqueue_style( 
				'ptg-mynote', 
				$plugin_url . 'assets/css/mynote.css', 
				['ptg-question-viewer-style'], // 공통 컴포넌트 CSS 의존성 추가
				'1.0.1' 
			);
		}
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'type' => 'all', // all, question, theory, custom
				'view' => 'card',
			],
			$atts,
			'ptg_mynote'
		);

		$view_mode = in_array( $atts['view'], [ 'list', 'card' ], true ) ? $atts['view'] : 'card';

		$plugin_url   = plugin_dir_url( __FILE__ );
		$platform_url = plugins_url( '0000-ptgates-platform/assets/js/platform.js' );
		$mynote_js    = $plugin_url . 'assets/js/mynote.js';
		$study_toolbar_url = plugins_url( '1100-ptgates-study/assets/js/study-toolbar.js' );
		$rest_url     = esc_url_raw( rest_url( 'ptg-mynote/v1/' ) );
		$nonce        = wp_create_nonce( 'wp_rest' );
		$user_id      = get_current_user_id();
		$dashboard_url = self::get_dashboard_url();

		// Inline Loader Script
		$loader_script = sprintf(
			'<script id="ptg-mynote-loader">
			(function(d){
				var cfg=d.defaultView||window;
				cfg.ptgMyNote=cfg.ptgMyNote||{};
				cfg.ptgMyNote.restUrl=%1$s;
				cfg.ptgMyNote.nonce=%2$s;
				cfg.ptgMyNote.dashboardUrl=%9$s;
				cfg.ptgMyNote.viewMode=%10$s;
				
				// 1100 study 설정 (툴바 기능을 위해 필요)
				if(!cfg.ptgStudy){
					cfg.ptgStudy={
						rest_url:%5$s,
						api_nonce:%2$s,
						is_user_logged_in:%6$s,
						login_url:%7$s
					};
				}
				
				var queue=[
					{check:function(){return typeof cfg.PTGPlatform!=="undefined";},url:%3$s},
					{check:function(){return typeof cfg.PTGStudyToolbar!=="undefined";},url:%4$s},
					{check:function(){return typeof cfg.PTGMyNote!=="undefined";},url:%8$s}
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
			wp_json_encode( $study_toolbar_url ),
			wp_json_encode( esc_url_raw( rest_url( 'ptg-quiz/v1/' ) ) ),
			is_user_logged_in() ? 'true' : 'false',
			wp_json_encode( esc_url( add_query_arg( 'redirect_to', urlencode( get_permalink() ), home_url( '/login/' ) ) ) ),
			wp_json_encode( $mynote_js ),
			wp_json_encode( esc_url_raw( $dashboard_url ) ),
			wp_json_encode( $view_mode )
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
