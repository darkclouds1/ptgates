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

    //    (/um-account/*, /register, /login 등)
    $uri = strtok( $_SERVER['REQUEST_URI'], '?' );
    $uri = rtrim( $uri, '/' );

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


/**
 * 워드프레스 이모지 로딩 비활성화
 */
function disable_emojis() {
    // 프런트엔드 및 관리자 페이지에서 이모지 스크립트/스타일 제거
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    
    // 이모지 관련 필터 제거
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    
    // TinyMCE(편집기)에서 이모지 플러그인 제거
    add_filter( 'tiny_mce_plugins', 'disable_emojis_tinymce' );
    
    // 이모지 CDN DNS 프리페치 힌트 제거 (성능 향상)
    add_filter( 'wp_resource_hints', 'disable_emojis_remove_dns_prefetch', 10, 2 );
}
add_action( 'init', 'disable_emojis' );

function disable_emojis_tinymce( $plugins ) {
    if ( is_array( $plugins ) ) {
        return array_diff( $plugins, array( 'wpemoji' ) );
    }
    return array();
}

function disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
    if ( 'dns-prefetch' == $relation_type ) {
        $emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );
        $urls = array_diff( $urls, array( $emoji_svg_url ) );
    }
    return $urls;
}