<?php
if (!defined('ABSPATH')) {
    exit;
}

// 스크립트 버전 관리
$js_file = defined('PTG_DASHBOARD_PATH') ? PTG_DASHBOARD_PATH . 'assets/js/bookmarks.js' : plugin_dir_path(dirname(__FILE__)) . 'assets/js/bookmarks.js';
$js_ver = file_exists($js_file) ? filemtime($js_file) : '0.1.0';
$js_url = defined('PTG_DASHBOARD_URL') ? PTG_DASHBOARD_URL . 'assets/js/bookmarks.js' : plugin_dir_url(dirname(__FILE__)) . 'assets/js/bookmarks.js';

// REST API 설정
$rest_url = get_rest_url(null, 'ptg-dash/v1/');
$nonce = wp_create_nonce('wp_rest');

// Quiz 페이지 URL 가져오기
$quiz_url = PTG_Dashboard::get_quiz_url();

// 대시보드 페이지 URL 가져오기
if (!function_exists('ptg_get_dashboard_url')) {
    function ptg_get_dashboard_url() {
        // 1. 옵션에 저장된 대시보드 페이지 ID 확인
        $dashboard_page_id = get_option('ptg_dashboard_page_id');
        
        // 2. 옵션에 저장된 ID가 있으면 유효성 검사
        if ($dashboard_page_id) {
            $page = get_post($dashboard_page_id);
            if ($page && $page->post_status === 'publish' && has_shortcode($page->post_content, 'ptg_dashboard')) {
                $url = get_permalink($dashboard_page_id);
                if ($url) {
                    return rtrim($url, '/');
                }
            } else {
                delete_option('ptg_dashboard_page_id');
                $dashboard_page_id = null;
            }
        }
        
        // 3. 옵션이 없거나 유효하지 않으면 [ptg_dashboard] 숏코드가 있는 페이지 찾기
        if (!$dashboard_page_id) {
            $query = new \WP_Query(array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
            ));
            
            if ($query->have_posts()) {
                foreach ($query->posts as $page_id) {
                    $page = get_post($page_id);
                    if ($page && has_shortcode($page->post_content, 'ptg_dashboard')) {
                        $dashboard_page_id = $page_id;
                        update_option('ptg_dashboard_page_id', $dashboard_page_id);
                        break;
                    }
                }
            }
            wp_reset_postdata();
        }
        
        // 4. 페이지 ID가 있으면 URL 반환
        if ($dashboard_page_id) {
            $url = get_permalink($dashboard_page_id);
            if ($url) {
                return rtrim($url, '/');
            }
        }
        
        // 5. 찾지 못한 경우 fallback URL 반환
        return home_url('/');
    }
}
$dashboard_url = ptg_get_dashboard_url();
?>

