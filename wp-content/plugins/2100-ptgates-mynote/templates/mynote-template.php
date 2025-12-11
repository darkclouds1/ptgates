<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dashboard_url = PTG_MyNote_Plugin::get_dashboard_url();
?>
<div id="ptg-mynote-app" class="ptg-mynote-container">
    <div class="ptg-mynote-header">
        <div class="ptg-mynote-header-left">
            <h2>마이노트</h2>
            <a href="<?php echo esc_url( $dashboard_url ); ?>" class="ptg-btn-dashboard">
                ← 학습현황으로 돌아가기
            </a>
        </div>
        <div class="ptg-mynote-controls">
            <input type="text" id="ptg-memo-search" placeholder="검색어 입력..." class="ptg-input">
        </div>
    </div>

    <div class="ptg-memo-list">
        <!-- JS will render list here -->
        <div class="ptg-loading">초기화 중...</div>
    </div>
</div>
