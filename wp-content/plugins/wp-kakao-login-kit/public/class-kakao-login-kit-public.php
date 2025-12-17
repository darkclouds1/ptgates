<?php

namespace Monolith\KakaoLoginKit;

/**
 * Public Facing Functionality
 */
class Public_Facing {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, WPKLK_PLUGIN_URL . 'public/css/kakao-login-kit.css', array(), $this->version, 'all' );
        wp_enqueue_script( 'jquery' ); // Make sure jQuery is loaded if needed for password toggle (optional)
        // Add minimal JS for password toggle if kept from original? 
        // Original members had JS for simple things. We can inline it or create a file.
        // For now, let's keep it simple.
    }

    /* Shortcode Callbacks */
    public function render_login_form( $atts ) {
        if ( is_user_logged_in() ) {
            return '<p>이미 로그인되어 있습니다.</p>';
        }
        ob_start();
        include WPKLK_PLUGIN_DIR . 'templates/login-form.php';
        return ob_get_clean();
    }

    public function render_signup_form( $atts ) {
        if ( is_user_logged_in() ) {
            return '<p>이미 로그인되어 있습니다.</p>';
        }
        ob_start();
        include WPKLK_PLUGIN_DIR . 'templates/signup-form.php';
        return ob_get_clean();
    }

    public function render_account_form( $atts ) {
        if ( ! is_user_logged_in() ) {
            return sprintf( '<p>로그인이 필요합니다. <a href="%s">로그인</a></p>', wp_login_url() );
        }
        ob_start();
        include WPKLK_PLUGIN_DIR . 'templates/account-form.php';
        return ob_get_clean();
    }

    public function render_verify_email( $atts ) {
        // This shortcode is just a placeholder for the verify page content
        // Actual logic handles URL query parameters on init hook.
        // We can display success/fail messages here if passed via query args.
        ob_start();
        ?>
        <div class="ptg-member-wrapper">
             <div class="ptg-member-card">
                 <h2>이메일 인증</h2>
                 <?php
                 if ( isset( $_GET['wpklk_action'] ) && $_GET['wpklk_action'] == 'verify_email' ) {
                     // Processing happens in Auth::handle_verify_email
                     // If we are here, it might have failed or succeeded and redirected?
                     // Actually Auth redirects on success. So here we handle generic messaging?
                 }
                 // If redirected here with success
                 if ( isset( $_GET['success'] ) && $_GET['success'] == 'verified' ) {
                     echo '<div class="ptg-success">인증이 완료되었습니다. 로그인해주세요.</div>';
                 }
                 ?>
             </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
