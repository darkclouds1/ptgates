<?php
if (!defined('ABSPATH')) {
    exit;
}

// 스크립트 버전 관리
$js_file = defined('PTG_BOOKMARKS_PATH') ? PTG_BOOKMARKS_PATH . 'assets/js/bookmarks.js' : plugin_dir_path(dirname(__FILE__)) . 'assets/js/bookmarks.js';
$js_ver = file_exists($js_file) ? filemtime($js_file) : '0.1.0';
$js_url = defined('PTG_BOOKMARKS_URL') ? PTG_BOOKMARKS_URL . 'assets/js/bookmarks.js' : plugin_dir_url(dirname(__FILE__)) . 'assets/js/bookmarks.js';

// REST API 설정
$rest_url = get_rest_url(null, 'ptg-bookmarks/v1/');
$nonce = wp_create_nonce('wp_rest');

// Quiz 페이지 URL 가져오기
if (!function_exists('ptg_get_quiz_url')) {
    function ptg_get_quiz_url() {
        $quiz_page_id = get_option('ptg_quiz_page_id');
        if ($quiz_page_id) {
            $url = get_permalink($quiz_page_id);
            if ($url) return rtrim($url, '/');
        }
        
        // Fallback: search for page with [ptg_quiz]
        $query = new \WP_Query([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        
        if ($query->have_posts()) {
            foreach ($query->posts as $page_id) {
                $page = get_post($page_id);
                if ($page && has_shortcode($page->post_content, 'ptg_quiz')) {
                    update_option('ptg_quiz_page_id', $page_id);
                    return rtrim(get_permalink($page_id), '/');
                }
            }
        }
        wp_reset_postdata();
        
        return home_url('/ptg_quiz/');
    }
}
$quiz_url = ptg_get_quiz_url();

// 대시보드 페이지 URL 가져오기
if (!function_exists('ptg_get_dashboard_url')) {
    function ptg_get_dashboard_url() {
        $dashboard_page_id = get_option('ptg_dashboard_page_id');
        if ($dashboard_page_id) {
            $url = get_permalink($dashboard_page_id);
            if ($url) return rtrim($url, '/');
        }
        
        $query = new \WP_Query([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        
        if ($query->have_posts()) {
            foreach ($query->posts as $page_id) {
                $page = get_post($page_id);
                if ($page && has_shortcode($page->post_content, 'ptg_dashboard')) {
                    update_option('ptg_dashboard_page_id', $page_id);
                    return rtrim(get_permalink($page_id), '/');
                }
            }
        }
        wp_reset_postdata();
        
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

    /* Header Styles matching 2200-ptgates-flashcards */
    .ptg-bookmarks-header {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        margin-top: 5px !important;
        margin-bottom: 18px !important;
        padding: 12px 14px !important;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 12px !important;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06) !important;
    }

    .ptg-bookmarks-header h1 {
        margin: 0 !important;
        font-size: 18px !important;
        font-weight: 700 !important;
        color: #0f172a !important;
        letter-spacing: -0.01em !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
    }

    .ptg-header-actions {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        flex-shrink: 0 !important;
    }

    .ptg-header-btn {
        display: inline-flex;
        align-items: center;
        padding: 9px 18px;
        background: #f5f6f8;
        color: #1f2937;
        font-size: 14px;
        font-weight: 600;
        border-radius: 999px;
        text-decoration: none !important;
        border: 1px solid #e5e7eb;
        cursor: pointer;
        transition: all 0.2s ease;
        font-family: inherit;
        white-space: nowrap;
    }

    .ptg-header-btn:hover {
        background: #e9ecf1;
        color: #111827;
    }

    .ptg-dashboard-link {
        display: inline-block;
        background: #edf2f7; /* Base background from flashcards */
        font-size: 13px !important;
        font-weight: 600 !important;
        color: #4a5568 !important;
        text-decoration: none !important;
        padding: 6px 12px !important;
        border-radius: 6px !important;
        transition: all 0.2s ease !important;
        white-space: nowrap !important;
    }

    .ptg-dashboard-link:hover {
        background: #f1f5f9 !important;
        color: #2d3748 !important;
        text-decoration: underline !important;
    }

    .ptg-btn-quiz-start {
        padding: 6px 12px !important;
        background: #3b82f6;
        color: #ffffff;
        border: none;
        border-radius: 6px !important;
        font-weight: 500;
        font-size: 13px !important;
        cursor: pointer;
        transition: background 0.2s ease;
        white-space: nowrap !important;
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
        justify-content: flex-start;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f3f4f6;
    }
    
    /* 선택지 보기 버튼 숨기기 - 헤더 내 모든 버튼 제거 */
    .ptg-bookmark-card-header button,
    .ptg-bookmark-card-header .ptg-bookmark-toggle,
    .ptg-bookmark-card-header button[class*="toggle-options"],
    .ptg-bookmark-card-header button[class*="options-toggle"] {
        display: none !important;
        visibility: hidden !important;
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
        display: block !important;
        flex-direction: column !important;
        flex-wrap: nowrap !important;
        border: 2px solid #e9ecef;
        border-radius: 6px;
        padding: 10px 12px;
        background: white;
        box-sizing: border-box;
        visibility: visible !important;
        opacity: 1 !important;
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
