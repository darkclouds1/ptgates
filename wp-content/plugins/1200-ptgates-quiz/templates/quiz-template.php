<?php
/**
 * PTGates Quiz 템플릿
 * 
 * 숏코드 [ptg_quiz] 렌더링
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

$question_id = !empty($atts['question_id']) ? absint($atts['question_id']) : 0;
$timer_minutes = !empty($atts['timer']) ? absint($atts['timer']) : 90;
$is_unlimited = $atts['unlimited'] === 'true' || $atts['unlimited'] === '1';

// 필터 조건 (연속 퀴즈용)
$year = !empty($atts['year']) ? absint($atts['year']) : 0;
$subject = !empty($atts['subject']) ? esc_attr($atts['subject']) : '';
$limit = !empty($atts['limit']) ? absint($atts['limit']) : 0;
$session = !empty($atts['session']) ? absint($atts['session']) : 0;
$full_session = !empty($atts['full_session']) && ($atts['full_session'] === '1' || $atts['full_session'] === 'true');
$bookmarked = !empty($atts['bookmarked']) && ($atts['bookmarked'] === true || $atts['bookmarked'] === '1' || $atts['bookmarked'] === 'true');
$needs_review = !empty($atts['needs_review']) && ($atts['needs_review'] === true || $atts['needs_review'] === '1' || $atts['needs_review'] === 'true');

// 타이머 초기 표시값 계산: 1교시(90분) 또는 2교시(75분)가 아니면 "계산 중..."으로 표시
// JavaScript에서 문제 수를 로드한 후 실제 값으로 업데이트됨
$is_session1 = $timer_minutes === 90;
$is_session2 = $timer_minutes === 75;

// 대시보드 페이지 URL 가져오기
$dashboard_url = home_url('/');
if (class_exists('PTG_Dashboard')) {
    $dashboard_url = PTG_Dashboard::get_dashboard_url();
}

$is_admin = current_user_can('manage_options');

?>

<!-- 디버깅: 템플릿 변수 확인 -->
<?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
<!-- DEBUG: question_id=<?php echo $question_id; ?>, timer=<?php echo $timer_minutes; ?> -->
<?php endif; ?>

<div id="ptg-quiz-container" 
     class="ptg-quiz-container" 
     data-question-id="<?php echo esc_attr($question_id); ?>"
     data-timer="<?php echo esc_attr($timer_minutes); ?>"
     data-unlimited="<?php echo esc_attr($is_unlimited ? '1' : '0'); ?>"
     data-year="<?php echo esc_attr($year); ?>"
     data-subject="<?php echo esc_attr($subject); ?>"
     data-limit="<?php echo esc_attr($limit); ?>"
     data-session="<?php echo esc_attr($session); ?>"
     data-full-session="<?php echo esc_attr($full_session ? '1' : '0'); ?>"
     data-bookmarked="<?php echo esc_attr($bookmarked ? '1' : '0'); ?>"
     data-needs-review="<?php echo esc_attr($needs_review ? '1' : '0'); ?>"
     data-is-admin="<?php echo esc_attr($is_admin ? '1' : '0'); ?>">
    
    <!-- 플러그인 헤더 -->
    <div class="ptg-quiz-header">
        <h1>실전|Quiz</h1>
        <div class="ptgates-filter-checkboxes header-checkboxes">
            <label class="ptg-checkbox-label">
                <span>복습문제만</span>
                <input type="checkbox" id="ptg-quiz-filter-review" value="1">
            </label>
            <label class="ptg-checkbox-label">
                <span>틀린문제만</span>
                <input type="checkbox" id="ptg-quiz-filter-wrong" value="1">
            </label>
        </div>
        
        <div class="ptg-quiz-header-right">
            <a href="<?php echo esc_url($dashboard_url); ?>" class="ptg-quiz-dashboard-link" aria-label="학습현황으로 돌아가기">학습현황</a>
            <a href="#" id="ptg-quiz-tip-btn" class="ptg-quiz-tip-link" aria-label="실전모의 학습Tip">[학습Tip]</a>
        </div>
        <!-- 활성 필터 표시 영역 (모바일에서 두 번째 줄로 표시) -->
        <div id="ptg-quiz-active-filters" class="ptg-quiz-active-filters"></div>
    </div>
    
    <!-- 필터 섹션 -->
    <div id="ptg-quiz-filter-section" class="ptgates-filter-section">
        <div class="ptgates-filter-row">
            <select id="ptg-quiz-filter-session" class="ptgates-filter-input" aria-label="교시">
                <option value="">교시</option>
                <option value="1">1교시</option>
                <option value="2">2교시</option>
            </select>
        </div>
        
        <div class="ptgates-filter-row">
            <select id="ptg-quiz-filter-subject" class="ptgates-filter-input" aria-label="과목">
                <option value="">과목</option>
            </select>
        </div>
		
		<div class="ptgates-filter-row">
			<select id="ptg-quiz-filter-subsubject" class="ptgates-filter-input" aria-label="세부과목">
				<option value="">세부과목</option>
			</select>
		</div>
        
        <div class="ptgates-filter-row">
            <select id="ptg-quiz-filter-limit" class="ptgates-filter-input" aria-label="문항 수">
                <option value="5" selected>5문제</option>
                <option value="10">10문제</option>
                <option value="20">20문제</option>
                <option value="30">30문제</option>
                <option value="50">50문제</option>
                <option value="full">전체 (모의고사)</option>
                <option value="unsolved">안푼 문제만(10문제)</option>
            </select>
        </div>
        

        

        
        <div class="ptgates-filter-actions">
            <button id="ptg-quiz-start-btn" class="ptgates-btn ptgates-btn-primary">조회</button>
            <button id="ptg-quiz-search-toggle" class="ptgates-btn ptgates-btn-icon" aria-label="검색" title="문제ID·검색어로 빠른 검색">
                <span class="dashicons dashicons-search"></span>
            </button>
        </div>
    </div>
    
    <div id="ptg-quiz-search-container" class="ptgates-filter-container ptgates-search-container" style="display: none;">
        <div class="ptgates-filter-row" style="flex: 0 0 80px;">
            <input type="text" id="ptg-quiz-search-id" class="ptgates-filter-input" placeholder="ID">
        </div>
        <div class="ptgates-filter-row" style="flex: 1;">
            <input type="text" id="ptg-quiz-search-keyword" class="ptgates-filter-input" placeholder="지문 또는 해설 검색...">
        </div>
    </div>

    <?php
    // --- 과목 그리드 데이터 준비 ---
    $subjects_map = [];
    if ( class_exists( '\\PTG\\Quiz\\Subjects' ) && method_exists( '\\PTG\\Quiz\\Subjects', 'get_map' ) ) {
        $subjects_map = \PTG\Quiz\Subjects::get_map();
    } elseif ( class_exists( '\\PTG\\Quiz\\Subjects' ) && defined( '\\PTG\\Quiz\\Subjects::MAP' ) ) {
        $subjects_map = \PTG\Quiz\Subjects::MAP;
    }

    $category_meta = [
        '물리치료 기초'   => [ 'id' => 'ptg-foundation', 'description' => '해부생리 · 운동학 · 물리적 인자치료 · 공중보건학' ],
        '물리치료 진단평가' => [ 'id' => 'ptg-assessment', 'description' => '근골격 · 신경계 · 원리 · 심폐혈관 · 기타 · 임상의사결정' ],
        '물리치료 중재'   => [ 'id' => 'ptg-intervention', 'description' => '근골격 · 신경계 · 심폐혈관 · 림프·피부 · 문제해결' ],
        '의료관계법규'    => [ 'id' => 'ptg-medlaw', 'description' => '의료법 · 의료기사법 · 노인복지법 · 장애인복지법 · 건보법' ],
    ];
    ?>

    <!-- 과목 선택 그리드 (Study 플러그인 스타일 복제) -->
    <style>
        .ptg-quiz-course-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .ptg-quiz-session-group {
            grid-column: 1 / -1;
            padding: 0;
        }
        .ptg-quiz-session-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .ptg-quiz-category {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(15,23,42,0.04);
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
            overflow: hidden;
            height: 100%; /* 카드 높이 맞춤 */
        }
        .ptg-quiz-category:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15,23,42,0.08);
            border-color: #d1d5db;
        }
        .ptg-quiz-category-header {
            padding: 14px 16px 8px 16px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }
        .ptg-quiz-category-title {
            margin: 0 0 6px 0;
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ptg-quiz-session-badge {
            display: inline-block;
            padding: 2px 8px;
            margin-right: 8px;
            font-size: 12px;
            line-height: 1.4;
            color: #0b3d2e;
            background: #d1fae5;
            border: 1px solid #10b981;
            border-radius: 9999px;
            vertical-align: middle;
            white-space: nowrap;
        }
        .ptg-quiz-category-desc {
            margin: 0;
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }
        .ptg-quiz-subject-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); /* Quiz 모드에서는 조금 더 좁게 */
            gap: 8px;
            margin: 0;
            padding: 12px 12px 14px 12px;
            list-style: none;
        }
        .ptg-quiz-subject-item {
            padding: 8px 10px;
            min-height: auto;
            display: flex;
            align-items: center;
            justify-content: center; /* 가운데 정렬 */
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f8fafc;
            cursor: pointer;
            transition: all .16s ease;
            color: #0f172a;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
        }
        .ptg-quiz-subject-item:hover {
            background: #eef2ff;
            border-color: #c7d2fe;
            box-shadow: 0 2px 6px rgba(79,70,229,0.12);
            color: #4338ca;
        }
        
        /* Category Theme Colors */
        .ptg-quiz-category[data-category-id="ptg-foundation"] .ptg-quiz-category-header {
            background: linear-gradient(180deg, #ecfeff 0%, #f0fdf4 100%);
            border-bottom-color: #dcfce7;
        }
        .ptg-quiz-category[data-category-id="ptg-foundation"] .ptg-quiz-session-badge {
            color: #064e3b; background: #d1fae5; border-color: #10b981;
        }
        .ptg-quiz-category[data-category-id="ptg-assessment"] .ptg-quiz-category-header {
            background: linear-gradient(180deg, #eff6ff 0%, #e0f2fe 100%);
            border-bottom-color: #dbeafe;
        }
        .ptg-quiz-category[data-category-id="ptg-assessment"] .ptg-quiz-session-badge {
            color: #1e3a8a; background: #dbeafe; border-color: #60a5fa;
        }
        .ptg-quiz-category[data-category-id="ptg-intervention"] .ptg-quiz-category-header {
            background: linear-gradient(180deg, #f5f3ff 0%, #eef2ff 100%);
            border-bottom-color: #e9d5ff;
        }
        .ptg-quiz-category[data-category-id="ptg-intervention"] .ptg-quiz-session-badge {
            color: #3730a3; background: #e0e7ff; border-color: #818cf8;
        }
        .ptg-quiz-category[data-category-id="ptg-medlaw"] .ptg-quiz-category-header {
            background: linear-gradient(180deg, #fffbeb 0%, #fef2f2 100%);
            border-bottom-color: #fde68a;
        }
        .ptg-quiz-category[data-category-id="ptg-medlaw"] .ptg-quiz-session-badge {
            color: #7c2d12; background: #fef3c7; border-color: #f59e0b;
        }

        @media (max-width: 768px) {
            .ptg-quiz-course-categories {
                grid-template-columns: 1fr;
            }
            .ptg-quiz-session-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="ptg-quiz-course-categories" id="ptg-quiz-grid-section">
        <?php if ( ! empty( $subjects_map ) ) : ?>
            <?php foreach ( $subjects_map as $session_key => $session_data ) : ?>
                <?php
                $sess_num = (int) $session_key;
                $subjects = isset( $session_data['subjects'] ) && is_array( $session_data['subjects'] ) ? $session_data['subjects'] : [];
                ?>
                <div class="ptg-quiz-session-group" data-session="<?php echo esc_attr( $sess_num ); ?>">
                    <div class="ptg-quiz-session-grid">
                        <?php foreach ( $subjects as $subject_name => $subject_data ) : ?>
                            <?php
                            $subs          = isset( $subject_data['subs'] ) && is_array( $subject_data['subs'] ) ? $subject_data['subs'] : [];
                            $meta          = isset( $category_meta[ $subject_name ] ) ? $category_meta[ $subject_name ] : [];
                            $category_id   = isset( $meta['id'] ) ? $meta['id'] : sanitize_title( $subject_name );
                            $description   = isset( $meta['description'] ) ? $meta['description'] : '';
                            ?>
                            <div class="ptg-quiz-category" data-category-id="<?php echo esc_attr( $category_id ); ?>">
                                <div class="ptg-quiz-category-header" 
                                     onclick="if(window.PTGQuiz && window.PTGQuiz.selectFilterAndStart) { window.PTGQuiz.selectFilterAndStart(<?php echo $sess_num; ?>, '<?php echo esc_js($subject_name); ?>', ''); }"
                                     style="cursor: pointer;">
                                    <h4 class="ptg-quiz-category-title">
                                        <span class="ptg-quiz-session-badge"><?php echo esc_html( $sess_num ); ?>교시</span>
                                        <?php echo esc_html( $subject_name ); ?>
                                    </h4>
                                    <?php if ( $description ) : ?>
                                        <p class="ptg-quiz-category-desc"><?php echo esc_html( $description ); ?></p>
                                    <?php endif; ?>
                                </div>
                                <ul class="ptg-quiz-subject-list">
                                    <?php foreach ( $subs as $sub_name => $count ) : ?>
                                        <!-- 클릭 이벤트: window.PTGQuiz.selectFilterAndStart(...) 호출 -->
                                        <li class="ptg-quiz-subject-item"
                                            onclick="if(window.PTGQuiz && window.PTGQuiz.selectFilterAndStart) { window.PTGQuiz.selectFilterAndStart(<?php echo $sess_num; ?>, '<?php echo esc_js($subject_name); ?>', '<?php echo esc_js($sub_name); ?>'); }">
                                            <?php echo esc_html( $sub_name ); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- 문제 ID 확인 메시지 제거: 기본값으로 자동 처리됨 -->
    <!-- 에러 메시지가 여기에 표시되지 않도록 확인 -->
    <script>
        // 즉시 실행: 에러 메시지가 있다면 제거 (DOMContentLoaded 전에도 실행)
        (function() {
            function removeErrorMessages() {
                const container = document.getElementById('ptg-quiz-container');
                if (container) {
                    // 모든 자식 요소 확인
                    const allElements = container.querySelectorAll('*');
                    const elementsToRemove = [];
                    
                    allElements.forEach(function(el) {
                        const text = el.textContent || el.innerText || '';
                        if (text.includes('문제 ID가 지정되지 않았습니다') || text.includes('ptg_quiz id=')) {
                            elementsToRemove.push(el);
                        }
                    });
                    
                    // 제거 실행
                    elementsToRemove.forEach(function(el) {
                        el.style.display = 'none';
                        el.remove();
                    });
                    
                    // 직접 텍스트 노드도 확인
                    const walker = document.createTreeWalker(
                        container,
                        NodeFilter.SHOW_TEXT,
                        null,
                        false
                    );
                    
                    let node;
                    while (node = walker.nextNode()) {
                        if (node.textContent.includes('문제 ID가 지정되지 않았습니다') || 
                            node.textContent.includes('ptg_quiz id=')) {
                            node.parentNode.removeChild(node);
                        }
                    }
                }
            }
            
            // 즉시 실행
            removeErrorMessages();
            
            // DOMContentLoaded 후에도 실행
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', removeErrorMessages);
            } else {
                setTimeout(removeErrorMessages, 0);
            }
        })();
    </script>

    <!-- 도구바 -->
    <!-- 위치 이동: 진행 상태 표시 아래로 -->
    
    <!-- 진행 상태 표시 -->
    <div id="ptgates-progress-section" class="ptgates-progress-section" style="display: none;">
        <div class="ptgates-progress-info">
            <span id="ptgates-question-counter">1 / 10</span>
            <div class="ptgates-progress-right">
                <span id="ptgates-timer" class="ptgates-timer">00:00</span>
                <button id="ptgates-time-tip-btn" class="ptgates-time-tip-btn">[시간관리]</button>
                <button id="ptgates-giveup-btn" class="ptgates-btn-giveup-inline">포기하기</button>
            </div>
        </div>
        <div class="ptgates-progress-bar">
            <div id="ptgates-progress-fill" class="ptgates-progress-fill"></div>
        </div>
    </div>
    
    <!-- 도구바 (progress 아래) -->
    <div class="ptg-quiz-toolbar">
        <!-- 드로잉 툴바 (왼쪽) -->
        <div class="ptg-drawing-toolbar" id="ptg-drawing-toolbar" style="display: none;">
            <div class="ptg-pen-controls">
                <button type="button" class="ptg-btn-draw" data-tool="pen" aria-label="펜" title="펜">✏️</button>
                <!-- 펜 색상/두께 선택 메뉴 -->
                <div class="ptg-pen-menu" id="ptg-pen-menu" style="display: none;">
                    <div class="ptg-pen-menu-section">
                        <div class="ptg-pen-menu-label">색상</div>
                        <div class="ptg-pen-color-options">
                            <button type="button" class="ptg-pen-color-btn" data-color="rgb(255, 0, 0)" style="background-color: rgb(255, 0, 0);" aria-label="빨강" title="빨강"></button>
                            <button type="button" class="ptg-pen-color-btn" data-color="rgb(255, 165, 0)" style="background-color: rgb(255, 165, 0);" aria-label="주황" title="주황"></button>
                            <button type="button" class="ptg-pen-color-btn" data-color="rgb(255, 255, 0)" style="background-color: rgb(255, 255, 0);" aria-label="노랑" title="노랑"></button>
                            <button type="button" class="ptg-pen-color-btn" data-color="rgb(0, 255, 0)" style="background-color: rgb(0, 255, 0);" aria-label="초록" title="초록"></button>
                            <button type="button" class="ptg-pen-color-btn" data-color="rgb(0, 0, 255)" style="background-color: rgb(0, 0, 255);" aria-label="파랑" title="파랑"></button>
                            <button type="button" class="ptg-pen-color-btn" data-color="rgb(128, 0, 128)" style="background-color: rgb(128, 0, 128);" aria-label="보라" title="보라"></button>
                            <button type="button" class="ptg-pen-color-btn" data-color="rgb(0, 0, 0)" style="background-color: rgb(0, 0, 0);" aria-label="검정" title="검정"></button>
                        </div>
                    </div>
                    <div class="ptg-pen-menu-section">
                        <div class="ptg-pen-menu-label">두께: <span id="ptg-pen-width-value">10</span>px</div>
                        <div class="ptg-pen-width-slider-wrapper">
                            <input type="range" class="ptg-pen-width-slider" id="ptg-pen-width-slider" min="1" max="30" value="10" aria-label="펜 두께" title="펜 두께">
                        </div>
                    </div>
                    <div class="ptg-pen-menu-section">
                        <div class="ptg-pen-menu-label">불투명도: <span id="ptg-pen-alpha-value">20</span>%</div>
                        <div class="ptg-pen-alpha-slider-wrapper">
                            <input type="range" class="ptg-pen-alpha-slider" id="ptg-pen-alpha-slider" min="0" max="100" value="20" aria-label="펜 불투명도" title="펜 불투명도 (높을수록 진함)">
                        </div>
                    </div>
                    <div class="ptg-pen-menu-section">
                        <label class="ptg-pen-auto-mode-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: #333;">
                            <input type="checkbox" id="ptg-pen-auto-mode" checked>
                            <span>자동 보정 (직선/도형)</span>
                        </label>
                    </div>
                </div>
            </div>
            <button type="button" class="ptg-btn-draw" data-tool="eraser" aria-label="지우개" title="지우개">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" width="18" height="18" fill="currentColor"><path d="M290.7 57.4L57.4 290.7c-25 25-25 65.5 0 90.5l80 80c12 12 28.3 18.7 45.3 18.7H288h9.4H512c17.7 0 32-14.3 32-32s-14.3-32-32-32H387.9L518.6 285.3c25-25 25-65.5 0-90.5L381.3 57.4c-25-25-65.5-25-90.5 0zM297.4 416H288l-105.4 0-80-80L227.3 211.3 364.7 348.7 297.4 416z"/></svg>
            </button>
            <button type="button" class="ptg-btn-draw" data-tool="undo" aria-label="실행 취소" title="실행 취소">↶</button>
            <button type="button" class="ptg-btn-draw" data-tool="redo" aria-label="다시 실행" title="다시 실행">↷</button>
            <button type="button" class="ptg-btn-draw" data-tool="clear" aria-label="전체 지우기" title="전체 지우기">🗑️</button>
            <button type="button" class="ptg-btn-close-drawing" aria-label="닫기" title="닫기 (Esc)">➡️</button>
        </div>
        
        <!-- 우측 아이콘 버튼들 -->
        <div class="ptg-toolbar-icons">
            <button type="button" class="ptg-btn-icon ptg-btn-bookmark" aria-label="북마크" title="북마크">
                <span class="ptg-icon">☆</span>
            </button>
            <!-- <button type="button" class="ptg-btn-icon ptg-btn-review" aria-label="복습 필요" title="복습 필요">
                <span class="ptg-icon">🔁</span>
            </button> -->
            <button type="button" class="ptg-btn-icon ptg-btn-notes" aria-label="메모" title="메모">
                <span class="ptg-icon">📝</span>
            </button>
            <button type="button" class="ptg-btn-icon ptg-btn-flashcard" aria-label="암기카드 생성" title="암기카드 생성">
                <span class="ptg-icon">🗂️</span>
            </button>
            <button type="button" class="ptg-btn-icon ptg-btn-drawing" aria-label="드로잉" title="드로잉">
                <span class="ptg-icon">✏️</span>
            </button>
        </div>
    </div>
    
    <!-- 메모 패널 (툴바 바로 아래로 이동) -->
    <div class="ptg-notes-panel" id="ptg-notes-panel" style="display: none;">
        <div class="ptg-notes-content">
            <textarea 
                id="ptg-notes-textarea" 
                class="ptg-notes-textarea" 
                placeholder="메모를 입력하세요..."
                rows="8"></textarea>
        </div>
    </div>
    
    <!-- 문제 카드 영역 (드로잉 오버레이 포함) -->
    <div class="ptg-quiz-card-wrapper" style="display: none;">
        <div class="ptg-quiz-card" id="ptg-quiz-card">
            <!-- 문제 콘텐츠가 여기에 동적으로 로드됨 -->
            <!-- 문제 콘텐츠가 여기에 동적으로 로드됨 -->

            
            <!-- 선택지 영역 (카드 안에 포함) -->
            <div class="ptg-quiz-choices" id="ptg-quiz-choices">
                <!-- 선택지가 동적으로 로드됨 -->
            </div>
            
            <!-- 해설 영역 (카드 안에 포함) -->
            <div class="ptg-quiz-explanation" id="ptg-quiz-explanation" style="display: none;">
                <!-- 해설이 동적으로 로드됨 -->
            </div>
            
            <!-- 드로잉 캔버스 오버레이 (카드 내부에 배치하여 자동으로 카드 크기와 일치) -->
            <div class="ptg-drawing-overlay" id="ptg-drawing-overlay" style="display: none;">
                <canvas id="ptg-drawing-canvas"></canvas>
            </div>
        </div>
    </div>
    
    <!-- 답안 제출 버튼 -->
    <div class="ptg-quiz-actions">
        <?php if ($is_admin): ?>
        <button type="button" class="ptg-btn ptg-btn-secondary" id="ptg-btn-edit-question">
            [편집]
        </button>
        <button type="button" class="ptg-btn ptg-btn-secondary" id="ptg-btn-cancel-edit" style="display: none;">
            [취소]
        </button>
        <?php endif; ?>
        <button type="button" class="ptg-btn ptg-btn-secondary" id="ptg-btn-prev-question">
            이전 문제
        </button>
        <button type="button" class="ptg-btn ptg-btn-secondary" id="ptg-btn-check-answer">
            정답 확인(해설)
        </button>
        <button type="button" class="ptg-btn ptg-btn-secondary" id="ptg-btn-next-question">
            다음 문제
        </button>
    </div>
    

    
    <!-- 결과 요약 (완료 화면) -->
    <div id="ptg-quiz-result-section" class="ptg-quiz-result-section" style="display: none;">
        <h2>학습 완료!</h2>
        <div class="ptg-quiz-result-stats">
            <div class="ptg-quiz-stat-item">
                <span class="ptg-quiz-stat-label">정답률:</span>
                <span id="ptg-quiz-result-accuracy" class="ptg-quiz-stat-value">0%</span>
            </div>
            <div class="ptg-quiz-stat-item">
                <span class="ptg-quiz-stat-label">맞힌 문제:</span>
                <span id="ptg-quiz-result-correct" class="ptg-quiz-stat-value">0개</span>
            </div>
            <div class="ptg-quiz-stat-item ptg-quiz-stat-incorrect" id="ptg-quiz-stat-incorrect" style="cursor: pointer;">
                <span class="ptg-quiz-stat-label">틀린 문제:</span>
                <span id="ptg-quiz-result-incorrect" class="ptg-quiz-stat-value">0개</span>
            </div>

        </div>
        <div class="ptg-quiz-result-actions">
            <button id="ptg-quiz-restart-btn" class="ptg-btn ptg-btn-secondary">다시 시작</button>
            <button id="ptg-quiz-dashboard-btn" class="ptg-btn ptg-btn-secondary" data-dashboard-url="<?php echo esc_url($dashboard_url); ?>">학습 현황</button>
        </div>
    </div>
    
    <!-- 팝업 HTML은 공통 팝업 유틸리티(0000-ptgates-platform)에서 동적으로 생성됨 -->
</div>

<!-- 팝업 HTML은 공통 팝업 유틸리티(0000-ptgates-platform)에서 동적으로 생성됨 -->

