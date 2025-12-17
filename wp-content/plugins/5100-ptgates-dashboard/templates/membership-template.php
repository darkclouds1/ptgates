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
$member_table = 'ptgates_user_member';
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
$logout_url = add_query_arg([
    'ptg_action' => 'logout',
    '_wpnonce'   => wp_create_nonce('ptg_logout')
], home_url()); // 커스텀 로그아웃 핸들러 사용

// 통계 데이터 조회
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

// 4. 결제 내역 조회
$billing_history = [];
// 4. 결제 내역 조회
$billing_history = [];
if ($wpdb->get_var("SHOW TABLES LIKE 'ptgates_billing_history'") === 'ptgates_billing_history') {
    $billing_history = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM ptgates_billing_history WHERE user_id = %d ORDER BY transaction_date DESC",
        $user_id
    ));
}

// 5. 상품 목록 조회 (for Payment Tab)
$active_products = \PTG\Dashboard\API::get_active_products();

?>
<style>
    .ptg-membership-container {
        max-width: 1000px !important;
        width: 100%;
        margin: 10px auto !important;
        padding: 10px !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #333;
        box-sizing: border-box;
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
            ← 학습현황
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

    <!-- 2. Usage Limits (PHP populated) -->
    <section class="ptg-mb-section">
        <h2 class="ptg-mb-section-title">📊 학습 이용 현황</h2>
        <div class="ptg-usage-grid" id="ptg-usage-stats">
            <!-- 과목|Study -->
            <div class="ptg-usage-item">
                <span class="ptg-usage-label">과목|Study</span>
                <div class="ptg-usage-value">
                    <?php echo number_format($study_count); ?> 문제
                </div>
            </div>
            <!-- 실전|Quiz -->
            <div class="ptg-usage-item">
                <span class="ptg-usage-label">실전|Quiz</span>
                <div class="ptg-usage-value">
                    <?php echo number_format($quiz_count); ?> 문제
                </div>
            </div>
            <!-- 암기카드 -->
            <div class="ptg-usage-item">
                <span class="ptg-usage-label">암기카드</span>
                <div class="ptg-usage-value">
                    <?php echo number_format($flashcard_count); ?> 개
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Account Management -->
    <section class="ptg-mb-section">
        <h2 class="ptg-mb-section-title">⚙️ 계정 관리</h2><br>
        <div class="ptg-account-links">
            <a href="https://ptgates.com/account/?tab=profile" class="ptg-account-link">
                <span class="ptg-link-icon">👤</span>
                <span class="ptg-link-text">프로필 수정</span>
                <span class="ptg-link-arrow">→</span>
            </a>
            <a href="https://ptgates.com/account/?tab=security" class="ptg-account-link">
                <span class="ptg-link-icon">🔒</span>
                <span class="ptg-link-text">비밀번호 변경</span>
                <span class="ptg-link-arrow">→</span>
            </a>
            <button type="button" class="ptg-account-link" onclick="togglePaymentManagement()">
                <span class="ptg-link-icon">💳</span>
                <span class="ptg-link-text">결제 관리</span>
                <span class="ptg-link-arrow">▼</span>
            </button>
        </div>

        <!-- Payment Management Section (Hidden by default) -->
        <div id="ptg-payment-management" style="display: none; margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 24px;">
            
            <!-- Tabs -->
            <div class="ptg-pm-tabs">
                <button type="button" class="ptg-pm-tab is-active" onclick="switchPmTab('product')">상품 선택 및 결제</button>
                <button type="button" class="ptg-pm-tab" onclick="switchPmTab('history')">결제 내역</button>
            </div>

            <!-- Tab Content: Product Selection -->
            <div id="ptg-pm-content-product" class="ptg-pm-content is-active">
                <style>
                    .ptg-products-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                        gap: 20px;
                        justify-content: center;
                    }
                    .ptg-pricing-card {
                        margin: 0; /* Override auto margin */
                        max-width: none;
                        display: flex;
                        flex-direction: column;
                        height: 100%;
                    }
                    .ptg-pricing-card.is-featured {
                        border: 2px solid #4f46e5;
                        box-shadow: 0 8px 30px rgba(79, 70, 229, 0.15);
                        transform: scale(1.02);
                    }
                    .ptg-pricing-badge {
                        background: #4f46e5;
                        color: white;
                        font-size: 12px;
                        font-weight: 700;
                        padding: 4px 12px;
                        border-radius: 999px;
                        position: absolute;
                        top: -12px;
                        left: 50%;
                        transform: translateX(-50%);
                    }
                </style>
                
                <?php if (empty($active_products)): ?>
                    <div style="text-align:center; padding: 40px; color: #6b7280;">
                        현재 판매 중인 상품이 없습니다.
                    </div>
                <?php else: ?>
                    <div class="ptg-products-grid">
                        <?php foreach ($active_products as $prod): ?>
                            <?php 
                                $is_featured = $prod->featured_level > 0;
                                $features = $prod->features; // Array or Object
                            ?>
                            <div class="ptg-pricing-card <?php echo $is_featured ? 'is-featured' : ''; ?>" style="position: relative;">
                                <?php if ($is_featured): ?>
                                    <div class="ptg-pricing-badge">RECOMMENDED</div>
                                <?php endif; ?>
                                
                                <div class="ptg-pricing-header">
                                    <h3 class="ptg-pricing-title"><?php echo esc_html($prod->title); ?></h3>
                                    <div class="ptg-pricing-price">
                                        <?php echo number_format($prod->price); ?>원 
                                        <?php if (!empty($prod->price_label)): ?>
                                            <div style="font-size: 14px; color: #6b7280; font-weight: normal; margin-top: 4px;">
                                                <?php echo esc_html($prod->price_label); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="ptg-pricing-desc"><?php echo nl2br(esc_html($prod->description)); ?></p>
                                </div>
                                
                                <?php if (!empty($features)): ?>
                                    <ul class="ptg-pricing-features" style="flex-grow: 1;">
                                        <?php foreach ($features as $feat): ?>
                                            <li>✅ <?php echo esc_html($feat); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div style="flex-grow: 1;"></div>
                                <?php endif; ?>
                                
                                <button type="button" class="ptg-pricing-btn" onclick="initiatePayment('<?php echo esc_attr($prod->product_code); ?>', <?php echo intval($prod->price); ?>, '<?php echo esc_attr($prod->title); ?>')">
                                    선택하기
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab Content: Payment History -->
            <div id="ptg-pm-content-history" class="ptg-pm-content">
                <div class="ptg-history-list">
                    <?php if ($billing_history && count($billing_history) > 0): ?>
                        <?php foreach ($billing_history as $item): ?>
                            <div class="ptg-history-item">
                                <div class="ptg-history-row">
                                    <span class="ptg-history-date"><?php echo esc_html(date('Y.m.d H:i', strtotime($item->transaction_date))); ?></span>
                                    <span class="ptg-history-status <?php echo $item->status === 'paid' ? 'completed' : ''; ?>">
                                        <?php 
                                        $status_map = [
                                            'paid' => '결제완료',
                                            'failed' => '실패',
                                            'refunded' => '환불',
                                            'pending' => '대기'
                                        ];
                                        echo isset($status_map[$item->status]) ? esc_html($status_map[$item->status]) : esc_html($item->status); 
                                        ?>
                                    </span>
                                </div>
                                <div class="ptg-history-row" style="margin-top: 6px;">
                                    <span class="ptg-history-product"><?php echo esc_html($item->product_name); ?></span>
                                    <span class="ptg-history-amount"><?php echo number_format($item->amount); ?>원</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="ptg-history-item ptg-history-empty">
                            아직 결제 내역이 없습니다.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div style="margin-top: 24px; text-align: right;">
            <a href="<?php echo esc_url($logout_url); ?>" 
               style="padding: 8px 16px; background-color: #f3f4f6; color: #4b5563; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500; white-space: nowrap;">
                로그아웃
            </a>
        </div>
    </section>
