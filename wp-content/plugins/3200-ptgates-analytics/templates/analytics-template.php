<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="ptg-analytics-app" class="ptg-analytics-container">
    <div class="ptg-loading">성적 분석 모듈 로딩 중...</div>
</div>

<style>
.ptg-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
.ptg-stat-card { background: #f5f5f5; padding: 20px; border-radius: 8px; text-align: center; }
.ptg-stat-value { font-size: 2em; font-weight: bold; color: #333; }
.ptg-weak-list { list-style: none; padding: 0; }
.ptg-weak-list li { display: flex; align-items: center; margin-bottom: 10px; }
.ptg-subject-name { width: 120px; font-weight: bold; }
.ptg-progress-bar { flex-grow: 1; height: 10px; background: #e0e0e0; border-radius: 5px; margin: 0 10px; overflow: hidden; }
.ptg-progress-fill { height: 100%; background: #ff6b6b; }
.ptg-accuracy { width: 50px; text-align: right; }
</style>
