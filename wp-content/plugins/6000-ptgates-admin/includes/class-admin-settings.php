<?php
/**
 * Admin Settings Controller
 * 
 * 통합 설정 페이지 (탭 방식)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PTG_Admin_Settings {
    
    /**
     * Render Settings Page
     */
    public static function render_page() {
        $is_new_plugin_active = class_exists( 'Monolith\KakaoLoginKit\Loader' );
        $default_tab = $is_new_plugin_active ? 'payment' : 'kakao';
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : $default_tab;
        ?>
        <div class="wrap">
            <h1>PTGates 설정</h1>
            
            <nav class="nav-tab-wrapper">
                <?php if ( ! $is_new_plugin_active ) : ?>
                <a href="?page=ptgates-admin-settings&tab=kakao" class="nav-tab <?php echo $active_tab === 'kakao' ? 'nav-tab-active' : ''; ?>">Kakao</a>
                <?php endif; ?>
                <a href="?page=ptgates-admin-settings&tab=payment" class="nav-tab <?php echo $active_tab === 'payment' ? 'nav-tab-active' : ''; ?>">NHN KCP (PortOne V2)</a>
            </nav>
            
            <div class="ptg-settings-content" style="margin-top: 20px;">
                <?php
                if ( $active_tab === 'kakao' && ! $is_new_plugin_active ) {
                    self::render_kakao_tab();
                } elseif ( $active_tab === 'payment' ) {
                    self::render_payment_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Kakao Settings
     */
    private static function render_kakao_tab() {
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
            update_option( 'ptg_kakao_admin_key', sanitize_text_field( $_POST['ptg_kakao_admin_key'] ) );
            update_option( 'ptg_kakao_scopes', $scopes );

            echo '<div class="notice notice-success is-dismissible"><p>카카오 설정이 저장되었습니다.</p></div>';
        }

        // Retrieve Options
        $enabled = get_option( 'ptg_kakao_enabled', 0 );
        $client_id = get_option( 'ptg_kakao_client_id', '' );
        $client_secret = get_option( 'ptg_kakao_client_secret', '' );
        $admin_key = get_option( 'ptg_kakao_admin_key', '' );
        $saved_scopes = get_option( 'ptg_kakao_scopes', array() );
        
        $redirect_uri = site_url( '/wp-json/ptg/v1/auth/kakao/callback' );
        
        $available_scopes = array(
            'profile_nickname' => '닉네임 (필수)',
            'profile_image' => '프로필 이미지',
            'account_email' => '이메일',
            'name' => '이름', // Real Name
        );

        ?>
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
                    <th scope="row">Admin Key (관리자 키)</th>
                    <td>
                        <input type="password" name="ptg_kakao_admin_key" value="<?php echo esc_attr( $admin_key ); ?>" class="regular-text">
                        <p class="description">카카오 디벨로퍼스 > 내 애플리케이션 > 앱 키 > Admin 키 (사용자 강제 연결 해제용)</p>
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
            <?php submit_button( '카카오 설정 저장', 'primary', 'ptg_kakao_save' ); ?>
        </form>
        <?php
    }

    /**
     * Tab: PortOne V2 Settings
     */
    private static function render_payment_tab() {
        // Option Saving
        // Option Saving
        if ( isset( $_POST['ptg_payment_save'] ) && check_admin_referer( 'ptg_payment_settings_nonce' ) ) {
            $store_id = sanitize_text_field( $_POST['ptg_portone_store_id'] );
            $channel_key = sanitize_text_field( $_POST['ptg_portone_channel_key'] );
            $api_secret = sanitize_text_field( $_POST['ptg_portone_api_secret'] );

            // KCP V2 Bypass Settings
            $kcp_site_name = sanitize_text_field( $_POST['ptg_kcp_site_name'] );
            $kcp_site_logo = sanitize_url( $_POST['ptg_kcp_site_logo'] );
            $kcp_skin_indx = intval( $_POST['ptg_kcp_skin_indx'] );

            $bypass = [
                'kcp_v2' => [
                    'site_name' => $kcp_site_name,
                    'site_logo' => $kcp_site_logo,
                    'skin_indx' => $kcp_skin_indx
                ]
            ];

            update_option( 'ptg_portone_store_id', $store_id );
            update_option( 'ptg_portone_channel_key', $channel_key );
            update_option( 'ptg_portone_api_secret', $api_secret );
            update_option( 'ptg_portone_bypass', $bypass );

            echo '<div class="notice notice-success is-dismissible"><p>PortOne V2 (NHN KCP) 결제 설정이 저장되었습니다.</p></div>';
        }

        // Retrieve Options
        $store_id = get_option( 'ptg_portone_store_id', '' );
        $channel_key = get_option( 'ptg_portone_channel_key', '' );
        $api_secret = get_option( 'ptg_portone_api_secret', '' );
        
        $bypass = get_option( 'ptg_portone_bypass', [] );
        $kcp_site_name = isset($bypass['kcp_v2']['site_name']) ? $bypass['kcp_v2']['site_name'] : 'PTGates';
        $kcp_site_logo = isset($bypass['kcp_v2']['site_logo']) ? $bypass['kcp_v2']['site_logo'] : '';
        $kcp_skin_indx = isset($bypass['kcp_v2']['skin_indx']) ? $bypass['kcp_v2']['skin_indx'] : 1;

        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'ptg_payment_settings_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Store ID (스토어 아이디)</th>
                    <td>
                        <input type="text" name="ptg_portone_store_id" value="<?php echo esc_attr( $store_id ); ?>" class="regular-text" required placeholder="store-...">
                        <p class="description">포트원 콘솔 > 결제 연동 > 연동 정보 > Store ID</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Channel Key (채널 키)</th>
                    <td>
                        <input type="text" name="ptg_portone_channel_key" value="<?php echo esc_attr( $channel_key ); ?>" class="large-text" required placeholder="channel-key-...">
                        <p class="description">포트원 콘솔 > 결제 연동 > 연동 정보 > 채널 관리 > Channel Key (V2)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">API Secret (시크릿)</th>
                    <td>
                        <input type="password" name="ptg_portone_api_secret" value="<?php echo esc_attr( $api_secret ); ?>" class="large-text" required>
                        <p class="description">포트원 콘솔 > 결제 연동 > 연동 정보 > API Keys > Secret (V2)</p>
                    </td>
                </tr>
            </table>
            
            </table>
            
            <h3>KCP 결제창 설정 (Bypass)</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">상점명 (Site Name)</th>
                    <td>
                        <input type="text" name="ptg_kcp_site_name" value="<?php echo esc_attr( $kcp_site_name ); ?>" class="regular-text" placeholder="PTGates">
                        <p class="description">결제창 상단에 표시될 상점 이름입니다.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">로고 URL (Site Logo)</th>
                    <td>
                        <input type="url" name="ptg_kcp_site_logo" value="<?php echo esc_attr( $kcp_site_logo ); ?>" class="large-text" placeholder="https://...">
                        <p class="description">결제창에 표시할 로고 이미지 URL (150x50 미만, jpg/gif).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">스킨 색상 (Skin Index)</th>
                    <td>
                        <select name="ptg_kcp_skin_indx">
                            <?php for($i=1; $i<=12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($kcp_skin_indx, $i); ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <p class="description">결제창 색상 테마 (1~12)</p>
                    </td>
                </tr>
            </table>

            <hr>
            <p class="description">
                <strong>참고:</strong> 이 설정은 NHN KCP V2 연동에 최적화되어 있습니다.<br>
                연동 가이드는 <a href="https://developers.portone.io/opi/ko/integration/start/v2/readme?v=v2" target="_blank">포트원 개발자 센터</a>를 참조하세요.
            </p>

            <?php submit_button( 'PortOne V2 설정 저장', 'primary', 'ptg_payment_save' ); ?>
        </form>
        <?php
    }

    /**
     * Admin Columns: Join Date & Last Login
     */
    public static function init_columns() {
        // High priority (999) to ensure our columns are added after others
        add_filter( 'manage_users_columns', array( __CLASS__, 'add_user_columns' ), 999 );
        // Content filter (It is a filter, not an action)
        add_filter( 'manage_users_custom_column', array( __CLASS__, 'show_user_column_content' ), 10, 3 );
        add_filter( 'manage_users_sortable_columns', array( __CLASS__, 'sortable_user_columns' ) );
    }

    public static function add_user_columns( $columns ) {
        $new_columns = array();
        // Insert Checkbox and Username first
        if ( isset( $columns['cb'] ) ) $new_columns['cb'] = $columns['cb'];
        if ( isset( $columns['username'] ) ) $new_columns['username'] = $columns['username'];
        
        // Add Custom 'Name' Column (Explicitly)
        $new_columns['ptg_name'] = '이름'; // Custom Name Header
        
        // Add remaining existing columns (skip 'name' if we replacing it, or keep it?)
        // Let's keep standard fields but ensuring ours is prominent
        foreach ( $columns as $key => $title ) {
            if ( $key !== 'cb' && $key !== 'username' && $key !== 'name' ) {
                 $new_columns[$key] = $title;
            }
        }

        // Add our custom tracking columns
        $new_columns['registered'] = '가입일';
        $new_columns['last_login'] = '최근 접속';

        return $new_columns;
    }

    public static function show_user_column_content( $value, $column_name, $user_id ) {
        if ( 'ptg_name' === $column_name ) {
            $user_info = get_userdata( $user_id );
            // Display 'Display Name' (synced with Kakao) and 'First Name' if different
            $name = $user_info->display_name;
            if ( ! empty( $user_info->first_name ) && $user_info->first_name !== $name ) {
                $name .= ' (' . $user_info->first_name . ')';
            }
            return $name ?: '-';
        }
        if ( 'registered' === $column_name ) {
            $user_info = get_userdata( $user_id );
            // Format: Y-m-d H:i
            return date( 'Y-m-d H:i', strtotime( $user_info->user_registered ) );
        }
        if ( 'last_login' === $column_name ) {
            // Check UM last login first (if available)
            $last_login = get_user_meta( $user_id, 'um_last_login', true ); // Using UM meta
            if ( ! $last_login ) {
                $last_login = get_user_meta( $user_id, 'last_login', true ); // Fallback
            }
            
            if ( $last_login ) {
                // If timestamp
                if ( is_numeric( $last_login ) ) {
                    return date( 'Y-m-d H:i', $last_login );
                }
                return $last_login;
            }
            return '-';
        }
        return $value;
    }
    
    public static function sortable_user_columns( $columns ) {
        $columns['registered'] = 'registered';
        $columns['ptg_name'] = 'display_name';
        return $columns;
    }
}
