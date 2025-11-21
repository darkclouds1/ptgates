<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="ptg-selftest-app" class="ptg-selftest-container">
    <h2>셀프 모의고사 생성</h2>
    <form id="ptg-selftest-form">
        <div class="ptg-form-group">
            <label for="ptg-st-subject">과목 선택</label>
            <select id="ptg-st-subject" class="ptg-select">
                <option value="all">전체 과목</option>
                <!-- Subjects should be dynamically loaded or hardcoded based on known subjects -->
                <option value="1교시">1교시 전체</option>
                <option value="2교시">2교시 전체</option>
            </select>
        </div>

        <div class="ptg-form-group">
            <label for="ptg-st-count">문항 수</label>
            <select id="ptg-st-count" class="ptg-select">
                <option value="10">10문제 (맛보기)</option>
                <option value="20" selected>20문제 (추천)</option>
                <option value="40">40문제 (실전)</option>
                <option value="80">80문제 (하드코어)</option>
            </select>
        </div>

        <div class="ptg-form-actions">
            <button type="submit" class="ptg-btn ptg-btn-primary ptg-btn-lg">모의고사 시작</button>
        </div>
    </form>
</div>
