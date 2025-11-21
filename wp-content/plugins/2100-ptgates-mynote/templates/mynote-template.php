<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="ptg-mynote-app" class="ptg-mynote-container">
    <div class="ptg-mynote-header">
        <h2>마이노트</h2>
        <div class="ptg-mynote-controls">
            <input type="text" id="ptg-note-search" placeholder="검색어 입력..." class="ptg-input">
        </div>
    </div>

    <div class="ptg-mynote-tabs">
        <button class="ptg-tab-btn active" data-type="all">전체</button>
        <button class="ptg-tab-btn" data-type="question">오답노트</button>
        <button class="ptg-tab-btn" data-type="theory">개념노트</button>
        <button class="ptg-tab-btn" data-type="custom">메모</button>
    </div>

    <div class="ptg-note-list">
        <!-- JS will render list here -->
        <div class="ptg-loading">초기화 중...</div>
    </div>
</div>
