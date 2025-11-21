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

// 관리자 메뉴 추가
add_action('admin_menu', 'contact_email_admin_menu');

function contact_email_admin_menu() {
    add_options_page(
        'Contact Email 설정',
        'Contact Email',
        'manage_options',
        'contact-email-settings',
        'contact_email_settings_page'
    );
}

// 설정 페이지
function contact_email_settings_page() {
    // 설정 저장 처리
    if (isset($_POST['save_contact_email_settings']) && check_admin_referer('contact_email_settings', 'contact_email_settings_nonce')) {
        $recipient_email = sanitize_email($_POST['recipient_email']);
        $cc_email = sanitize_email($_POST['cc_email']);
        
        $has_error = false;
        
        // 받는 이메일 검증
        if (is_email($recipient_email)) {
            update_option('contact_email_recipient', $recipient_email);
        } else {
            echo '<div class="notice notice-error is-dismissible"><p><strong>오류!</strong> 받는 이메일 주소가 유효하지 않습니다.</p></div>';
            $has_error = true;
        }
        
        // 참조 이메일 검증 (선택사항)
        if (!empty($cc_email)) {
            if (is_email($cc_email)) {
                update_option('contact_email_cc', $cc_email);
            } else {
                echo '<div class="notice notice-error is-dismissible"><p><strong>오류!</strong> 참조 이메일 주소가 유효하지 않습니다.</p></div>';
                $has_error = true;
            }
        } else {
            update_option('contact_email_cc', ''); // 비워두면 삭제
        }
        
        if (!$has_error) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>저장 완료!</strong> 설정이 저장되었습니다.</p></div>';
        }
    }
    
    // 현재 설정값 가져오기
    $recipient_email = get_option('contact_email_recipient', 'urienergy@urienergy.com');
    $cc_email = get_option('contact_email_cc', '');
    ?>
    <div class="wrap">
        <h1>Contact Email Form 설정</h1>
        
        <style>
            .contact-email-settings-table th {
                white-space: nowrap;
                width: 200px;
            }
        </style>
        
        <div class="card">
            <h2>이메일 수신 설정</h2>
            <form method="post" action="">
                <?php wp_nonce_field('contact_email_settings', 'contact_email_settings_nonce'); ?>
                <table class="form-table contact-email-settings-table">
                    <tr>
                        <th scope="row">
                            <label for="recipient_email">문의 수신 이메일 <span style="color:red;">*</span></label>
                        </th>
                        <td>
                            <input type="email" 
                                   name="recipient_email" 
                                   id="recipient_email" 
                                   value="<?php echo esc_attr($recipient_email); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">웹사이트 문의를 받을 메인 이메일 주소입니다. (필수)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cc_email">참조(CC) 이메일</label>
                        </th>
                        <td>
                            <input type="email" 
                                   name="cc_email" 
                                   id="cc_email" 
                                   value="<?php echo esc_attr($cc_email); ?>" 
                                   class="regular-text">
                            <p class="description">문의 내용을 함께 받을 참조 이메일 주소입니다. (선택사항)</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="save_contact_email_settings" class="button button-primary">
                        설정 저장
                    </button>
                </p>
            </form>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>SMTP 설정 정보</h2>
            <table class="form-table">
                <tr>
                    <th>SMTP 호스트:</th>
                    <td>smtp.hostinger.com</td>
                </tr>
                <tr>
                    <th>포트:</th>
                    <td>465 (SSL)</td>
                </tr>
                <tr>
                    <th>발신 이메일:</th>
                    <td>urienergy@urienergy.com</td>
                </tr>
                <tr>
                    <th>현재 수신 이메일:</th>
                    <td><strong><?php echo esc_html($recipient_email); ?></strong></td>
                </tr>
                <tr>
                    <th>현재 참조(CC) 이메일:</th>
                    <td>
                        <?php 
                        if (!empty($cc_email)) {
                            echo '<strong>' . esc_html($cc_email) . '</strong>';
                        } else {
                            echo '<span style="color: #999;">설정 안 함</span>';
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>테스트 이메일 발송</h2>
            <p>SMTP 설정이 올바른지 테스트합니다.</p>
            <form method="post" action="">
                <?php wp_nonce_field('contact_email_test', 'contact_email_test_nonce'); ?>
                <p>
                    <input type="email" name="test_email" placeholder="테스트 수신 이메일" 
                           value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                           style="width: 300px;" required>
                </p>
                <p>
                    <button type="submit" name="send_test_email" class="button button-primary">
                        테스트 이메일 발송
                    </button>
                </p>
            </form>
            
            <?php
            if (isset($_POST['send_test_email']) && check_admin_referer('contact_email_test', 'contact_email_test_nonce')) {
                $test_email = sanitize_email($_POST['test_email']);
                
                // SMTP 설정 활성화
                add_action('phpmailer_init', 'configure_contact_email_smtp');
                
                $subject = '[테스트] Contact Email Form';
                $message = '<h2>테스트 이메일</h2><p>SMTP 설정이 정상적으로 작동합니다.</p>';
                $headers = array('Content-Type: text/html; charset=UTF-8');
                
                $result = wp_mail($test_email, $subject, $message, $headers);
                
                remove_action('phpmailer_init', 'configure_contact_email_smtp');
                
                if ($result) {
                    echo '<div class="notice notice-success"><p><strong>성공!</strong> 테스트 이메일이 발송되었습니다.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p><strong>실패!</strong> 이메일 발송에 실패했습니다. WordPress debug.log를 확인하세요.</p></div>';
                }
            }
            ?>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>사용 방법</h2>
            <p>페이지나 포스트에 다음 숏코드를 추가하세요:</p>
            <code style="background: #f0f0f0; padding: 10px; display: inline-block; font-size: 14px;">
                [contact_email_form]
            </code>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>디버그 정보</h2>
            <p>이메일 발송 문제가 있다면 다음을 확인하세요:</p>
            <ol>
                <li>WordPress의 wp-config.php에서 디버그 모드 활성화:
                    <pre style="background: #f0f0f0; padding: 10px;">define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);</pre>
                </li>
                <li>wp-content/debug.log 파일 확인</li>
                <li>다른 SMTP 플러그인과 충돌이 없는지 확인</li>
                <li>Hostinger SMTP 설정 확인</li>
            </ol>
        </div>
    </div>
    <?php
}

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

// SMTP 설정 함수
function configure_contact_email_smtp($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = 'smtp.hostinger.com';
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = 465;
    $phpmailer->Username = 'urienergy@urienergy.com';
    $phpmailer->Password = '#G6ce*?#ySeFY2$';
    $phpmailer->SMTPSecure = 'ssl';
    $phpmailer->From = 'urienergy@urienergy.com';
    $phpmailer->FromName = 'URI Energy 웹사이트';
    $phpmailer->CharSet = 'UTF-8';
    
    // 디버그 모드 (개발 중에만 활성화)
    $phpmailer->SMTPDebug = 0; // 0 = off, 1 = client, 2 = client and server
    $phpmailer->Debugoutput = function($str, $level) {
        error_log("SMTP Debug ($level): $str");
    };
    
    // 타임아웃 설정
    $phpmailer->Timeout = 10;
    $phpmailer->SMTPKeepAlive = false;
}

