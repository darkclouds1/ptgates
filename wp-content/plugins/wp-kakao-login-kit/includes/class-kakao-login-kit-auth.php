<?php

namespace Monolith\KakaoLoginKit;

/**
 * Local Authentication Handler
 */
class Auth {

    /**
     * Handle Signup Form Submission
     */
    public function handle_signup() {
        if ( ! isset( $_POST['wpklk_signup_nonce'] ) || ! wp_verify_nonce( $_POST['wpklk_signup_nonce'], 'wpklk_signup_action' ) ) {
            return;
        }

        $email    = sanitize_email( $_POST['email'] );
        $password = $_POST['password'];
        $pc       = $_POST['password_confirm'];
        $name     = sanitize_text_field( $_POST['name'] );
        $username = isset($_POST['username']) ? sanitize_user( $_POST['username'] ) : '';

        // Initial Validation
        if ( empty( $email ) || empty( $password ) ) {
            $this->redirect_with_error( 'signup', 'missing_fields' );
        }
        
        // Username Check
        if ( empty( $username ) ) {
            $username = $email; // Fallback only if not provided, but form should require it
        }

        if ( $password !== $pc ) {
            // ... (existing logic)
            wp_safe_redirect( add_query_arg( 'error', 'password_mismatch', wp_get_referer() ) );
            exit;
        }

        if ( email_exists( $email ) ) {
             $this->redirect_with_error( 'signup', 'email_exists' );
        }

        if ( username_exists( $username ) ) {
             $this->redirect_with_error( 'signup', 'username_exists' );
        }

        // Create User (Pending Verification)
        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            $this->redirect_with_error( 'signup', $user_id->get_error_code() );
        }

        // Update Meta
        update_user_meta( $user_id, 'display_name', $name );
        wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
        
        // Email Verification Setup
        $token = wp_generate_password( 32, false );
        update_user_meta( $user_id, 'wpklk_email_verified', 0 ); // Not verified
        update_user_meta( $user_id, 'wpklk_verification_token', $token );

        // Send Verification Email
        $this->send_verification_email( $email, $token );

