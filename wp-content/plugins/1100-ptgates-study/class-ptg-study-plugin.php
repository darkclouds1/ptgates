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

            // JS는 워드프레스 enqueue 대신 숏코드 HTML에서 직접 로드

        } else {
            error_log('[PTG Study] 현재 페이지의 콘텐츠에서 "[ptg_study]" 숏코드를 찾지 못했습니다. 스크립트를 로드하지 않습니다.');
        }
    }

    public function render_study_shortcode($atts) {
        // Default attributes
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'ptg_study');

		// 숏코드 렌더링 시에도 안전하게 스크립트/스타일을 보장 로드
		$plugin_dir_url = plugin_dir_url(PTG_STUDY_MAIN_FILE);

		$style_path = plugin_dir_path(PTG_STUDY_MAIN_FILE) . 'assets/css/study.css';
		$style_version = file_exists($style_path) ? filemtime($style_path) : '0.1.0';
		if ( ! wp_style_is('ptg-study-style', 'enqueued') ) {
			wp_enqueue_style(
				'ptg-study-style',
				$plugin_dir_url . 'assets/css/study.css',
				[],
				$style_version
			);
		}

		$script_path   = plugin_dir_path(PTG_STUDY_MAIN_FILE) . 'assets/js/study.js';
		$script_version = file_exists($script_path) ? filemtime($script_path) : '0.1.0';

		// 공용 UI(PTGQuizUI) 스크립트 경로/버전 (플랫폼 플러그인)
		$platform_quizui_rel   = '/0000-ptgates-platform/assets/js/quiz-ui.js';
		$platform_quizui_path  = WP_PLUGIN_DIR . $platform_quizui_rel;
		$platform_quizui_url   = WP_PLUGIN_URL . $platform_quizui_rel;
		$platform_quizui_ver   = file_exists($platform_quizui_path) ? filemtime($platform_quizui_path) : '1.0.0';

        // 교시/과목/세부과목 정의를 quiz 모듈의 Subjects::MAP에서 가져옴
        $subjects_map = [];
        if ( class_exists( '\\PTG\\Quiz\\Subjects' ) ) {
            $subjects_map = \PTG\Quiz\Subjects::MAP;
        } else {
            $subjects_class_file = WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php';
            if ( file_exists( $subjects_class_file ) ) {
                require_once $subjects_class_file;
                if ( class_exists( '\\PTG\\Quiz\\Subjects' ) ) {
                    $subjects_map = \PTG\Quiz\Subjects::MAP;
                }
            }
        }

        // 과목 카드 ID 및 설명 매핑 (키: 세부 과목 그룹명)
        $category_meta = [
            '물리치료 기초'   => [
                'id'          => 'ptg-foundation',
                'description' => '해부생리 · 운동학 · 물리적 인자치료 · 공중보건학',
            ],
            '물리치료 진단평가' => [
                'id'          => 'ptg-assessment',
                'description' => '근골격 · 신경계 · 원리 · 심폐혈관 · 기타 · 임상의사결정',
            ],
            '물리치료 중재'   => [
                'id'          => 'ptg-intervention',
                'description' => '근골격 · 신경계 · 심폐혈관 · 림프·피부 · 문제해결',
            ],
            '의료관계법규'    => [
                'id'          => 'ptg-medlaw',
                'description' => '의료법 · 의료기사법 · 노인복지법 · 장애인복지법 · 건보법',
            ],
        ];

        ob_start();
        ?>
		<div id="ptg-study-app" class="ptg-study-container" data-id="<?php echo esc_attr($atts['id']); ?>">
            <div class="ptg-study-header">
			    <h2>🗝️학습할 과목을 선택하세요</h2>
                <button type="button" class="ptg-study-tip-trigger" data-ptg-tip-open>
                    [학습Tip]
                </button>
            </div>
			<div class="ptg-course-categories">
                <?php if ( ! empty( $subjects_map ) ) : ?>
                    <?php foreach ( $subjects_map as $session => $session_data ) : ?>
                        <?php
                        $session = (int) $session;
                        $subjects = isset( $session_data['subjects'] ) && is_array( $session_data['subjects'] )
                            ? $session_data['subjects']
                            : [];
                        ?>
                        <div class="ptg-session-group" data-session="<?php echo esc_attr( $session ); ?>">
                            <div class="ptg-session-grid">
                                <?php foreach ( $subjects as $subject_name => $subject_data ) : ?>
                                    <?php
                                    $subject_total = isset( $subject_data['total'] ) ? (int) $subject_data['total'] : 0;
                                    $subs          = isset( $subject_data['subs'] ) && is_array( $subject_data['subs'] ) ? $subject_data['subs'] : [];
                                    $meta          = isset( $category_meta[ $subject_name ] ) ? $category_meta[ $subject_name ] : [];
                                    $category_id   = isset( $meta['id'] ) ? $meta['id'] : sanitize_title( $subject_name );
                                    $description   = isset( $meta['description'] ) ? $meta['description'] : '';
                                    ?>
                                    <section class="ptg-category" data-category-id="<?php echo esc_attr( $category_id ); ?>">
                                        <header class="ptg-category-header">
                                            <h4 class="ptg-category-title">
                                                <span class="ptg-session-badge"><?php echo esc_html( $session ); ?>교시</span>
                                                <?php echo esc_html( $subject_name ); ?>
                                                <?php if ( $subject_total > 0 ) : ?>
                                                    (<?php echo esc_html( $subject_total ); ?>)
                                                <?php endif; ?>
                                            </h4>
                                            <?php if ( $description ) : ?>
                                                <p class="ptg-category-desc"><?php echo esc_html( $description ); ?></p>
                                            <?php endif; ?>
                                        </header>
                                        <ul class="ptg-subject-list ptg-subject-list--stack">
                                            <?php foreach ( $subs as $sub_name => $count ) : ?>
                                                <li class="ptg-subject-item" data-subject-id="<?php echo rawurlencode( $sub_name ); ?>">
                                                    <?php echo esc_html( $sub_name ); ?> (<?php echo (int) $count; ?>)
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p>교과 정보가 준비되지 않았습니다.</p>
                <?php endif; ?>
			</div>

            <!-- 학습 Tip 모달 -->
            <div id="ptg-study-tip-modal" class="ptg-study-tip-modal" aria-hidden="true">
                <div class="ptg-study-tip-backdrop" data-ptg-tip-close></div>
                <div class="ptg-study-tip-dialog" role="dialog" aria-modal="true" aria-labelledby="ptg-study-tip-title">
                    <div class="ptg-study-tip-header">
                        <h3 id="ptg-study-tip-title">기출 학습 가이드</h3>
                        <button type="button" class="ptg-study-tip-close" data-ptg-tip-close aria-label="닫기">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="ptg-study-tip-body">
                        <section class="ptg-study-tip-section">
                            <h4>💡 ptGates Study 프로그램 사용 팁</h4>
                            <ul class="ptg-study-tip-list">
                                <li><strong>암기카드 활용:</strong> 이해가 어렵거나 외울 부분이 많은 개념은 툴바의 암기카드 기능을 이용해 즉시 저장하고 <strong>간격 반복 학습(SRS)</strong>을 활용할 것.</li>
                                <li><strong>취약점 분석:</strong> 학습 후에는 <strong>대시보드(ptgates-analytics)</strong>를 확인하여, 연관 개념 중 취약한 단원을 찾아 복습 우선순위를 정할 것.</li>
                                <li><strong>연속 학습:</strong> 출제 순서 경향을 참조하여 <strong>기초 → 응용</strong> 흐름에 따라 세부 영역 묶음 단위로 끊임없이 학습하는 것을 추천함.</li>
                            </ul>
                        </section>

                        <section class="ptg-study-tip-section">
                            <h4>📌 출제 순서 경향 요약</h4>
                            <ul class="ptg-study-tip-list">
                                <li><strong>기본 흐름:</strong> 출제는 보통 <strong>기초 → 응용 → 임상</strong>의 큰 패턴을 따름.</li>
                                <li><strong>과목별 배치:</strong> 각 과목 내에서 <strong>개론/역학</strong> 같은 범용 개념이 앞쪽에, 세부 응용/임상 사례가 뒤쪽에 배치되는 경향이 명확함.</li>
                            </ul>
                        </section>

                        <section class="ptg-study-tip-section">
                            <h4>🎯 학습 구조</h4>
                            <div class="ptg-tip-block">
                                <h5>교시별 배열</h5>
                                <ul class="ptg-study-tip-list ptg-study-tip-list--sub">
                                    <li><strong>1교시:</strong> 기초(60) → 진단평가(45)</li>
                                    <li><strong>2교시:</strong> 중재(65) → 법규(20)</li>
                                </ul>
                            </div>
                            <div class="ptg-tip-block">
                                <h5>세부 영역 순서</h5>
                                <ul class="ptg-study-tip-list ptg-study-tip-list--sub">
                                    <li><strong>기초:</strong> 해부생리 → 운동학 → 물리적 인자 → 공중보건</li>
                                    <li><strong>중재:</strong> 근골격 → 신경계 → 기타(심폐/피부/문제해결)</li>
                                </ul>
                            </div>
                            <div class="ptg-tip-block">
                                <h5>학습 전략</h5>
                                <ul class="ptg-study-tip-list ptg-study-tip-list--sub">
                                    <li>교시·과목·세부영역 <strong>묶음</strong>으로 연속 학습</li>
                                    <li>정렬 모드로 <strong>흐름</strong> 익힌 뒤, 랜덤으로 <strong>복습</strong></li>
                                </ul>
                            </div>
                        </section>

                        <div class="ptg-tip-legend">
                            <span class="ptg-chip">정렬 학습</span>
                            <span class="ptg-chip">랜덤 복습</span>
                            <span class="ptg-chip">세부영역 집중</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ( ! is_admin() ) : ?>
            <!-- 공용 UI 먼저 로드: PTGQuizUI (플랫폼) -->
            <script src="<?php echo esc_url( $platform_quizui_url ); ?>?ver=<?php echo esc_attr( $platform_quizui_ver ); ?>"></script>
            
            <!-- 스터디 설정 주입 -->
            <script>
                window.ptgStudy = {
                    rest_url: '<?php echo esc_url( get_rest_url( null, 'ptg-study/v1/' ) ); ?>',
                    api_nonce: '<?php echo wp_create_nonce( 'wp_rest' ); ?>',
                    is_user_logged_in: <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
                    login_url: '<?php echo esc_url( add_query_arg( 'redirect_to', urlencode( get_permalink() ), home_url( '/login/' ) ) ); ?>'
                };
            </script>

            <!-- 스터디 전용 스크립트 -->
            <script src="<?php echo esc_url( $plugin_dir_url . 'assets/js/study.js' ); ?>?v=<?php echo esc_attr( $script_version ); ?>"></script>
            
            <!-- 툴바 기능 스크립트 -->
            <script src="<?php echo esc_url( $plugin_dir_url . 'assets/js/study-toolbar.js' ); ?>?v=<?php echo esc_attr( $script_version ); ?>"></script>
        <?php endif; ?>
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
                .ptg-study-header {
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    gap: 12px !important;
                    margin-bottom: 18px !important;
                    padding: 12px 14px !important;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
                    border: 1px solid #e5e7eb !important;
                    border-radius: 12px !important;
                    box-shadow: 0 4px 12px rgba(15,23,42,0.06) !important;
                }
                .ptg-study-tip-trigger {
                    border: 1px solid #dbeafe !important;
                    background: #eff6ff !important;
                    color: #1d4ed8 !important;
                    font-size: 12px !important;
                    cursor: pointer !important;
                    text-decoration: none !important;
                    padding: 6px 10px !important;
                    border-radius: 9999px !important;
                    line-height: 1 !important;
                    transition: all .18s ease !important;
                }
                .ptg-study-tip-trigger:hover {
                    background: #dbeafe !important;
                    border-color: #bfdbfe !important;
                    color: #1e40af !important;
                }
                .ptg-study-header h2 {
                    margin: 0 !important;
                    font-size: 18px !important;
                    font-weight: 700 !important;
                    color: #0f172a !important;
                    letter-spacing: -0.01em !important;
                    display: flex !important;
                    align-items: center !important;
                    gap: 8px !important;
                }
                .ptg-study-header h2::after {
                    content: " - 과목을 선택하면 10문제씩 학습합니다" !important;
                    font-size: 12px !important;
                    font-weight: 500 !important;
                    color: #64748b !important;
                }
                .ptg-study-tip-modal {
                    position: fixed !important;
                    inset: 0 !important;
                    display: none !important;
                    align-items: center !important;
                    justify-content: center !important;
                    z-index: 10000 !important;
                }
                .ptg-study-tip-modal.is-open {
                    display: flex !important;
                }
                .ptg-study-tip-backdrop {
                    position: absolute !important;
                    inset: 0 !important;
                    background: rgba(0,0,0,0.6) !important;
                    backdrop-filter: blur(2px) !important;
                }
                .ptg-study-tip-dialog {
                    position: relative !important;
                    width: min(900px, calc(100% - 40px)) !important;
                    max-height: 90vh !important;
                    background: #ffffff !important;
                    border-radius: 16px !important;
                    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35) !important;
                    overflow: hidden !important;
                    display: flex !important;
                    flex-direction: column !important;
                    animation: ptg-quiz-tip-modal-fade-in 0.3s ease !important;
                }
                .ptg-study-tip-header {
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    gap: 16px !important;
                    padding: 24px 32px !important;
                    background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%) !important;
                    color: #fff !important;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.25) !important;
                    border-top-left-radius: inherit !important;
                    border-top-right-radius: inherit !important;
                    min-height: 72px !important;
                    box-sizing: border-box !important;
                }
                .ptg-study-tip-header h3 {
                    margin: 0 !important;
                    font-size: 24px !important;
                    font-weight: 700 !important;
                    color: #fff !important;
                }
                .ptg-study-tip-close {
                    background: #ffffff !important;
                    border: none !important;
                    color: #1d3f7c !important;
                    font-size: 26px !important;
                    width: 44px !important;
                    height: 44px !important;
                    border-radius: 999px !important;
                    cursor: pointer !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    transition: all 0.2s ease !important;
                    box-shadow: 0 6px 16px rgba(0,0,0,0.15) !important;
                    padding: 0 !important;
                    line-height: 1 !important;
                }
                .ptg-study-tip-close span {
                    pointer-events: none !important;
                }
                .ptg-study-tip-close:hover {
                    background: #f0f4ff !important;
                    color: #0f2a57 !important;
                    transform: scale(1.05) !important;
                }
                .ptg-study-tip-close:focus-visible {
                    outline: 3px solid rgba(255,255,255,0.85) !important;
                    outline-offset: 2px !important;
                }
                .ptg-study-tip-body {
                    padding: 30px !important;
                    overflow-y: auto !important;
                    flex: 1 !important;
                }
                .ptg-study-tip-section {
                    margin-bottom: 30px !important;
                }
                .ptg-study-tip-section:last-child {
                    margin-bottom: 0 !important;
                }
                .ptg-study-tip-section h4 {
                    margin: 0 0 15px 0 !important;
                    font-size: 20px !important;
                    font-weight: 700 !important;
                    color: #333 !important;
                    border-bottom: 2px solid #4a90e2 !important;
                    padding-bottom: 8px !important;
                }
                .ptg-study-tip-list {
                    list-style: none !important;
                    margin: 10px 0 !important;
                    padding: 0 !important;
                }
                .ptg-study-tip-list li {
                    margin-bottom: 10px !important;
                    padding-left: 24px !important;
                    position: relative !important;
                    color: #555 !important;
                    line-height: 1.6 !important;
                    font-size: 15px !important;
                }
                .ptg-study-tip-list li::before {
                    content: "•" !important;
                    position: absolute !important;
                    left: 0 !important;
                    color: #4a90e2 !important;
                    font-weight: 700 !important;
                }
                .ptg-study-tip-list--sub li {
                    color: #444 !important;
                    font-size: 14px !important;
                }
                .ptg-tip-block {
                    background: #f8f9fa !important;
                    border-radius: 8px !important;
                    padding: 15px !important;
                    margin-bottom: 15px !important;
                }
                .ptg-tip-block:last-child {
                    margin-bottom: 0 !important;
                }
                .ptg-tip-block h5 {
                    margin: 0 0 10px 0 !important;
                    color: #333 !important;
                    font-size: 16px !important;
                }
                .ptg-tip-legend {
                    display: flex !important;
                    gap: 8px !important;
                    flex-wrap: wrap !important;
                    margin-top: 10px !important;
                }
                .ptg-chip {
                    display: inline-flex !important;
                    align-items: center !important;
                    padding: 6px 12px !important;
                    border-radius: 16px !important;
                    background: #eff6ff !important;
                    color: #1d4ed8 !important;
                    font-size: 13px !important;
                    font-weight: 600 !important;
                    border: 1px solid #dbeafe !important;
                }
                @media (max-width: 768px) {
                    .ptg-study-tip-body {
                        padding: 24px 20px !important;
                    }
                    .ptg-study-tip-header {
                        padding: 20px !important;
                    }
                }
                .ptg-study-tip-footer {
                    margin-top: 12px !important;
                    padding-top: 8px !important;
                    border-top: 1px solid #e5e7eb !important;
                    font-size: 13px !important;
                    color: #111827 !important;
                }
                .ptg-lesson-pagination {
                    margin-top: 16px !important;
                    padding-top: 10px !important;
                    border-top: 1px solid #e5e7eb !important;
                    display: flex !important;
                    flex-wrap: wrap !important;
                    gap: 8px !important;
                    align-items: center !important;
                }
                .ptg-lesson-page-info {
                    font-size: 13px !important;
                    color: #4b5563 !important;
                    margin-right: auto !important;
                }
                /* 문제 보기(선택지) - 시험지 스타일 */
                .ptg-question-options {
                    list-style: none !important;
                    margin: 8px 0 0 0 !important;
                    padding: 0 !important;
                }
                .ptg-question-option {
                    display: flex !important;
                    align-items: flex-start !important;
                    gap: 8px !important;
                    margin: 0 !important;
                    padding: 2px 0 !important;
                    background: #ffffff !important;
                    border: none !important;
                    box-shadow: none !important;
                }
                .ptg-option-index {
                    display: inline-block !important;
                    min-width: 1.4em !important;
                    color: #111827 !important;
                }
                .ptg-course-categories {
                    display: grid !important;
                    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
                    gap: 20px !important;
                }
                .ptg-session-group {
                    grid-column: 1 / -1 !important;
                    padding: 0 !important;
                    border-top: none !important;
                }
                .ptg-session-group:first-child {
                    border-top: none !important;
                    padding-top: 0 !important;
                }
                .ptg-session-grid {
                    display: grid !important;
                    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
                    gap: 20px !important;
                }
                .ptg-category {
                    border: 1px solid #e5e7eb !important;
                    border-radius: 12px !important;
                    background: #ffffff !important;
                    box-shadow: 0 2px 8px rgba(15,23,42,0.04) !important;
                    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease !important;
                    overflow: hidden !important;
                }
                .ptg-category:hover {
                    transform: translateY(-2px) !important;
                    box-shadow: 0 8px 20px rgba(15,23,42,0.08) !important;
                    border-color: #d1d5db !important;
                }
                .ptg-session-badge {
                    display: inline-block !important;
                    padding: 2px 8px !important;
                    margin-right: 8px !important;
                    font-size: 12px !important;
                    line-height: 1.4 !important;
                    color: #0b3d2e !important;
                    background: #d1fae5 !important; /* emerald-100 */
                    border: 1px solid #10b981 !important; /* emerald-500 */
                    border-radius: 9999px !important;
                    vertical-align: middle !important;
                }
                .ptg-session-badge--sm {
                    font-size: 11px !important;
                    padding: 1px 6px !important;
                    margin-right: 6px !important;
                }
                .ptg-category-header {
                    padding: 14px 16px 8px 16px !important;
                    border-bottom: 1px solid #f1f5f9 !important;
                    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%) !important;
                }
                .ptg-category-title {
                    margin: 0 0 6px 0 !important;
                    font-size: 16px !important;
                    font-weight: 700 !important;
                    color: #0f172a !important;
                    display: flex !important;
                    align-items: center !important;
                    gap: 8px !important;
                }
                .ptg-category-desc {
                    margin: 0 !important;
                    font-size: 12px !important;
                    color: #64748b !important;
                }
                .ptg-subject-list {
                    display: grid !important;
                    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)) !important;
                    gap: 6px !important;
                    margin: 0 !important;
                    padding: 12px 12px 14px 12px !important;
                }
                .ptg-subject-item {
                    list-style: none !important;
                    padding: 0 8px !important;
                    height: auto !important;
                    min-height: 45px !important;
                    display: flex !important;
                    align-items: center !important;
                    border: 1px solid #e5e7eb !important;
                    border-radius: 8px !important;
                    background: #f8fafc !important;
                    cursor: pointer !important;
                    transition: background .16s ease, border-color .16s ease, box-shadow .16s ease !important;
                    color: #0f172a !important;
                    line-height: 1.0 !important;
                    font-size: 14px !important;
                    white-space: nowrap !important;
                    overflow: hidden !important;
                    text-overflow: ellipsis !important;
                }
                .ptg-subject-item:hover {
                    background: #eef2ff !important; /* indigo-50 */
                    border-color: #c7d2fe !important; /* indigo-200 */
                    box-shadow: 0 2px 6px rgba(79,70,229,0.12) !important; /* indigo */
                }

                /* 과목 카드별 컬러 테마 */
                .ptg-category[data-category-id="ptg-foundation"] .ptg-category-header {
                    background: linear-gradient(180deg, #ecfeff 0%, #f0fdf4 100%) !important; /* cyan to green */
                    border-bottom-color: #dcfce7 !important;
                }
                .ptg-category[data-category-id="ptg-foundation"] .ptg-session-badge {
                    color: #064e3b !important;
                    background: #d1fae5 !important;
                    border-color: #10b981 !important;
                }
                .ptg-category[data-category-id="ptg-assessment"] .ptg-category-header {
                    background: linear-gradient(180deg, #eff6ff 0%, #e0f2fe 100%) !important; /* blue to sky */
                    border-bottom-color: #dbeafe !important;
                }
                .ptg-category[data-category-id="ptg-assessment"] .ptg-session-badge {
                    color: #1e3a8a !important;
                    background: #dbeafe !important;
                    border-color: #60a5fa !important;
                }
                .ptg-category[data-category-id="ptg-intervention"] .ptg-category-header {
                    background: linear-gradient(180deg, #f5f3ff 0%, #eef2ff 100%) !important; /* violet to indigo */
                    border-bottom-color: #e9d5ff !important;
                }
                .ptg-category[data-category-id="ptg-intervention"] .ptg-session-badge {
                    color: #3730a3 !important;
                    background: #e0e7ff !important;
                    border-color: #818cf8 !important;
                }
                .ptg-category[data-category-id="ptg-medlaw"] .ptg-category-header {
                    background: linear-gradient(180deg, #fffbeb 0%, #fef2f2 100%) !important; /* amber to rose */
                    border-bottom-color: #fde68a !important;
                }
                .ptg-category[data-category-id="ptg-medlaw"] .ptg-session-badge {
                    color: #7c2d12 !important;
                    background: #fef3c7 !important;
                    border-color: #f59e0b !important;
                }
                @media (max-width: 768px) {
                    .ptg-course-categories {
                        grid-template-columns: 1fr !important;
                    }
                    .ptg-subject-list {
                        grid-template-columns: 1fr !important;
                    }
                }
                /* 정답 및 해설 영역 스타일 */
                .ptg-lesson-answer-area .answer-content {
                    margin-top: 16px !important;
                    padding: 16px 20px !important;
                    background-color: #f8f9fa !important;
                    border-radius: 8px !important;
                    border: 1px solid #e9ecef !important;
                }
                .ptg-lesson-answer-area .answer-content p {
                    margin: 0 0 12px 0 !important;
                }
                .ptg-lesson-answer-area .answer-content p:last-child {
                    margin-bottom: 0 !important;
                }
                .ptg-lesson-answer-area .answer-content hr {
                    margin: 12px 0 !important;
                    border: none !important;
                    border-top: 1px solid #dee2e6 !important;
                }
                /* 헤더 줄 스타일 */
                .ptg-lesson-header-row {
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    gap: 16px !important;
                    margin-bottom: 16px !important;
                }
                .ptg-lesson-header-row h2 {
                    margin: 0 !important;
                }
                .ptg-random-toggle {
                    display: flex !important;
                    align-items: center !important;
                    gap: 8px !important;
                    font-size: 0.9rem !important;
                    cursor: pointer !important;
                    white-space: nowrap !important;
                }
                /* 선택지 간 높이 줄이기 */
                .ptg-question-options {
                    gap: 2px !important;
                }
                .ptg-question-option {
                    padding: 4px 10px !important;
                    line-height: 1.4 !important;
                }
            </style>
            <?php
        }
    }
}
