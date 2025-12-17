<?php
/**
 * Plugin Activation Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTG_Members_Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_pages();
    }

    /**
     * Create necessary pages if they don't exist
     */
    private static function create_pages() {
        $pages = [
            'signup' => [
                'title' => '회원가입',
                'content' => '[ptg_signup]'
            ],
            'login' => [
                'title' => '로그인',
                'content' => '[ptg_login]'
            ],
            'verify-email' => [
                'title' => '이메일 인증',
                'content' => '[ptg_verify_email]'
            ],
            'account' => [
                'title' => '계정 관리',
                'content' => '[ptg_account]'
            ],
        ];

        foreach ( $pages as $slug => $data ) {
            $page_check = get_page_by_path( $slug );
            if ( ! $page_check ) {
                $page_id = wp_insert_post( [
                    'post_title'     => $data['title'],
                    'post_name'      => $slug,
                    'post_content'   => $data['content'],
                    'post_status'    => 'publish',
                    'post_type'      => 'page',
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                ] );
            }
        }
    }
}
