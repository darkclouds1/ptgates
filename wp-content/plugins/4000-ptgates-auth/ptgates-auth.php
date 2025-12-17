<?php
/**
 * Plugin Name: 4000-PTGates Auth
 * Description: PTGates 인증 및 소셜 로그인 플러그인 (카카오 등)
 * Version: 1.0.0
 * Author: PTGates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 상수 정의
define( 'PTG_AUTH_VERSION', '1.0.0' );
define( 'PTG_AUTH_PATH', plugin_dir_path( __FILE__ ) );
define( 'PTG_AUTH_URL', plugin_dir_url( __FILE__ ) );

// 클래스 오토로딩 또는 include
require_once PTG_AUTH_PATH . 'includes/social-login/class-kakao-login.php';

// 플러그인 초기화
function ptg_auth_init() {
    $kakao_login = new PTG_Kakao_Login();
    $kakao_login->init();
}
add_action( 'plugins_loaded', 'ptg_auth_init' );
