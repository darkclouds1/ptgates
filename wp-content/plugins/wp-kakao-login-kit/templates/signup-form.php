<?php
/**
 * Signup Form Template (WP Kakao Login Kit)
 */
?>
<div class="ptg-member-wrapper">
    <div class="ptg-member-card">
        <h2>회원가입</h2>

        <?php if ( isset( $_GET['success'] ) && $_GET['success'] === 'signup_complete' ) : ?>
            <div class="ptg-success">
                가입이 완료되었습니다.<br>이메일로 발송된 인증 링크를 확인해주세요.
            </div>
            
            <div class="ptg-links">
                <a href="/login">← 로그인 화면으로 돌아가기</a>
            </div>
        <?php else : ?>

            <?php if ( isset( $_GET['error'] ) ) : ?>
                <div class="ptg-error">
                    <?php 
                    if ( 'email_exists' === $_GET['error'] ) echo '이미 사용 중인 이메일입니다.';
                    elseif ( 'missing_fields' === $_GET['error'] ) echo '모든 필드를 입력해주세요.';
                    else echo '가입 중 오류가 발생했습니다.';
                    ?>
                </div>
            <?php endif; ?>

            <form method="post" class="ptg-form">
                <?php wp_nonce_field( 'wpklk_signup_action', 'wpklk_signup_nonce' ); ?>
                <input type="hidden" name="wpklk_signup" value="1">
                
                <div class="ptg-input-group">
                    <input type="text" name="name" class="ptg-input" placeholder="이름 (필수)" required>
                </div>

                <div class="ptg-input-group">
                    <input type="email" name="email" class="ptg-input" placeholder="이메일" required>
                </div>

                <div class="ptg-input-group ptg-password-wrapper">
                    <input type="password" name="password" class="ptg-input" placeholder="패스워드 (8자리 이상)" required minlength="8">
                </div>

                <div class="ptg-button-row">
                    <button type="submit" class="ptg-btn ptg-btn-primary">회원가입하기</button>
                    <a href="/login" class="ptg-btn ptg-btn-secondary">로그인</a>
                </div>
            </form>
            
            <?php if ( get_option('wpklk_kakao_enabled') ) : ?>
            <div style="text-align:center; margin: 15px 0; color:#999; font-size:12px;">또는</div>

            <?php $kakao_url = home_url( '/wp-json/wpklk/v1/auth/kakao/start' ); ?>
            <a href="<?php echo esc_url( $kakao_url ); ?>" class="ptg-kakao-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right:8px;">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 3C6.477 3 2 6.477 2 10.765C2 13.567 3.767 16.035 6.45 17.385L5.337 21.365C5.232 21.737 5.666 22.049 5.986 21.829L10.669 18.675C11.104 18.706 11.547 18.723 11.999 18.723C17.522 18.723 22 15.246 22 10.958C22 6.67 17.522 3 12 3Z" fill="black"/>
                </svg>
                카카오 3초 간편회원가입
            </a>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
