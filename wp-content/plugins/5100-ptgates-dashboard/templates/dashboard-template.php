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

// --- Membership Logic Start ---
$user = wp_get_current_user();
$user_id = $user->ID;

// Retrieve Member Grade
$grade = 'basic';
if (class_exists('\\PTG\\Platform\\Repo')) {
    $member = \PTG\Platform\Repo::find_one('ptgates_user_member', ['user_id' => $user_id]);
    if ($member && !empty($member['member_grade'])) {
        $grade = $member['member_grade'];
        // Check for Trial Expiry
        if ($grade === 'trial' && !empty($member['billing_expiry_date']) && strtotime($member['billing_expiry_date']) < time()) {
            $grade = 'basic';
        }
    }
}

// URL 설정
$account_url = function_exists('um_get_core_page') ? um_get_core_page('account') : home_url('/account');
$logout_url = add_query_arg([
    'ptg_action' => 'logout',
    '_wpnonce'   => wp_create_nonce('ptg_logout')
], home_url());

// 통계 데이터 조회
global $wpdb;

// 1. 과목|Study (Study Count > 0)
$study_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT question_id) FROM ptgates_user_states WHERE user_id = %d AND study_count > 0",
    $user_id
));
$study_count = $study_count ? intval($study_count) : 0;

// 2. 실전|Quiz (Quiz Count > 0)
$quiz_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT question_id) FROM ptgates_user_states WHERE user_id = %d AND quiz_count > 0",
    $user_id
));
$quiz_count = $quiz_count ? intval($quiz_count) : 0;

// 3. 암기카드 (Flashcards)
$flashcard_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM ptgates_flashcards WHERE user_id = %d",
    $user_id
));
$flashcard_count = $flashcard_count ? intval($flashcard_count) : 0;

// --- Limit Calculation ---

// 1. Study Limits
$study_conf = get_option('ptg_conf_study', []);
$study_limit_guest = isset($study_conf['LIMIT_GUEST_VIEW']) ? (int)$study_conf['LIMIT_GUEST_VIEW'] : 10;
$study_limit_free = isset($study_conf['LIMIT_FREE_VIEW']) ? (int)$study_conf['LIMIT_FREE_VIEW'] : 20;

$study_limit = $study_limit_guest; // Default guest
if ($grade === 'basic' || $grade === 'trial') {
    $study_limit = $study_limit_free;
} elseif ($grade === 'premium' || $grade === 'pt_admin') {
    $study_limit = 999999; // Unlimited
}

// 2. Quiz Limits
$quiz_conf = get_option('ptg_conf_quiz', []);
$quiz_limit_basic = isset($quiz_conf['LIMIT_QUIZ_QUESTIONS']) ? (int)$quiz_conf['LIMIT_QUIZ_QUESTIONS'] : 20;
$quiz_limit_trial = isset($quiz_conf['LIMIT_TRIAL_QUESTIONS']) ? (int)$quiz_conf['LIMIT_TRIAL_QUESTIONS'] : 50;

$quiz_limit = 0; // Guest has 0
if ($grade === 'basic') {
    $quiz_limit = $quiz_limit_basic;
} elseif ($grade === 'trial') {
    $quiz_limit = $quiz_limit_trial;
} elseif ($grade === 'premium' || $grade === 'pt_admin') {
    $quiz_limit = 999999;
}

// 3. Flashcards Limits
$flash_conf = get_option('ptg_conf_flash', []);
$flash_limit_basic = isset($flash_conf['LIMIT_BASIC_CARDS']) ? (int)$flash_conf['LIMIT_BASIC_CARDS'] : 20;
$flash_limit_trial = isset($flash_conf['LIMIT_TRIAL_CARDS']) ? (int)$flash_conf['LIMIT_TRIAL_CARDS'] : 50;

