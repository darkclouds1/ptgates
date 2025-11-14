<?php

if (!defined('ABSPATH')) {
    exit;
}

class PTG_Study_Plugin {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register hooks and filters
        $this->register_hooks();
    }

    private function register_hooks() {
        // Actions
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts_styles']);
        add_action('wp_head', [$this, 'inject_critical_styles']);
        
        // Shortcodes
        add_shortcode('ptg_study', [$this, 'render_study_shortcode']);
        
        // Initialize API
        $this->init_api();
    }

    public function enqueue_scripts_styles() {
        // 진단 로그: 함수 호출 확인
        error_log('[PTG Study] enqueue_scripts_styles 함수가 호출되었습니다.');

        global $post;
        
        // 진단 로그: $post 객체 상태 확인
        if ( ! is_a( $post, 'WP_Post' ) ) {
            error_log('[PTG Study] 현재 페이지는 WP_Post 객체가 아닙니다.');
            return;
        }

        // 진단 로그: 숏코드 존재 여부 확인
        if ( has_shortcode( $post->post_content, 'ptg_study' ) ) {
            error_log('[PTG Study] "[ptg_study]" 숏코드를 발견했습니다. 스크립트를 로드합니다.');

            $plugin_dir_url = plugin_dir_url(PTG_STUDY_MAIN_FILE);
            
            // 캐시 방지를 위해 파일 수정 시간을 버전으로 사용합니다.
            $style_path = plugin_dir_path(PTG_STUDY_MAIN_FILE) . 'assets/css/study.css';
            $style_version = file_exists($style_path) ? filemtime($style_path) : '0.1.0';

            // Enqueue styles
            wp_enqueue_style(
                'ptg-study-style',
                $plugin_dir_url . 'assets/css/study.css',
                [],
                $style_version
            );

            // 캐시 방지를 위해 파일 수정 시간을 버전으로 사용합니다.
            $script_path = plugin_dir_path(PTG_STUDY_MAIN_FILE) . 'assets/js/study.js';
            $script_version = file_exists($script_path) ? filemtime($script_path) : '0.1.0';

            // Enqueue scripts
            wp_enqueue_script(
                'ptg-study-script',
                $plugin_dir_url . 'assets/js/study.js',
                ['jquery', 'ptg-platform-script', 'ptg-quiz-ui-script'],
                $script_version,
                true
            );

            // Localize script
            wp_localize_script('ptg-study-script', 'ptgStudy', [
                'api_nonce' => wp_create_nonce('wp_rest'),
                'rest_url' => esc_url_raw(rest_url('ptg-study/v1/')),
            ]);
        
        } else {
            error_log('[PTG Study] 현재 페이지의 콘텐츠에서 "[ptg_study]" 숏코드를 찾지 못했습니다. 스크립트를 로드하지 않습니다.');
        }
    }

    public function render_study_shortcode($atts) {
        // Default attributes
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'ptg_study');

        ob_start();
        ?>
        <div id="ptg-study-app" class="ptg-study-container" data-id="<?php echo esc_attr($atts['id']); ?>">
            <p>Loading study content...</p>
        </div>
        <?php
        return ob_get_clean();
    }

    private function init_api() {
        require_once plugin_dir_path(PTG_STUDY_MAIN_FILE) . 'includes/class-api.php';
        $api = new \PTG\Study\Study_API();
        add_action('rest_api_init', [$api, 'register_routes']);
    }

    /**
     * 핵심 CSS 스타일을 페이지 헤더에 직접 주입합니다.
     * 이는 모든 캐싱 문제와 스타일 충돌을 우회하기 위한 강력한 조치입니다.
     */
    public function inject_critical_styles() {
        // [ptg_study] 숏코드가 있는 페이지에서만 실행
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ptg_study' ) ) {
            ?>
            <style type="text/css" id="ptg-study-critical-styles">
                .ptg-course-categories {
                    display: grid !important;
                    grid-template-columns: repeat(3, 1fr) !important;
                    gap: 24px !important;
                }
                .ptg-subject-list {
                    display: flex !important;
                    flex-wrap: wrap !important;
                    gap: 12px !important;
                }
                .ptg-subject-item {
                    flex-shrink: 0 !important;
                }
                @media (max-width: 768px) {
                    .ptg-course-categories {
                        grid-template-columns: 1fr !important;
                    }
                    .ptg-subject-list {
                        flex-direction: column !important;
                    }
                    .ptg-subject-item {
                        width: 100% !important;
                        text-align: center !important;
                    }
                }
            </style>
            <?php
        }
    }
}
