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

// 디버깅: 문제 ID 확인
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[PTG Quiz Template] question_id: ' . $question_id . ', timer: ' . $timer_minutes);
}

?>

<!-- 디버깅: 템플릿 변수 확인 -->
<?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
<!-- DEBUG: question_id=<?php echo $question_id; ?>, timer=<?php echo $timer_minutes; ?> -->
<?php endif; ?>

<div id="ptg-quiz-container" 
     class="ptg-quiz-container" 
     data-question-id="<?php echo esc_attr($question_id); ?>"
     data-timer="<?php echo esc_attr($timer_minutes); ?>"
     data-unlimited="<?php echo esc_attr($is_unlimited ? '1' : '0'); ?>">
    
    <!-- 문제 ID 확인 메시지 (문제 ID가 없을 때만 표시) -->
    <?php if (!$question_id): ?>
    <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 10px; border-radius: 4px; color: #856404;">
        <strong>⚠️ 문제 ID가 지정되지 않았습니다!</strong><br>
        Shortcode 사용법: <code>[ptg_quiz question_id="380"]</code>
    </div>
    <?php endif; ?>
    
    <!-- 도구바 -->
    <div class="ptg-quiz-toolbar">
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
        <button type="button" class="ptg-btn-icon ptg-btn-flashcard" aria-label="암기카드 만들기" title="암기카드 만들기">
            <span class="ptg-icon">🃏</span>
        </button>
        <button type="button" class="ptg-btn-icon ptg-btn-notebook" aria-label="노트에 추가" title="노트에 추가">
            <span class="ptg-icon">📓</span>
        </button>
    </div>
    
    <!-- 타이머 영역 -->
    <?php if (!$is_unlimited): ?>
    <div class="ptg-quiz-timer">
        <span class="ptg-timer-label">남은 시간:</span>
        <span class="ptg-timer-display" id="ptg-timer-display"><?php echo esc_html($timer_minutes); ?>:00</span>
    </div>
    <?php endif; ?>
    
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
        </div>
        
        <!-- 드로잉 캔버스 오버레이 -->
        <div class="ptg-drawing-overlay" id="ptg-drawing-overlay" style="display: none;">
            <canvas id="ptg-drawing-canvas"></canvas>
            <div class="ptg-drawing-toolbar">
                <button type="button" class="ptg-btn-draw" data-tool="pen">✏️ 펜</button>
                <button type="button" class="ptg-btn-draw" data-tool="eraser">🧹 지우개</button>
                <button type="button" class="ptg-btn-draw" data-tool="undo">↶ 실행 취소</button>
                <button type="button" class="ptg-btn-draw" data-tool="redo">↷ 다시 실행</button>
                <button type="button" class="ptg-btn-draw" data-tool="clear">🗑️ 전체 지우기</button>
                <button type="button" class="ptg-btn-close-drawing">닫기 (Esc)</button>
            </div>
        </div>
    </div>
    
    <!-- 정답 확인 버튼 -->
    <div class="ptg-quiz-actions">
        <button type="button" class="ptg-btn ptg-btn-primary" id="ptg-btn-check-answer" disabled>
            정답 확인
        </button>
        <button type="button" class="ptg-btn ptg-btn-secondary" id="ptg-btn-next-question" style="display: none;">
            다음 문제
        </button>
    </div>
    
    <!-- 해설 영역 -->
    <div class="ptg-quiz-explanation" id="ptg-quiz-explanation" style="display: none;">
        <!-- 해설이 동적으로 로드됨 -->
    </div>
    
    <!-- 메모 패널 (바텀시트/사이드바) -->
    <div class="ptg-notes-panel" id="ptg-notes-panel" style="display: none;">
        <div class="ptg-notes-header">
            <h3>메모</h3>
            <button type="button" class="ptg-btn-close-notes">✕</button>
        </div>
        <div class="ptg-notes-content">
            <textarea 
                id="ptg-notes-textarea" 
                class="ptg-notes-textarea" 
                placeholder="메모를 입력하세요..."
                rows="10"></textarea>
        </div>
        <div class="ptg-notes-footer">
            <button type="button" class="ptg-btn ptg-btn-primary" id="ptg-btn-save-notes">저장</button>
            <span class="ptg-notes-status" id="ptg-notes-status"></span>
        </div>
    </div>
</div>

<!-- 스크립트 로드 확인 -->
<script type="text/javascript">
// alert 차단 (중복 재정의 에러 방지: 단순 대입만 시도)
(function() {
    'use strict';
    if (typeof window !== 'undefined') {
        try { window.alert = function() { return false; }; } catch (e) {}
    }
})();

// 문제 ID가 없으면 경고
<?php if (!$question_id): ?>
try {
    var container = document.getElementById('ptg-quiz-container');
    if (container) {
        var card = document.getElementById('ptg-quiz-card');
        if (card) {
            card.innerHTML = '<div style="color: red; padding: 20px; text-align: center; background: #fff3cd; border: 2px solid #ffc107; border-radius: 4px;"><strong>⚠️ 문제 ID가 지정되지 않았습니다!</strong><br><br>Shortcode 사용법: <code>[ptg_quiz question_id="380"]</code></div>';
        }
    }
} catch (e) {
    console.error('[PTG Quiz Template] 오류:', e);
}
<?php endif; ?>
</script>

