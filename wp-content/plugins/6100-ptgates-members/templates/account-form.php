<?php 
/**
 * Account Form Template
 */
$user = wp_get_current_user(); 
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
?>

<div class="ptg-member-wrapper">
    <div class="ptg-member-card">
        <h2>계정 관리</h2>

        <div style="display:flex; justify-content:center; gap:20px; margin-bottom:30px; border-bottom:1px solid #eee; padding-bottom:15px;">
            <a href="?tab=profile" style="text-decoration:none; font-weight:600; color:<?php echo $active_tab === 'profile' ? '#111' : '#999'; ?>">프로필 수정</a>
            <a href="?tab=security" style="text-decoration:none; font-weight:600; color:<?php echo $active_tab === 'security' ? '#111' : '#999'; ?>">보안 설정</a>
        </div>

        <?php if ( isset( $_GET['success'] ) ) : ?>
            <div class="ptg-success">
                <?php 
                    if ( 'profile_updated' === $_GET['success'] ) echo '프로필이 저장되었습니다.';
                    if ( 'profile_updated_email_sent' === $_GET['success'] ) echo '프로필 저장됨. 새 이메일로 인증 메일을 발송했습니다.';
                    if ( 'password_changed' === $_GET['success'] ) echo '비밀번호가 변경되었습니다.';
                ?>
            </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['error'] ) ) : ?>
            <div class="ptg-error">
                <?php 
                   if ( 'email_exists' === $_GET['error'] ) echo '이미 사용 중인 이메일입니다.';
                   if ( 'current_password_mismatch' === $_GET['error'] ) echo '현재 비밀번호가 일치하지 않습니다.';
                   if ( 'password_mismatch' === $_GET['error'] ) echo '새 비밀번호가 일치하지 않습니다.';
                ?>
            </div>
        <?php endif; ?>

        <?php if ( $active_tab === 'profile' ) : ?>
            <form method="post" class="ptg-form">
                <?php wp_nonce_field( 'ptg_profile_action', 'ptg_profile_nonce' ); ?>
                
                <div class="ptg-input-group">
                    <input type="text" value="<?php echo esc_attr( $user->user_login ); ?>" class="ptg-input" disabled style="background:#f5f5f5;">
                </div>

                <div class="ptg-input-group">
                    <input type="text" name="name" value="<?php echo esc_attr( $user->display_name ); ?>" class="ptg-input" placeholder="이름" required>
                </div>

                <div class="ptg-input-group">
                    <input type="email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" class="ptg-input" placeholder="이메일" required>
                </div>

                <div class="ptg-button-row ptg-button-row-center">
                    <button type="submit" class="ptg-btn ptg-btn-primary">저장</button>
                </div>
            </form>

        <?php elseif ( $active_tab === 'security' ) : ?>
            <form method="post" class="ptg-form">
                <?php wp_nonce_field( 'ptg_pw_change_action', 'ptg_pw_change_nonce' ); ?>
                
                <div class="ptg-input-group ptg-password-wrapper">
                    <input type="password" name="current_password" class="ptg-input" placeholder="현재 비밀번호" required>
                </div>

                <div class="ptg-input-group ptg-password-wrapper">
                    <input type="password" name="pass1" class="ptg-input" placeholder="새 비밀번호" required>
                    <span class="dashicons dashicons-visibility ptg-password-toggle"></span>
                </div>

                <div class="ptg-input-group ptg-password-wrapper">
                    <input type="password" name="pass2" class="ptg-input" placeholder="새 비밀번호 확인" required>
                </div>

                <div class="ptg-button-row ptg-button-row-center">
                    <button type="submit" class="ptg-btn ptg-btn-primary">비밀번호 변경</button>
                </div>
            </form>

            <div class="ptg-links" style="margin-top:40px; border-top:1px solid #eee; padding-top:20px; flex-direction:column; gap:10px;">
                <div style="display:flex; justify-content:center; align-items:center; gap:10px;">
                    <form method="post" class="ptg-form-delete" onsubmit="return confirm('정말로 탈퇴하시겠습니까? 이 작업은 되돌릴 수 없습니다.');" style="display:inline;">
                        <?php wp_nonce_field( 'ptg_delete_account_action', 'ptg_delete_account_nonce' ); ?>
                        <button type="submit" style="background:none; border:none; color:#dc2626; cursor:pointer; font-size:14px;">회원 탈퇴</button>
                    </form>
                    <span style="color:#ccc;">|</span>
                    <a href="<?php echo home_url( '/logout' ); ?>" style="color:#6b7280;">로그아웃</a>
                </div>
                <p style="font-size:12px; color:#999; margin:0; text-align:center;">계정을 삭제하면 모든 학습 기록과 데이터가 영구적으로 삭제됩니다.</p>
            </div>
        <?php endif; ?>

    </div>
</div>
