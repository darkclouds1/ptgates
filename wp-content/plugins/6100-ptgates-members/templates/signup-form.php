<?php
/**
 * Signup Form Template
 */
?>

<div class="ptg-member-wrapper">
    <div class="ptg-member-card">
        <h2>회원가입</h2>

        <?php if ( isset( $_GET['error'] ) ) : ?>
            <div class="ptg-error">
                <?php 
                    switch( $_GET['error'] ) {
                        case 'email_exists': echo '이미 사용 중인 이메일입니다.'; break;
                        case 'username_exists': echo '이미 사용 중인 아이디입니다.'; break;
                        case 'password_mismatch': echo '패스워드가 일치하지 않습니다.'; break;
                        case 'invalid_email': echo '유효하지 않은 이메일 주소입니다.'; break;
                        case 'invalid_password': echo '패스워드는 8자리 이상이며, 문자와 숫자를 포함해야 합니다.'; break;
                        case 'missing_name': echo '이름을 입력해주세요.'; break;
                        default: echo '회원가입 중 오류가 발생했습니다.';
                    }
                ?>
            </div>
        <?php endif; ?>

        <form method="post" class="ptg-form">
            <?php wp_nonce_field( 'ptg_signup_action', 'ptg_signup_nonce' ); ?>
            
            <div class="ptg-input-group">
                <input type="text" name="username" class="ptg-input" placeholder="아이디 (필수)" required autocomplete="username">
            </div>

            <div class="ptg-input-group">
                <input type="text" name="name" class="ptg-input" placeholder="이름 (필수)" required autocomplete="name">
            </div>

            <div class="ptg-input-group">
                <input type="email" name="email" class="ptg-input" placeholder="이메일 (필수, 인증 필요)" required autocomplete="email">
            </div>

            <div class="ptg-input-group ptg-password-wrapper">
                <input type="password" name="password" class="ptg-input" placeholder="패스워드 8자리 이상 (문자, 숫자 조합)" required autocomplete="new-password" minlength="8">
                <span class="dashicons dashicons-visibility ptg-password-toggle"></span>
            </div>

            <div class="ptg-input-group ptg-password-wrapper">
                <input type="password" name="pass_confirm" class="ptg-input" placeholder="패스워드 확인" required autocomplete="new-password">
            </div>

            <div class="ptg-button-row">
                <button type="submit" class="ptg-btn ptg-btn-primary">회원가입</button>
                <?php if ( class_exists( 'PTG\Platform\KakaoAuth' ) ) : ?>
                    <a href="<?php echo esc_url( home_url( '/wp-json/ptg/v1/auth/kakao/start' ) ); ?>" class="ptg-btn ptg-btn-kakao" style="background-color:#fee500; color:#000;">
                        <span class="dashicons dashicons-format-chat" style="margin-right:5px;"></span> 카카오 로그인
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <div class="ptg-links">
            <a href="<?php echo home_url( '/login' ); ?>">이미 계정이 있으신가요? 로그인</a>
        </div>
    </div>
</div>
