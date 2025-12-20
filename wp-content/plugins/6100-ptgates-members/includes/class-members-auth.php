<?php
/**
 * Member Auth Logic
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTG_Members_Auth {

    public function init() {
        // Handle Form Submissions
        add_action( 'init', [ $this, 'handle_signup' ] );
        add_action( 'init', [ $this, 'handle_login' ] );
        add_action( 'init', [ $this, 'handle_account_update' ] );
        
        // Prevent login if not verified
        add_filter( 'authenticate', [ $this, 'check_email_verified' ], 30, 3 );
        
        // Logout redirect
        add_action( 'wp_logout', [ $this, 'redirect_after_logout' ] );

        // Handle /logout custom endpoint
        add_action( 'init', [ $this, 'handle_custom_logout' ] );
    }

    /**
     * Process Signup
     */
    public function handle_signup() {
        if ( ! isset( $_POST['ptg_signup_nonce'] ) || ! wp_verify_nonce( $_POST['ptg_signup_nonce'], 'ptg_signup_action' ) ) {
            return;
        }

        // [DEBUG]
        $debug_file = plugin_dir_path( __FILE__ ) . 'signup_debug.txt';
        $log_entry = date('Y-m-d H:i:s') . " - Signup Attempt: Data=" . print_r($_POST, true) . "\n";
        file_put_contents($debug_file, $log_entry, FILE_APPEND);

        $email    = sanitize_email( $_POST['email'] );
        $username = sanitize_user( $_POST['username'] );
        $name     = sanitize_text_field( $_POST['name'] );
        $password = $_POST['password'];
        $pass_confirm = $_POST['pass_confirm'];

        if ( empty( $name ) ) {
            wp_redirect( add_query_arg( 'error', 'missing_name', home_url( '/signup' ) ) );
            exit;
        }

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_redirect( add_query_arg( 'error', 'invalid_email', home_url( '/signup' ) ) );
            exit;
        }

        if ( empty( $username ) || username_exists( $username ) ) {
             wp_redirect( add_query_arg( 'error', 'username_exists', home_url( '/signup' ) ) );
             exit;
        }

        if ( email_exists( $email ) ) {
            wp_redirect( add_query_arg( 'error', 'email_exists', home_url( '/signup' ) ) );
            exit;
        }

        // Password Validation: 8+ chars, Letters + Numbers
        if ( strlen( $password ) < 8 || ! preg_match( '/[A-Za-z]/', $password ) || ! preg_match( '/[0-9]/', $password ) ) {
             wp_redirect( add_query_arg( 'error', 'invalid_password', home_url( '/signup' ) ) );
             exit;
        }

        if ( $password !== $pass_confirm ) {
            wp_redirect( add_query_arg( 'error', 'password_mismatch', home_url( '/signup' ) ) );
            exit;
        }

        // Create User
        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_redirect( add_query_arg( 'error', 'registration_failed', home_url( '/signup' ) ) );
            exit;
        }

        // Setup User Meta
        $user = new WP_User( $user_id );
        $user->set_role( 'subscriber' );
        
        // Update Name
        wp_update_user( [ 'ID' => $user_id, 'display_name' => $name, 'first_name' => $name ] );
        
        update_user_meta( $user_id, 'email_verified', 0 );
        $token = wp_generate_password( 32, false );
        update_user_meta( $user_id, 'email_verify_token', $token );
        update_user_meta( $user_id, 'auth_provider', 'local' );

        // Send Email
        $this->send_verification_email( $user_id, $email, $token );

        // Redirect to Login with specific success message
        wp_redirect( add_query_arg( 'success', 'registered_verify', home_url( '/login' ) ) );
        exit;
    }

    /**
     * Send Verification Email
     */
    private function send_verification_email( $user_id, $email, $token ) {
        $subject = '[ptGates] 이메일 인증을 완료해주세요';
        $verify_link = home_url( "/verify-email?uid={$user_id}&token={$token}" );
        
        $message = "안녕하세요,\n\n";
        $message .= "아래 링크를 클릭하면 회원가입이 완료됩니다.\n";
        $message .= $verify_link . "\n\n";
        $message .= "감사합니다.";

        $headers = [ 'Content-Type: text/plain; charset=UTF-8', 'From: ptGates <no-reply@ptgates.com>' ];

        error_log( "[PTG Auth] Attempting to send verification email to: $email" );
        $result = wp_mail( $email, $subject, $message, $headers );
        
        if ( $result ) {
            error_log( "[PTG Auth] Email sent successfully to: $email" );
        } else {
            error_log( "[PTG Auth] Failed to send email to: $email. Check SMTP/Mail settings." );
        }
    }

    /**
     * Verify Email Token
     */
    public function verify_email_token( $user_id, $token ) {
        // Check for Email Change
        if ( isset( $_GET['type'] ) && 'change_email' === $_GET['type'] ) {
            $stored_token = get_user_meta( $user_id, 'new_email_token', true );
            $new_email = get_user_meta( $user_id, 'new_email_pending', true );
            
            if ( $stored_token && hash_equals( $stored_token, $token ) && $new_email ) {
                $args = [ 'ID' => $user_id, 'user_email' => $new_email ];
                wp_update_user( $args );
                
                delete_user_meta( $user_id, 'new_email_token' );
                delete_user_meta( $user_id, 'new_email_pending' );
                return true;
            }
            return false;
        }

        // Standard Verification
        $stored_token = get_user_meta( $user_id, 'email_verify_token', true );
        
        if ( $stored_token && hash_equals( $stored_token, $token ) ) {
            update_user_meta( $user_id, 'email_verified', 1 );
            delete_user_meta( $user_id, 'email_verify_token' );
            return true;
        }
        return false;
    }

    /**
     * Check Login Verification
     */
    public function check_email_verified( $user, $username, $password ) {
        if ( is_a( $user, 'WP_User' ) ) {
            $verified = get_user_meta( $user->ID, 'email_verified', true );
            $provider = get_user_meta( $user->ID, 'auth_provider', true );
            
            if ( 'local' === $provider && '1' !== (string) $verified ) {
                return new WP_Error( 'email_not_verified', '이메일 인증이 완료되지 않았습니다. 메일함을 확인해주세요.' );
            }
        }
        return $user;
    }

    /**
     * Handle Login (Custom Form)
     */
    public function handle_login() {
        if ( ! isset( $_POST['ptg_login_nonce'] ) || ! wp_verify_nonce( $_POST['ptg_login_nonce'], 'ptg_login_action' ) ) {
            return;
        }

        $login_identifier = sanitize_text_field( $_POST['log'] );
        $password = $_POST['password'];
        $remember = isset( $_POST['remember'] );

        // 1. Resolve User (ID or Email)
        $user = get_user_by( 'login', $login_identifier );
        if ( ! $user && is_email( $login_identifier ) ) {
            $user = get_user_by( 'email', $login_identifier );
        }

        if ( ! $user ) {
            // User not found
            wp_redirect( add_query_arg( 'error', 'login_failed', home_url( '/login' ) ) );
            exit;
        }

        // 2. Validate Password
        if ( ! wp_check_password( $password, $user->data->user_pass, $user->ID ) ) {
            wp_redirect( add_query_arg( 'error', 'login_failed', home_url( '/login' ) ) );
            exit;
        }

        // 3. Check Email Verification (Optional hook, but double check here if needed)
        // logic moved to authenticate filter basically, but we are bypassing wp_signon mostly now?
        // Let's use wp_signon to trigger standard hooks, but valid credentials first.
        // Actually, let's just do manual login for full control OR ensure wp_signon works.
        // Using manual set_auth_cookie is safer for broken redirection sessions.

        // Re-check verification manually since we bypass 'authenticate' filter if we don't use wp_signon
        $verified = get_user_meta( $user->ID, 'email_verified', true );
        $provider = get_user_meta( $user->ID, 'auth_provider', true );
        if ( 'local' === $provider && '1' !== (string) $verified ) {
             wp_redirect( add_query_arg( 'error', 'email_not_verified', home_url( '/login' ) ) );
             exit;
        }

        // 4. Log User In
        wp_set_current_user( $user->ID, $user->user_login );
        wp_set_auth_cookie( $user->ID, $remember );
        do_action( 'wp_login', $user->user_login, $user );

        // 5. Redirect based on Role
        if ( in_array( 'administrator', (array) $user->roles ) || user_can( $user, 'manage_options' ) ) {
            wp_safe_redirect( admin_url() ); // Go to /wp-admin/
            exit;
        }

        $redirect_url = get_option( 'ptg_members_login_redirect_url', home_url( '/account' ) );
        wp_safe_redirect( $redirect_url ); 
        exit;
    }

    /**
     * Handle Account Update
     */
    public function handle_account_update() {
        if ( ! is_user_logged_in() ) {
            return;
        }
        
        $user = wp_get_current_user();

        // 1. Profile Update (Name & Email)
        if ( isset( $_POST['ptg_profile_nonce'] ) && wp_verify_nonce( $_POST['ptg_profile_nonce'], 'ptg_profile_action' ) ) {
            $name = sanitize_text_field( $_POST['name'] );
            $email = sanitize_email( $_POST['email'] );
            
            // Check Email
            if ( $email !== $user->user_email && email_exists( $email ) ) {
                 wp_redirect( add_query_arg( 'error', 'email_exists', home_url( '/account' ) ) );
                 exit;
            }
            
            // Update Name
            wp_update_user( [ 'ID' => $user->ID, 'display_name' => $name, 'first_name' => $name ] );
            
            // Update Email 
            if ( $email !== $user->user_email ) {
                $token = wp_generate_password( 32, false );
                update_user_meta( $user->ID, 'new_email_pending', $email );
                update_user_meta( $user->ID, 'new_email_token', $token );
                
                // Send Verification to NEW email
                $subject = '[ptGates] 이메일 변경 확인';
                $link = home_url( "/verify-email?uid={$user->ID}&token={$token}&type=change_email" );
                $msg = "이메일 변경을 완료하려면 아래 링크를 클릭하세요:\n" . $link;
                wp_mail( $email, $subject, $msg );
                
                wp_redirect( add_query_arg( 'success', 'profile_updated_email_sent', home_url( '/account' ) ) );
                exit;
            }

            wp_redirect( add_query_arg( 'success', 'profile_updated', home_url( '/account' ) ) );
            exit;
        }

        // 2. Password Change
        if ( isset( $_POST['ptg_pw_change_nonce'] ) && wp_verify_nonce( $_POST['ptg_pw_change_nonce'], 'ptg_pw_change_action' ) ) {
            $current_pass = $_POST['current_password'];
            $pass1 = $_POST['pass1'];
            $pass2 = $_POST['pass2'];
            
            // Check Current Password
            if ( ! wp_check_password( $current_pass, $user->data->user_pass, $user->ID ) ) {
                 wp_redirect( add_query_arg( 'error', 'current_password_mismatch', home_url( '/account' ) ) );
                 exit;
            }

            if ( $pass1 === $pass2 && ! empty( $pass1 ) ) {
                wp_set_password( $pass1, $user->ID );
                
                // Re-login
                $creds = array(
                    'user_login'    => $user->user_login,
                    'user_password' => $pass1,
                    'remember'      => true,
                );
                wp_signon( $creds, false );

                wp_redirect( add_query_arg( 'success', 'password_changed', home_url( '/account' ) ) );
                exit;
            } else {
                wp_redirect( add_query_arg( 'error', 'password_mismatch', home_url( '/account' ) ) );
                exit;
            }
        }

        // Delete Account
        if ( isset( $_POST['ptg_delete_account_nonce'] ) && wp_verify_nonce( $_POST['ptg_delete_account_nonce'], 'ptg_delete_account_action' ) ) {
             require_once( ABSPATH . 'wp-admin/includes/user.php' );
             $user_id = get_current_user_id();
             wp_delete_user( $user_id );
             wp_redirect( home_url( '/signup' ) );
             exit;
        }
    }

    public function redirect_after_logout(){
        wp_redirect( home_url( '/login?logged_out=true' ) );
        exit;
    }

    /**
     * Handle /logout endpoint
     */
    public function handle_custom_logout() {
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
            if ( rtrim( $path, '/' ) === '/logout' ) {
                wp_logout();
                exit;
            }
        }
    }
}
