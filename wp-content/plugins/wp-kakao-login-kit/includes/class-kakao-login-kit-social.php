<?php

namespace Monolith\KakaoLoginKit;

/**
 * Social Authentication (Kakao)
 */
class Social {

    private $api_key;
    private $redirect_uri;
    private $client_secret;

    public function __construct() {
        $this->api_key       = get_option( 'wpklk_kakao_api_key' );
        $this->client_secret = get_option( 'wpklk_kakao_client_secret' );
        // Dynamic construction of redirect URI based on registered route
        $this->redirect_uri  = home_url( '/wp-json/wpklk/v1/auth/kakao/callback' );
    }

    public function register_routes() {
        register_rest_route( 'wpklk/v1', '/auth/kakao/start', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_start' ),
            'permission_callback' => '__return_true',
        ));

        register_rest_route( 'wpklk/v1', '/auth/kakao/callback', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_callback' ),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Step 1: Redirect to Kakao Auth
     */
    public function handle_start( $request ) {
        if ( ! get_option( 'wpklk_kakao_enabled' ) ) {
            return new \WP_Error( 'kakao_disabled', 'Kakao login is disabled.', array( 'status' => 403 ) );
        }

        if ( empty( $this->api_key ) ) {
            return new \WP_Error( 'kakao_config_error', 'Kakao API Key is missing.', array( 'status' => 500 ) );
        }

        $kakao_auth_url = sprintf(
            'https://kauth.kakao.com/oauth/authorize?client_id=%s&redirect_uri=%s&response_type=code',
            $this->api_key,
            urlencode( $this->redirect_uri )
        );

        // Redirect user to Kakao
        // Since this is a REST endpoint, we can return a redirect header or JSON. 
        // Standard flow usage usually redirects directly.
        header( "Location: $kakao_auth_url" );
        exit;
    }

    /**
     * Step 2: Handle Callback
     */
    public function handle_callback( $request ) {
        $code = $request->get_param( 'code' );
        
        if ( ! $code ) {
            // Error handling
            wp_redirect( home_url( '/login?error=kakao_no_code' ) );
            exit;
        }

        // 1. Get Access Token
        $response = wp_remote_post( 'https://kauth.kakao.com/oauth/token', array(
            'body' => array(
                'grant_type'    => 'authorization_code',
                'client_id'     => $this->api_key,
                'redirect_uri'  => $this->redirect_uri,
                'code'          => $code,
                'client_secret' => $this->client_secret, // Optional
            )
        ));

        if ( is_wp_error( $response ) ) {
            wp_redirect( home_url( '/login?error=kakao_token_fail' ) );
            exit;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( ! isset( $body['access_token'] ) ) {
            wp_redirect( home_url( '/login?error=kakao_token_invalid' ) );
            exit;
        }

        $access_token = $body['access_token'];

        // 2. Get User Info
        $user_info_response = wp_remote_get( 'https://kapi.kakao.com/v2/user/me', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            )
        ));

        if ( is_wp_error( $user_info_response ) ) {
            wp_redirect( home_url( '/login?error=kakao_user_info_fail' ) );
            exit;
        }

        $user_info = json_decode( wp_remote_retrieve_body( $user_info_response ), true );
        $kakao_id  = $user_info['id'];
        
        // Extract properties (nickname, email)
        $properties = isset( $user_info['properties'] ) ? $user_info['properties'] : array();
        $kakao_account = isset( $user_info['kakao_account'] ) ? $user_info['kakao_account'] : array();

        $nickname = isset( $properties['nickname'] ) ? $properties['nickname'] : 'Kakao User';
        $email    = isset( $kakao_account['email'] ) ? $kakao_account['email'] : '';

        // 3. Resolve User
        $user = $this->resolve_user( $kakao_id, $email, $nickname );

        if ( $user ) {
            // Login User
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID, true );
            
            // [COMPAT] 0000-ptgates-platform Integration (Create Membership Record)
            // REST API contexts sometimes miss wp_login hooks for platform init. Explicit call ensures data integrity.
            if ( class_exists( '\PTG_Platform' ) ) {
                \PTG_Platform::get_instance()->check_and_create_member_on_login( $user->user_login, $user );
            }
            
            // Redirect
            $redirect_to = get_option( 'wpklk_login_redirect_url', home_url() );
            wp_redirect( $redirect_to );
            exit;
        } else {
             wp_redirect( home_url( '/login?error=login_failed' ) );
             exit;
        }
    }

    private function resolve_user( $kakao_id, $email, $nickname ) {
        // 1. Find by Kakao ID (Meta)
        $users = get_users(array(
            'meta_key'   => 'wpklk_kakao_id',
            'meta_value' => $kakao_id,
            'number'     => 1
        ));

        if ( ! empty( $users ) ) {
            $u = $users[0];
            // [FIX] Auto-approve existing Kakao users
            update_user_meta( $u->ID, 'account_status', 'approved' );
            return $u;
        }

        // 2. Find by Email
        if ( ! empty( $email ) ) {
            $user = get_user_by( 'email', $email );
            if ( $user ) {
                // Link account
                update_user_meta( $user->ID, 'wpklk_kakao_id', $kakao_id );
                update_user_meta( $user->ID, 'wpklk_email_verified', 1 ); // Trust Kakao email
                // [FIX] Auto-approve linked users
                update_user_meta( $user->ID, 'account_status', 'approved' );
                return $user;
            }
        }

        // 3. Create New User
        // Generate unique username
        $username = 'kakao_' . $kakao_id;
        $password = wp_generate_password( 16, false );
        
        if ( empty( $email ) ) {
            $email = $username . '@example.com'; // Fallback if email not provided
        }

        $user_id = wp_create_user( $username, $password, $email );

        if ( ! is_wp_error( $user_id ) ) {
            update_user_meta( $user_id, 'wpklk_kakao_id', $kakao_id );
            update_user_meta( $user_id, 'display_name', $nickname );
            update_user_meta( $user_id, 'wpklk_email_verified', 1 ); // Verified by Kakao
            
            // [FIX] Auto-approve Kakao users (avoid 'Pending' status in UM/WP)
            update_user_meta( $user_id, 'account_status', 'approved' );
            
            wp_update_user( array( 'ID' => $user_id, 'display_name' => $nickname ) );
            
            return get_user_by( 'ID', $user_id );
        }

        return false;
    }
}
