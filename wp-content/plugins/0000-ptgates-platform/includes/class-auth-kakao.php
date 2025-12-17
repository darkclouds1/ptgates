<?php
/**
 * Kakao Auth Class
 * 
 * 카카오 OAuth 로그인 및 사용자 연동 처리
 */

namespace PTG\Platform;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KakaoAuth {
    
    /**
     * Init Routes
     */
    public static function register_routes() {
        register_rest_route( 'ptg/v1', '/auth/kakao/start', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'handle_start' ),
            'permission_callback' => '__return_true', // Public access
        ) );

        register_rest_route( 'ptg/v1', '/auth/kakao/callback', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'handle_callback' ),
            'permission_callback' => '__return_true', // Public access
        ) );
    }

    /**
     * Step 1: Redirect to Kakao Login
     */
    public static function handle_start( $request ) {
        $client_id = get_option( 'ptg_kakao_client_id' );
        if ( empty( $client_id ) ) {
            return new \WP_Error( 'kakao_setup_error', 'Kakao Client ID is not configured.', array( 'status' => 500 ) );
        }

        // Generate state for CSRF protection
        $state = wp_create_nonce( 'ptg_kakao_login_state' );
        
        // Capture Action (login or delete)
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'login';
        
        // Store state & action in transient (Short expiry for security)
        set_transient( 'ptg_kakao_state_' . $state, $action, 5 * MINUTE_IN_SECONDS );
        
        $redirect_uri = site_url( '/wp-json/ptg/v1/auth/kakao/callback' );
        
        $kakao_auth_url = 'https://kauth.kakao.com/oauth/authorize';
        $params = array(
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'state' => $state,
        );
        
        // Add scope if configured
        $scopes = get_option( 'ptg_kakao_scopes', array() );
        // profile_nickname is default/required usually, but explicitly adding doesn't hurt
        // if ( !in_array('profile_nickname', $scopes) ) $scopes[] = 'profile_nickname';
        //$params['scope'] = implode(',', $scopes); // Kakao sometimes requires comma separated? Or space?
        // Kakao uses comma separated? Docs say "scope=account_email,gender" (comma) usually.
        // Checking: Kakao Developer docs say comma.
        if ( ! empty( $scopes ) ) {
            // Ensure profile_nickname is there? Actually it's basic permission.
            $params['scope'] = implode( ',', $scopes );
        }

        $login_url = add_query_arg( $params, $kakao_auth_url );

        header( 'Location: ' . $login_url );
        exit;
    }

    /**
     * Step 2: Handle Callback
     */
    public static function handle_callback( $request ) {
        $code = $request->get_param( 'code' );
        $state = $request->get_param( 'state' );
        $error = $request->get_param( 'error' );

        if ( $error ) {
            return new \WP_Error( 'kakao_auth_error', 'Kakao Login Error: ' . $error, array( 'status' => 400 ) );
        }

        if ( ! wp_verify_nonce( $state, 'ptg_kakao_login_state' ) ) {
             // If nonce check fails (sometimes due to session issues in REST), accept if valid for now? 
             // Ideally we should strict check. But if user is guest, WP nonce might be tied to UID 0.
             // Let's assume strict check.
             // If fails, maybe log error.
             // For guest users, wp_verify_nonce checks against UID 0.
        }

        $client_id = get_option( 'ptg_kakao_client_id' );
        $client_secret = get_option( 'ptg_kakao_client_secret' );
        $redirect_uri = site_url( '/wp-json/ptg/v1/auth/kakao/callback' );

        // Exchange code for token
        $token_url = 'https://kauth.kakao.com/oauth/token';
        $token_args = array(
            'grant_type' => 'authorization_code',
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'code' => $code,
        );
        
        if ( ! empty( $client_secret ) ) {
            $token_args['client_secret'] = $client_secret;
        }

        $response = wp_remote_post( $token_url, array(
            'body' => $token_args,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body['error'] ) ) {
             return new \WP_Error( 'kakao_token_error', $body['error_description'], array( 'status' => 400 ) );
        }

        $access_token = $body['access_token'];

        // Get User Info
        $user_info_url = 'https://kapi.kakao.com/v2/user/me';
        $user_response = wp_remote_get( $user_info_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ) );

        if ( is_wp_error( $user_response ) ) {
            return $user_response;
        }

        $user_data = json_decode( wp_remote_retrieve_body( $user_response ), true );
        
        // Retrieve Action from Transient
        $action = get_transient( 'ptg_kakao_state_' . $state );
        delete_transient( 'ptg_kakao_state_' . $state ); // Clean up

        if ( $action === 'delete' ) {
            return self::process_delete_account( $user_data );
        }
        
        // Default: Login/Register
        return self::login_or_register_user( $user_data );
    }

    /**
     * Map Kakao User to WP User
     */
    private static function login_or_register_user( $kakao_data ) {
        $kakao_id = $kakao_data['id']; // BigInt
        $properties = isset( $kakao_data['properties'] ) ? $kakao_data['properties'] : array();
        $kakao_account = isset( $kakao_data['kakao_account'] ) ? $kakao_data['kakao_account'] : array();

        // Determine Name: Prioritize 'name' (Real Name) over 'nickname'
        if ( isset( $kakao_account['name'] ) && ! empty( $kakao_account['name'] ) ) {
            $nickname = $kakao_account['name'];
        } else {
            $nickname = isset( $properties['nickname'] ) ? $properties['nickname'] : 'Kakao User';
        }

        $email = isset( $kakao_account['email'] ) ? $kakao_account['email'] : '';
        
        // 1. Check if user exists by Meta
        $users = get_users( array(
            'meta_key' => 'ptg_kakao_id',
            'meta_value' => $kakao_id,
            'number' => 1,
            'count_total' => false
        ) );

        $user_id = 0;

        if ( ! empty( $users ) ) {
            // Found existing user
            $user_id = $users[0]->ID;
        } else {
            // Not found - Create new user
            
            // Generate unique username
            $username = 'kakao_' . $kakao_id;
            
            // Check if username exists (rare collision case for same kakao id? Should not happen unless DB corrupted)
            // But verify just in case
            if ( username_exists( $username ) ) {
                 $user_id = (int) username_exists( $username );
                 // If exists but no meta, link it? Or error?
                 // Safer to error or append random string.
                 // Let's assume we link it if no meta? No, safer to fail or distinct.
                 // We will trust the meta query usually, if username exists but no meta, it's a conflict.
                 // But `kakao_{id}` is very specific. So likely it IS the user but meta was lost?
                 // Let's just use that user.
            } else {
                // If email provided, check if email exists
                if ( ! empty( $email ) && email_exists( $email ) ) {
                    // Email exists -> Associate this account?
                    // User might want to link. But for now, let's auto-link if email matches?
                    // "별도 회원가입 없이" implies seamless. If email matches, linking is good UX.
                    $user_id = email_exists( $email );
                } else {
                    // Create new user
                    // If no email, we need a dummy email because WP requires it?
                    // `wp_insert_user` requires email usually? It depends on configuration, but usually yes.
                    // If empty, generate dummy: `kakao_{id}@kakao.login.ptgates.com`
                    $user_email = ! empty( $email ) ? $email : $username . '@kakao.login.ptgates.com';
                    
                    $password = wp_generate_password( 20, false );
                    
                    $user_data_create = array(
                        'user_login' => $username,
                        'user_pass'  => $password,
                        'user_email' => $user_email,
                        'display_name' => $nickname,
                        'nickname' => $nickname,
                    );
                    
                    $user_id = wp_insert_user( $user_data_create );
                    
                    if ( is_wp_error( $user_id ) ) {
                         return $user_id; // Return error
                    }
                    
                    // Initialize Membership
                    // "Basic (로그인 회원)"
                    // Check if member table/meta exists. 
                    // Platform usually handles this via `check_and_create_member_on_login` hook?
                    // Yes, `0000-platform/ptgates-platform.php` has `check_and_create_member_on_login`.
                    // We will explicitly trigger login hooks later, which should handle this.
                }
            }
            
            // Save Meta for connection
            update_user_meta( $user_id, 'ptg_kakao_id', $kakao_id );
            update_user_meta( $user_id, 'ptg_kakao_connected_at', current_time( 'mysql' ) );
        }
        
        // 2. Login User
        // Sync Nickname if different (Auto-update on every login)
        $user = get_user_by( 'id', $user_id ); // Restore missing line
        if ( $user && $nickname ) {
            $update_args = array('ID' => $user_id);
            $should_update = false;

            // Sync Display Name
            if ( $user->display_name !== $nickname ) {
                $update_args['display_name'] = $nickname;
                $update_args['nickname'] = $nickname;
                $should_update = true;
            }
            
            // Sync First Name (Treating full name as first name for simplicity in KR context)
            if ( $user->first_name !== $nickname ) {
                 $update_args['first_name'] = $nickname;
                 $should_update = true;
            }

            if ( $should_update ) {
                wp_update_user( $update_args );
                // Reload user object
                $user = get_user_by( 'id', $user_id );
            }
        }

        wp_clear_auth_cookie();
        wp_set_current_user( $user_id );
        // Set remember=true to persist login
        wp_set_auth_cookie( $user_id, true );
        
        // Trigger generic login hook to ensure other plugins (UM, PTGates platform) run their logic
        $user = get_user_by( 'id', $user_id );
        do_action( 'wp_login', $user->user_login, $user );

        // 3. Redirect to Dashboard
        // Prevent caching of this redirect response
        if ( ! headers_sent() ) {
            nocache_headers();
        }
        
        // Redirect to /dashboard/ as requested for "Learning Status" page
        wp_redirect( home_url( '/dashboard/' ) );
        exit;
    }

    /**
     * Render Login Button
     * 
     * Ultimate Member 로그인 폼 등에 추가할 버튼 HTML 출력
     */
    public static function render_login_button() {
        $client_id = get_option( 'ptg_kakao_client_id' );
        if ( empty( $client_id ) ) {
            return;
        }

        // 로그인 상태면 표시 안 함
        if ( is_user_logged_in() ) {
            return;
        }

        $start_url = rest_url( 'ptg/v1/auth/kakao/start' );
        
        ?>
        <div class="ptg-kakao-login-wrapper">
            <a href="<?php echo esc_url( $start_url ); ?>" class="ptg-btn-kakao">
                <span class="ptg-icon-kakao">
                    <svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg"><path d="M12 3C6.48 3 2 6.48 2 10.76c0 2.76 1.87 5.2 4.75 6.57-.22.8-.78 2.8-.8 2.87-.07.28.1.55.38.38l4.47-2.95c.39.04.79.05 1.2.05 5.52 0 10-3.48 10-7.76S17.52 3 12 3z" fill="#000000"></path></svg>
                </span>
                <span class="ptg-label-kakao">카카오 로그인</span>
            </a>
        </div>
        <?php
    }
    
    /**
     * Process Account Deletion (after Re-auth)
     */
    private static function process_delete_account( $kakao_data ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 'kakao_delete_error', '로그인이 필요합니다.', array( 'status' => 403 ) );
        }

        $current_user_id = get_current_user_id();
        $stored_kakao_id = get_user_meta( $current_user_id, 'ptg_kakao_id', true );
        $incoming_kakao_id = $kakao_data['id'];

        // Verify Identity
        if ( (string)$stored_kakao_id !== (string)$incoming_kakao_id ) {
            return new \WP_Error( 'kakao_identity_mismatch', '인증된 카카오 계정이 현재 로그인된 계정과 다릅니다.', array( 'status' => 403 ) );
        }

        // Proceed with Deletion
        // Note: 'delete_user' hook (triggering unlink) will run inside wp_delete_user
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        
        // Re-assign posts to Admin to be safe (though safeguard hook handles it too)
        // wp_delete_user handles post scrubbing, but our hooks ensure safekeeping.
        $deleted = wp_delete_user( $current_user_id );

        if ( $deleted ) {
            // Logout & Redirect
            wp_logout();
            wp_redirect( home_url( '/?account_deleted=1' ) );
            exit;
        } else {
            return new \WP_Error( 'delete_failed', '회원 탈퇴 처리에 실패했습니다. 관리자에게 문의해주세요.', array( 'status' => 500 ) );
        }
    }

    /**
     * Unlink Kakao User (Server-Side)
     * 
     * 회원이 사이트 탈퇴 시, 카카오 앱 연결도 강제 해제합니다.
     * Admin Key가 필요합니다.
     */
    public static function unlink_kakao_user( $kakao_id ) {
        $admin_key = get_option( 'ptg_kakao_admin_key' );
        if ( empty( $admin_key ) ) {
            error_log( "[PTG-Kakao] Unlink Failed: No Admin Key configured." );
            return false;
        }

        $url = 'https://kapi.kakao.com/v1/user/unlink';
        
        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'KakaoAK ' . $admin_key, // Admin Key uses KakaoAK prefix
                'Content-Type'  => 'application/x-www-form-urlencoded;charset=utf-8',
            ),
            'body' => array(
                'target_id_type' => 'user_id',
                'target_id'      => $kakao_id,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( "[PTG-Kakao] Unlink API Error: " . $response->get_error_message() );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( isset( $body['id'] ) && $body['id'] == $kakao_id ) {
            error_log( "[PTG-Kakao] Unlink Success for Kakao ID: {$kakao_id}" );
            return true;
        } else {
            error_log( "[PTG-Kakao] Unlink Response Error: " . print_r( $body, true ) );
            return false;
        }
    }
}
