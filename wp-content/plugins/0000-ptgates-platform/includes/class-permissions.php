<?php
/**
 * PTGates Platform Permissions Class
 * 
 * 권한 관리 및 nonce 검증
 */

namespace PTG\Platform;

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class Permissions {
    
    /**
     * REST API 권한 콜백: 로그인 필수
     * 
     * @return bool
     */
    public static function rest_is_user_logged_in() {
        return is_user_logged_in();
    }
    
    /**
     * REST API 권한 콜백: 공개 (모든 사용자)
     * 
     * @return bool
     */
    public static function rest_is_public() {
        return true;
    }
    
    /**
     * Nonce 검증
     * 
     * @param string $nonce nonce 값
     * @param string $action action 이름
     * @return bool
     */
    public static function verify_nonce($nonce, $action = 'wp_rest') {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * REST API 요청에서 nonce 검증
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function verify_rest_nonce($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            // 쿼리 파라미터에서도 확인
            $nonce = $request->get_param('_wpnonce');
        }
        
        if (!$nonce) {
            return false;
        }
        
        return self::verify_nonce($nonce, 'wp_rest');
    }
    
    /**
     * 현재 사용자 ID 반환 (로그인 필수)
     * 
     * @return int 사용자 ID
     * @throws \WP_Error 로그인하지 않은 경우
     */
    public static function get_user_id_or_error() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new \WP_Error(
                'unauthorized',
                '로그인이 필요합니다.',
                array('status' => 401)
            );
        }
        
        return $user_id;
    }
    
    /**
     * REST API 권한 콜백: 로그인 필수 + nonce 검증
     * 
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public static function rest_check_user_and_nonce($request) {
        // 로그인 확인
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'unauthorized',
                '로그인이 필요합니다.',
                array('status' => 401)
            );
        }
        
        // Nonce 검증
        if (!self::verify_rest_nonce($request)) {
            return new \WP_Error(
                'invalid_nonce',
                '보안 토큰이 유효하지 않습니다.',
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * 특정 사용자의 데이터인지 확인
     * 
     * @param int $user_id 확인할 사용자 ID
     * @param int|null $current_user_id 현재 사용자 ID (null이면 자동 조회)
     * @return bool
     */
    public static function is_own_data($user_id, $current_user_id = null) {
        if ($current_user_id === null) {
            $current_user_id = get_current_user_id();
        }
        
        return absint($user_id) === absint($current_user_id);
    }
    
    /**
     * REST API에서 사용자 데이터 접근 권한 확인
     * 
     * @param \WP_REST_Request $request
     * @param int $target_user_id 대상 사용자 ID
     * @return bool|\WP_Error
     */
    public static function rest_check_user_access($request, $target_user_id) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return new \WP_Error(
                'unauthorized',
                '로그인이 필요합니다.',
                array('status' => 401)
            );
        }
        
        if (!self::is_own_data($target_user_id, $current_user_id)) {
            return new \WP_Error(
                'forbidden',
                '접근 권한이 없습니다.',
                array('status' => 403)
            );
        }
        
        return true;
    }
}

