<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="ptg-flash-app" class="ptg-flash-container">
    <!-- Dashboard View -->
    <div id="ptg-flash-dashboard" class="ptg-view active">
        <!-- Header (Quiz Style) -->
        <div class="ptg-flash-header ptg-quiz-header-style">
            <h1>암기카드 학습현황</h1>
            <div class="ptg-flash-header-right">
                <button id="ptg-toggle-create" class="ptg-dash-link" style="margin-right: 5px; border:none; background:none; cursor:pointer;">만들기</button>
                <a href="/dashboard/" class="ptg-dash-link">학습현황</a>
                <a href="#" id="ptg-tip-toggle" class="ptg-tip-link">[암기Tip]</a>
            </div>
        </div>

        <!-- Tip Popup -->
        <div id="ptg-tip-popup" class="ptg-tip-popup" style="display:none;">
            <div class="ptg-tip-content">
                <div class="ptg-tip-header">
                    <h4>💡 암기Tip</h4>
                    <button class="ptg-tip-close" aria-label="닫기">×</button>
                </div>
                <div class="ptg-tip-body">
                    <div class="ptgates-tip-summary">
                        <h5>만들기 (세트 생성)</h5>
                        <p style="margin-bottom: 10px; font-weight: normal;">조건을 선택하고 '만들기'를 누르면 새로운 세트가 생성됩니다.</p>
                        <ul>
                            <li><strong>🎲 랜덤(모의):</strong> 전체 문제은행에서 새로운 문제를 무작위로 가져와 모의고사처럼 학습합니다.</li>
                            <li><strong>🎴 암기카드만:</strong> '암기카드'에 저장해둔 문제들 중에서 선택하여 학습합니다.</li>
                            <li><strong>북마크:</strong> '북마크'한 문제들만 모아서 학습합니다.</li>
                            <li><strong>복습문제만:</strong> '복습 필요'로 체크한 문제들만 학습합니다.</li>
                            <li><strong>틀린문제만:</strong> 최근 퀴즈에서 '틀린' 문제들만 모아서 학습합니다.</li>
                        </ul>
                    </div>

                    <div class="ptgates-tip-summary" style="border-left-color: #ed8936;">
                        <h5>제목 생성 규칙</h5>
                         <p style="margin-bottom: 0; font-weight: normal;">
                            선택한 조건에 따라 자동으로 제목이 정해집니다.<br>
                            (예: 모의 암기카드 (전체:190문제) > 1교시 > 물리치료 기초 > 해부생리학 )
                        </p>
                    </div>

                    <div class="ptgates-tip-summary" style="border-left-color: #48bb78;">
                        <h5>학습 버튼 (SRS)</h5>
                        <ul>
                            <li><strong>다시 (< 1분):</strong> 카드를 몰라서 바로 다시 봐야 합니다. (진행도 초기화)</li>
                            <li><strong>어려움 (2일):</strong> 맞췄지만 어려웠습니다. (짧은 간격으로 복습)</li>
                            <li><strong>알맞음 (3일):</strong> 적당한 난이도입니다. (표준 간격으로 복습)</li>
                            <li><strong>쉬움 (5일):</strong> 너무 쉽습니다. (긴 간격으로 복습)</li>
                        </ul>
                    </div>
                    
                    <p style="font-size: 0.9em; color: #666; margin-top: 10px;">
                        * 이미 생성된 조건은 중복해서 만들어지지 않습니다.
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Section (Quiz Style) -->
        <div class="ptgates-filter-section" style="display:none;">
            <div class="ptgates-filter-row">
                <select id="ptg-flash-filter-random" class="ptgates-filter-input">
                    <option value="random" selected>랜덤</option>
                    <option value="flashcard">암기카드만</option>
                    <option value="bookmark">북마크</option>
                    <option value="review">복습문제만</option>
                    <option value="wrong">틀린문제만</option>
                </select>
            </div>

            <div class="ptgates-filter-row">
                <select id="ptg-flash-filter-session" class="ptgates-filter-input">
                    <option value="">교시</option>
                    <option value="1">1교시</option>
                    <option value="2">2교시</option>
                </select>
            </div>
            
            <div class="ptgates-filter-row">
                <select id="ptg-flash-filter-subject" class="ptgates-filter-input">
                    <option value="">과목</option>
                </select>
            </div>
            
            <div class="ptgates-filter-row">
                <select id="ptg-flash-filter-subsubject" class="ptgates-filter-input">
                    <option value="">세부과목</option>
                </select>
            </div>

            <div class="ptgates-filter-row">
                <select id="ptg-flash-filter-limit" class="ptgates-filter-input">
                    <option value="5" selected>5문제</option>
                    <option value="10">10문제</option>
                    <option value="20">20문제</option>
                    <option value="30">30문제</option>
                    <option value="50">50문제</option>
                    <option value="full">전체</option>
                </select>
            </div>

            <button id="ptg-create-set-btn" class="ptgates-btn ptgates-btn-primary">만들기</button>
        </div>

        <div id="ptg-sets-grid" class="ptg-sets-grid">
            <div class="ptg-loading">세트 목록을 불러오는 중...</div>
        </div>
    </div>

    <!-- Study View -->
    <div id="ptg-flash-study" class="ptg-view">
        <div class="ptg-study-header">
            <button class="ptg-btn-back" id="ptg-back-to-dash">← 나가기</button>
            <div class="ptg-study-progress">
                <span id="ptg-progress-text">0 / 0</span>
                <div class="ptg-progress-bar"><div class="ptg-progress-fill" id="ptg-progress-fill"></div></div>
            </div>
        </div>
        
        <div class="ptg-card-container">
            <div class="ptg-flashcard" id="ptg-active-card">
                <div class="ptg-card-inner">
                    <div class="ptg-card-front">
                        <div class="ptg-card-content" id="ptg-card-front-content"></div>
                        <div class="ptg-card-hint">Space 또는 클릭하여 뒤집기</div>
                    </div>
                    <div class="ptg-card-back">
                        <div class="ptg-card-content" id="ptg-card-back-content"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ptg-study-controls" id="ptg-study-controls">
            <button class="ptg-btn-srs ptg-srs-again" data-quality="again">
                <span class="ptg-srs-label">다시</span>
                <span class="ptg-srs-time">&lt; 1분</span>
                <span class="ptg-srs-key">1</span>
            </button>
            <button class="ptg-btn-srs ptg-srs-hard" data-quality="hard">
                <span class="ptg-srs-label">어려움</span>
                <span class="ptg-srs-time">2일</span>
                <span class="ptg-srs-key">2</span>
            </button>
            <button class="ptg-btn-srs ptg-srs-good" data-quality="good">
                <span class="ptg-srs-label">알맞음</span>
                <span class="ptg-srs-time">3일</span>
                <span class="ptg-srs-key">3</span>
            </button>
            <button class="ptg-btn-srs ptg-srs-easy" data-quality="easy">
                <span class="ptg-srs-label">쉬움</span>
                <span class="ptg-srs-time">5일</span>
                <span class="ptg-srs-key">4</span>
            </button>
        </div>
    </div>

    <div id="ptg-flash-result" class="ptg-view">
        <div class="ptg-result-content">
            <h3>🎉 학습 완료!</h3>
            <p>오늘 계획된 모든 카드를 학습했습니다.</p>
            <button class="ptg-btn ptg-btn-primary" id="ptg-result-home">암기카드 목록보기</button>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="ptg-toast" class="ptg-toast"></div>
    </div>
</div>
