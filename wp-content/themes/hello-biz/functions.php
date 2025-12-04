<?php
/**
 * Theme functions and definitions
 *
 * @package HelloBiz
 */

use HelloBiz\Theme;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'HELLO_BIZ_ELEMENTOR_VERSION', '1.2.0' );
define( 'EHP_THEME_SLUG', 'hello-biz' );

define( 'HELLO_BIZ_PATH', get_template_directory() );
define( 'HELLO_BIZ_URL', get_template_directory_uri() );
define( 'HELLO_BIZ_ASSETS_PATH', HELLO_BIZ_PATH . '/assets/' );
define( 'HELLO_BIZ_ASSETS_URL', HELLO_BIZ_URL . '/assets/' );
define( 'HELLO_BIZ_SCRIPTS_PATH', HELLO_BIZ_ASSETS_PATH . 'js/' );
define( 'HELLO_BIZ_SCRIPTS_URL', HELLO_BIZ_ASSETS_URL . 'js/' );
define( 'HELLO_BIZ_STYLE_PATH', HELLO_BIZ_ASSETS_PATH . 'css/' );
define( 'HELLO_BIZ_STYLE_URL', HELLO_BIZ_ASSETS_URL . 'css/' );
define( 'HELLO_BIZ_IMAGES_PATH', HELLO_BIZ_ASSETS_PATH . 'images/' );
define( 'HELLO_BIZ_IMAGES_URL', HELLO_BIZ_ASSETS_URL . 'images/' );
define( 'HELLO_BIZ_STARTER_IMAGES_PATH', HELLO_BIZ_IMAGES_PATH . 'starter-content/' );
define( 'HELLO_BIZ_STARTER_IMAGES_URL', HELLO_BIZ_IMAGES_URL . 'starter-content/' );

if ( ! isset( $content_width ) ) {
    $content_width = 800; // Pixels.
}

// Init the Theme class
require HELLO_BIZ_PATH . '/theme.php';

Theme::instance();


/**
 * ptGates 성능 최적화 - Gutenberg(블록 에디터)만 전역 제거
 * UM(Ultimate Member)은 전혀 건드리지 않는다.
 */
add_action( 'wp_enqueue_scripts', function () {

    // 관리자 화면은 건드리지 않음
    if ( is_admin() ) {
        return;
    }

    // 🔹 UM 관련 페이지에서는 어떤 것도 건드리지 않는다.
    //    (/um-account/*, /register, /login 등)
    $uri = strtok( $_SERVER['REQUEST_URI'], '?' );
    $uri = rtrim( $uri, '/' );

    if (
        strpos( $uri, '/um-account' ) === 0 ||
        $uri === '/register' ||
        $uri === '/login'
    ) {
        return;
    }

    // 🔹 여기서부터는 Gutenberg(블록 에디터) 관련만 제거
    $wp_scripts = array(
        'wp-blocks',
        'wp-block-editor',
        'wp-editor',
        'wp-edit-post',
        'wp-dom-ready',
        'wp-hooks',
        'wp-i18n',
        'wp-components',
        'wp-compose',
        'wp-data',
        'wp-element',
        'wp-polyfill',
        'wp-format-library',
    );
    foreach ( $wp_scripts as $handle ) {
        wp_dequeue_script( $handle );
        wp_deregister_script( $handle );
    }

    $wp_styles = array(
        'wp-block-library',
        'wp-block-library-theme',
        'global-styles',
    );
    foreach ( $wp_styles as $handle ) {
        wp_dequeue_style( $handle );
        wp_deregister_style( $handle );
    }

}, PHP_INT_MAX );

/**
 * (선택) 관리자에서 블록 에디터 끄기 – 필요 없으면 이 두 줄은 지워도 됨
 */
add_filter( 'use_block_editor_for_post', '__return_false' );
add_filter( 'use_block_editor_for_post_type', '__return_false', 10, 2 );

// 관리자에서 Wordfence 라이선스 배너 숨기기(꼼수)
add_action('admin_head', function () {
    echo '<style>
        .wordfence-stats-wrap .wf-notice,
        .wf-onboarding-notice,
        .wordfenceMode_banner,
        .wf-banner { display:none !important; }
    </style>';
});
