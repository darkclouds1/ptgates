<?php
/**
 * Login Form Template
 */
if ( defined( 'UM_IS_RESTRICTED' ) ) {
    return; // Conflict avoidance
}
?>

<div class="ptg-member-wrapper">
    <div class="ptg-member-card">
        <h2>로그인</h2>

        <?php if ( isset( $_GET['error'] ) ) : ?>
            <div class="ptg-error">
                <?php 
                    if ( 'login_failed' === $_GET['error'] ) {
                        echo '아이디 또는 패스워드가 올바르지 않습니다.';
                    } elseif ( 'email_not_verified' === $_GET['error'] ) { // Handled by WP_Error actually but query arg backup
                        echo '이메일 인증 후 로그인할 수 있습니다.';
                    }
                ?>
            </div>
        <?php endif; ?>
        
        <?php if ( isset( $_GET['success'] ) && 'registered_verify' === $_GET['success'] ) : ?>
            <div class="ptg-success">
                가입이 완료되었습니다.<br>이메일 인증 후 로그인해주세요.
            </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['logged_out'] ) ) : ?>
            <div class="ptg-success">
                로그아웃 되었습니다.
            </div>
        <?php endif; ?>

        <form method="post" class="ptg-form">
            <?php wp_nonce_field( 'ptg_login_action', 'ptg_login_nonce' ); ?>
            
            <div class="ptg-input-group">
                <input type="text" name="log" class="ptg-input" placeholder="아이디 또는 이메일" required autocomplete="username">
            </div>

            <div class="ptg-input-group ptg-password-wrapper">
                <input type="password" name="password" class="ptg-input" placeholder="패스워드" required autocomplete="current-password">
                <span class="dashicons dashicons-visibility ptg-password-toggle"></span>
            </div>

            <div class="ptg-button-row">
                <button type="submit" class="ptg-btn ptg-btn-primary">로그인</button>
                <a href="<?php echo home_url( '/signup' ); ?>" class="ptg-btn ptg-btn-secondary">회원가입</a>
            </div>
        </form>

        <div class="ptg-links">
            <a href="<?php echo wp_lostpassword_url(); ?>">패스워드 찾기</a>
        </div>

        <?php 
        // Kakao Login Implementation
        if ( class_exists( 'PTG\Platform\KakaoAuth' ) ) {
            // Manually render button or link nicely
            echo '<a href="' . esc_url( home_url( '/wp-json/ptg/v1/auth/kakao/start' ) ) . '" class="ptg-kakao-btn"><span class="dashicons dashicons-format-chat" style="margin-right:8px;"></span> 카카오 로그인</a>';
        }
        ?>

    </div>
</div>
