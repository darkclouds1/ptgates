<?php
/**
 * PTGates Platform - 기존 테이블 접근 헬퍼
 * 
 * 기존 테이블(ptgates_questions, ptgates_categories, ptgates_user_results) 접근 전용
 * 실제 DB 구조에 맞춰 작성됨
 */

namespace PTG\Platform;

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class LegacyRepo {
    
    /**
     * 문제 정보 조회 (categories와 JOIN)
     * 
     * @param array $args {
     *     @type int $question_id 문제 ID
     *     @type int $year 시험 연도
     *     @type string $subject 과목명
     *     @type string $exam_course 교시
     *     @type int $limit 가져올 개수
     *     @type int $offset 오프셋
     * }
     * @return array 문제 목록 (categories 정보 포함)
     */
    public static function get_questions_with_categories($args = array()) {
        global $wpdb;
        
        // 레거시 테이블은 prefix 없이 직접 사용
        $questions_table = 'ptgates_questions';
        $categories_table = 'ptgates_categories';
        
        $where = array("q.is_active = 1");
        $where_values = array();
        
        // question_id 필터
        if (!empty($args['question_id'])) {
            $where[] = "q.question_id = %d";
            $where_values[] = absint($args['question_id']);
        }
        
        // year 필터
        if (!empty($args['year'])) {
            $where[] = "c.exam_year = %d";
            $where_values[] = absint($args['year']);
        }
        
        // subject 필터
        if (!empty($args['subject'])) {
            $where[] = "c.subject = %s";
            $where_values[] = sanitize_text_field($args['subject']);
        }

        // exam_course 필터
        if (!empty($args['exam_course'])) {
            $where[] = "c.exam_course = %s";
            $where_values[] = sanitize_text_field($args['exam_course']);
        }

        // exam_session 최소값 필터 (예: 1000 이상만)
        if (!empty($args['exam_session_min'])) {
            $where[] = "c.exam_session >= %d";
            $where_values[] = absint($args['exam_session_min']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        // LIMIT 및 OFFSET
        $limit = isset($args['limit']) ? absint($args['limit']) : 10;
        $offset = isset($args['offset']) ? absint($args['offset']) : 0;
        
        $sql = "
            SELECT 
                q.question_id,
                q.content,
                q.answer,
                q.explanation,
                q.type,
                q.difficulty,
                q.is_active,
                q.created_at,
                q.updated_at,
                c.category_id,
                c.exam_year,
                c.exam_session,
                c.exam_course,
                c.subject,
                c.source_company
            FROM {$questions_table} q
            INNER JOIN {$categories_table} c ON q.question_id = c.question_id
            WHERE {$where_clause}
            ORDER BY q.question_id DESC
            LIMIT %d OFFSET %d
        ";
        
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        $query = $wpdb->prepare($sql, $where_values);
        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * 문제 정보 개수 조회 (categories와 JOIN, 필터 조건 동일)
     *
     * @param array $args get_questions_with_categories 와 동일한 필터 인자
     * @return int 총 개수
     */
    public static function count_questions_with_categories($args = array()) {
        global $wpdb;

        $questions_table = 'ptgates_questions';
        $categories_table = 'ptgates_categories';

        $where = array("q.is_active = 1");
        $where_values = array();

        if (!empty($args['question_id'])) {
            $where[] = "q.question_id = %d";
            $where_values[] = absint($args['question_id']);
        }

        if (!empty($args['year'])) {
            $where[] = "c.exam_year = %d";
            $where_values[] = absint($args['year']);
        }

        if (!empty($args['subject'])) {
            $where[] = "c.subject = %s";
            $where_values[] = sanitize_text_field($args['subject']);
        }

        if (!empty($args['exam_course'])) { 
            $where[] = "c.exam_course = %s";
            $where_values[] = sanitize_text_field($args['exam_course']);
        }

        if (!empty($args['exam_session_min'])) {
            $where[] = "c.exam_session >= %d";
            $where_values[] = absint($args['exam_session_min']);
        }

        $where_clause = implode(' AND ', $where);

        $sql = "
            SELECT COUNT(*) 
            FROM {$questions_table} q
            INNER JOIN {$categories_table} c ON q.question_id = c.question_id
            WHERE {$where_clause}
        ";

        $query = $wpdb->prepare($sql, $where_values);
        $count = $wpdb->get_var($query);

        return (int) $count;
    }
    
    /**
     * 사용 가능한 연도 목록 조회
     * 
     * @return array 연도 배열
     */
    public static function get_available_years() {
        global $wpdb;
        
        // 레거시 테이블은 prefix 없이 직접 사용
        $categories_table = 'ptgates_categories';
        
        $sql = "
            SELECT DISTINCT exam_year 
            FROM {$categories_table} 
            ORDER BY exam_year DESC
        ";
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        return array_map(function($row) {
            return (int) $row['exam_year'];
        }, $results);
    }
    
    /**
     * 사용 가능한 과목 목록 조회
     * 
     * @param int|null $year 연도 필터 (선택)
     * @return array 과목 배열
     */
    public static function get_available_subjects($year = null) {
        global $wpdb;
        
        // 레거시 테이블은 prefix 없이 직접 사용
        $categories_table = 'ptgates_categories';
        
        $where = '';
        $where_values = array();
        
        if ($year) {
            $where = 'WHERE exam_year = %d';
            $where_values[] = absint($year);
        }
        
        $sql = "
            SELECT DISTINCT subject 
            FROM {$categories_table} 
            {$where}
            ORDER BY subject ASC
        ";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($sql, $where_values);
        } else {
            $query = $sql;
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        return array_map(function($row) {
            return $row['subject'];
        }, $results);
    }
    
    /**
     * 문제별 분류 정보 조회 (한 문제에 여러 분류가 있을 수 있음)
     * 
     * @param int $question_id 문제 ID
     * @return array 분류 배열
     */
    public static function get_question_categories($question_id) {
        global $wpdb;
        
        // 레거시 테이블은 prefix 없이 직접 사용
        $categories_table = 'ptgates_categories';
        
        $sql = $wpdb->prepare("
            SELECT 
                category_id,
                question_id,
                exam_year,
                exam_session,
                exam_course,
                subject,
                source_company
            FROM {$categories_table}
            WHERE question_id = %d
            ORDER BY exam_year DESC, exam_session DESC
        ", absint($question_id));
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * 사용자 결과 저장
     * 
     * @param int $user_id 사용자 ID
     * @param array $data {
     *     @type int $question_id 문제 ID
     *     @type string $user_answer 사용자 답안
     *     @type bool $is_correct 정답 여부
     *     @type int $elapsed_time 소요 시간 (초)
     * }
     * @return int|false result_id 또는 false
     */
    public static function save_user_result($user_id, $data) {
        global $wpdb;
        
        // 레거시 테이블은 prefix 없이 직접 사용
        $results_table = 'ptgates_user_results';
        
        $insert_data = array(
            'user_id' => absint($user_id),
            'question_id' => absint($data['question_id']),
            'user_answer' => isset($data['user_answer']) ? sanitize_text_field($data['user_answer']) : null,
            'is_correct' => isset($data['is_correct']) ? (int) $data['is_correct'] : 0,
            'elapsed_time' => isset($data['elapsed_time']) ? absint($data['elapsed_time']) : null,
        );
        
        $result = $wpdb->insert($results_table, $insert_data);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
}

