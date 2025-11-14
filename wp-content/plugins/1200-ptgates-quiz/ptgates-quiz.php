<?php
/**
 * Plugin Name: PTGates Quiz
 * Description: PTGates 퀴즈 풀이 기능 플러그인.
 * Version: 1.0.13
 * Author: PTGates
 * Requires Plugins: 0000-ptgates-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class PTG_Quiz_Plugin {

    private static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // 워드프레스 표준에 따라, 각 기능에 맞는 정확한 훅(hook)에 연결합니다.
        
        // 1. REST API는 'rest_api_init' 훅에서 초기화합니다.
        add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
        add_filter( 'rest_authentication_errors', [ $this, 'rest_auth_guard' ] );
        
        // 2. 숏코드는 'init' 훅에서 등록합니다.
        add_action( 'init', [ $this, 'register_shortcode' ] );

        // 3. 스크립트와 스타일은 'wp_enqueue_scripts' 훅에서 로드합니다.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * 우리 네임스페이스 요청일 때 비로그인은 JSON 에러로 즉시 응답
     */
    public function rest_auth_guard( $result ) {
        if ( ! empty( $result ) ) {
            return $result;
        }
        // 현재 요청 경로 확인
        $route = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( strpos( $route, '/wp-json/ptg-quiz/v1/' ) !== false ) {
            if ( ! is_user_logged_in() ) {
                return new \WP_Error( 'rest_forbidden', '로그인이 필요합니다.', [ 'status' => 401 ] );
            }
        }
        return $result;
    }

    /**
     * REST API를 초기화합니다.
     */
    public function init_rest_api() {
        $rest_api_file = plugin_dir_path( __FILE__ ) . 'includes/class-api.php';

        if ( file_exists( $rest_api_file ) && is_readable( $rest_api_file ) ) {
            require_once $rest_api_file;
            if ( class_exists( '\PTG\Quiz\API' ) ) {
                \PTG\Quiz\API::register_routes();
            }
        }
    }

    /**
     * 숏코드를 등록합니다.
     */
    public function register_shortcode() {
        add_shortcode( 'ptg_quiz', [ $this, 'render_shortcode' ] );
    }
    
    /**
     * 스크립트와 스타일을 조건부로 enqueue 합니다.
     * CSS는 WordPress 큐를 사용하되, JS는 숏코드 렌더링 시 별도 로더로 처리합니다.
     */
    public function enqueue_assets() {
        global $post;
        // [ptg_quiz] 숏코드가 있는 페이지에서만 스크립트와 스타일을 로드합니다.
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ptg_quiz' ) ) {
            wp_enqueue_style(
                'ptg-quiz-style',
                plugin_dir_url( __FILE__ ) . 'assets/css/quiz.css',
                [ 'ptg-platform-style' ],
                '1.0.13' // 버전 업데이트로 캐시 무효화
            );
        }
    }

    /**
     * 숏코드를 렌더링합니다.
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'id'          => null,
                'question_id' => null,
                'timer'       => 90,
                'unlimited'   => '0',
            ],
            $atts,
            'ptg_quiz'
        );

        // "id" 또는 "question_id" 어디로 호출해도 대응
        $question_id = $atts['question_id'] ?: $atts['id'];

        if ( ! $question_id ) {
            return '<div class="ptg-quiz-container"><p>⚠️ 문제 ID가 지정되지 않았습니다. <code>[ptg_quiz id="380"]</code>과 같이 호출해 주세요.</p></div>';
        }

        $plugin_url     = plugin_dir_url( __FILE__ );
        $platform_url   = plugins_url( '0000-ptgates-platform/assets/js/platform.js' );
        $quiz_ui_url    = plugins_url( '0000-ptgates-platform/assets/js/quiz-ui.js' );
        $quiz_js_url    = $plugin_url . 'assets/js/quiz.js';
        $rest_url       = esc_url_raw( rest_url( 'ptg-quiz/v1/' ) );
        $rest_base      = esc_url_raw( rest_url( '/' ) );
        $nonce          = wp_create_nonce( 'wp_rest' );
        $user_id        = get_current_user_id();
        $timezone       = wp_timezone_string();

        $loader_script = sprintf(
            '<script id="ptg-quiz-script-loader">(function(d){var cfg=d.defaultView||window;cfg.ptgQuiz=cfg.ptgQuiz||{};cfg.ptgQuiz.restUrl=%1$s;cfg.ptgQuiz.nonce=%2$s;cfg.ptgPlatform=cfg.ptgPlatform||{};if(!cfg.ptgPlatform.restUrl){cfg.ptgPlatform.restUrl=%6$s;}cfg.ptgPlatform.nonce=%2$s;cfg.ptgPlatform.userId=%7$d;cfg.ptgPlatform.timezone=%8$s;var queue=[{check:function(){return typeof cfg.PTGPlatform!=="undefined";},url:%3$s},{check:function(){return typeof cfg.PTGQuizUI!=="undefined";},url:%4$s},{check:function(){return typeof cfg.PTGQuiz!=="undefined";},url:%5$s}];function load(i){if(i>=queue.length){return;}var item=queue[i];if(item.check()){load(i+1);return;}var existing=d.querySelector(\'script[data-ptg-src="\'+item.url+\'"]\');if(existing){existing.addEventListener("load",function(){load(i+1);});return;}var s=d.createElement("script");s.src=item.url+(item.url.indexOf("?")===-1?"?ver=1.0.13":"");s.async=false;s.setAttribute("data-ptg-src",item.url);s.onload=function(){load(i+1);};s.onerror=function(){console.error("[PTG Quiz] 스크립트를 불러오지 못했습니다:",item.url);load(i+1);};(d.head||d.body||d.documentElement).appendChild(s);}if(d.readyState==="loading"){d.addEventListener("DOMContentLoaded",function(){load(0);});}else{load(0);}})(document);</script>',
            wp_json_encode( $rest_url ),
            wp_json_encode( $nonce ),
            wp_json_encode( $platform_url ),
            wp_json_encode( $quiz_ui_url ),
            wp_json_encode( $quiz_js_url ),
            wp_json_encode( $rest_base ),
            $user_id,
            wp_json_encode( $timezone )
        );

        $template = plugin_dir_path( __FILE__ ) . 'templates/quiz-template.php';

        if ( file_exists( $template ) && is_readable( $template ) ) {
            ob_start();

            // 템플릿에서 사용할 수 있도록 어트리뷰트 정리
            $atts = [
                'question_id' => absint( $question_id ),
                'timer'       => absint( $atts['timer'] ),
                'unlimited'   => $atts['unlimited'],
            ];

            // JS 로더 출력
            echo $loader_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            include $template;

            return ob_get_clean();
        }

        // 템플릿이 없을 경우 최소한의 마크업만 출력
        ob_start();
        ?>
        <div id="ptg-quiz-container"
             class="ptg-quiz-container"
             data-question-id="<?php echo esc_attr( absint( $question_id ) ); ?>"
             data-timer="<?php echo esc_attr( absint( $atts['timer'] ) ); ?>"
             data-unlimited="<?php echo esc_attr( $atts['unlimited'] ? '1' : '0' ); ?>">
            <div class="ptg-quiz-loading">
                <p>문제를 불러오는 중...</p>
            </div>
        </div>
        <?php echo $loader_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php
        return ob_get_clean();
    }
}

// 플러그인 인스턴스 생성
PTG_Quiz_Plugin::get_instance();