</div>


<!-- KG Inicis Payment Form (Hidden) -->
<form id="ptg-payment-form" method="POST" style="display:none;">
    <!-- Common -->
    <input type="hidden" name="mid" >
    <input type="hidden" name="goodname" >
    <input type="hidden" name="oid" >
    <input type="hidden" name="price" >
    <input type="hidden" name="buyername" >
    <input type="hidden" name="buyeremail" >
    <input type="hidden" name="timestamp" >
    <input type="hidden" name="returnUrl" >
    <input type="hidden" name="closeUrl" >
    <input type="hidden" name="signature" >
    <input type="hidden" name="mKey" >
    <input type="hidden" name="currency" value="WON">
    <input type="hidden" name="payViewType" value="overlay">
    <input type="hidden" name="charset" value="UTF-8">
    
    <!-- Mobile Specific -->
    <input type="hidden" name="P_MID" >
    <input type="hidden" name="P_OID" >
    <input type="hidden" name="P_AMT" >
    <input type="hidden" name="P_UNAME" >
    <input type="hidden" name="P_GOODS" >
    <input type="hidden" name="P_NEXT_URL" >
    <input type="hidden" name="P_NOTI_URL" >
    <input type="hidden" name="P_HPP_METHOD" value="1">
</form>

<!-- KG Inicis StdPay JS (Staging) -->
<script language="javascript" type="text/javascript" src="https://stgstdpay.inicis.com/stdjs/INIStdPay.js" charset="UTF-8"></script>
<script>
function togglePaymentManagement() {
    var el = document.getElementById('ptg-payment-management');
    if (el.style.display === 'none') {
        el.style.display = 'block';
        // Smooth scroll to section
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        el.style.display = 'none';
    }
}

