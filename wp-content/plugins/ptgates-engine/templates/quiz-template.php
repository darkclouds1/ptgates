<?php
/**
 * PTGates Quiz Template
 * 
 * Shortcode에서 로드되는 퀴즈 UI 템플릿
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="ptgates-quiz-container" class="ptgates-quiz-wrapper" 
     data-year="<?php echo esc_attr($atts['year']); ?>"
     data-subject="<?php echo esc_attr($atts['subject']); ?>"
     data-limit="<?php echo esc_attr($atts['limit']); ?>">
    
    <!-- 플러그인 헤더 -->
    <div id="ptgates-header" class="ptgates-header">
        <div class="ptgates-header-content">
            <h1 class="ptgates-header-title">기출 문제 학습</h1>
        </div>
    </div>
    
    <!-- 필터 섹션 -->
    <div class="ptgates-filter-section">
        <div class="ptgates-filter-row">
            <label for="ptgates-filter-year">연도:</label>
            <select id="ptgates-filter-year" class="ptgates-filter-input">
                <option value="">전체</option>
            </select>
        </div>
        
        <div class="ptgates-filter-row">
            <label for="ptgates-filter-subject">과목:</label>
            <select id="ptgates-filter-subject" class="ptgates-filter-input">
                <option value="">전체</option>
            </select>
        </div>
        
        <div class="ptgates-filter-row">
            <label for="ptgates-filter-limit">문제 수:</label>
            <select id="ptgates-filter-limit" class="ptgates-filter-input">
                <option value="5">5문제</option>
                <option value="10" selected>10문제</option>
                <option value="20">20문제</option>
                <option value="50">50문제</option>
            </select>
        </div>
        
        <button id="ptgates-start-btn" class="ptgates-btn ptgates-btn-primary">시작</button>
    </div>
    
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
    
    <!-- 문제 표시 영역 -->
    <div id="ptgates-question-section" class="ptgates-question-section" style="display: none;">
        <div class="ptgates-question-header">
            <h3 id="ptgates-question-text" class="ptgates-question-text"></h3>
        </div>
        
        <div id="ptgates-options-container" class="ptgates-options-container">
            <!-- 옵션들이 동적으로 삽입됨 -->
        </div>
        
        <div class="ptgates-question-actions">
            <button id="ptgates-submit-btn" class="ptgates-btn ptgates-btn-submit">답안 제출</button>
            <button id="ptgates-next-btn" class="ptgates-btn ptgates-btn-next" style="display: none;">다음 문제</button>
        </div>
        
        <!-- 해설 표시 영역 -->
        <div id="ptgates-explanation-section" class="ptgates-explanation-section" style="display: none;">
            <div class="ptgates-explanation-header">
                <h4>해설</h4>
            </div>
            <div id="ptgates-base-explanation" class="ptgates-base-explanation"></div>
            <div id="ptgates-advanced-explanation" class="ptgates-advanced-explanation"></div>
        </div>
        
        <!-- 정답 피드백 -->
        <div id="ptgates-feedback" class="ptgates-feedback" style="display: none;">
            <div id="ptgates-feedback-message"></div>
        </div>
    </div>
    
    <!-- 결과 요약 -->
    <div id="ptgates-result-section" class="ptgates-result-section" style="display: none;">
        <h2>학습 완료!</h2>
        <div class="ptgates-result-stats">
            <div class="ptgates-stat-item">
                <span class="ptgates-stat-label">정답률:</span>
                <span id="ptgates-result-accuracy" class="ptgates-stat-value">0%</span>
            </div>
            <div class="ptgates-stat-item">
                <span class="ptgates-stat-label">맞힌 문제:</span>
                <span id="ptgates-result-correct" class="ptgates-stat-value">0</span>
            </div>
            <div class="ptgates-stat-item">
                <span class="ptgates-stat-label">틀린 문제:</span>
                <span id="ptgates-result-incorrect" class="ptgates-stat-value">0</span>
            </div>
            <div class="ptgates-stat-item">
                <span class="ptgates-stat-label">소요 시간:</span>
                <span id="ptgates-result-time" class="ptgates-stat-value">00:00</span>
            </div>
        </div>
        <button id="ptgates-restart-btn" class="ptgates-btn ptgates-btn-primary">다시 시작</button>
    </div>
    
    <!-- 로딩 표시 -->
    <div id="ptgates-loading" class="ptgates-loading" style="display: none;">
        <div class="ptgates-spinner"></div>
        <p>문제를 불러오는 중...</p>
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
