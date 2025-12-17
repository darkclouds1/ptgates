<?php
/**
 * Login Form Template (WP Kakao Login Kit)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="ptg-member-wrapper">
    <div class="ptg-member-card">
        <h2>로그인</h2>

        <?php if ( isset( $_GET['success'] ) && $_GET['success'] === 'verified' ) : ?>
            <div class="ptg-success">이메일 인증이 완료되었습니다. 로그인해 주세요.</div>
        <?php endif; ?>

        <?php if ( isset( $_GET['login_error'] ) ) : ?>
            <div class="ptg-error">
                <?php 
                   if ( 'invalid_credentials' === $_GET['login_error'] ) echo '아이디 또는 비밀번호가 올바르지 않습니다.';
                   elseif ( 'not_verified' === $_GET['login_error'] ) echo '이메일 인증이 필요합니다.';
                   else echo '로그인 실패. 다시 시도해 주세요.';
                ?>
            </div>
        <?php endif; ?>
        
        <?php if ( isset( $_GET['error'] ) ) : ?>
             <div class="ptg-error">
                 <?php
                    // Kakao Errors
                    $err_map = array(
                        'kakao_disabled' => '카카오 로그인이 비활성화되어 있습니다.',
                        'kakao_no_code' => '카카오 인증 코드를 받지 못했습니다.',
                        'kakao_token_fail' => '카카오 토큰 발급에 실패했습니다.',
                        'kakao_user_info_fail' => '카카오 사용자 정보를 가져오지 못했습니다.',
                        'login_failed' => '로그인 처리 중 오류가 발생했습니다.'
                    );
                    echo isset($err_map[$_GET['error']]) ? $err_map[$_GET['error']] : '오류가 발생했습니다.';
                 ?>
             </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['reset_msg'] ) ) : ?>
            <div class="<?php echo ($_GET['reset_msg'] === 'sent') ? 'ptg-success' : 'ptg-error'; ?>">
                <?php 
                   if ( 'sent' === $_GET['reset_msg'] ) echo '이메일로 비밀번호 재설정 링크를 발송했습니다. 메일함을 확인해주세요.';
                   elseif ( 'not_found' === $_GET['reset_msg'] ) echo '사용자를 찾을 수 없습니다.';
                   elseif ( 'fail' === $_GET['reset_msg'] ) echo '이메일 발송에 실패했습니다.';
                   elseif ( 'empty' === $_GET['reset_msg'] ) echo '아이디 또는 이메일을 입력해주세요.';
                ?>
            </div>
        <?php endif; ?>

        <form method="post" class="ptg-form" id="ptg-login-form">
            <?php wp_nonce_field( 'wpklk_login_action', 'wpklk_login_nonce' ); ?>
            <input type="hidden" name="wpklk_action" id="wpklk_action" value="login">
            
            <div class="ptg-input-group">
                <input type="text" name="log" id="ptg_log" class="ptg-input" placeholder="아이디 또는 이메일" required autofocus value="<?php echo isset($_GET['log']) ? esc_attr($_GET['log']) : ''; ?>">
            </div>

            <div class="ptg-input-group ptg-password-wrapper">
                <input type="password" name="pwd" id="ptg_pwd" class="ptg-input" placeholder="비밀번호" required>
            </div>

            <div class="ptg-button-row">
                <button type="submit" class="ptg-btn ptg-btn-primary">로그인</button>
                <a href="/signup" class="ptg-btn ptg-btn-secondary">회원가입</a>
            </div>

            <div class="ptg-links">
                <button type="button" class="ptg-text-btn" onclick="submitFindPw()">비밀번호 찾기</button>
            </div>
        </form>

        <script>
        function submitFindPw() {
            var form = document.getElementById('ptg-login-form');
            var log = document.getElementById('ptg_log');
            var pwd = document.getElementById('ptg_pwd');
            
            if (!log.value) {
                alert('아이디 또는 이메일을 입력해주세요.');
                log.focus();
                return;
            }
            
            // 비밀번호 검증 우회 및 액션 변경
            pwd.required = false;
            document.getElementById('wpklk_action').value = 'find_password';
            form.submit();
        }
        </script>

        <?php if ( get_option('wpklk_kakao_enabled') ) : ?>
            <?php $kakao_url = home_url( '/wp-json/wpklk/v1/auth/kakao/start' ); ?>
            <a href="<?php echo esc_url( $kakao_url ); ?>" class="ptg-kakao-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right:8px;">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 3C6.477 3 2 6.477 2 10.765C2 13.567 3.767 16.035 6.45 17.385L5.337 21.365C5.232 21.737 5.666 22.049 5.986 21.829L10.669 18.675C11.104 18.706 11.547 18.723 11.999 18.723C17.522 18.723 22 15.246 22 10.958C22 6.67 17.522 3 12 3Z" fill="black"/>
                </svg>
                카카오 로그인
            </a>
        <?php endif; ?>
    </div>
</div>

<style>
.ptg-text-btn {
    background: none;
    border: none;
    padding: 0;
    color: #6b7280;
    text-decoration: underline;
    cursor: pointer;
    font-size: 0.9em;
}
.ptg-text-btn:hover {
    color: #111827;
}
</style>
