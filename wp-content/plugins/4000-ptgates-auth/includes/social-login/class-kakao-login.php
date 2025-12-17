<?php
/**
 * Kakao Login Handler
 */

class PTG_Kakao_Login {

    private $api_key;
    private $redirect_uri;

    public function init() {
        $this->api_key = get_option('ptg_kakao_client_id');
        $this->redirect_uri = site_url('/wp-json/ptg/v1/auth/kakao/callback');

        // Register REST Route for Callback
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        // Conflict Check: If Platform plugin already handles this, do not register routes here.
        if ( class_exists( '\PTG\Platform\KakaoAuth' ) ) {
            return;
        }

        register_rest_route('ptg/v1', '/auth/kakao/start', array(
            'methods'  => 'GET',
            'callback' => array($this, 'handle_start'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('ptg/v1', '/auth/kakao/callback', array(
            'methods'  => 'GET',
            'callback' => array($this, 'handle_callback'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_start($request) {
        if ( empty($this->api_key) ) {
            return new WP_Error('config_error', 'Kakao Client ID not configured', array('status' => 500));
        }
        
        $url = 'https://kauth.kakao.com/oauth/authorize';
        $params = array(
            'client_id'     => $this->api_key,
            'redirect_uri'  => $this->redirect_uri,
            'response_type' => 'code',
        );
        
        $login_url = add_query_arg($params, $url);
        
        return new WP_REST_Response(array('url' => $login_url), 200); 
        // Or cleaner: redirect directly if accessed via browser? 
        // REST API shouldn't really redirect, but for this flow it's often used as a bounce.
        // Better: frontend button links to this, and this function redirects properly?
        // Actually, let's make it consistent. The user clicked a link.
        // If accessed directly via browser, we can redirect.
        header("Location: " . $login_url);
        exit;
    }

    public function handle_callback($request) {
        $code = $request->get_param('code');
        
        if (empty($code)) {
            return new WP_Error('no_code', 'Authorization code missing', array('status' => 400));
        }

        // 1. Get Access Token
        $token_response = wp_remote_post('https://kauth.kakao.com/oauth/token', array(
            'body' => array(
                'grant_type'    => 'authorization_code',
                'client_id'     => $this->api_key,
                'redirect_uri'  => $this->redirect_uri,
                'code'          => $code,
            )
        ));

        if (is_wp_error($token_response)) {
            return $token_response;
        }

        $token_body = json_decode(wp_remote_retrieve_body($token_response), true);
        $access_token = isset($token_body['access_token']) ? $token_body['access_token'] : '';

        if (empty($access_token)) {
            return new WP_Error('token_error', 'Failed to get access token', array('status' => 400));
        }

        // 2. Get User Info
        $user_response = wp_remote_get('https://kapi.kakao.com/v2/user/me', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            )
        ));

        if (is_wp_error($user_response)) {
            return $user_response;
        }

        $user_body = json_decode(wp_remote_retrieve_body($user_response), true);
        $kakao_id = $user_body['id'];
        $kakao_account = $user_body['kakao_account'];
        
        // 3. Extract Data
        $email = isset($kakao_account['email']) ? $kakao_account['email'] : '';
        $nickname = isset($kakao_account['profile']['nickname']) ? $kakao_account['profile']['nickname'] : '';
        $profile_image = isset($kakao_account['profile']['profile_image_url']) ? $kakao_account['profile']['profile_image_url'] : '';
        
        // Real Name (Check if scope allowed)
        $real_name = isset($kakao_account['name']) ? $kakao_account['name'] : '';
        
        // 4. Login or Register
        $user = $this->get_user_by_kakao_id($kakao_id);

        if (!$user) {
            // Check if email exists
            if ($email) {
                $user = get_user_by('email', $email);
                if ($user) {
                    // Link existing user
                    update_user_meta($user->ID, 'ptg_kakao_id', $kakao_id);
                }
            }
        }

        if (!$user) {
            // Register New User
            $username = 'kakao_' . $kakao_id;
            $password = wp_generate_password();
            $user_id = wp_create_user($username, $password, $email);
            
            if (is_wp_error($user_id)) {
                return $user_id; // Return error
            }
            
            $user = get_user_by('id', $user_id);
            update_user_meta($user_id, 'ptg_kakao_id', $kakao_id);
        }

        // 5. Update User Profile (Sync)
        $update_args = array('ID' => $user->ID);
        $meta_updates = array();

        // 5.1 Real Name Sync logic
        if (!empty($real_name)) {
            // Update WordPress Display Name to Real Name
            $update_args['display_name'] = $real_name;
            $update_args['first_name'] = $real_name; 
            // Also save as meta for reference
            update_user_meta($user->ID, 'ptg_kakao_real_name', $real_name);
        } elseif (!empty($nickname)) {
            // Fallback to Nickname if Real Name not available
            if ($user->display_name == $user->user_login || empty($user->display_name)) {
                 $update_args['display_name'] = $nickname;
            }
        }
        
        // 5.2 Profile Image
        if (!empty($profile_image)) {
            update_user_meta($user->ID, 'ptg_kakao_profile_image', $profile_image);
        }

        if (count($update_args) > 1) { // ID plus at least one field
            wp_update_user($update_args);
        }

        // 6. Login User
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login, $user);

        // 7. Redirect
        wp_safe_redirect(home_url());
        exit;
    }

    private function get_user_by_kakao_id($kakao_id) {
        $args = array(
            'meta_key' => 'ptg_kakao_id',
            'meta_value' => $kakao_id,
            'number' => 1
        );
        $users = get_users($args);
        return !empty($users) ? $users[0] : false;
    }
}
