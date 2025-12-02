<?php
if (!defined('ABSPATH')) {
    exit;
}

// 사용자 정보 가져오기
$user = wp_get_current_user();
$user_id = $user->ID;

// 멤버십 등급 및 상태 확인 (API 로직 복제)
$grade_label = 'Basic';
$premium_status = 'free';
$expiry_date = null;

// 1. DB 테이블 확인
global $wpdb;
$member_table = $wpdb->prefix . 'ptgates_user_member';
$member_data = null;

// 테이블 존재 여부 확인
if ($wpdb->get_var("SHOW TABLES LIKE '$member_table'") === $member_table) {
    $member_data = $wpdb->get_row($wpdb->prepare(
        "SELECT member_grade, billing_expiry_date FROM $member_table WHERE user_id = %d",
        $user_id
    ));
}

if ($member_data) {
    $grade = $member_data->member_grade;
    if ($grade === 'premium' || $grade === 'trial' || $grade === 'pt_admin') {
        $premium_status = 'active';
    }
    
    if ($grade === 'pt_admin') {
        $grade_label = 'Admin';
    } elseif ($grade === 'premium') {
        $grade_label = 'Premium';
    } elseif ($grade === 'trial') {
        $grade_label = 'Trial';
    }
    
    if (!empty($member_data->billing_expiry_date)) {
        $expiry_val = $member_data->billing_expiry_date;
        if (is_numeric($expiry_val)) {
            $expiry_date = date('Y-m-d', (int)$expiry_val);
        } else {
            $expiry_date = date('Y-m-d', strtotime($expiry_val));
        }
    }
} else {
    // 2. Meta 확인
    $meta_status = get_user_meta($user_id, 'ptg_premium_status', true);
    if ($meta_status === 'active') {
        $premium_status = 'active';
        $grade_label = 'Premium';
    }
    
    // 3. Admin 권한 확인
    if (class_exists('\PTG\Platform\Permissions') && \PTG\Platform\Permissions::is_pt_admin($user_id)) {
        $grade_label = 'Admin';
        $premium_status = 'active';
    }
}

// URL 설정
$dashboard_url = remove_query_arg('view');
$account_url = function_exists('um_get_core_page') ? um_get_core_page('account') : home_url('/account');
$logout_url = wp_logout_url($dashboard_url);

