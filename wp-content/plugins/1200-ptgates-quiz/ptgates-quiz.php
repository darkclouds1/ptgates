<?php
/**
 * Plugin Name: PTGates Quiz
 * Plugin URI: https://ptgates.com
 * Description: 문제 풀이 모듈 - 문제 카드, 선택지, 정답확인, 해설, 드로잉, 메모, 북마크 기능
 * Version: 1.0.1
 * Author: PTGates
 * Author URI: https://ptgates.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ptgates-quiz
 * Domain Path: /languages
 * Requires Plugins: 0000-ptgates-platform
 * Requires PHP: 8.1
 * Requires at least: 6.0
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플랫폼 코어 의존성 확인 및 강제 로드
if (!function_exists('ptg_platform_is_active')) {
    // 플랫폼 플러그인이 아직 로드되지 않았을 수 있으므로 직접 로드 시도
    $platform_plugin_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/ptgates-platform.php';
    if (file_exists($platform_plugin_file)) {
        require_once $platform_plugin_file;
    }
}

if (!function_exists('ptg_platform_is_active') || !ptg_platform_is_active()) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>PTGates Quiz</strong> 플러그인을 사용하려면 <strong>PTGates Platform</strong> 플러그인이 활성화되어 있어야 합니다.';
        echo '</p></div>';
    });
    return;
}

// 플러그인 상수 정의
define('PTG_QUIZ_VERSION', '1.0.3');
define('PTG_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PTG_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PTG_QUIZ_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * 플러그인 활성화 시 실행
 */
function ptg_quiz_activate() {
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'ptg_quiz_activate');

/**
 * 플러그인 비활성화 시 실행
 */
function ptg_quiz_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'ptg_quiz_deactivate');

/**
 * 플러그인 메인 클래스
 */
class PTG_Quiz {
    
    /**
     * 싱글톤 인스턴스
     */
    private static $instance = null;
    
    /**
     * 싱글톤 인스턴스 반환
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 생성자
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * 훅 초기화
     */
    private function init_hooks() {
        // 필수 파일 포함
        $this->load_dependencies();
        
        // REST API 등록
        add_action('rest_api_init', array('PTG\Quiz\API', 'register_routes'));
        
        // 디버깅: 플러그인 초기화 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Quiz] 플러그인 초기화 완료 - REST API 훅 등록됨');
        }
        
        // Shortcode 등록
        add_shortcode('ptg_quiz', array($this, 'render_quiz_shortcode'));
        
