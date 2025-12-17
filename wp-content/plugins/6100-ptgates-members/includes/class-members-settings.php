<?php
/**
 * Admin Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTG_Members_Settings {

    public function init() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_admin_menu() {
        // [COMPAT] 9000-wp-kakao-login-kit 플러그인이 활성화되어 있으면 메뉴 숨김
        if ( class_exists( 'Monolith\KakaoLoginKit\Loader' ) ) {
            return;
        }

        add_submenu_page(
            'options-general.php',
            'ptGates 멤버 설정',
            'ptGates 멤버',
            'manage_options',
            'ptg-members-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'ptg_members_options', 'ptg_members_login_redirect_url' );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>ptGates 멤버 설정</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'ptg_members_options' );
                do_settings_sections( 'ptg_members_options' );
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">로그인 후 리다이렉트 URL</th>
                        <td>
                            <input type="text" name="ptg_members_login_redirect_url" value="<?php echo esc_attr( get_option( 'ptg_members_login_redirect_url', home_url( '/account' ) ) ); ?>" class="regular-text" />
                            <p class="description">로그인(비관리자) 후 이동할 페이지 경로를 입력하세요. (기본값: /account)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