        // Redirect to success page (or same page with success message)
        wp_safe_redirect( add_query_arg( 'success', 'signup_complete', wp_get_referer() ) );
        exit;
    }

    /**
     * Send Verification Email
     */
    private function send_verification_email( $email, $token ) {
        // Assume 'verify-email' is the slug for the page containing [wp_kakao_verify_email]
        // Ideally this should be configurable, but for now we look for the page or use home_url
        $verify_url = add_query_arg(
            array(
                'wpklk_action' => 'verify_email',
                'token'        => $token,
                'email'        => urlencode( $email ),
            ),
            home_url( '/' ) // Fallback to home, logic handles it on init
        );

        $subject = sprintf( '[ptGates] 이메일 인증을 완료해주세요.', get_bloginfo( 'name' ) );
        $message = sprintf( "안녕하세요,\n\n아래 링크를 클릭하여 회원가입을 완료해주세요:\n%s\n\n감사합니다.", $verify_url );
        $headers = array( 'Content-Type: text/plain; charset=UTF-8', 'From: ptGates <no-reply@ptgates.com>' );

        error_log( "[WPKLK] Attempting to send verification email to: $email" );
        $result = wp_mail( $email, $subject, $message, $headers );
        
        if ( $result ) {
            error_log( "[WPKLK] Email sent successfully to: $email" );
        } else {
            error_log( "[WPKLK] Failed to send email to: $email. Check SMTP settings." );
        }
    }

    /**
     * Handle Email Verification (GET Request)
     */
    public function handle_verify_email() {
        if ( isset( $_GET['wpklk_action'] ) && $_GET['wpklk_action'] == 'verify_email' ) {
            $token = sanitize_text_field( $_GET['token'] );
            $email = sanitize_email( $_GET['email'] );

            $user = get_user_by( 'email', $email );

            if ( ! $user ) {
                wp_die( '유효하지 않은 사용자입니다.' );
            }

            $stored_token = get_user_meta( $user->ID, 'wpklk_verification_token', true );

            if ( $stored_token && $stored_token === $token ) {
                update_user_meta( $user->ID, 'wpklk_email_verified', 1 );
                delete_user_meta( $user->ID, 'wpklk_verification_token' );
                
                // Redirect to login or a success page
                wp_safe_redirect( home_url( '/login?success=verified' ) ); // Assuming /login exists or handled elsewhere
                exit;
            } else {
                wp_die( '인증 토큰이 유효하지 않거나 만료되었습니다.' );
            }
        }
    }

    /**
     * Handle Login
     */
    public function handle_login() {
        if ( ! isset( $_POST['wpklk_login_nonce'] ) || ! wp_verify_nonce( $_POST['wpklk_login_nonce'], 'wpklk_login_action' ) ) {
            return;
        }

        $action = isset( $_POST['wpklk_action'] ) ? sanitize_text_field( $_POST['wpklk_action'] ) : 'login';

        if ( $action === 'find_password' ) {
            $this->handle_find_password();
            return;
        }

        $creds = array(
            'user_login'    => sanitize_text_field( $_POST['log'] ),
            'user_password' => $_POST['pwd'],
            'remember'      => isset( $_POST['rememberme'] ),
        );

        // Pre-check for verification is done via 'authenticate' filter? 
        // Or we can do it here if we use wp_signon manually.
        // Let's use wp_signon.
        
        $user = wp_signon( $creds, false );

        if ( is_wp_error( $user ) ) {
            // Redirect back with error
            wp_safe_redirect( add_query_arg( 'login_error', 'invalid_credentials', wp_get_referer() ) );
            exit;
        } else {
            // Success
            
            // [COMPAT] 0000-ptgates-platform Integration (Create Membership Record)
            if ( class_exists( '\PTG_Platform' ) ) {
                \PTG_Platform::get_instance()->check_and_create_member_on_login( $user->user_login, $user );
            }

            $redirect_to = get_option( 'wpklk_login_redirect_url', home_url() );
            wp_safe_redirect( $redirect_to );
            exit;
        }
    }

    /**
     * Handle Password Reset Request (Inline)
     */
    private function handle_find_password() {
        $login_input = sanitize_text_field( $_POST['log'] );
        
        if ( empty( $login_input ) ) {
            wp_safe_redirect( add_query_arg( 'reset_msg', 'empty', wp_get_referer() . '?log=' . urlencode($login_input) ) );
            exit;
        }

        // Try to find user by Login or Email
        $user = get_user_by( 'login', $login_input );
        if ( ! $user && strpos( $login_input, '@' ) ) {
            $user = get_user_by( 'email', $login_input );
        }

        if ( ! $user ) {
            // Explicitly requested: Show "User not found"
            wp_safe_redirect( add_query_arg( 'reset_msg', 'not_found', wp_get_referer() . '?log=' . urlencode($login_input) ) );
            exit;
        }

        // Send Password Reset Email
        $user_login = $user->user_login;
        $user_email = $user->user_email;
        $key = get_password_reset_key( $user );

        if ( is_wp_error( $key ) ) {
            wp_safe_redirect( add_query_arg( 'reset_msg', 'fail', wp_get_referer() . '?log=' . urlencode($login_input) ) );
            exit;
        }

        // Standard WP Password Reset URL logic
        if ( is_multisite() ) {
            $site_name = get_network()->site_name;
        } else {
            $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
        }

        // Use standard wp-login.php action=rp unless we have a custom reset page.
        // For now, link to standard RP page.
        $rp_link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');

        $message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";
        $message .= sprintf( __( 'Site Name: %s' ), $site_name ) . "\r\n\r\n";
        $message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
        $message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
        $message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
        $message .= '<' . $rp_link . '>' . "\r\n";

        $title = sprintf( __( '[%s] Password Reset' ), $site_name );
        
        // Korean Translation Override (Hardcoded for this specific request if needed, or stick to WP default strings which are localized)
        // Since get_locale() might allow automatic translation, I will assume WP default strings are fine.
        // BUT user asked for explicit messages "Check email link...". The EMAIL content itself I can customize or leave standard.
        // Let's customize it to ensure Korean delivery if site locale isn't set.
        $title = sprintf( '[%s] 비밀번호 재설정 요청', $site_name );
        $message = "안녕하세요,\r\n\r\n";
        $message .= "{$site_name} 계정의 비밀번호 재설정이 요청되었습니다.\r\n\r\n";
        $message .= "아래 링크를 클릭하여 비밀번호를 새로 설정하세요:\r\n";
        $message .= $rp_link . "\r\n\r\n";
        $message .= "본인이 요청하지 않았다면 이 메일을 무시하세요.\r\n";

        if ( wp_mail( $user_email, $title, $message ) ) {
             wp_safe_redirect( add_query_arg( 'reset_msg', 'sent', wp_get_referer() ) );
             exit;
        } else {
             wp_safe_redirect( add_query_arg( 'reset_msg', 'fail', wp_get_referer() . '?log=' . urlencode($login_input) ) );
             exit;
        }
    }

    /**
     * Filter: Authenticate - Check Email Verification
     */
    public function check_email_verified( $user, $username, $password ) {
        if ( is_a( $user, 'WP_User' ) ) {
            // Check if user is verified
            $verified = get_user_meta( $user->ID, 'wpklk_email_verified', true );
            // If meta doesn't exist, assume legacy user verified? Or enforce 0?
            // For new system, we enforce. For legacy compatibility, maybe check if created before...
            // User requested "Independent", so we enforce verified=1.
            // BUT: If copying from old system, we might need to assume existing users are verified.
            // Let's assume default verified for now unless explicitly 0. 
            // Wait, for new signups we set to 0. So if it's missing (legacy), it's 1 (active).
            
            if ( $verified === '0' ) {
                return new \WP_Error( 'not_verified', '이메일 인증이 완료되지 않았습니다. 메일함을 확인해주세요.' );
            }
        }
        return $user;
    }

    public function handle_logout() {
        if ( isset( $_GET['wpklk_action'] ) && $_GET['wpklk_action'] == 'logout' ) {
            wp_logout();
            $redirect_to = get_option( 'wpklk_logout_redirect_url', home_url() );
            wp_safe_redirect( $redirect_to );
            exit;
        }
    }

    /* Account Management Handlers (Profile, Password, Delete) */
    public function handle_profile_update() {
        if ( ! isset( $_POST['wpklk_profile_nonce'] ) || ! wp_verify_nonce( $_POST['wpklk_profile_nonce'], 'wpklk_profile_action' ) ) {
            return;
        }
        
        if ( ! is_user_logged_in() ) return;
        
        $user_id = get_current_user_id();
        $name    = sanitize_text_field( $_POST['display_name'] );
        
        wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
        
        wp_safe_redirect( add_query_arg( 'success', 'profile_updated', wp_get_referer() ) );
        exit;
    }
    
    public function handle_password_change() {
        if ( ! isset( $_POST['wpklk_pw_nonce'] ) || ! wp_verify_nonce( $_POST['wpklk_pw_nonce'], 'wpklk_pw_action' ) ) {
            return;
        }
        
        if ( ! is_user_logged_in() ) return;
        
        $user = wp_get_current_user();
        $current_pw = $_POST['current_password'];
        $new_pw     = $_POST['pass1'];
        $new_pw2    = $_POST['pass2'];
        
        if ( ! wp_check_password( $current_pw, $user->user_pass, $user->ID ) ) {
            wp_safe_redirect( add_query_arg( 'error', 'current_password_mismatch', wp_get_referer() ) );
            exit;
        }
        
        if ( $new_pw !== $new_pw2 ) {
           wp_safe_redirect( add_query_arg( 'error', 'password_mismatch', wp_get_referer() ) );
           exit; 
        }

        // Complexity Check: 8+ chars, Letter + Number
        if ( strlen( $new_pw ) < 8 || ! preg_match( '/[a-zA-Z]/', $new_pw ) || ! preg_match( '/[0-9]/', $new_pw ) ) {
            wp_safe_redirect( add_query_arg( 'error', 'complexity_error', wp_get_referer() ) );
            exit;
        }
        
        wp_set_password( $new_pw, $user->ID );
        
        // Re-login
        $creds = array(
            'user_login'    => $user->user_login,
            'user_password' => $new_pw,
            'remember'      => true
        );
        wp_signon( $creds );
        
        wp_safe_redirect( add_query_arg( 'success', 'password_changed', wp_get_referer() ) );
        exit;
    }
    
    public function handle_account_delete() {
        if ( ! isset( $_POST['wpklk_delete_nonce'] ) || ! wp_verify_nonce( $_POST['wpklk_delete_nonce'], 'wpklk_delete_action' ) ) {
            return;
        }
        
        if ( ! is_user_logged_in() ) return;
        
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        $user_id = get_current_user_id();

        // Prevent Admin Deletion
        if ( user_can( $user_id, 'manage_options' ) ) {
            $this->redirect_with_error( 'account', 'admin_delete_forbidden' ); // Reuse helper or manual redirect
            // Since redirect_with_error expects context, let's look at it.
            // Helper: wp_safe_redirect( add_query_arg( array( 'error' => $error_code ), wp_get_referer() ) );
            // So:
            wp_safe_redirect( add_query_arg( 'error', 'admin_delete_forbidden', wp_get_referer() ) );
            exit;
        }
        
        wp_delete_user( $user_id );
        
        wp_safe_redirect( home_url() );
        exit;
    }

    private function redirect_with_error( $context, $error_code ) {
        wp_safe_redirect( add_query_arg( array( 'error' => $error_code ), wp_get_referer() ) );
        exit;
    }
}