$flashcard_limit = 0;
if ($grade === 'basic') {
    $flashcard_limit = $flash_limit_basic;
} elseif ($grade === 'trial') {
    $flashcard_limit = $flash_limit_trial;
} elseif ($grade === 'premium' || $grade === 'pt_admin') {
    $flashcard_limit = 999999;
}
// --- Membership Logic End ---

// 인라인 스타일 (간단한 스타일은 여기에 포함, 복잡하면 css 파일로 분리 권장)
?>
<style>

    .ptg-dashboard-container {
        max-width: 1000px !important;
        margin: 10px auto !important;
        padding: 10px !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    
    .ptg-dash-welcome {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 24px;
        margin-top: 0;
        margin-bottom: 24px;
        padding: 24px 30px;
        background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    }

    .ptg-welcome-text {
        flex: 1;
        min-width: 0; /* For text truncation if needed */
    }

    .ptg-dash-welcome h2 {
        margin: 0 0 8px 0;
        font-size: 1.5rem; /* Increased size */
        color: #111827;
        font-weight: 800;
        letter-spacing: -0.025em;
    }
    
    .ptg-greeting-wrapper {
        font-size: 1rem;
        color: #4b5563;
        line-height: 1.5;
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
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        border: 1px solid;
        min-width: 130px; /* Fixed width */
        max-width: 130px;
        text-align: center;
        white-space: nowrap; /* Prevent wrapping */
        flex-shrink: 0; /* Prevent shrinking */
        height: fit-content;
    }
    .ptg-dash-premium-badge.is-active {
        background: #eff6ff;
        color: #1e40af;
        border-color: #bfdbfe;
    }
    .ptg-dash-premium-badge.is-free {
        background: #f9fafb;
        color: #4b5563;
        border-color: #e5e7eb;
    }
    .ptg-badge-label {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ptg-settings-icon {
        font-size: 12px;
        opacity: 0.6;
        transition: opacity 0.2s;
    }
    .ptg-dash-premium-badge:hover .ptg-settings-icon {
        opacity: 1;
    }
    .ptg-dash-premium-badge small {
        display: block;
        font-size: 0.7rem;
        font-weight: 400;
        margin-top: 4px;
        opacity: 0.8;
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
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
        cursor: pointer;
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



    @media (max-width: 768px) {
        .ptg-dash-welcome {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
            padding: 16px; /* Reduced padding */
        }
        .ptg-dash-premium-badge {
            width: 100%;
            max-width: none;
            flex-direction: row;
            justify-content: space-between;
            padding: 12px;
        }
        .ptg-dash-premium-badge small {
            margin-top: 0;
            text-align: right;
            width: auto;
            overflow: visible;
            text-overflow: clip;
            white-space: nowrap;
        }
        
        /* Force 2 columns for Stats and Actions on mobile */
        .ptg-dash-stats,
        .ptg-action-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 12px; /* Reduced gap */
        }

        /* Compact card height for mobile */
        .ptg-dash-card {
            padding: 12px 16px;
            min-height: auto;
            align-items: center;
        }
        .ptg-card-icon {
            font-size: 1.5rem; /* Smaller icon */
        }
        .ptg-stat-value {
            font-size: 1.25rem; /* Smaller text */
            margin-bottom: 4px;
        }
        .ptg-card-content h3 {
            font-size: 0.8rem;
            margin-bottom: 2px;
        }
        
        /* Action Card Mobile Optimization */
        .ptg-action-card {
            padding: 12px 16px;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            text-align: left;
        }
        .ptg-action-icon {
            font-size: 1.5rem;
            margin-bottom: 0;
            margin-right: 12px;
        }
        .ptg-action-info {
            flex: 1;
            align-items: flex-start;
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
    /* New Action Card Styles */
    .ptg-action-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        text-decoration: none;
        color: inherit;
    }
    .ptg-action-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-color: #d1d5db;
    }
    .ptg-action-icon {
        font-size: 2rem;
        margin-bottom: 12px;
    }
    .ptg-action-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
    }
    .ptg-action-label {
        font-weight: 600;
        font-size: 0.95rem;
        color: #1f2937;
        margin-bottom: 8px;
    }
    .ptg-action-percent {
        font-size: 0.8rem;
        color: #6b7280;
        margin-top: 4px;
        font-weight: 500;
    }

    /* Small Progress Bar for Action Cards */
    .ptg-progress-sm {
        height: 4px;
        width: 100%;
        background: #f3f4f6;
        border-radius: 2px;
        overflow: hidden;
    }
    .ptg-progress-sm .ptg-progress-fill {
        background: #3b82f6; /* Blue */
        height: 100%;
        border-radius: 2px;
        transition: width 0.5s ease;
    }

    /* Make stats cards clickable */
    .ptg-dash-card {
        cursor: pointer;
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

    /* --- Membership Toggle Styles --- */
    #ptg-membership-details {
        margin-bottom: 24px;
        animation: ptg-slide-down 0.3s ease-out;
    }
    
    @keyframes ptg-slide-down {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .ptg-mb-section {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        padding: 24px;
        margin-bottom: 24px;
        border: 1px solid #e5e7eb;
    }

    .ptg-mb-section-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0 0 20px 0;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ptg-usage-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }

    @media (max-width: 768px) {
        .ptg-usage-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .ptg-usage-item {
            padding: 12px;
        }
        .ptg-usage-value {
            font-size: 14px;
        }
    }

    .ptg-usage-item {
        background: #f9fafb;
        padding: 16px;
        border-radius: 8px;
        border: 1px solid #f3f4f6;
    }

    .ptg-usage-label {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 8px;
        display: block;
    }

    .ptg-usage-value {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
    }

    .ptg-account-links {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 16px;
    }

    .ptg-account-link {
        display: flex;
        align-items: center;
        padding: 16px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        text-decoration: none;
        color: #374151;
        transition: all 0.2s;
    }
    .ptg-account-link:hover {
        border-color: #d1d5db;
        background: #f9fafb;
        transform: translateY(-1px);
    }

    .ptg-footer-actions {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .ptg-footer-buttons {
        display: flex;
        gap: 8px;
    }

    @media (max-width: 768px) {
        .ptg-footer-actions {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }
        .ptg-footer-buttons {
            width: 100%;
            justify-content: flex-end;
        }
    }

    .ptg-link-icon {
        font-size: 20px;
        margin-right: 12px;
        color: #6b7280;
    }
    
    .ptg-link-text {
        flex: 1;
        font-weight: 500;
    }

    .ptg-link-arrow {
        color: #9ca3af;
    }
    /* --- End Membership Toggle Styles --- */
</style>

<div id="ptg-dashboard-app" class="ptg-dashboard-container">
    <div class="ptg-loading">대시보드를 불러오는 중...</div>
</div>

<script>
window.ptg_dashboard_vars = {
    rest_url: '<?php echo esc_url($rest_url); ?>',
    nonce: '<?php echo esc_js($nonce); ?>',
    study_url: '<?php echo esc_url($study_url); ?>',
    // Membership Data
    study_count: <?php echo intval($study_count); ?>,
    quiz_count: <?php echo intval($quiz_count); ?>,
    flashcard_count: <?php echo intval($flashcard_count); ?>,
    study_limit: <?php echo intval($study_limit); ?>,
    quiz_limit: <?php echo intval($quiz_limit); ?>,
    flashcard_limit: <?php echo intval($flashcard_limit); ?>,
    account_url: '<?php echo esc_url($account_url); ?>',
    logout_url: '<?php echo esc_url($logout_url); ?>'
};

// 인라인 스크립트 로더
(function() {
    var script = document.createElement('script');
    script.src = '<?php echo esc_url($js_url); ?>?ver=<?php echo esc_attr($js_ver); ?>';
    script.async = true;
    document.body.appendChild(script);
})();
</script>
