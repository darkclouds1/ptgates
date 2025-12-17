<?php

namespace Monolith\KakaoLoginKit;

/**
 * Admin Area Functionality
 */
class Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    public function add_plugin_admin_menu() {
        add_options_page(
            'WP Kakao Login Kit', 
            'WP Kakao Login Kit', 
            'manage_options', 
            'wp-kakao-login-kit', 
            array( $this, 'display_plugin_settings_page' )
        );
    }

    public function register_settings() {
        // Register Settings
        register_setting( 'wpklk_options_group', 'wpklk_kakao_api_key' );
        register_setting( 'wpklk_options_group', 'wpklk_kakao_client_secret' ); // Optional
        register_setting( 'wpklk_options_group', 'wpklk_kakao_admin_key' );
        register_setting( 'wpklk_options_group', 'wpklk_login_redirect_url' );
        register_setting( 'wpklk_options_group', 'wpklk_logout_redirect_url' );
        register_setting( 'wpklk_options_group', 'wpklk_kakao_enabled' );
    }

    public function enqueue_styles() {
        // Add Admin Styles if needed
    }

    public function display_plugin_settings_page() {
        ?>
        <div class="wrap">
            <h1>WP Kakao Login Kit</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'wpklk_options_group' );
                do_settings_sections( 'wpklk_options_group' );
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Kakao Login 활성화</th>
                        <td>
                            <input type="checkbox" name="wpklk_kakao_enabled" value="1" <?php checked( 1, get_option( 'wpklk_kakao_enabled' ), true ); ?> />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Kakao REST API Key</th>
                        <td>
                            <input type="text" name="wpklk_kakao_api_key" value="<?php echo esc_attr( get_option( 'wpklk_kakao_api_key' ) ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Kakao Client Secret (Optional)</th>
                        <td>
                            <input type="text" name="wpklk_kakao_client_secret" value="<?php echo esc_attr( get_option( 'wpklk_kakao_client_secret' ) ); ?>" class="regular-text" />
                            <p class="description">보안 설정에서 활성화한 경우에만 입력하세요.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Kakao Admin Key (Optional)</th>
                        <td>
                            <input type="text" name="wpklk_kakao_admin_key" value="<?php echo esc_attr( get_option( 'wpklk_kakao_admin_key' ) ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Kakao Redirect URI</th>
                        <td>
                            <code><?php echo home_url( '/wp-json/wpklk/v1/auth/kakao/callback' ); ?></code>
                            <p class="description">카카오 개발자 센터의 [내 애플리케이션] > [카카오 로그인] > [Redirect URI]에 이 주소를 등록하세요.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">로그인 후 리다이렉트 URL</th>
                        <td>
                            <input type="text" name="wpklk_login_redirect_url" value="<?php echo esc_attr( get_option( 'wpklk_login_redirect_url' ) ); ?>" class="regular-text" placeholder="예: /dashboard" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">로그아웃 후 리다이렉트 URL</th>
                        <td>
                            <input type="text" name="wpklk_logout_redirect_url" value="<?php echo esc_attr( get_option( 'wpklk_logout_redirect_url' ) ); ?>" class="regular-text" placeholder="예: /" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>

            <h2>도움말 (Shortcodes)</h2>
            <ul>
                <li><code>[wp_kakao_login]</code> : 로그인 폼</li>
                <li><code>[wp_kakao_signup]</code> : 회원가입 폼</li>
                <li><code>[wp_kakao_account]</code> : 계정 관리 (프로필/비밀번호)</li>
                <li><code>[wp_kakao_verify_email]</code> : 이메일 인증 결과 페이지용</li>
            </ul>
        </div>
        <?php
    }
}
