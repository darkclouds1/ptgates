<?php
/**
 * Shortcodes Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTG_Members_Shortcodes {

    private $auth;

    public function __construct( $auth ) {
        $this->auth = $auth;
    }

    public function init() {
        add_shortcode( 'ptg_signup', [ $this, 'render_signup' ] );
        add_shortcode( 'ptg_login', [ $this, 'render_login' ] );
        add_shortcode( 'ptg_verify_email', [ $this, 'render_verify_email' ] );
        add_shortcode( 'ptg_account', [ $this, 'render_account' ] );
    }

    public function render_signup() {
        if ( is_user_logged_in() ) {
            return '<div class="ptg-member-wrapper"><div class="ptg-member-card">
                <p>이미 로그인되어 있습니다.</p>
                <p><a href="' . home_url( '/account' ) . '" class="ptg-btn">계정 관리</a></p>
            </div></div>';
        }
        ob_start();
        include PTG_MEMBERS_PATH . 'templates/signup-form.php';
        return ob_get_clean();
    }

    public function render_login() {
        if ( is_user_logged_in() ) {
             $output = '<div class="ptg-member-wrapper"><div class="ptg-member-card">';
             $output .= '<p>이미 로그인되어 있습니다.</p>';
             $output .= '<p><a href="' . home_url( '/account' ) . '" class="ptg-btn">계정 관리</a></p>';
             
             $current_user = wp_get_current_user();
             if ( current_user_can( 'manage_options' ) ) {
                 $output .= '<p><a href="' . admin_url() . '" class="ptg-btn" style="background:#2271b1; margin-top:10px;">관리자 대시보드</a></p>';
                 $output .= '<p style="font-size:12px; color:#666;">Debug: User ID ' . $current_user->ID . ', Roles: ' . implode( ', ', (array) $current_user->roles ) . '</p>';
             } else {
                 $output .= '<p style="font-size:12px; color:#666;">Debug: User ID ' . $current_user->ID . ', Roles: ' . implode( ', ', (array) $current_user->roles ) . '</p>';
             }
             
             $output .= '</div></div>';
             return $output;
        }
        ob_start();
        include PTG_MEMBERS_PATH . 'templates/login-form.php';
        return ob_get_clean();
    }

    public function render_verify_email() {
        $uid = isset( $_GET['uid'] ) ? intval( $_GET['uid'] ) : 0;
        $token = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';

        if ( $uid && $token ) {
            if ( $this->auth->verify_email_token( $uid, $token ) ) {
                return '<div class="ptg-message success"><h2>인증 완료</h2><p>이메일 인증이 성공적으로 완료되었습니다. <a href="' . home_url( '/login' ) . '">로그인하기</a></p></div>';
            } else {
                return '<div class="ptg-message error"><h2>인증 실패</h2><p>유효하지 않거나 만료된 링크입니다.</p></div>';
            }
        }
        return '';
    }

    public function render_account() {
        if ( ! is_user_logged_in() ) {
            return '<p>로그인이 필요합니다. <a href="' . home_url( '/login' ) . '">로그인</a></p>';
        }
        ob_start();
        include PTG_MEMBERS_PATH . 'templates/account-form.php';
        return ob_get_clean();
    }
}