<style>
    .ptg-bookmarks-container {
        max-width: 900px !important;
        margin: 10px auto !important;
        padding: 10px !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    .ptg-bookmarks-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 16px 20px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .ptg-bookmarks-header h1 {
        margin: 0;
        font-size: 20px;
        font-weight: 600;
        color: #1f2937;
    }

    .ptg-btn-quiz-start {
        padding: 8px 16px;
        background: #3b82f6;
        color: #ffffff;
        border: none;
        border-radius: 6px;
        font-weight: 500;
        font-size: 0.875rem;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    .ptg-btn-quiz-start:hover {
        background: #2563eb;
    }

    .ptg-bookmarks-back-link {
        margin-bottom: 12px;
        font-size: 0.875rem;
    }

    .ptg-bookmarks-back-link a {
        color: #3b82f6;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: color 0.2s ease;
    }

    .ptg-bookmarks-back-link a:hover {
        color: #2563eb;
        text-decoration: underline;
    }

    .ptg-bookmarks-back-link a::before {
        content: '←';
        font-size: 1rem;
    }

    .ptg-bookmarks-info {
        padding: 12px 16px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        margin-bottom: 24px;
        font-size: 0.875rem;
        color: #6b7280;
    }

    .ptg-map-link {
        color: #3b82f6;
        text-decoration: underline;
        cursor: pointer;
        font-weight: 500;
        transition: color 0.2s ease;
    }

    .ptg-map-link:hover {
        color: #2563eb;
    }

    .ptg-map-modal-close:hover {
        background: #f3f4f6 !important;
        color: #1f2937 !important;
    }

    .ptg-bookmarks-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .ptg-bookmark-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .ptg-bookmark-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f3f4f6;
    }

    .ptg-bookmark-meta {
        display: flex;
        gap: 12px;
        align-items: center;
        font-size: 0.875rem;
        color: #6b7280;
    }

    .ptg-bookmark-date {
        color: #6b7280;
    }

    .ptg-bookmark-id {
        padding: 2px 8px;
        background: #f3f4f6;
        border-radius: 4px;
        font-family: monospace;
        font-size: 0.8rem;
    }

    .ptg-bookmark-toggle {
        background: none;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 6px 12px;
        font-size: 0.875rem;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .ptg-bookmark-toggle:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }

    .ptg-bookmark-toggle.is-active {
        background: #e5e7eb;
        border-color: #9ca3af;
    }

    .ptg-bookmark-question-text {
        margin: 16px 0;
        font-size: 0.95rem;
        line-height: 1.6;
        color: #1f2937;
        white-space: pre-wrap;
    }

    .ptg-bookmark-number {
        font-size: 0.95rem;
        font-weight: 700;
        color: #000000;
        margin-right: 4px;
    }

    .ptg-bookmark-subject {
        margin-left: 8px;
        padding: 2px 8px;
        background: #f3f4f6;
        border-radius: 4px;
        font-size: 0.75rem;
        color: #6b7280;
    }

    .ptg-bookmark-options {
        margin: 16px 0 !important;
        margin-bottom: 0 !important;
        margin-top: 0 !important;
        width: 100% !important;
        display: none !important;
        flex-direction: column !important;
        flex-wrap: nowrap !important;
        border: 2px solid #e9ecef;
        border-radius: 6px;
        padding: 10px 12px;
        background: white;
        box-sizing: border-box;
    }

    .ptg-bookmark-options.is-visible {
        display: block !important;
    }

    .ptg-bookmark-options > * {
        display: block !important;
        width: 100% !important;
        float: none !important;
        clear: both !important;
        flex: none !important;
        flex-basis: auto !important;
    }

    .ptg-bookmark-option {
        display: flex !important;
        flex-direction: row !important;
        align-items: flex-start;
        padding: 4px 8px !important;
        margin-bottom: 0 !important;
        border: none !important;
        border-radius: 0 !important;
        cursor: default;
        transition: all 0.2s;
        background: transparent !important;
        width: 100% !important;
        box-sizing: border-box;
        min-height: auto;
        user-select: none;
        clear: both;
        font-size: 16px !important;
        line-height: 1.3;
        color: #333 !important;
        white-space: normal !important;
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
    }

    .ptg-bookmark-explanation {
        margin: 16px 0;
        padding: 16px;
        background: #eff6ff;
        border-left: 3px solid #3b82f6;
        border-radius: 6px;
        display: none;
    }

    .ptg-bookmark-explanation.is-visible {
        display: block;
    }

    .ptg-bookmark-explanation-content {
        font-size: 0.9rem;
        line-height: 1.6;
        color: #1f2937;
        white-space: pre-wrap;
    }

    .ptg-bookmark-answer {
        margin-top: 12px;
        padding: 8px 12px;
        background: #dcfce7;
        border-radius: 4px;
        font-weight: 600;
        color: #166534;
    }

    .ptg-bookmarks-empty {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
        font-size: 1rem;
    }

    .ptg-bookmarks-loading {
        text-align: center;
        padding: 40px 20px;
        color: #6b7280;
        font-size: 0.9rem;
    }

    .ptg-bookmarks-load-more {
        text-align: center;
        margin-top: 24px;
    }

    .ptg-btn-load-more {
        padding: 10px 24px;
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-weight: 500;
        font-size: 0.875rem;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .ptg-btn-load-more:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }

    .ptg-btn-load-more:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
</style>

<div id="ptg-bookmarks-app" class="ptg-bookmarks-container">
    <div class="ptg-bookmarks-loading">북마크 목록을 불러오는 중...</div>
</div>

<script>
window.ptg_bookmarks_vars = {
    rest_url: '<?php echo esc_url($rest_url); ?>',
    nonce: '<?php echo esc_js($nonce); ?>',
    quiz_url: '<?php echo esc_url($quiz_url); ?>',
    dashboard_url: '<?php echo esc_url($dashboard_url); ?>'
};

// 인라인 스크립트 로더 (공통 팝업 유틸리티 로드 확인)
(function() {
    function loadBookmarksScript() {
        // tips.js가 로드되었는지 확인 (최대 5초 대기)
        var checkCount = 0;
        var maxChecks = 50; // 5초 (100ms * 50)
        
        function checkAndLoad() {
            checkCount++;
            
            // tips.js가 로드되었거나 최대 대기 시간 초과 시
            if (typeof window.PTGTips !== 'undefined' || checkCount >= maxChecks) {
                // bookmarks.js 로드
                var script = document.createElement('script');
                script.src = '<?php echo esc_url($js_url); ?>?ver=<?php echo esc_attr($js_ver); ?>';
                script.async = false; // 순차 로드 보장
                document.body.appendChild(script);
            } else {
                // 아직 로드되지 않았으면 잠시 후 다시 확인
                setTimeout(checkAndLoad, 100);
            }
        }
        
        checkAndLoad();
    }
    
    // DOM 로드 완료 후 실행
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadBookmarksScript);
    } else {
        loadBookmarksScript();
    }
})();
</script>

