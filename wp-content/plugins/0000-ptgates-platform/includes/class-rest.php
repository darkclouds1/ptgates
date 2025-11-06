<?php
/**
 * PTGates Platform REST Class
 * 
 * 공통 REST API 응답 및 에러 처리
 */

namespace PTG\Platform;

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class Rest {
    
    /**
     * 성공 응답 반환
     * 
     * @param mixed $data 응답 데이터
     * @param string $message 메시지
     * @param int $status HTTP 상태 코드
     * @return \WP_REST_Response
     */
    public static function success($data = null, $message = '성공', $status = 200) {
        $response = array(
            'success' => true,
            'message' => $message,
        );
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return new \WP_REST_Response($response, $status);
    }
    
    /**
     * 에러 응답 반환
     * 
     * @param string $code 에러 코드
     * @param string $message 에러 메시지
     * @param int $status HTTP 상태 코드
     * @param array $data 추가 데이터
     * @return \WP_Error
     */
    public static function error($code, $message, $status = 400, $data = array()) {
        return new \WP_Error($code, $message, array_merge(array('status' => $status), $data));
    }
    
    /**
     * 유효성 검증 실패 응답
     * 
     * @param string $field 필드 이름
     * @param string $message 에러 메시지
     * @return \WP_Error
     */
    public static function validation_error($field, $message) {
        return self::error(
            'validation_error',
            $message,
            400,
            array('field' => $field)
        );
    }
    
    /**
     * 권한 없음 응답
     * 
     * @param string $message 메시지
     * @return \WP_Error
     */
    public static function unauthorized($message = '로그인이 필요합니다.') {
        return self::error('unauthorized', $message, 401);
    }
    
    /**
     * 접근 거부 응답
     * 
     * @param string $message 메시지
     * @return \WP_Error
     */
    public static function forbidden($message = '접근 권한이 없습니다.') {
        return self::error('forbidden', $message, 403);
    }
    
    /**
     * 리소스 없음 응답
     * 
     * @param string $message 메시지
     * @return \WP_Error
     */
    public static function not_found($message = '리소스를 찾을 수 없습니다.') {
        return self::error('not_found', $message, 404);
    }
    
    /**
     * 서버 오류 응답
     * 
     * @param string $message 메시지
     * @return \WP_Error
     */
    public static function server_error($message = '서버 오류가 발생했습니다.') {
        return self::error('server_error', $message, 500);
    }
    
    /**
     * 페이징 정보가 포함된 응답
     * 
     * @param array $items 아이템 배열
     * @param int $total 전체 개수
     * @param int $page 현재 페이지
     * @param int $per_page 페이지당 개수
     * @param string $message 메시지
     * @return \WP_REST_Response
     */
    public static function paginated($items, $total, $page, $per_page, $message = '성공') {
        $total_pages = ceil($total / $per_page);
        
        return self::success(
            array(
                'items' => $items,
                'pagination' => array(
                    'total' => $total,
                    'total_pages' => $total_pages,
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'has_next' => $page < $total_pages,
                    'has_prev' => $page > 1,
                ),
            ),
            $message
        );
    }
    
    /**
     * 날짜 포맷팅 (KST 기준)
     * 
     * @param string $date UTC 날짜/시간
     * @param string $format 포맷 (기본: Y-m-d H:i:s)
     * @return string 포맷된 날짜
     */
    public static function format_date_kst($date, $format = 'Y-m-d H:i:s') {
        if (empty($date)) {
            return null;
        }
        
        $utc_time = new \DateTime($date, new \DateTimeZone('UTC'));
        $utc_time->setTimezone(new \DateTimeZone('Asia/Seoul'));
        
        return $utc_time->format($format);
    }
    
    /**
     * 오늘 날짜 (KST 기준, date 타입)
     * 
     * @return string Y-m-d 형식의 날짜
     */
    public static function today_kst() {
        $now = new \DateTime('now', new \DateTimeZone('Asia/Seoul'));
        return $now->format('Y-m-d');
    }
    
    /**
     * KST 기준으로 날짜 계산 (복습 스케줄용)
     * 
     * @param int $days 더할 일수
     * @return string Y-m-d 형식의 날짜
     */
    public static function add_days_kst($days) {
        $now = new \DateTime('now', new \DateTimeZone('Asia/Seoul'));
        $now->modify("+{$days} days");
        return $now->format('Y-m-d');
    }
}

