<?php
/**
 * Plugin Name: 2200-ptgates-flashcards (PTGates Flashcards)
 * Description: PTGates 암기카드 - 문제 참조 방식의 암기카드 및 SM-lite 반복 학습
 * Version: 1.0.0
 * Author: PTGates
 * Requires Plugins: 0000-ptgates-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PTG_Flashcards_Plugin {

	private static $instance = null;

    // --- Configuration Constants ---
    const LIMIT_BASIC_CARDS = 20; // Basic(로그인 무료회원) 1일 카드 제한
    const LIMIT_TRIAL_CARDS = 50; // Trial 회원 1일 카드 제한
    const MEMBERSHIP_URL    = '/membership'; // 멤버십 안내 페이지 URL
    // -------------------------------

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		
		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
		add_action( 'init', [ $this, 'register_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function activate() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-migration.php';
		\PTG\Flashcards\Migration::run_migrations();
	}

	public function init_rest_api() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';

		if ( class_exists( '\PTG\Flashcards\API' ) ) {
			\PTG\Flashcards\API::register_routes();
		}
	}

	public function register_shortcode() {
		add_shortcode( 'ptg_flash', [ $this, 'render_shortcode' ] );
	}

	public function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ptg_flash' ) ) {
			// CSS
			wp_enqueue_style( 'ptg-flash-css', plugin_dir_url( __FILE__ ) . 'assets/css/flashcards.css', [], '1.0.0' );
		}
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'mode' => 'list', // list, review
				'set_id' => null,
			],
			$atts,
			'ptg_flash'
		);

		$plugin_url   = plugin_dir_url( __FILE__ );
		$platform_url = plugins_url( '0000-ptgates-platform/assets/js/platform.js' );
		$flash_js     = $plugin_url . 'assets/js/flashcards.js';
		$rest_url     = esc_url_raw( rest_url( 'ptg-flash/v1/' ) );
		$nonce        = wp_create_nonce( 'wp_rest' );
        $user_id      = get_current_user_id();

        // 회원 등급 조회
        $member_grade = 'guest';
        if ( is_user_logged_in() ) {
            if ( current_user_can( 'administrator' ) ) {
                $member_grade = 'administrator';
            } elseif ( class_exists( '\PTG\Platform\Repo' ) ) {
                // 0000-ptgates-platform의 Repo 클래스 사용
                $member = \PTG\Platform\Repo::find_one( 'ptgates_user_member', [ 'user_id' => $user_id ] );
                if ( $member && ! empty( $member['member_grade'] ) ) {
                    $member_grade = $member['member_grade'];
                    // Trial 만료 체크
                    if ( $member_grade === 'trial' && ! empty( $member['billing_expiry_date'] ) && strtotime( $member['billing_expiry_date'] ) < time() ) {
                        $member_grade = 'basic';
                    }
                } else {
                    $member_grade = 'basic'; // 로그인했지만 정보 없으면 Basic 취급
                }
            } else {
                $member_grade = 'basic'; // 플랫폼 없으면 기본 Basic
            }
        }
		
		// Inline Loader Script
		$loader_script = sprintf(
			'<script id="ptg-flash-loader">
			(function(d){
				var cfg=d.defaultView||window;
				cfg.ptgFlash=cfg.ptgFlash||{};
				cfg.ptgFlash.restUrl=%1$s;
				cfg.ptgFlash.nonce=%2$s;
				cfg.ptgFlash.subjectMap=%5$s;
                cfg.ptgFlash.memberGrade=%6$s;
                cfg.ptgFlash.limits={basic:%7$d,trial:%8$d};
                cfg.ptgFlash.membershipUrl=%9$s;
				
				var queue=[
					{check:function(){return typeof cfg.PTGPlatform!=="undefined";},url:%3$s},
					{check:function(){return typeof cfg.PTGFlash!=="undefined";},url:%4$s}
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
			wp_json_encode( $flash_js ),
			wp_json_encode( class_exists( '\PTG\Quiz\Subjects' ) ? \PTG\Quiz\Subjects::MAP : [] ),
            wp_json_encode( $member_grade ),
            self::LIMIT_BASIC_CARDS,
            self::LIMIT_TRIAL_CARDS,
            wp_json_encode( home_url( self::MEMBERSHIP_URL ) )
		);

		ob_start();
		
		// Include template
		$template_path = plugin_dir_path( __FILE__ ) . 'templates/flashcards-template.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div id="ptg-flash-app">Template not found.</div>';
		}

		echo $loader_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return ob_get_clean();
	}
}

PTG_Flashcards_Plugin::get_instance();