        // 스타일 및 스크립트 등록
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * 필수 파일 로드
     */
    private function load_dependencies() {
        require_once PTG_QUIZ_PLUGIN_DIR . 'includes/class-api.php';
        require_once PTG_QUIZ_PLUGIN_DIR . 'includes/class-quiz-handler.php';
        
        // 플랫폼 플러그인 클래스 확인
        if (!class_exists('PTG\Platform\Permissions')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>PTGates Quiz</strong>: 플랫폼 플러그인의 필수 클래스를 찾을 수 없습니다.';
                echo '</p></div>';
            });
            return;
        }
    }
    
    /**
     * 스타일 및 스크립트 로드
     */
    public function enqueue_scripts() {
        // 디버깅: 스크립트 로드 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Quiz] enqueue_scripts 호출됨');
        }
        
        // 플랫폼 플러그인의 스크립트가 로드되도록 보장
        // 플랫폼 플러그인이 아직 로드되지 않았을 수 있으므로 직접 enqueue 시도
        if (!wp_script_is('ptg-platform-script', 'registered')) {
            // 플랫폼 스크립트 직접 등록
            // 플랫폼 플러그인 상수가 정의되어 있으면 사용, 없으면 plugins_url 사용
            if (defined('PTG_PLATFORM_PLUGIN_URL')) {
                $platform_js_url = PTG_PLATFORM_PLUGIN_URL . 'assets/js/platform.js';
            } else {
                // plugins_url의 두 번째 인자로 플러그인 메인 파일 경로 전달
                $platform_plugin_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/ptgates-platform.php';
                $platform_js_url = plugins_url('assets/js/platform.js', $platform_plugin_file);
            }
            
            wp_register_script(
                'ptg-platform-script',
                $platform_js_url,
                array('jquery'),
                PTG_PLATFORM_VERSION,
                true
            );
            wp_enqueue_script('ptg-platform-script');
            
            // 플랫폼 설정 localize
            wp_localize_script('ptg-platform-script', 'ptgPlatform', array(
                'restUrl' => rest_url('/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'userId' => get_current_user_id(),
                'timezone' => wp_timezone_string()
            ));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Quiz] ptg-platform-script 직접 등록: ' . $platform_js_url);
            }
        }
        
        if (!wp_script_is('ptg-quiz-ui-script', 'registered')) {
            // 공통 퀴즈 UI 스크립트 직접 등록
            // 플랫폼 플러그인 상수가 정의되어 있으면 사용, 없으면 plugins_url 사용
            if (defined('PTG_PLATFORM_PLUGIN_URL')) {
                $quiz_ui_js_url = PTG_PLATFORM_PLUGIN_URL . 'assets/js/quiz-ui.js';
            } else {
                // plugins_url의 두 번째 인자로 플러그인 메인 파일 경로 전달
                $platform_plugin_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/ptgates-platform.php';
                if (file_exists($platform_plugin_file)) {
                    $quiz_ui_js_url = plugins_url('assets/js/quiz-ui.js', $platform_plugin_file);
                } else {
                    // 최후의 수단: 직접 URL 구성
                    $quiz_ui_js_url = content_url('plugins/0000-ptgates-platform/assets/js/quiz-ui.js');
                }
            }
            
            // 파일 존재 확인 및 URL 검증
            $quiz_ui_js_path = WP_PLUGIN_DIR . '/0000-ptgates-platform/assets/js/quiz-ui.js';
            if (!file_exists($quiz_ui_js_path)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Quiz] ⚠️ quiz-ui.js 파일이 존재하지 않음: ' . $quiz_ui_js_path);
                }
                return; // 파일이 없으면 스크립트 등록 중단
            }
            
            wp_register_script(
                'ptg-quiz-ui-script',
                $quiz_ui_js_url,
                array('ptg-platform-script'),
                PTG_PLATFORM_VERSION,
                true
            );
            wp_enqueue_script('ptg-quiz-ui-script');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Quiz] ptg-quiz-ui-script 직접 등록: ' . $quiz_ui_js_url);
                error_log('[PTG Quiz] 파일 존재 확인: ' . (file_exists($quiz_ui_js_path) ? '예' : '아니오'));
                error_log('[PTG Quiz] PTG_PLATFORM_PLUGIN_URL 정의됨: ' . (defined('PTG_PLATFORM_PLUGIN_URL') ? '예' : '아니오'));
                if (defined('PTG_PLATFORM_PLUGIN_URL')) {
                    error_log('[PTG Quiz] PTG_PLATFORM_PLUGIN_URL 값: ' . PTG_PLATFORM_PLUGIN_URL);
                }
            }
        }
        
        // CSS
        wp_enqueue_style(
            'ptg-quiz-style',
            PTG_QUIZ_PLUGIN_URL . 'assets/css/quiz.css',
            array('ptg-platform-style'),
            PTG_QUIZ_VERSION
        );
        
        // JavaScript 의존성 (조건부로 설정)
        // 의존성이 로드되지 않았을 수 있으므로, 조건부로 처리
        $dependencies = array('jquery');
        
        // ptg-platform-script가 등록되어 있으면 의존성에 추가
        if (wp_script_is('ptg-platform-script', 'registered')) {
            $dependencies[] = 'ptg-platform-script';
        }
        
        // ptg-quiz-ui-script가 등록되어 있으면 의존성에 추가
        if (wp_script_is('ptg-quiz-ui-script', 'registered')) {
            $dependencies[] = 'ptg-quiz-ui-script';
        }
        
        // JavaScript
        wp_enqueue_script(
            'ptg-quiz-script',
            PTG_QUIZ_PLUGIN_URL . 'assets/js/quiz.js',
            $dependencies,
            PTG_QUIZ_VERSION,
            true // footer에 로드
        );
        
        // REST API 엔드포인트 정보를 JS에 전달
        // 스크립트가 등록되어 있을 때만 localize
        if (wp_script_is('ptg-quiz-script', 'registered')) {
            wp_localize_script('ptg-quiz-script', 'ptgQuiz', array(
                'restUrl' => rest_url('ptg-quiz/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'userId' => get_current_user_id(),
            ));
        }
        
        // 디버깅: 스크립트 등록 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Quiz] 스크립트 등록 완료: ptg-quiz-script');
            error_log('[PTG Quiz] 의존성: ' . implode(', ', $dependencies));
            error_log('[PTG Quiz] 스크립트 URL: ' . PTG_QUIZ_PLUGIN_URL . 'assets/js/quiz.js');
            error_log('[PTG Quiz] ptg-platform-script 등록됨: ' . (wp_script_is('ptg-platform-script', 'registered') ? '예' : '아니오'));
            error_log('[PTG Quiz] ptg-quiz-ui-script 등록됨: ' . (wp_script_is('ptg-quiz-ui-script', 'registered') ? '예' : '아니오'));
        }
    }
    
    /**
     * 퀴즈 Shortcode 렌더링
     */
    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'question_id' => '',
            'timer' => '90', // 기본 90분 (1교시)
            'unlimited' => 'false'
        ), $atts, 'ptg_quiz');
        
        // 디버깅: shortcode 실행 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Quiz] Shortcode 실행됨: question_id=' . $atts['question_id']);
        }
        
        // 스크립트가 로드되도록 보장
        $this->enqueue_scripts();
        
        // wp_enqueue_scripts 훅이 이미 실행되었을 수 있으므로
        // 스크립트를 직접 출력하도록 보장
        add_action('wp_footer', function() {
            // 플랫폼 스크립트가 출력되지 않았으면 직접 출력
            if (!wp_script_is('ptg-platform-script', 'done') && !wp_script_is('ptg-platform-script', 'to_do')) {
                $platform_js_url = defined('PTG_PLATFORM_PLUGIN_URL') 
                    ? PTG_PLATFORM_PLUGIN_URL . 'assets/js/platform.js'
                    : plugins_url('assets/js/platform.js', WP_PLUGIN_DIR . '/0000-ptgates-platform/ptgates-platform.php');
                echo '<script src="' . esc_url($platform_js_url) . '?ver=' . (defined('PTG_PLATFORM_VERSION') ? PTG_PLATFORM_VERSION : '1.0.2') . '"></script>' . "\n";
                
                // 플랫폼 설정 localize
                echo '<script type="text/javascript">' . "\n";
                echo 'var ptgPlatform = ' . json_encode(array(
                    'restUrl' => rest_url('/'),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'userId' => get_current_user_id(),
                    'timezone' => wp_timezone_string()
                )) . ';' . "\n";
                echo '</script>' . "\n";
            }
            
            // 퀴즈 UI 스크립트가 출력되지 않았으면 직접 출력
            if (!wp_script_is('ptg-quiz-ui-script', 'done') && !wp_script_is('ptg-quiz-ui-script', 'to_do')) {
                // content_url 사용 (가장 안정적인 방법)
                $quiz_ui_js_url = content_url('plugins/0000-ptgates-platform/assets/js/quiz-ui.js');
                
                // URL 검증 및 디버깅
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Quiz] quiz-ui.js URL 생성 (content_url): ' . $quiz_ui_js_url);
                    error_log('[PTG Quiz] 파일 존재 확인: ' . (file_exists(WP_PLUGIN_DIR . '/0000-ptgates-platform/assets/js/quiz-ui.js') ? '예' : '아니오'));
                }
                
                echo '<script src="' . esc_url($quiz_ui_js_url) . '?ver=1.0.2"></script>' . "\n";
            }
            
            // 퀴즈 스크립트는 enqueue로 로드됨 (중복 방지)
            
            // 퀴즈 설정 localize (스크립트보다 먼저 출력)
            echo '<script type="text/javascript">' . "\n";
            echo 'try {' . "\n";
            echo '  var ptgQuiz = ' . json_encode(array(
                'restUrl' => rest_url('ptg-quiz/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'userId' => get_current_user_id(),
            )) . ';' . "\n";
            echo '} catch(e) {' . "\n";
            echo '  console.error("[PTG Quiz] ptgQuiz 변수 설정 오류:", e);' . "\n";
            echo '}' . "\n";
            echo '</script>' . "\n";
            
            // 직접 출력 제거 (중복 로드 방지)
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Quiz] quiz.js 직접 출력 (항상): ' . $quiz_js_url);
            }
        }, 999); // 높은 우선순위로 실행
        
        ob_start();
        include PTG_QUIZ_PLUGIN_DIR . 'templates/quiz-template.php';
        $output = ob_get_clean();
        
        // 디버깅: 템플릿 출력 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Quiz] 템플릿 출력 길이: ' . strlen($output));
            error_log('[PTG Quiz] 템플릿에 스크립트 포함: ' . (strpos($output, '<script') !== false ? '예' : '아니오'));
        }
        
        return $output;
    }
}

// 플러그인 초기화
PTG_Quiz::get_instance();


