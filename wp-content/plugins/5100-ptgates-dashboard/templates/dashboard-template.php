<?php
if (!defined('ABSPATH')) {
    exit;
}

// 스크립트 버전 관리
$js_file = plugin_dir_path(dirname(__FILE__)) . 'assets/js/dashboard.js';
$js_ver = file_exists($js_file) ? filemtime($js_file) : '0.1.0';
$js_url = plugin_dir_url(dirname(__FILE__)) . 'assets/js/dashboard.js';

// REST API 설정
$rest_url = get_rest_url(null, 'ptg-dash/v1/');
$nonce = wp_create_nonce('wp_rest');

// 인라인 스타일 (간단한 스타일은 여기에 포함, 복잡하면 css 파일로 분리 권장)
?>
<style>
    .ptg-dashboard-container {
        max-width: 1000px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    .ptg-dash-welcome {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding: 20px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .ptg-dash-welcome h2 {
        margin: 0;
        font-size: 1.5rem;
        color: #333;
    }
    .ptg-dash-premium-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: bold;
    }
    .ptg-dash-premium-badge.is-active {
        background: #e3f2fd;
        color: #1976d2;
    }
    .ptg-dash-premium-badge.is-free {
        background: #f5f5f5;
        color: #616161;
    }
    
    .ptg-dash-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .ptg-dash-card {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .ptg-card-icon {
        font-size: 2.5rem;
    }
    .ptg-card-content {
        flex: 1;
    }
    .ptg-card-content h3 {
        margin: 0 0 5px 0;
        font-size: 1rem;
        color: #666;
    }
    .ptg-stat-value {
        font-size: 2rem;
        font-weight: bold;
        margin: 0;
        color: #333;
    }
    .ptg-stat-unit {
        font-size: 1rem;
        font-weight: normal;
        color: #888;
    }
    .ptg-progress-bar {
        height: 8px;
        background: #eee;
        border-radius: 4px;
        margin: 10px 0;
        overflow: hidden;
    }
    .ptg-progress-fill {
        height: 100%;
        background: #4caf50;
    }
    .ptg-stat-desc {
        font-size: 0.85rem;
        color: #888;
        margin: 0;
    }

    .ptg-dash-actions {
        margin-bottom: 30px;
    }
    .ptg-action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }
    .ptg-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: #fff;
        border: 1px solid #eee;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .ptg-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #ddd;
    }
    .ptg-action-btn .icon {
        font-size: 2rem;
        margin-bottom: 10px;
    }
    .ptg-action-btn .label {
        font-weight: 600;
        color: #333;
    }

    .ptg-dash-recent {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .ptg-activity-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .ptg-activity-item {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #eee;
    }
    .ptg-activity-item:last-child {
        border-bottom: none;
    }
    .ptg-activity-status {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: bold;
        margin-right: 12px;
    }
    .ptg-activity-item.is-correct .ptg-activity-status {
        background: #e8f5e9;
        color: #2e7d32;
    }
    .ptg-activity-item.is-wrong .ptg-activity-status {
        background: #ffebee;
        color: #c62828;
    }
    .ptg-activity-title {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-right: 10px;
    }
    .ptg-activity-date {
        font-size: 0.85rem;
        color: #888;
    }
    
    /* 버튼 스타일 */
    .ptg-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.2s;
    }
    .ptg-btn-primary {
        background: #2196f3;
        color: #fff;
    }
    .ptg-btn-primary:hover {
        background: #1976d2;
    }
    .ptg-btn-sm {
        padding: 6px 12px;
        font-size: 0.9rem;
    }
</style>

<div id="ptg-dashboard-app" class="ptg-dashboard-container">
    <div class="ptg-loading">대시보드를 불러오는 중...</div>
</div>

<script>
window.ptg_dashboard_vars = {
    rest_url: '<?php echo esc_url($rest_url); ?>',
    nonce: '<?php echo esc_js($nonce); ?>'
};

// 인라인 스크립트 로더
(function() {
    var script = document.createElement('script');
    script.src = '<?php echo esc_url($js_url); ?>?ver=<?php echo esc_attr($js_ver); ?>';
    script.async = true;
    document.body.appendChild(script);
})();
</script>