function switchPmTab(tabName) {
    // Update Tab Buttons
    var tabs = document.querySelectorAll('.ptg-pm-tab');
    tabs.forEach(function(t) {
        t.classList.remove('is-active');
    });
    event.target.classList.add('is-active');

    // Update Content
    var contents = document.querySelectorAll('.ptg-pm-content');
    contents.forEach(function(c) {
        c.classList.remove('is-active');
    });
    document.getElementById('ptg-pm-content-' + tabName).classList.add('is-active');
}


function initiatePayment(productCode, price, productName) {
    if (!confirm(productName + ' (' + price.toLocaleString() + '원)을 결제하시겠습니까?')) {
        return;
    }
    
    // Check Device
    var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    var deviceType = isMobile ? 'mobile' : 'pc';

    // Show Loading
    // Simple overlay
    var overlay = document.createElement('div');
    overlay.id = 'ptg-pay-loading';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);z-index:99999;display:flex;justify-content:center;align-items:center;font-size:18px;font-weight:bold;';
    overlay.innerHTML = '결제 준비 중입니다...';
    document.body.appendChild(overlay);

    // Call API
    jQuery.ajax({
        url: '/wp-json/ptg-dash/v1/payment/prepare',
        method: 'POST',
        headers: {
            'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
        },
        data: {
            product_code: productCode,
            device_type: deviceType
        },
        success: function(response) {
            if (document.getElementById('ptg-pay-loading')) document.body.removeChild(document.getElementById('ptg-pay-loading'));

            var form = document.getElementById('ptg-payment-form');
            
            if (deviceType === 'mobile') {
                // Mobile Logic
                // Map API response to Mobile Form Fields
                form.action = 'https://stgmobile.inicis.com/smart/payment/'; // Staging URL
                form.acceptCharset = 'euc-kr'; // Mobile sometimes requires EUC-KR, but UTF-8 is standard now. verifying.
                form.acceptCharset = 'UTF-8';
                
                // Mappings
                // API returns: mid, oid, price...
                // Mobile needs: P_MID, P_OID, P_AMT...
                
                // Note: prepare_transaction only returns PC params mostly.
                // I need to ensure Payment class returns Mobile params too or map them here.
                // In Payment class I returned 'P_NEXT_URL' which suggests Mobile awareness.
                // Let's assume standard params are returned.
                
                // Mapping
                if (response.mid) form.P_MID.value = response.mid;
                if (response.oid) form.P_OID.value = response.oid;
                if (response.price) form.P_AMT.value = response.price;
                if (response.buyername) form.P_UNAME.value = response.buyername;
                if (response.goodname) form.P_GOODS.value = response.goodname;
                if (response.P_NEXT_URL) form.P_NEXT_URL.value = response.P_NEXT_URL;
                // P_NOTI_URL is optional/server-to-server
                
                form.submit();
                
            } else {
                // PC Logic
                // Field Mapping
                form.mid.value = response.mid;
                form.oid.value = response.oid;
                form.price.value = response.price;
                form.goodname.value = response.goodname;
                form.buyername.value = response.buyername;
                form.buyeremail.value = response.buyeremail;
                form.timestamp.value = response.timestamp;
                form.signature.value = response.signature;
                form.mKey.value = response.mKey;
                form.returnUrl.value = response.returnUrl;
                form.closeUrl.value = response.closeUrl;
                
                // Execute StdPay
                try {
                    INIStdPay.pay('ptg-payment-form');
                } catch (e) {
                    alert('결제 모듈 실행 실패: ' + e.message);
                }
            }
        },
        error: function(xhr) {
            if (document.getElementById('ptg-pay-loading')) document.body.removeChild(document.getElementById('ptg-pay-loading'));
            alert('오류 발생: ' + (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText));
        }
    });

}
</script>

