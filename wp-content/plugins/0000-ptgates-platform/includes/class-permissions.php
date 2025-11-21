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
    /**
     * ptGates 멤버십 관리자 권한 확인
     * 
     * @param int|null $user_id 사용자 ID (null이면 현재 사용자)
     * @return bool
     */
    public static function is_pt_admin($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // ptgates_user_member 테이블에서 등급 확인
        $member = Repo::find_one('ptgates_user_member', array('user_id' => $user_id));
        
        if ($member && isset($member['member_grade']) && $member['member_grade'] === 'pt_admin') {
            return true;
        }
        
        return false;
    }

    /**
     * ptGates 관리 기능 접근 권한 확인
     * (워드프레스 관리자 권한과는 별개로 pt_admin 등급 필요)
     * 
     * @param int|null $user_id 사용자 ID
     * @return bool
     */
    public static function can_manage_ptgates($user_id = null) {
        return self::is_pt_admin($user_id);
    }

    /**
     * 사용량 제한 확인 (관리자는 무제한)
     * 
     * @param int $user_id 사용자 ID
     * @param string $type 제한 유형 (exam_count, study_count 등)
     * @return bool|int true(무제한) 또는 남은 횟수/false(제한됨)
     */
    public static function check_usage_limit($user_id, $type) {
        // 관리자는 무제한
        if (self::is_pt_admin($user_id)) {
            return true;
        }
        
        // 일반 사용자 로직은 호출하는 쪽에서 처리하거나 여기에 추가 구현
        // 현재는 관리자 우회 로직만 제공
        return false; // 기본적으로 제한됨 (호출자가 상세 로직 처리)
    }

    /**
     * 사용량 기록 (관리자는 기록하지 않음)
     * 
     * @param int $user_id 사용자 ID
     * @param string $type 사용 유형
     * @return bool 기록 여부
     */
    public static function should_record_usage($user_id) {
        // 관리자는 사용량을 기록하지 않음 (무제한 이용)
        if (self::is_pt_admin($user_id)) {
            return false;
        }
        
        return true;
    }

    /**
     * 사용자 멤버십 등급 설정 (관리자용)
     * 
     * @param int $target_user_id 대상 사용자 ID
     * @param string $grade 설정할 등급 (pt_admin, premium, basic, trial)
     * @return bool|WP_Error 성공 여부
     */
    public static function set_member_grade($target_user_id, $grade) {
        // 실행하는 사람은 워드프레스 관리자 권한(manage_options)이 있어야 함
        if (!current_user_can('manage_options')) {
            return new \WP_Error('forbidden', '권한이 없습니다.', array('status' => 403));
        }

        $valid_grades = array('pt_admin', 'premium', 'basic', 'trial');
        if (!in_array($grade, $valid_grades)) {
            return new \WP_Error('invalid_param', '유효하지 않은 등급입니다.', array('status' => 400));
        }

        // 기존 레코드 확인
        $existing = Repo::find_one('ptgates_user_member', array('user_id' => $target_user_id));

        if ($existing) {
            $result = Repo::update(
                'ptgates_user_member',
                array('member_grade' => $grade),
                array('user_id' => $target_user_id)
            );
        } else {
            $result = Repo::insert(
                'ptgates_user_member',
                array(
                    'user_id' => $target_user_id,
                    'member_grade' => $grade,
                    'billing_status' => 'active' // 신규 생성 시 기본 활성
                )
            );
        }

        return $result !== false;
    }

    /**
     * 기능 접근 권한 확인 (Feature Access Control)
     * 
     * @param string $feature 기능 이름 (video_lecture, advanced_quiz, ai_analysis 등)
     * @param int|null $user_id 사용자 ID
     * @return bool 접근 허용 여부
     */
    public static function can_access_feature($feature, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // 관리자는 모든 기능 접근 가능
        if (self::is_pt_admin($user_id)) {
            return true;
        }
        
        $member = Repo::find_one('ptgates_user_member', array('user_id' => $user_id));
        if (!$member) {
            return false;
        }
        
        $grade = $member['member_grade'];
        $expiry = $member['billing_expiry_date'];
        
        // Trial 만료 체크 (만료되었으면 Basic으로 간주)
        if ($grade === 'trial' && $expiry && strtotime($expiry) < time()) {
            $grade = 'basic';
        }
        
        switch ($feature) {
            case 'video_lecture': // 심화 해설 동영상
            case 'premium_content': // 프리미엄 콘텐츠
                // Trial(유효) 또는 Premium만 가능
                return ($grade === 'trial' || $grade === 'premium');
                
            case 'advanced_quiz': // 고급 퀴즈 유형
                // Trial(유효) 또는 Premium만 가능
                return ($grade === 'trial' || $grade === 'premium');
                
            case 'ai_analysis': // AI 분석 리포트
                // Trial(유효) 또는 Premium만 가능
                return ($grade === 'trial' || $grade === 'premium');
                
            case 'basic_content': // 기본 콘텐츠
                // 모든 등급 가능
                return true;
                
            default:
                return false;
        }
    }

    /**
     * 모의고사 생성 가능 여부 확인 및 차감 (Usage Limit Check & Deduct)
     * 
     * @param int|null $user_id 사용자 ID
     * @param bool $deduct 차감 여부 (true이면 확인 후 차감까지 수행)
     * @return bool|WP_Error 성공 시 true, 실패 시 에러 객체
     */
    public static function check_and_deduct_exam_count($user_id = null, $deduct = true) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // 1. 관리자는 무제한 (차감 없음)
        if (self::is_pt_admin($user_id)) {
            return true;
        }
        
        $member = Repo::find_one('ptgates_user_member', array('user_id' => $user_id));
        if (!$member) {
            return new \WP_Error('no_membership', '멤버십 정보가 없습니다.');
        }
        
        $grade = $member['member_grade'];
        $expiry = $member['billing_expiry_date'];
        $used = intval($member['exam_count_used']);
        $total = intval($member['exam_count_total']);
        
        // 2. Trial 만료 확인 -> Basic으로 전환하여 로직 적용
        if ($grade === 'trial' && $expiry && strtotime($expiry) < time()) {
            $grade = 'basic';
            // 만료된 Trial은 Basic 한도(1회)를 따름
            // 단, 이미 Trial 기간에 많이 썼더라도 Basic 전환 후에는 추가 생성이 안 되어야 함
            // Basic의 total은 보통 1회임.
            $total = 1; 
        }
        
        // 3. Basic 횟수 확인 (누적 1회만 가능)
        if ($grade === 'basic') {
            if ($used >= 1) { // Basic은 1회 제한 (설정값 무시하고 하드코딩된 정책인 경우)
                // 또는 $total을 따르도록 할 수도 있음. 여기서는 요구사항 "Basic: 누적 1회만 생성 가능"을 따름
                return new \WP_Error('limit_exceeded', '기본(Basic) 회원은 모의고사를 1회만 생성할 수 있습니다. 무제한 이용을 위해 프리미엄으로 업그레이드하세요.');
            }
        }
        
        // 4. 일반적인 횟수 제한 확인 (Trial 유효 기간 내, Premium 등)
        // Premium이 무제한인 경우 $total을 매우 크게 설정하거나 여기서 예외 처리
        if ($grade === 'premium' && $total == -1) {
            // 무제한
        } else {
            if ($used >= $total) {
                return new \WP_Error('limit_exceeded', '모의고사 생성 한도를 초과했습니다.');
            }
        }
        
        // 5. 차감 수행
        if ($deduct) {
            $new_used = $used + 1;
            $result = Repo::update(
                'ptgates_user_member',
                array('exam_count_used' => $new_used),
                array('user_id' => $user_id)
            );
            
            if ($result === false) {
                return new \WP_Error('db_error', '사용량 업데이트 중 오류가 발생했습니다.');
            }
        }
        
        return true;
    }
}

