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

// Study 페이지 URL 가져오기
$study_url = PTG_Dashboard::get_study_url();

// 인라인 스타일 (간단한 스타일은 여기에 포함, 복잡하면 css 파일로 분리 권장)
?>
<style>

    .ptg-dashboard-container {
        max-width: 900px !important;
        margin: 10px auto !important;
        padding: 10px !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    
    .ptg-dash-welcome {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        margin-top: 0;
        margin-bottom: 24px;
        padding: 20px 24px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .ptg-dash-welcome h2 {
        margin: 0;
        font-size: 18px;
        color: #1f2937;
        font-weight: 600;
    }
    .ptg-greeting {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .ptg-greeting.is-english {
        cursor: help;
    }
    .ptg-greeting.is-english::after {
        content: attr(data-translation);
        position: absolute;
        left: 0;
        top: calc(100% + 8px);
        font-size: 12px;
        line-height: 1.4;
        color: #374151;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 8px 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        white-space: normal;
        min-width: 220px;
        max-width: 360px;
        opacity: 0;
        transform: translateY(4px);
        pointer-events: none;
        transition: opacity 0.2s ease, transform 0.2s ease;
        z-index: 10;
    }
    .ptg-greeting.is-english:hover::after {
        opacity: 1;
        transform: translateY(0);
    }
    .ptg-dash-premium-badge {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        border: 1px solid;
    }
    .ptg-dash-premium-badge.is-active {
        background: #eff6ff;
        color: #1e40af;
        border-color: #bfdbfe;
    }
    .ptg-dash-premium-badge.is-free {
        background: #f9fafb;
        color: #6b7280;
        border-color: #e5e7eb;
    }
    .ptg-dash-premium-badge small {
        display: block;
        font-size: 0.75rem;
        font-weight: 400;
        margin-top: 2px;
        opacity: 0.8;
    }
    
    .ptg-dash-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .ptg-dash-card {
        background: #ffffff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: flex-start;
        gap: 16px;
        border: 1px solid #e5e7eb;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .ptg-dash-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .ptg-card-icon {
        font-size: 2rem;
        flex-shrink: 0;
    }
    .ptg-card-content {
        flex: 1;
        min-width: 0;
    }
    .ptg-card-content h3 {
        margin: 0 0 8px 0;
        font-size: 0.875rem;
        color: #6b7280;
        font-weight: 500;
    }
    .ptg-stat-value {
        font-size: 1.75rem;
        font-weight: 600;
        margin: 0 0 12px 0;
        color: #111827;
    }
    .ptg-stat-unit {
        font-size: 0.875rem;
        font-weight: 400;
        color: #6b7280;
    }
    .ptg-progress-bar {
        height: 6px;
        background: #f3f4f6;
        border-radius: 3px;
        margin: 8px 0;
        overflow: hidden;
    }
    .ptg-progress-fill {
        height: 100%;
        background: #3b82f6;
        transition: width 0.3s ease;
    }
    .ptg-stat-desc {
        font-size: 0.75rem;
        color: #6b7280;
        margin: 4px 0 0 0;
    }

    .ptg-dash-banner {
        width: 100%;
        height: 120px;
        margin: 10px 0 30px 0;
        border-radius: 12px;
        background: linear-gradient(135deg, #87CEEB 0%, #E6E6FA 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 40px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }

    .ptg-banner-icon {
        font-size: 3rem;
        opacity: 0.9;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
    }

    .ptg-banner-brain {
        flex-shrink: 0;
    }

    .ptg-banner-bulb {
        flex-shrink: 0;
    }

    .ptg-banner-content {
        flex: 1;
        text-align: center;
        padding: 0 30px;
    }

    .ptg-banner-quote {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: #ffffff;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        line-height: 1.4;
        letter-spacing: 0.02em;
    }

    @media (max-width: 768px) {
        .ptg-dash-banner {
            height: auto;
            min-height: 100px;
            padding: 20px;
            flex-direction: column;
            gap: 15px;
        }
        .ptg-dash-stats {
            grid-template-columns: 1fr;
        }

        .ptg-banner-icon {
            font-size: 2rem;
        }

        .ptg-banner-content {
            padding: 0;
        }

        .ptg-banner-quote {
            font-size: 1.2rem;
        }
    }

    .ptg-dash-actions {
        margin-bottom: 30px;
    }

    .ptg-dash-recent-activity {
        margin-bottom: 30px;
    }
    .ptg-action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
    }
    .ptg-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 24px 20px;
        background: #ffffff !important;
        background-color: #ffffff !important;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        color: #374151 !important;
    }
    .ptg-action-btn:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-color: #d1d5db;
        background: #ffffff !important;
        background-color: #ffffff !important;
        color: #374151 !important;
    }
    .ptg-action-btn:hover .label {
        color: #374151 !important;
    }
    .ptg-action-btn .icon {
        font-size: 1.75rem;
        margin-bottom: 8px;
    }
    .ptg-action-btn .label {
        font-weight: 500;
        font-size: 0.875rem;
        color: #374151;
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

    .ptg-dash-learning {
        background: #fff;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        margin-bottom: 30px;
    }

    .ptg-learning-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 18px;
        padding: 12px 14px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(15,23,42,0.06);
    }

    .ptg-learning-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ptg-study-tip-trigger {
        border: 1px solid #dbeafe;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 12px;
        cursor: pointer;
        text-decoration: none;
        padding: 6px 10px;
        border-radius: 9999px;
        line-height: 1;
        transition: all .18s ease;
    }

    .ptg-study-tip-trigger:hover {
        background: #dbeafe;
        border-color: #bfdbfe;
        color: #1e40af;
    }

    .ptg-course-categories {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .ptg-session-group {
        grid-column: 1 / -1;
    }

    .ptg-session-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .ptg-category {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(15,23,42,0.04);
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        overflow: hidden;
    }

    .ptg-category:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15,23,42,0.08);
        border-color: #d1d5db;
    }

    .ptg-category-header {
        padding: 14px 16px 8px 16px;
        border-bottom: 1px solid #f1f5f9;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    }

    /* 1100 Study 헤드 색상 매칭 */
    .ptg-dash-learning .ptg-category[data-category-id="ptg-foundation"] .ptg-category-header {
        background: linear-gradient(180deg, #ecfeff 0%, #f0fdf4 100%);
        border-bottom-color: #dcfce7;
    }
    .ptg-dash-learning .ptg-category[data-category-id="ptg-assessment"] .ptg-category-header {
        background: linear-gradient(180deg, #eff6ff 0%, #e0f2fe 100%);
        border-bottom-color: #dbeafe;
    }
    .ptg-dash-learning .ptg-category[data-category-id="ptg-intervention"] .ptg-category-header {
        background: linear-gradient(180deg, #f5f3ff 0%, #eef2ff 100%);
        border-bottom-color: #e9d5ff;
    }
    .ptg-dash-learning .ptg-category[data-category-id="ptg-medlaw"] .ptg-category-header {
        background: linear-gradient(180deg, #fffbeb 0%, #fef2f2 100%);
        border-bottom-color: #fde68a;
    }

    .ptg-category-title {
        margin: 0 0 6px 0;
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .ptg-category-name {
        flex: 1;
    }

    .ptg-category-total {
        font-size: 14px;
        font-weight: 600;
        color: #475569;
        white-space: nowrap;
    }

    .ptg-category-desc {
        margin: 0;
        font-size: 12px;
        color: #64748b;
    }

    .ptg-session-badge {
        display: inline-block;
        padding: 2px 8px;
        font-size: 12px;
        line-height: 1.4;
        color: #0b3d2e;
        background: #d1fae5;
        border: 1px solid #10b981;
        border-radius: 9999px;
        vertical-align: middle;
    }

    .ptg-subject-list {
        display: grid;
        grid-template-columns: 1fr;
        gap: 6px;
        margin: 0;
        padding: 12px;
    }

    .ptg-subject-item {
        list-style: none;
        padding: 8px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-radius: 20px;
        background-color: #f1f5f9;
        color: #0f172a;
        font-size: 0.875rem;
        cursor: pointer;
        transition: background-color 0.2s, color 0.2s;
        flex-shrink: 0;
    }

    .ptg-subject-item:hover {
        background-color: #e2e8f0;
        color: #0f172a;
    }

    .ptg-subject-item:hover .ptg-subject-counts {
        color:rgb(197, 214, 250) !important;
    }

    @media (max-width: 768px) {
        .ptg-subject-item {
            width: 100%;
            text-align: center;
        }
    }

    .ptg-subject-item .ptg-subject-name {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-right: 12px;
    }

    .ptg-subject-counts {
        font-size: 13px;
        color: #1f3b75;
        white-space: nowrap;
    }

    .ptg-subject-counts strong {
        font-weight: 700;
        color: #111e55;
    }

    .ptg-learning-recent {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .ptg-learning-recent-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 0;
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(15,23,42,0.04);
        overflow: hidden;
    }

    .ptg-learning-column-head {
        padding: 14px 16px;
        margin: 0;
        border-bottom: 1px solid rgba(0,0,0,0.08);
    }

    .ptg-learning-column-head h4 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
    }

    /* 과목 Study 카드 헤더 - 차분한 파란색 계열 */
    .ptg-learning-recent-card.ptg-card-study .ptg-learning-column-head {
        background: linear-gradient(180deg, #e0f2fe 0%, #dbeafe 100%);
        border-bottom-color: #bfdbfe;
    }

    /* 학습 Quiz 카드 헤더 - 활기찬 보라색 계열 */
    .ptg-learning-recent-card.ptg-card-quiz .ptg-learning-column-head {
        background: linear-gradient(180deg, #f3e8ff 0%, #e9d5ff 100%);
        border-bottom-color: #d8b4fe;
    }

    .ptg-learning-recent-card > *:not(.ptg-learning-column-head) {
        padding: 16px;
    }

    .ptg-learning-header {
        margin-top: 0;
    }

    .ptg-learning-day {
        border: 1px solid #e4e9f5;
        border-radius: 12px;
        padding: 14px 16px;
        margin: 10px 14px 10px 14px;
        background: #fdfdff;
        box-shadow: 0 6px 16px rgba(13, 31, 68, 0.04);
        cursor: pointer;
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
    }

    .ptg-learning-day:hover,
    .ptg-learning-day.is-active {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(130, 151, 202, 0.08);
        border-color: #d1d5db;
        background: #fdfdff !important;
    }
    .ptg-learning-day:focus,
    .ptg-learning-day:focus-within {
        background: #fdfdff !important;
        outline: none;
    }

    .ptg-learning-date-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        margin: 0;
        padding: 0;
        background: transparent;
        border: none;
        cursor: pointer;
        font: inherit;
        color: inherit;
        text-align: left;
    }
    .ptg-learning-date-row:hover {
        background:rgb(248, 250, 252) !important;
    }
    .ptg-learning-date-row:focus,
    .ptg-learning-date-row:focus-visible {
        background: rgb(248, 250, 252) !important;
        outline: none;
        color: inherit !important;
    }
    .ptg-learning-date-row:focus .ptg-learning-date,
    .ptg-learning-date-row:focus-visible .ptg-learning-date {
        color: #142a63 !important;
    }

    .ptg-learning-date {
        font-weight: 700;
        color: #142a63;
        font-size: 0.95rem;
    }

    .ptg-learning-total {
        font-weight: 700;
        color: #4250d4;
        font-size: 0.9rem;
        background: #e8edff;
        padding: 2px 10px;
        border-radius: 999px;
    }

    .ptg-learning-toggle {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: rgba(255,255,255,0.65);
        color: #22336b;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-left: 10px;
        box-shadow: inset 0 1px 3px rgba(15,23,42,0.15);
        transition: transform 0.2s ease;
    }

    .ptg-learning-day:not(.is-open) .ptg-learning-toggle {
        transform: rotate(180deg);
    }

    .ptg-learning-day-content {
        margin-top: 12px;
    }

    .ptg-learning-lines {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .ptg-learning-line {
        display: flex;
        justify-content: space-between;
        font-size: 0.92rem;
        padding: 4px 2px;
        color: #1d2f4f;
    }

    .ptg-learning-line-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .ptg-learning-line-count {
        font-weight: normal;
        color: #1d2f4f;
    }

    .ptg-learning-tip-modal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }

    .ptg-learning-tip-modal.is-open {
        display: flex;
    }

    .ptg-learning-tip-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.55);
        backdrop-filter: blur(2px);
    }

    .ptg-learning-tip-dialog {
        position: relative;
        width: min(600px, calc(100% - 40px));
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
        overflow: hidden;
        animation: ptg-tip-fade-in 0.3s ease;
        max-height: 80vh;
        display: flex;
        flex-direction: column;
    }

    .ptg-learning-tip-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
        color: #fff;
    }

    .ptg-learning-tip-header h3 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
    }

    .ptg-learning-tip-close {
        background: #ffffff;
        border: none;
        color: #1d3f7c;
        font-size: 24px;
        width: 36px;
        height: 36px;
        border-radius: 999px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .ptg-learning-tip-body {
        padding: 24px;
        overflow-y: auto;
        flex: 1;
    }

    .ptg-learning-tip-body section {
        margin-bottom: 20px;
    }

    .ptg-learning-tip-body h4 {
        margin: 0 0 10px;
        font-size: 16px;
        color: #0f172a;
    }

    .ptg-learning-tip-body ul {
        margin: 0;
        padding-left: 18px;
        color: #4b5563;
        font-size: 14px;
    }

    @keyframes ptg-tip-fade-in {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .ptg-no-data-sm {
        font-size: 0.9rem;
        color: #90a4ae;
    }
    
    /* 버튼 스타일 */
    .ptg-btn {
        padding: 8px 16px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: #ffffff;
        color: #374151;
        width: 100%;
    }
    .ptg-btn-primary {
        background: #ffffff;
        color: #374151;
        border-color: #e5e7eb;
    }
    .ptg-btn-primary:hover {
        background: #f9fafb;
        border-color: #d1d5db;
    }
    .ptg-btn-sm {
        padding: 6px 12px;
        font-size: 0.875rem;
    }
</style>

<div id="ptg-dashboard-app" class="ptg-dashboard-container">
    <div class="ptg-loading">대시보드를 불러오는 중...</div>
</div>

<script>
window.ptg_dashboard_vars = {
    rest_url: '<?php echo esc_url($rest_url); ?>',
    nonce: '<?php echo esc_js($nonce); ?>',
    study_url: '<?php echo esc_url($study_url); ?>'
};

// 인라인 스크립트 로더
(function() {
    var script = document.createElement('script');
    script.src = '<?php echo esc_url($js_url); ?>?ver=<?php echo esc_attr($js_ver); ?>';
    script.async = true;
    document.body.appendChild(script);
})();
</script>