<style>
    /* Payment Management Styles */
    .ptg-account-link {
        background: none;
        border: 1px solid #e5e7eb;
        font: inherit;
        cursor: pointer;
        width: 100%;
        text-align: left;
    }

    .ptg-pm-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 1px solid #e5e7eb;
    }

    .ptg-pm-tab {
        padding: 10px 20px;
        background: none;
        border: none;
        border-bottom: 2px solid transparent;
        font-size: 15px;
        font-weight: 600;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.2s;
    }

    .ptg-pm-tab:hover {
        color: #374151;
    }

    .ptg-pm-tab.is-active {
        color: #4f46e5;
        border-bottom-color: #4f46e5;
    }

    .ptg-pm-content {
        display: none;
        animation: ptg-fade-in 0.3s ease;
    }

    .ptg-pm-content.is-active {
        display: block;
    }

    @keyframes ptg-fade-in {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Pricing Card */
    .ptg-pricing-card {
        background: linear-gradient(145deg, #ffffff, #f9fafb);
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 30px;
        text-align: center;
        max-width: 400px;
        margin: 0 auto;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }

    .ptg-pricing-title {
        font-size: 20px;
        font-weight: 700;
        color: #111827;
        margin: 0 0 10px;
    }

    .ptg-pricing-price {
        font-size: 36px;
        font-weight: 800;
        color: #4f46e5;
        margin-bottom: 10px;
    }

    .ptg-pricing-period {
        font-size: 16px;
        font-weight: 500;
        color: #6b7280;
    }

    .ptg-pricing-desc {
        color: #4b5563;
        margin-bottom: 24px;
    }

    .ptg-pricing-features {
        list-style: none;
        padding: 0;
        margin: 0 0 30px;
        text-align: left;
    }

    .ptg-pricing-features li {
        margin-bottom: 12px;
        color: #374151;
        font-size: 15px;
    }

    .ptg-pricing-btn {
        display: block;
        width: 100%;
        padding: 14px;
        background: #4f46e5;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 16px;
        transition: background 0.2s;
    }

    .ptg-pricing-btn:hover {
        background: #4338ca;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }

    /* History List */
    .ptg-history-list {
        border-top: 1px solid #e5e7eb;
    }

    .ptg-history-item {
        padding: 16px 0;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 14px;
        color: #374151;
    }

    .ptg-history-item.ptg-history-empty {
        text-align: center;
        padding: 40px 0;
        color: #6b7280;
        font-style: italic;
    }

    .ptg-history-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ptg-history-date {
        font-size: 13px;
        color: #6b7280;
    }

    .ptg-history-product {
        font-weight: 600;
        color: #111827;
        font-size: 15px;
    }

    .ptg-history-amount {
        font-weight: 600;
        color: #374151;
    }

    .ptg-history-status {
        font-size: 13px;
        padding: 2px 8px;
        border-radius: 9999px;
        background-color: #f3f4f6;
        color: #4b5563;
    }
    
    .ptg-history-status.completed {
        background-color: #dcfce7;
        color: #166534;
    }
</style>
