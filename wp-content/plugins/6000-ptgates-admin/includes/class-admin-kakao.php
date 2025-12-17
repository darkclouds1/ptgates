<?php
/**
 * Kakao Login Settings
 * 
 * 카카오 로그인 설정 탭 처리
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PTG_Admin_Kakao {
    
    /**
     * Settings Page Render
     */
    public static function render_page() {
        // Option Saving
        if ( isset( $_POST['ptg_kakao_save'] ) && check_admin_referer( 'ptg_kakao_settings_nonce' ) ) {
            $enabled = isset( $_POST['ptg_kakao_enabled'] ) ? 1 : 0;
            $client_id = sanitize_text_field( $_POST['ptg_kakao_client_id'] );
            $client_secret = sanitize_text_field( $_POST['ptg_kakao_client_secret'] );
            
            // Scopes array
            $scopes = isset( $_POST['ptg_kakao_scopes'] ) ? (array) $_POST['ptg_kakao_scopes'] : array();
            $scopes = array_map( 'sanitize_text_field', $scopes );

            update_option( 'ptg_kakao_enabled', $enabled );
            update_option( 'ptg_kakao_client_id', $client_id );
            update_option( 'ptg_kakao_client_secret', $client_secret );
            update_option( 'ptg_kakao_scopes', $scopes );

            echo '<div class="notice notice-success is-dismissible"><p>설정이 저장되었습니다.</p></div>';
        }

        // Retrieve Options
        $enabled = get_option( 'ptg_kakao_enabled', 0 );
        $client_id = get_option( 'ptg_kakao_client_id', '' );
        $client_secret = get_option( 'ptg_kakao_client_secret', '' );
        $saved_scopes = get_option( 'ptg_kakao_scopes', array() );
        
        $redirect_uri = site_url( '/wp-json/ptg/v1/auth/kakao/callback' );
        
        $available_scopes = array(
            'profile_nickname' => '닉네임 (필수)',
            'profile_image' => '프로필 이미지',
            'account_email' => '이메일',
        );

        ?>
        <div class="wrap">
            <h1>카카오 로그인 설정</h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'ptg_kakao_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">활성화</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ptg_kakao_enabled" value="1" <?php checked( 1, $enabled ); ?>>
                                카카오 로그인 사용
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">REST API Key (Client ID)</th>
                        <td>
                            <input type="text" name="ptg_kakao_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" required>
                            <p class="description">카카오 디벨로퍼스 > 내 애플리케이션 > 앱 키 > REST API 키</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Client Secret</th>
                        <td>
                            <input type="password" name="ptg_kakao_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text">
                            <p class="description">카카오 디벨로퍼스 > 보안 > Client Secret (선택 사항)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Redirect URI</th>
                        <td>
                            <input type="text" value="<?php echo esc_attr( $redirect_uri ); ?>" class="large-text" readonly id="kakao-redirect-uri">
                            <button type="button" class="button" onclick="copyToClipboard()">주소 복사</button>
                            <p class="description">카카오 디벨로퍼스 > 카카오 로그인 > Redirect URI 에 이 주소를 추가하세요.</p>
                            <script>
                            function copyToClipboard() {
                                var copyText = document.getElementById("kakao-redirect-uri");
                                copyText.select();
                                document.execCommand("copy");
                                alert("복사되었습니다: " + copyText.value);
                            }
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">동의 항목 (Scopes)</th>
                        <td>
                            <fieldset>
                                <?php foreach ( $available_scopes as $scope_key => $scope_label ) : ?>
                                    <label>
                                        <input type="checkbox" name="ptg_kakao_scopes[]" value="<?php echo esc_attr( $scope_key ); ?>" 
                                            <?php checked( in_array( $scope_key, $saved_scopes ) || $scope_key === 'profile_nickname' ); ?>
                                            <?php disabled( $scope_key === 'profile_nickname' ); ?>
                                        >
                                        <?php echo esc_html( $scope_label ); ?>
                                    </label><br>
                                    <?php if ( $scope_key === 'profile_nickname' ) : ?>
                                        <input type="hidden" name="ptg_kakao_scopes[]" value="profile_nickname">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description">카카오 디벨로퍼스 > 카카오 로그인 > 동의 항목 설정과 일치시켜야 합니다.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( '설정 저장', 'primary', 'ptg_kakao_save' ); ?>
            </form>
        </div>
        <?php
    }
}
