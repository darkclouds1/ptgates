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
     data-needs-review="<?php echo esc_attr($needs_review ? '1' : '0'); ?>">
    
    <!-- 플러그인 헤더 -->
    <div id="ptgates-header" class="ptgates-header">
        <div class="ptgates-header-content">
            <h1 class="ptgates-header-title">실전 모의 학습</h1>
            <a href="#" id="ptg-quiz-tip-btn" class="ptg-quiz-tip-link" aria-label="실전모의 학습Tip">[학습 tip]</a>
        </div>
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
            </select>
        </div>
        
        <button id="ptg-quiz-start-btn" class="ptgates-btn ptgates-btn-primary">조회</button>
    </div>
    
    <!-- 실전 모의 학습Tip 모달 -->
    <div id="ptg-quiz-tip-modal" class="ptg-quiz-tip-modal" style="display: none;">
        <div class="ptg-quiz-tip-modal-overlay"></div>
        <div class="ptg-quiz-tip-modal-content">
            <div class="ptg-quiz-tip-modal-header">
                <h2>실전 모의 학습 가이드</h2>
                <button type="button" class="ptg-quiz-tip-modal-close" aria-label="닫기">&times;</button>
            </div>
            <div class="ptg-quiz-tip-modal-body">
                <div class="ptg-quiz-tip-section">
                    <h3>🎯 교시별 모의고사</h3>
                    <div class="ptg-quiz-tip-grid">
                        <div class="ptg-quiz-tip-card">
                            <h4>1교시 105문항</h4>
                            <ul>
                                <h5>물리치료 기초(60문항)</h5>
                                <li>해부생리학(22) 운동학(12) 물리적 인자치료(16) 공중보건학(10)</li>
                                <h5>물리치료 진단평가(45문항)</h5>
                                <li>근골격계 물리치료 진단평가(10) 신경계 물리치료 진단평가(16) <br>진단평가 원리(6) 심폐혈관계 검사 및 평가(4) 기타 계통 검사(2) 임상의사결정(7)</li>
                            </ul>
                        </div>
                        <div class="ptg-quiz-tip-card">
                            <h4>2교시 85문항</h4>
                            <ul>
                                <h5>물리치료 중재(65문항)</h5>
                                <li>근골격계 중재(28) 신경계 중재(25) 심폐혈관계 중재(5) <br>림프, 피부계 중재(2) 물리치료 문제해결(5)</li>
                                <h5>의료관계법규(20문항)</h5>
                                <li>의료법(5) 의료기사법(5) 노인복지법(4) 장애인복지법(3) 국민건강보험법(3)</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="ptg-quiz-tip-section">
                    <h3>🔍 주요 기능</h3>
                    <ul>
                        <li><strong>기본 퀴즈</strong>: 필터 없이 사용 시 5문제 랜덤 출제</li>
                        <li><strong>교시 선택</strong>: 1교시 또는 2교시 문제만 선택 가능</li>
                        <li><strong>과목 선택</strong>: 특정 과목 문제만 선택 가능</li>
                        <li><strong>문항 수 지정</strong>: 원하는 문제 수만큼 출제 가능</li>
                        <li><strong>북마크/복습</strong>: 북마크하거나 복습 필요한 문제만 풀기 (로그인 필요)</li>
                    </ul>
                </div>
                
                <div class="ptg-quiz-tip-section">
                    <h3>📌 참고사항</h3>
                    <ul>
                        <li>기출문제는 자동으로 제외됩니다</li>
                        <li>전체 교시 모의고사는 국가시험 문항 구성 비율을 자동 적용합니다</li>
                    </ul>
                </div>
            </div>
        </div>
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
                <button id="ptgates-time-tip-btn" class="ptgates-time-tip-btn">[시간관리 tip]</button>
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
                            <button type="button" class="ptg-pen-color-btn" data-color="rgb(255, 255, 0)" style="background-color: rgb(255, 255, 0);" aria-label="노랑" title="노랑"></button>
                            <button type="button" class="ptg-pen-color-btn" data-color="rgb(0, 0, 255)" style="background-color: rgb(0, 0, 255);" aria-label="파랑" title="파랑"></button>
                            <button type="button" class="ptg-pen-color-btn" data-color="rgb(0, 255, 0)" style="background-color: rgb(0, 255, 0);" aria-label="초록" title="초록"></button>
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
                </div>
            </div>
            <button type="button" class="ptg-btn-draw" data-tool="eraser" aria-label="지우개" title="지우개">🧹</button>
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
            <button type="button" class="ptg-btn-icon ptg-btn-review" aria-label="복습 필요" title="복습 필요">
                <span class="ptg-icon">🔁</span>
            </button>
            <button type="button" class="ptg-btn-icon ptg-btn-notes" aria-label="메모" title="메모">
                <span class="ptg-icon">📝</span>
            </button>
            <button type="button" class="ptg-btn-icon ptg-btn-drawing" aria-label="드로잉" title="드로잉">
                <span class="ptg-icon">✏️</span>
            </button>
        </div>
    </div>
    
    <!-- 문제 카드 영역 (드로잉 오버레이 포함) -->
    <div class="ptg-quiz-card-wrapper">
        <div class="ptg-quiz-card" id="ptg-quiz-card">
            <!-- 문제 콘텐츠가 여기에 동적으로 로드됨 -->
            <div class="ptg-quiz-loading">
                <div class="ptg-spinner"></div>
                <p>문제를 불러오는 중...</p>
            </div>
            
            <!-- 선택지 영역 (카드 안에 포함) -->
            <div class="ptg-quiz-choices" id="ptg-quiz-choices">
                <!-- 선택지가 동적으로 로드됨 -->
            </div>
            
            <!-- 해설 영역 (카드 안에 포함) -->
            <div class="ptg-quiz-explanation" id="ptg-quiz-explanation" style="display: none;">
                <!-- 해설이 동적으로 로드됨 -->
            </div>
        </div>
        
    </div>
    
    <!-- 답안 제출 버튼 -->
    <div class="ptg-quiz-actions">
        <button type="button" class="ptg-btn ptg-btn-primary" id="ptg-btn-check-answer" disabled>
            답안 제출
        </button>
        <button type="button" class="ptg-btn ptg-btn-secondary" id="ptg-btn-next-question" style="display: none;">
            다음 문제
        </button>
    </div>
    
    <!-- 드로잉 캔버스 오버레이 (문제 카드 + 해설 영역 포함) -->
    <div class="ptg-drawing-overlay" id="ptg-drawing-overlay" style="display: none;">
        <canvas id="ptg-drawing-canvas"></canvas>
    </div>
    
    <!-- 메모 패널 -->
    <div class="ptg-notes-panel" id="ptg-notes-panel" style="display: none;">
        <div class="ptg-notes-content">
            <textarea 
                id="ptg-notes-textarea" 
                class="ptg-notes-textarea" 
                placeholder="메모를 입력하세요..."
                rows="8"></textarea>
        </div>
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
            <div class="ptg-quiz-stat-item">
                <span class="ptg-quiz-stat-label">틀린 문제:</span>
                <span id="ptg-quiz-result-incorrect" class="ptg-quiz-stat-value">0개</span>
            </div>
            <div class="ptg-quiz-stat-item">
                <span class="ptg-quiz-stat-label">소요 시간:</span>
                <span id="ptg-quiz-result-time" class="ptg-quiz-stat-value">00:00</span>
            </div>
        </div>
        <button id="ptg-quiz-restart-btn" class="ptg-btn ptg-btn-primary">다시 시작</button>
    </div>
    
    <!-- 시간관리 tip 모달 -->
    <div id="ptgates-time-tip-modal" class="ptgates-modal" style="display: none;">
        <div class="ptgates-modal-overlay"></div>
        <div class="ptgates-modal-content">
            <div class="ptgates-modal-header">
                <h3>물리치료사 국가시험 시간관리 가이드</h3>
                <button class="ptgates-modal-close" id="ptgates-time-tip-close">&times;</button>
            </div>
            <div class="ptgates-modal-body">
                <p>물리치료사 국가시험은 전체 260문항에 총 250분의 시험 시간이 주어지므로, 전체적으로 한 문제당 평균 약 57.7초를 배분하여 풀어야 합니다.</p>
                
                <p>하지만 각 교시별로 문항 수와 시간이 다르기 때문에, 실제 시험에서는 각 교시의 할당 시간에 맞춰 문제를 풀어야 합니다.</p>
                
                <p>다음은 제48회 국가시험부터 적용된 교시별 평균 소요 시간입니다:</p>
                
                <table class="ptgates-time-table">
                    <thead>
                        <tr>
                            <th>교시</th>
                            <th>시험 과목 (총 문항 수)</th>
                            <th>시험 시간 (분)</th>
                            <th>한 문제당 평균 시간 (초)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1교시</td>
                            <td>물리치료 기초 + 진단평가 (105문항)</td>
                            <td>90분</td>
                            <td>약 51.4초</td>
                        </tr>
                        <tr>
                            <td>2교시</td>
                            <td>물리치료 중재 + 의료관계법규 (85문항)</td>
                            <td>75분</td>
                            <td>약 52.9초</td>
                        </tr>
                        <tr>
                            <td>3교시</td>
                            <td>실기시험 (70문항)</td>
                            <td>85분</td>
                            <td>약 72.8초</td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="ptgates-tip-summary">
                    <h4>핵심 요약:</h4>
                    <ul>
                        <li><strong>필기(1/2교시):</strong> 문제당 약 51~53초로, 1분 이내에 문제를 해결하는 속도가 요구됩니다.</li>
                        <li><strong>실기(3교시):</strong> 문제당 약 73초로, 필기시험에 비해 상대적으로 시간이 더 많이 주어집니다.</li>
                    </ul>
                    <p>물리치료사 국시는 과목 수와 문제 수가 많으므로, 시간 관리가 합격을 좌우하는 중요한 요소입니다. 따라서 실제 시험 시간과 동일하게 모의고사를 치르면서 시간 배분을 철저히 훈련하는 것이 중요합니다.</p>
                </div>
            </div>
        </div>
    </div>
</div>

