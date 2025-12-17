<?php
/**
 * Plugin Name: Contact Email Form
 * Plugin URI: https://urienergy.com
 * Description: 깔끔한 문의 및 제안 이메일 폼, 숏코드 [contact_email_form]
 * Version: 1.0.1
 * Author: URI Energy
 * Author URI: https://urienergy.com
 * Text Domain: contact-email
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('CONTACT_EMAIL_VERSION', '1.0.1');
define('CONTACT_EMAIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CONTACT_EMAIL_PLUGIN_URL', plugin_dir_url(__FILE__));

// CSS 및 JS 파일 로드
function contact_email_enqueue_scripts() {
    wp_enqueue_style(
        'contact-email-style',
        CONTACT_EMAIL_PLUGIN_URL . 'contact-email.css',
        array(),
        CONTACT_EMAIL_VERSION . '.' . time() // 강력한 캐시 무효화
    );
    
    wp_enqueue_script(
        'contact-email-script',
        CONTACT_EMAIL_PLUGIN_URL . 'contact-email.js',
        array('jquery'),
        CONTACT_EMAIL_VERSION . '.' . time(), // 강력한 캐시 무효화
        true
    );
    
    // AJAX URL 전달
    wp_localize_script('contact-email-script', 'contactEmailAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('contact_email_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'contact_email_enqueue_scripts');





// 숏코드 등록
function contact_email_form_shortcode() {
    ob_start();
    ?>
    <div class="contact-email-wrapper">
        <form id="contact-email-form" class="contact-email-form">
            <div class="form-message"></div>
            
            <!-- 1줄: 문의명(요약), 담당자명 -->
            <div class="form-row">
                <div class="form-group half">
                    <input 
                        type="text" 
                        name="contact_company" 
                        id="contact_company" 
                        class="form-control" 
                        placeholder="문의명(요약) *" 
                        required
                    >
                </div>
                <div class="form-group half">
                    <input 
                        type="text" 
                        name="contact_name" 
                        id="contact_name" 
                        class="form-control" 
                        placeholder="담당자명 *" 
                        required
                    >
                </div>
            </div>
            
            <!-- 2줄: 연락처, 이메일 -->
            <div class="form-row">
                <div class="form-group half">
                    <input 
                        type="tel" 
                        name="contact_phone" 
                        id="contact_phone" 
                        class="form-control" 
                        placeholder="연락처 *" 
                        required
                    >
                </div>
                <div class="form-group half">
                    <input 
                        type="email" 
                        name="contact_email" 
                        id="contact_email" 
                        class="form-control" 
                        placeholder="이메일 *" 
                        required
                    >
                </div>
            </div>
            
            <!-- 3줄: 문의내용 -->
            <div class="form-row full-width">
                <div class="form-group">
                    <textarea 
                        name="contact_message" 
                        id="contact_message" 
                        class="form-control" 
                        rows="4" 
                        placeholder="문의내용 *" 
                        required
                    ></textarea>
                </div>
            </div>
            
            <div class="form-group submit-group">
                <button type="submit" class="submit-btn">
                    <span class="btn-text">제출하기</span>
                    <span class="btn-loading" style="display: none;">전송 중...</span>
                </button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('contact_email_form', 'contact_email_form_shortcode');

// AJAX 핸들러 - 로그인 사용자용
add_action('wp_ajax_submit_contact_email', 'handle_contact_email_submission');
// AJAX 핸들러 - 비로그인 사용자용
add_action('wp_ajax_nopriv_submit_contact_email', 'handle_contact_email_submission');

function handle_contact_email_submission() {
    error_log('========================================');
    error_log('Contact Email Form 제출 시작');
    error_log('POST 데이터: ' . print_r($_POST, true));
    
    // Nonce 검증
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'contact_email_nonce')) {
        error_log('❌ Nonce 검증 실패');
        wp_send_json_error(array('message' => '보안 검증에 실패했습니다.'));
        return;
    }
    error_log('✅ Nonce 검증 성공');
    
    // 데이터 검증 및 정제
    $company = isset($_POST['company']) ? sanitize_text_field($_POST['company']) : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
    
    error_log('수신한 데이터 - 문의명(요약): ' . $company . ', 담당자: ' . $name . ', 연락처: ' . $phone . ', 이메일: ' . $email);
    
    // 빈 필드 검증
    if (empty($company) || empty($name) || empty($phone) || empty($email) || empty($message)) {
        error_log('❌ 빈 필드 검증 실패');
        wp_send_json_error(array('message' => '모든 필드를 입력해주세요.'));
        return;
    }
    error_log('✅ 필드 검증 통과');
    
    // 이메일 유효성 검증
    if (!is_email($email)) {
        error_log('❌ 이메일 형식 검증 실패: ' . $email);
        wp_send_json_error(array('message' => '유효한 이메일 주소를 입력해주세요.'));
        return;
    }
    error_log('✅ 이메일 형식 검증 통과');
    
    // DB에 저장 (메일 발송 대신)
    $post_data = array(
        'post_title'   => '[문의] ' . $company . ' - ' . $name,
        'post_content' => $message,
        'post_status'  => 'publish',
        'post_type'    => 'ptg_contact',
    );

    $post_id = wp_insert_post( $post_data );

    if ( ! is_wp_error( $post_id ) ) {
        update_post_meta( $post_id, '_ptg_contact_company', $company );
        update_post_meta( $post_id, '_ptg_contact_name', $name );
        update_post_meta( $post_id, '_ptg_contact_phone', $phone );
        update_post_meta( $post_id, '_ptg_contact_email', $email );
        
        error_log('✅ 문의 DB 저장 성공! ID: ' . $post_id);
        error_log('========================================');
        
        // Clean output buffer to prevent JSON errors
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        wp_send_json_success(array(
            'message' => '정상적으로 제출되었습니다.'
        ));
    } else {
        error_log('❌ 문의 DB 저장 실패: ' . $post_id->get_error_message());
        error_log('========================================');
        
        wp_send_json_error(array(
            'message' => '문의 접수 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.'
        ));
    }
}



