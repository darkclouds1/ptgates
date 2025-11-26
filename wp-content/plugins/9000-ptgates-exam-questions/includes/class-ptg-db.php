<?php
/**
 * PTGates DB 접근 클래스
 * 
 * 데이터베이스 쿼리 헬퍼 메서드 제공
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class PTG_DB {
    
    /**
     * 테이블 이름
     */
    const TABLE_QUESTIONS = 'ptgates_questions';
    const TABLE_CATEGORIES = 'ptgates_categories';
    const TABLE_USER_RESULTS = 'ptgates_user_results';
    
    /**
     * 예외 과목 목록 (각각 별도 과목으로 표시)
     * 첫 단어로 그룹화하지 않고 그대로 유지
     */
    private static $exception_subjects = array(
        '물리적 인자치료',
        '물리치료 중재',
        '물리치료 진단평가'
    );
    
    /**
     * DB 테이블 생성 (활성화 시)
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // ptgates_user_results 테이블은 이미 존재할 수 있으므로 확인
        // prefix 없이 사용 (ptgates_questions, ptgates_categories와 일관성 유지)
        $results_table = self::TABLE_USER_RESULTS;
        
        // 테이블이 없으면 생성 (questions와 categories는 이미 존재)
        if ($wpdb->get_var("SHOW TABLES LIKE '$results_table'") != $results_table) {
            $sql = "CREATE TABLE $results_table (
                result_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                question_id bigint(20) unsigned NOT NULL,
                user_answer varchar(255) DEFAULT NULL,
                is_correct tinyint(1) NOT NULL,
                elapsed_time int(10) unsigned DEFAULT NULL,
                attempted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (result_id),
                KEY idx_user_question (user_id, question_id),
                KEY question_id (question_id),
                KEY idx_user_id (user_id),
                KEY idx_user_correct (user_id, is_correct),
                KEY idx_attempted_at (attempted_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * 문제 목록 조회
     * 
     * @param array $args {
     *     @type int    $year    시험 연도
     *     @type string $subject 과목명
     *     @type int    $limit   가져올 개수
     *     @type int    $offset  오프셋
     * }
     * @return array 문제 목록
     */
    public static function get_questions($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'year' => null,
            'subject' => null,
            'session' => null,
            'full_session' => false,
            'limit' => 10,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // 실제 테이블은 prefix 없이 사용 (ptgates_questions, ptgates_categories)
        $questions_table = self::TABLE_QUESTIONS;
        $categories_table = self::TABLE_CATEGORIES;
        
        $where = array("q.is_active = 1");
        $join_params = array();
        
        // 기출문제만 필터링 (exam_session < 1000)
        $where[] = "(c.exam_session IS NULL OR c.exam_session < 1000)";
        
        // 연도 필터
        if (!empty($args['year'])) {
            $where[] = $wpdb->prepare("c.exam_year = %d", $args['year']);
        }
        
        // 과목 필터
        if (!empty($args['subject'])) {
            $subject_filter = trim($args['subject']);
            
            // 예외 과목인 경우 정확히 일치하는 조건 사용
            if (in_array($subject_filter, self::$exception_subjects, true)) {
                $where[] = $wpdb->prepare("c.subject = %s", $subject_filter);
            } else {
                // 일반 과목: 첫 단어로 시작하는 모든 과목 포함
                $where[] = $wpdb->prepare("c.subject LIKE %s", $subject_filter . '%');
            }
        }

        // 교시 필터
        if (!empty($args['session'])) {
            $session_val = intval($args['session']);
            // exam_session(숫자) 또는 exam_course(문자: '1교시'/'2교시', '제1교시', '1 교시', 숫자 문자열 등) 모두 대응
            $exact_label = $session_val . '교시';
            $where[] = $wpdb->prepare(
                "(c.exam_session = %d 
                  OR c.exam_course = %s 
                  OR c.exam_course = %s 
                  OR c.exam_course LIKE %s 
                  OR c.exam_course LIKE %s 
                  OR c.exam_course LIKE %s)",
                $session_val,
                (string) $session_val,
                $exact_label,
                '%' . $session_val . '교시%',
                '%제' . $session_val . '교시%',
                '%' . $session_val . ' 교시%'
            );
        }
        
        $where_clause = implode(' AND ', $where);
        
        // LIMIT 및 OFFSET
        $limit_clause = '';
        if (empty($args['full_session'])) {
            $limit = absint($args['limit']);
            $offset = absint($args['offset']);
            $limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $limit, $offset);
        }

        // 정렬: 전체 교시인 경우 고정 순서(문항 ID 오름차순), 그 외 무작위
        $order_clause = !empty($args['session']) && !empty($args['full_session']) ? "ORDER BY q.question_id ASC" : "ORDER BY RAND()";
        
        $query = "
            SELECT 
                q.question_id as id,
                q.content,
                q.answer,
                q.explanation,
                q.type,
                q.difficulty,
                c.exam_year as year,
                c.exam_session as session,
                c.exam_course as course,
                c.subject,
                c.source_company as source
            FROM {$questions_table} q
            INNER JOIN {$categories_table} c ON q.question_id = c.question_id
            WHERE {$where_clause}
            {$order_clause}
            {$limit_clause}
        ";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (!is_array($results)) {
            return array();
        }
        
        // 결과 포맷팅
        return array_map(array(__CLASS__, 'format_question'), $results);
    }
    
    /**
     * 문제 포맷팅 (보기 추출 등)
     * 
     * @param array $row DB 행 데이터
     * @return array 포맷팅된 문제 데이터
     */
    private static function format_question($row) {
        $content = $row['content'];
        
        // 보기 추출 (정규식으로 객관식 보기 찾기)
        // 예: ①, ②, ③, ④ 또는 (1), (2), (3), (4) 형식
        $options = array();
        $question_text = $content;
        
        // ①~④ 형식의 보기 추출
        // 원형 숫자나 괄호 숫자로 시작하는 부분을 찾아 각 옵션으로 분리
        // 한 줄에 여러 옵션이 있을 수 있으므로, 각 원형 숫자 위치에서 다음 원형 숫자 전까지 추출
        
        // 먼저 모든 원형 숫자 위치 찾기
        if (preg_match_all('/([①-⑳]|\([0-9]+\))\s*/u', $content, $number_matches, PREG_OFFSET_CAPTURE)) {
            $option_ranges = array();
            
            // 각 원형 숫자의 시작/끝 위치 저장
            foreach ($number_matches[0] as $idx => $number_match) {
                $start_pos = $number_match[1];
                
                // 다음 원형 숫자의 위치 찾기
                $end_pos = strlen($content);
                if (isset($number_matches[0][$idx + 1])) {
                    $end_pos = $number_matches[0][$idx + 1][1];
                }
                
                // 옵션 텍스트 추출 (원형 숫자 포함)
                $option_text = substr($content, $start_pos, $end_pos - $start_pos);
                $option_text = trim($option_text);
                
                if (!empty($option_text)) {
                    $options[] = $option_text;
                    $option_ranges[] = array('start' => $start_pos, 'end' => $end_pos);
                }
            }
            
            // 문제 본문에서 옵션 부분 제거 (뒤에서부터 제거하여 위치 변화 방지)
            if (!empty($option_ranges)) {
                $question_parts = array();
                $last_pos = 0;
                
                // 옵션이 없는 부분들을 조합하여 문제 본문 재구성
                foreach ($option_ranges as $range) {
                    if ($range['start'] > $last_pos) {
                        $question_parts[] = substr($content, $last_pos, $range['start'] - $last_pos);
                    }
                    $last_pos = $range['end'];
                }
                
                // 마지막 옵션 이후 텍스트 추가
                if ($last_pos < strlen($content)) {
                    $question_parts[] = substr($content, $last_pos);
                }
                
                $question_text = trim(implode('', $question_parts));
            } else {
                $question_text = trim($content);
            }
        } else {
            // 옵션이 없는 경우 원본 그대로
            $question_text = trim($content);
        }
        
        // 설명 분리 (base_explanation, advanced_explanation)
        $explanation = !empty($row['explanation']) ? $row['explanation'] : '';
        $base_explanation = $explanation;
        $advanced_explanation = '';
        
        // 해설에 "고급:" 또는 "상세:" 같은 구분자가 있으면 분리
        if (preg_match('/^(.*?)(?:고급|상세|Advanced)[\s]*[:：]\s*(.+)$/is', $explanation, $exp_matches)) {
            $base_explanation = trim($exp_matches[1]);
            $advanced_explanation = trim($exp_matches[2]);
        }
        
        return array(
            'id' => intval($row['id']),
            'year' => !empty($row['year']) ? intval($row['year']) : null,
            'session' => $row['session'] ? intval($row['session']) : null,
            'course' => $row['course'],
            'subject' => $row['subject'],
            'question_text' => trim($question_text),
            'options' => $options,
            'answer' => $row['answer'],
            'base_explanation' => $base_explanation,
            'advanced_explanation' => $advanced_explanation,
            'type' => $row['type'],
            'difficulty' => intval($row['difficulty'])
        );
    }
    
    /**
     * 단일 문제 조회 (ID로)
     * 
     * @param int $question_id 문제 ID
     * @return array|null 문제 데이터 또는 null
     */
    public static function get_question_by_id($question_id) {
        global $wpdb;
        
        $questions_table = self::TABLE_QUESTIONS;
        $categories_table = self::TABLE_CATEGORIES;
        
        $query = $wpdb->prepare("
            SELECT 
                q.question_id as id,
                q.content,
                q.answer,
                q.explanation,
                q.type,
                q.difficulty,
                c.exam_year as year,
                c.exam_session as session,
                c.exam_course as course,
                c.subject,
                c.source_company as source
            FROM {$questions_table} q
            LEFT JOIN {$categories_table} c ON q.question_id = c.question_id
            WHERE q.question_id = %d
              AND q.is_active = 1
            LIMIT 1
        ", $question_id);
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        if (!$result) {
            return null;
        }
        
        return self::format_question($result);
    }
    
    /**
     * 기출이 아닌 문제 중 랜덤으로 하나 조회
     * 
     * @return array|null 문제 데이터 또는 null
     */
    public static function get_random_non_exam_question() {
        global $wpdb;
        
        $questions_table = self::TABLE_QUESTIONS;
        $categories_table = self::TABLE_CATEGORIES;
        
        // 기출이 아닌 문제: exam_session >= 1000 또는 exam_session IS NULL AND exam_year IS NULL
        $query = "
            SELECT 
                q.question_id as id,
                q.content,
                q.answer,
                q.explanation,
                q.type,
                q.difficulty,
                c.exam_year as year,
                c.exam_session as session,
                c.exam_course as course,
                c.subject,
                c.source_company as source
            FROM {$questions_table} q
            LEFT JOIN {$categories_table} c ON q.question_id = c.question_id
            WHERE q.is_active = 1
              AND (c.exam_session >= 1000 OR (c.exam_session IS NULL AND c.exam_year IS NULL))
            ORDER BY RAND()
            LIMIT 1
        ";
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        if (!$result) {
            return null;
        }
        
        return self::format_question($result);
    }
    
    /**
     * 사용 가능한 연도 목록 조회
     * 
     * 기출문제만 포함 (exam_session < 1000 또는 NULL)
     * 
     * @return array 연도 목록
     */
    public static function get_available_years() {
        global $wpdb;
        
        try {
            // 실제 테이블은 prefix 없이 사용
            $questions_table = self::TABLE_QUESTIONS;
            $categories_table = self::TABLE_CATEGORIES;
            
            // 기출문제만 필터링: exam_session이 NULL이거나 1000 미만인 경우만
            // exam_session >= 1000인 경우는 기출문제가 아니므로 제외
            $query = $wpdb->prepare("
                SELECT DISTINCT c.exam_year
                FROM {$categories_table} c
                INNER JOIN {$questions_table} q ON c.question_id = q.question_id
                WHERE q.is_active = 1
                  AND (c.exam_session IS NULL OR c.exam_session < %d)
                ORDER BY c.exam_year DESC
            ", 1000);
            
            $years = $wpdb->get_col($query);
            
            if (!is_array($years)) {
                return array();
            }
            
            return array_values($years);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PTG_DB::get_available_years Error: ' . $e->getMessage());
            }
            return array();
        }
    }
}
