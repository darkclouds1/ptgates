<?php
/**
 * Plugin Name: 1200-ptgates-quiz (PTGates Quiz)
 * Description: PTGates 퀴즈 풀이 기능 플러그인.
 * Version: 1.0.18
 * Author: PTGates
 * Requires Plugins: 0000-ptgates-platform
 * 
 * Shortcode: [ptg_quiz]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class PTG_Quiz_Plugin {

    private static $instance = null;

    // --- Configuration Constants ---
    const LIMIT_MOCK_EXAM      = 1;  // 모의고사(1교시/2교시/전체) 1일 제한 횟수
    const LIMIT_QUIZ_QUESTIONS = 20; // Basic(로그인 무료회원) 1일 일반 퀴즈 제한
    const LIMIT_TRIAL_QUESTIONS= 50; // Trial 회원 1일 일반 퀴즈 제한
    const MEMBERSHIP_URL       = '/membership'; // 멤버십 안내 페이지 URL
    // -------------------------------

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 설정 값을 가져옵니다.
     * wp_options에 저장된 'ptg_custom_settings' 배열에서 값을 찾고, 없으면 기본값을 반환합니다.
     */
    public static function get_config( $key, $default = null ) {
        $settings = get_option( 'ptg_custom_settings', [] );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    private function __construct() {
        // 워드프레스 표준에 따라, 각 기능에 맞는 정확한 훅(hook)에 연결합니다.
        
        // 1. REST API는 'rest_api_init' 훅에서 초기화합니다.
        add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
        
        // 2. 숏코드는 'init' 훅에서 등록합니다.
        add_action( 'init', [ $this, 'register_shortcode' ] );

        // 3. 스크립트와 스타일은 'wp_enqueue_scripts' 훅에서 로드합니다.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * REST API를 초기화합니다.
     */
    public function init_rest_api() {
		$rest_api_file     = plugin_dir_path( __FILE__ ) . 'includes/class-api.php';
		$subjects_api_file = plugin_dir_path( __FILE__ ) . 'includes/class-subjects.php';

		// 교시/과목/세부과목 정적 정의 클래스 로드 (플랫폼 코어에서 이미 로드되었으면 생략)
		// 주의: 호환성을 위해 이 코드는 유지하지만, 최초 로드는 0000-ptgates-platform에서 수행됨
		if ( ! class_exists( '\PTG\Quiz\Subjects' ) ) {
			if ( file_exists( $subjects_api_file ) && is_readable( $subjects_api_file ) ) {
				require_once $subjects_api_file;
			}
		}

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
                '1.0.17' // 버전 업데이트로 캐시 무효화
            );
        }
    }

    /**
     * 숏코드를 렌더링합니다.
     */
    public function render_shortcode( $atts ) {
        
        // URL 파라미터에서 값 읽기 (숏코드 속성보다 우선순위가 낮음)
        $url_bookmarked = isset($_GET['bookmarked']) ? sanitize_text_field($_GET['bookmarked']) : null;
        $url_needs_review = isset($_GET['needs_review']) ? sanitize_text_field($_GET['needs_review']) : null;
        $url_year = isset($_GET['year']) ? absint($_GET['year']) : null;
        $url_subject = isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : null;
        $url_limit = isset($_GET['limit']) ? absint($_GET['limit']) : null;
        $url_session = isset($_GET['session']) ? absint($_GET['session']) : null;
        $url_full_session = isset($_GET['full_session']) ? sanitize_text_field($_GET['full_session']) : null;
        
        $atts = shortcode_atts(
            [
                'id'          => null,
                'question_id' => null,
                'timer'       => 90,
                'unlimited'   => '0',
                'year'        => $url_year,  // URL 파라미터 우선
                'subject'     => $url_subject,  // URL 파라미터 우선
                'limit'       => $url_limit,  // URL 파라미터 우선
                'session'     => $url_session,  // URL 파라미터 우선
                'full_session' => $url_full_session ?: '0',  // URL 파라미터 우선
                'bookmarked'  => $url_bookmarked,  // URL 파라미터 우선
                'needs_review' => $url_needs_review,  // URL 파라미터 우선
                'ids'         => null,
            ],
            $atts,
            'ptg_quiz'
        );

        // "id" 또는 "question_id" 어디로 호출해도 대응
        $question_id = $atts['question_id'] ?: $atts['id'];
        
        // 필터 조건 확인 (연속 퀴즈용)
        $has_filters = ! empty( $atts['year'] ) || ! empty( $atts['subject'] ) || ! empty( $atts['limit'] ) || ! empty( $atts['session'] );

        // 1200-ptgates-quiz는 기본적으로 기출문제 제외하고 5문제 연속 퀴즈
        // question_id가 없어도 기본값으로 동작하므로 에러 메시지 제거
        // (JavaScript에서 기본값 처리)
        
        // 에러 메시지 출력하지 않음 - 항상 정상적으로 렌더링

        $plugin_url     = plugin_dir_url( __FILE__ );
        $platform_url   = plugins_url( '0000-ptgates-platform/assets/js/platform.js' );
        $quiz_ui_url    = plugins_url( '0000-ptgates-platform/assets/js/quiz-ui.js' );
        $study_toolbar_url = plugins_url( '1100-ptgates-study/assets/js/study-toolbar.js' );
        $quiz_drawing_url = $plugin_url . 'assets/js/quiz-drawing.js';
        $quiz_toolbar_url = $plugin_url . 'assets/js/quiz-toolbar.js';
        $quiz_js_url    = $plugin_url . 'assets/js/quiz.js';
        $rest_url       = esc_url_raw( rest_url( 'ptg-quiz/v1/' ) );
        $rest_base      = esc_url_raw( rest_url( '/' ) );
        $nonce          = wp_create_nonce( 'wp_rest' );
        $user_id        = get_current_user_id();
        $timezone       = wp_timezone_string();

        // 회원 등급 조회
        $member_grade = 'guest';
        if ( is_user_logged_in() ) {
            // 0000-ptgates-platform의 Repo 클래스 사용
            if ( class_exists( '\PTG\Platform\Repo' ) ) {
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

        $loader_script = sprintf(
            '<script id="ptg-quiz-script-loader">(function(d){var cfg=d.defaultView||window;cfg.ptgQuiz=cfg.ptgQuiz||{};cfg.ptgQuiz.restUrl=%1$s;cfg.ptgQuiz.nonce=%2$s;cfg.ptgQuiz.subjectMap=%12$s;cfg.ptgQuiz.memberGrade=%13$s;cfg.ptgQuiz.limits={mock:%14$d,quiz:%15$d,trial:%16$d};cfg.ptgQuiz.membershipUrl=%17$s;cfg.ptgPlatform=cfg.ptgPlatform||{};if(!cfg.ptgPlatform.restUrl){cfg.ptgPlatform.restUrl=%6$s;}cfg.ptgPlatform.nonce=%2$s;cfg.ptgPlatform.userId=%7$d;cfg.ptgPlatform.timezone=%8$s;var queue=[{check:function(){return typeof cfg.PTGPlatform!=="undefined";},url:%3$s},{check:function(){return typeof cfg.PTGQuizUI!=="undefined";},url:%4$s},{check:function(){return typeof cfg.PTGStudyToolbar!=="undefined";},url:%9$s},{check:function(){return typeof cfg.PTGQuizDrawing!=="undefined";},url:%10$s},{check:function(){return typeof cfg.PTGQuizToolbar!=="undefined";},url:%11$s},{check:function(){return typeof cfg.PTGQuiz!=="undefined";},url:%5$s}];function load(i){if(i>=queue.length){return;}var item=queue[i];if(item.check()){load(i+1);return;}var existing=d.querySelector(\'script[data-ptg-src="\'+item.url+\'"]\');if(existing){existing.addEventListener("load",function(){load(i+1);});return;}var s=d.createElement("script");s.src=item.url+(item.url.indexOf("?")===-1?"?ver=1.0.18":"");s.async=false;s.setAttribute("data-ptg-src",item.url);s.onload=function(){load(i+1);};s.onerror=function(){console.error("[PTG Quiz] 스크립트를 불러오지 못했습니다:",item.url);load(i+1);};(d.head||d.body||d.documentElement).appendChild(s);}if(d.readyState==="loading"){d.addEventListener("DOMContentLoaded",function(){load(0);});}else{load(0);}})(document);</script>',
            wp_json_encode( $rest_url ),
            wp_json_encode( $nonce ),
            wp_json_encode( $platform_url ),
            wp_json_encode( $quiz_ui_url ),
            wp_json_encode( $quiz_js_url ),
            wp_json_encode( $rest_base ),
            $user_id,
            wp_json_encode( $timezone ),
            wp_json_encode( $study_toolbar_url ),
            wp_json_encode( $quiz_drawing_url ),
            wp_json_encode( $quiz_toolbar_url ),
            wp_json_encode( class_exists( '\PTG\Quiz\Subjects' ) ? \PTG\Quiz\Subjects::MAP : [] ),
            wp_json_encode( $member_grade ),
            self::get_config('LIMIT_MOCK_EXAM', 1),
            self::get_config('LIMIT_QUIZ_QUESTIONS', 20),
            self::get_config('LIMIT_TRIAL_QUESTIONS', 50),
            wp_json_encode( home_url( self::get_config('MEMBERSHIP_URL', '/membership') ) )
        );

        $template = plugin_dir_path( __FILE__ ) . 'templates/quiz-template.php';

        if ( file_exists( $template ) && is_readable( $template ) ) {
            ob_start();

            // 템플릿에서 사용할 수 있도록 어트리뷰트 정리
            $atts = [
                'question_id' => absint( $question_id ),
                'timer'       => absint( $atts['timer'] ),
                'unlimited'   => $atts['unlimited'],
                'year'        => $atts['year'] ? absint( $atts['year'] ) : null,
                'subject'     => $atts['subject'] ? sanitize_text_field( $atts['subject'] ) : null,
                'limit'       => $atts['limit'] ? absint( $atts['limit'] ) : null,
                'session'     => $atts['session'] ? absint( $atts['session'] ) : null,
                'full_session' => $atts['full_session'] === '1' || $atts['full_session'] === 'true',
                'bookmarked'  => $atts['bookmarked'] === '1' || $atts['bookmarked'] === 'true',
                'needs_review' => $atts['needs_review'] === '1' || $atts['needs_review'] === 'true',
                'ids'         => $atts['ids'] ? sanitize_text_field( $atts['ids'] ) : null,
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


