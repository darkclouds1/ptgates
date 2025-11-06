<?php
/**
 * PTGates Logger 클래스
 * 
 * 사용자 학습 로그 저장 처리
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class PTG_Logger {
    
    /**
     * 사용자 결과 저장
     * 
     * @param int   $user_id 사용자 ID
     * @param array $data {
     *     @type int    $question_id 문제 ID
     *     @type string $user_answer 사용자 답안
     *     @type int    $is_correct  정답 여부 (1:정답, 0:오답)
     *     @type int    $elapsed_time 소요 시간 (초)
     * }
     * @return int|WP_Error 결과 ID 또는 에러
     */
    public static function save_result($user_id, $data) {
        global $wpdb;
        
        // prefix 없이 사용 (ptgates_questions, ptgates_categories와 일관성 유지)
        $table = PTG_DB::TABLE_USER_RESULTS;
        
        // 필수 필드 검증
        if (empty($data['question_id'])) {
            return new WP_Error(
                'missing_question_id',
                '문제 ID가 필요합니다.',
                array('status' => 400)
            );
        }
        
        // 데이터 준비
        $insert_data = array(
            'user_id' => absint($user_id),
            'question_id' => absint($data['question_id']),
            'user_answer' => !empty($data['user_answer']) ? sanitize_text_field($data['user_answer']) : null,
            'is_correct' => !empty($data['is_correct']) ? 1 : 0,
            'elapsed_time' => !empty($data['elapsed_time']) ? absint($data['elapsed_time']) : null,
        );
        
        // INSERT
        $result = $wpdb->insert($table, $insert_data, array(
            '%d', // user_id
            '%d', // question_id
            '%s', // user_answer
            '%d', // is_correct
            '%d', // elapsed_time
        ));
        
        if ($result === false) {
            return new WP_Error(
                'db_error',
                '데이터 저장에 실패했습니다: ' . $wpdb->last_error,
                array('status' => 500)
            );
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * 사용자의 문제 풀이 기록 조회
     * 
     * @param int $user_id 사용자 ID
     * @param int $question_id 문제 ID
     * @return array|null 결과 데이터
     */
    public static function get_user_result($user_id, $question_id) {
        global $wpdb;
        
        // prefix 없이 사용
        $table = PTG_DB::TABLE_USER_RESULTS;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND question_id = %d ORDER BY attempted_at DESC LIMIT 1",
            $user_id,
            $question_id
        );
        
        return $wpdb->get_row($query, ARRAY_A);
    }
    
    /**
     * 사용자의 전체 학습 통계 조회
     * 
     * @param int $user_id 사용자 ID
     * @return array 통계 데이터
     */
    public static function get_user_statistics($user_id) {
        global $wpdb;
        
        // prefix 없이 사용
        $table = PTG_DB::TABLE_USER_RESULTS;
        
        $query = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_attempts,
                SUM(is_correct) as correct_count,
                AVG(elapsed_time) as avg_time
            FROM {$table}
            WHERE user_id = %d",
            $user_id
        );
        
        return $wpdb->get_row($query, ARRAY_A);
    }
}