?>
<style>
    .ptg-membership-container {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #333;
    }

    .ptg-mb-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
    }

    .ptg-mb-title {
        font-size: 24px;
        font-weight: 700;
        margin: 0;
        color: #111827;
    }

    .ptg-mb-back-btn {
        display: inline-flex;
        align-items: center;
        padding: 8px 16px;
        background-color: #f3f4f6;
        color: #4b5563;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        font-size: 14px;
        transition: background-color 0.2s;
    }
    .ptg-mb-back-btn:hover {
        background-color: #e5e7eb;
        color: #1f2937;
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

    /* Membership Card */
    .ptg-mb-card {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        color: white;
        padding: 30px;
        border-radius: 16px;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    
    .ptg-mb-card.is-basic {
        background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    }

    .ptg-mb-card-content {
        position: relative;
        z-index: 1;
    }

    .ptg-mb-grade {
        font-size: 32px;
        font-weight: 800;
        margin-bottom: 8px;
        letter-spacing: -0.02em;
    }

    .ptg-mb-status {
        font-size: 14px;
        opacity: 0.9;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .ptg-mb-status-dot {
        width: 8px;
        height: 8px;
        background-color: #4ade80;
        border-radius: 50%;
        display: inline-block;
    }

    .ptg-mb-upgrade-btn {
        background: white;
        color: #4f46e5;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        font-size: 14px;
        transition: transform 0.2s, box-shadow 0.2s;
        border: none;
        cursor: pointer;
        display: inline-block;
    }
    .ptg-mb-upgrade-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    /* Usage Limits */
    .ptg-usage-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
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

    .ptg-usage-bar {
        height: 6px;
        background: #e5e7eb;
        border-radius: 3px;
        margin-top: 12px;
        overflow: hidden;
    }
    
    .ptg-usage-fill {
        height: 100%;
        background: #3b82f6;
        width: 0%;
        transition: width 0.5s ease;
    }

    /* Account Links */
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

    /* Danger Zone */
    .ptg-danger-zone {
        border-color: #fecaca;
        background: #fef2f2;
    }
    .ptg-danger-zone .ptg-mb-section-title {
        color: #991b1b;
    }
    .ptg-delete-account {
        color: #dc2626;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
    }
    .ptg-delete-account:hover {
        text-decoration: underline;
    }

    .ptg-logout-btn {
        display: block;
        width: 100%;
        text-align: center;
        padding: 12px;
        background: #fff;
        border: 1px solid #e5e7eb;
        color: #ef4444;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        margin-top: 30px;
        transition: background 0.2s;
    }
    .ptg-logout-btn:hover {
        background: #fef2f2;
    }

    @media (max-width: 640px) {
        .ptg-mb-card {
            flex-direction: column;
            align-items: flex-start;
            gap: 20px;
        }
        .ptg-mb-upgrade-btn {
            width: 100%;
            text-align: center;
        }
    }
</style>

<div class="ptg-membership-container">
    <header class="ptg-mb-header">
        <h1 class="ptg-mb-title">내 멤버십</h1>
        <a href="<?php echo esc_url($dashboard_url); ?>" class="ptg-mb-back-btn">
            ← 대시보드
        </a>
    </header>

    <!-- 1. Membership Card -->
    <div class="ptg-mb-card <?php echo $grade_label === 'Basic' ? 'is-basic' : ''; ?>">
        <div class="ptg-mb-card-content">
            <div class="ptg-mb-grade"><?php echo esc_html($grade_label); ?></div>
            <div class="ptg-mb-status">
                <span class="ptg-mb-status-dot"></span>
                <?php 
                if ($premium_status === 'active') {
                    echo '이용 중';
                    if ($expiry_date) {
                        echo ' (' . esc_html($expiry_date) . ' 만료)';
                    }
                } else {
                    echo '무료 이용 중';
                }
                ?>
            </div>
        </div>
        <?php if ($grade_label === 'Basic'): ?>
            <a href="/membership" class="ptg-mb-upgrade-btn">Premium 업그레이드</a>
        <?php endif; ?>
    </div>

    <!-- 2. Usage Limits (JS populated) -->
    <section class="ptg-mb-section">
        <h2 class="ptg-mb-section-title">📊 학습 이용 현황</h2>
        <div class="ptg-usage-grid" id="ptg-usage-stats">
            <!-- Loading state -->
            <div class="ptg-usage-item">
                <span class="ptg-usage-label">데이터 불러오는 중...</span>
            </div>
        </div>
    </section>

    <!-- 3. Account Management -->
    <section class="ptg-mb-section">
        <h2 class="ptg-mb-section-title">⚙️ 계정 관리</h2>
        <div class="ptg-account-links">
            <a href="<?php echo esc_url($account_url . '/general'); ?>" class="ptg-account-link">
                <span class="ptg-link-icon">👤</span>
                <span class="ptg-link-text">프로필 수정</span>
                <span class="ptg-link-arrow">→</span>
            </a>
            <a href="<?php echo esc_url($account_url . '/password'); ?>" class="ptg-account-link">
                <span class="ptg-link-icon">🔒</span>
                <span class="ptg-link-text">비밀번호 변경</span>
                <span class="ptg-link-arrow">→</span>
            </a>
            <a href="<?php echo esc_url($account_url . '/notifications'); ?>" class="ptg-account-link">
                <span class="ptg-link-icon">🔔</span>
                <span class="ptg-link-text">알림 설정</span>
                <span class="ptg-link-arrow">→</span>
            </a>
        </div>
    </section>

    <!-- 4. Danger Zone -->
    <section class="ptg-mb-section ptg-danger-zone">
        <h2 class="ptg-mb-section-title">⚠️ 위험 구역</h2>
        <p style="font-size: 14px; color: #7f1d1d; margin-bottom: 12px;">
            계정을 삭제하면 모든 학습 기록과 데이터가 영구적으로 삭제됩니다.
        </p>
        <a href="<?php echo esc_url($account_url . '/delete'); ?>" class="ptg-delete-account">
            계정 탈퇴하기
        </a>
    </section>

    <a href="<?php echo esc_url($logout_url); ?>" class="ptg-logout-btn">
        로그아웃
    </a>
</div>

<script>
(function($) {
    $(document).ready(function() {
        // Load Usage Stats from localStorage
        loadUsageStats();
    });

    function loadUsageStats() {
        const today = new Date().toISOString().split('T')[0].replace(/-/g, '');
        
        // Keys from ptgates-quiz.php / quiz.js
        const mockKey = 'ptg_mock_exam_count_' + today;
        const quizKey = 'ptg_quiz_question_count_' + today;
        
        const mockCount = parseInt(localStorage.getItem(mockKey) || 0);
        const quizCount = parseInt(localStorage.getItem(quizKey) || 0);
        
        // Limits (Hardcoded for display, ideally passed from PHP)
        // Basic: Mock 1, Quiz 20
        // Premium: Unlimited
        const isPremium = '<?php echo $premium_status; ?>' === 'active';
        
        let html = '';
        
        if (isPremium) {
            html += createUsageItem('모의고사', mockCount, '무제한', 0);
            html += createUsageItem('퀴즈 풀이', quizCount, '무제한', 0);
        } else {
            // Basic Limits
            const limitMock = 1;
            const limitQuiz = 20;
            
            html += createUsageItem('모의고사 (일일)', mockCount, limitMock, (mockCount / limitMock) * 100);
            html += createUsageItem('퀴즈 풀이 (일일)', quizCount, limitQuiz, (quizCount / limitQuiz) * 100);
        }
        
        $('#ptg-usage-stats').html(html);
    }

    function createUsageItem(label, current, max, percent) {
        const isUnlimited = max === '무제한';
        const displayMax = isUnlimited ? '∞' : max + '회';
        const barWidth = isUnlimited ? 0 : Math.min(100, percent);
        const barColor = percent >= 100 ? '#ef4444' : '#3b82f6';
        
        return `
            <div class="ptg-usage-item">
                <span class="ptg-usage-label">${label}</span>
                <div class="ptg-usage-value">
                    ${current} / ${displayMax}
                </div>
                ${!isUnlimited ? `
                <div class="ptg-usage-bar">
                    <div class="ptg-usage-fill" style="width: ${barWidth}%; background-color: ${barColor}"></div>
                </div>
                ` : ''}
            </div>
        `;
    }
})(jQuery);
</script>
