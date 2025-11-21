<?php
/**
 * PTGates Admin REST API 클래스
 * 
 * REST API 네임스페이스: ptg-admin/v1
 */

namespace PTG\Admin;

use PTG\Platform\Rest;

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// Subjects 클래스가 없으면 로드 시도
if (!class_exists('\PTG\Quiz\Subjects')) {
    // 여러 경로 시도
    $possible_paths = array(
        WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php',
        plugin_dir_path(__FILE__) . '../../1200-ptgates-quiz/includes/class-subjects.php',
    );
    
    foreach ($possible_paths as $subjects_file) {
        if (file_exists($subjects_file) && is_readable($subjects_file)) {
            require_once $subjects_file;
            break;
        }
    }
}

class API {
    
    /**
     * REST API 네임스페이스
     */
    const NAMESPACE = 'ptg-admin/v1';
    
    /**
     * 관리자 권한 체크
     */
    public static function check_admin_permission() {
        if (!current_user_can('manage_options')) {
            return Rest::unauthorized('관리자 권한이 필요합니다.');
        }
        return true;
    }
    
    /**
     * REST API 라우트 등록
     */
    public static function register_routes() {
        // 문제 목록 조회
        register_rest_route(self::NAMESPACE, '/questions', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_questions'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
            'args' => array(
                'subject' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'subsubject' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'search' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'exam_year' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'exam_session' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'exam_course' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'default' => 1,
                ),
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'default' => 20,
                ),
            ),
        ));
        
        // 단일 문제 조회
        register_rest_route(self::NAMESPACE, '/questions/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_question'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // 문제 수정
        register_rest_route(self::NAMESPACE, '/questions/(?P<id>\d+)', array(
            'methods' => 'PATCH',
            'callback' => array(__CLASS__, 'update_question'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'content' => array(
                    'required' => false,
                    'type' => 'string',
                ),
                'answer' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'explanation' => array(
                    'required' => false,
                    'type' => 'string',
                ),
                'type' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'difficulty' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'is_active' => array(
                    'required' => false,
                    'type' => 'boolean',
                ),
            ),
        ));
        
        // 교시 목록 조회
        register_rest_route(self::NAMESPACE, '/sessions', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_sessions'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));
        
        // 과목 목록 조회 (교시별)
        register_rest_route(self::NAMESPACE, '/subjects', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_subjects'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
            'args' => array(
                'session' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // 시험 년도 목록
        register_rest_route(self::NAMESPACE, '/exam-years', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_exam_years'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));

        // 시험 회차 목록 (년도 기반)
        register_rest_route(self::NAMESPACE, '/exam-sessions', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_exam_sessions'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
            'args' => array(
                'year' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }
    
    /**
     * 문제 목록 조회
     */
    public static function get_questions($request) {
        global $wpdb;
        
        try {
            $subject = $request->get_param('subject');
            $subsubject = $request->get_param('subsubject');
            $search = $request->get_param('search');
            $exam_year = $request->get_param('exam_year');
            $exam_session = $request->get_param('exam_session');
            $exam_course = $request->get_param('exam_course');
            $page = $request->get_param('page') ?: 1;
            $per_page = $request->get_param('per_page') ?: 20;
            $offset = ($page - 1) * $per_page;
            
            // 캐시 키 생성 (검색어 포함 - 관리자는 실시간 데이터 필요할 수 있으므로 짧은 캐시)
            $cache_params = array(
                'subject' => $subject,
                'subsubject' => $subsubject,
                'search' => $search,
                'exam_year' => $exam_year,
                'exam_session' => $exam_session,
                'exam_course' => $exam_course,
                'page' => $page,
                'per_page' => $per_page,
            );
            $cache_key = 'ptg_admin_questions_' . md5(serialize($cache_params));
            
            // 캐시 확인 (5분 유효 - 관리자는 짧은 캐시)
            $cached = wp_cache_get($cache_key, 'ptg_admin');
            if ($cached !== false) {
                return Rest::success($cached);
            }
            
            // 테이블 이름은 prefix 없이 사용 (다른 플러그인과 일관성 유지)
            $questions_table = 'ptgates_questions';
            $categories_table = 'ptgates_categories';
            
            $where = array("q.is_active = 1");
            $where_values = array();
            
            // 과목 필터
            if (!empty($subsubject)) {
                // 세부과목이 지정된 경우 정확히 일치
                $where[] = "c.subject = %s";
                $where_values[] = $subsubject;
            } elseif (!empty($subject)) {
                // 과목(대분류)이 지정된 경우: Subjects 클래스를 사용하여 해당 과목의 모든 세부과목을 포함
                if (class_exists('\PTG\Quiz\Subjects')) {
                    $subsubjects_to_include = array();
                    // 모든 교시에서 해당 과목의 세부과목 찾기
                    $sessions = \PTG\Quiz\Subjects::get_sessions();
                    foreach ($sessions as $sess) {
                        $subsubjects = \PTG\Quiz\Subjects::get_subsubjects($sess, $subject);
                        if (!empty($subsubjects)) {
                            $subsubjects_to_include = array_merge($subsubjects_to_include, $subsubjects);
                        }
                    }
                    $subsubjects_to_include = array_values(array_unique($subsubjects_to_include));
                    
                    if (!empty($subsubjects_to_include)) {
                        // 세부과목 목록을 OR 조건으로 생성
                        $or_parts = array();
                        $or_params = array();
                        foreach ($subsubjects_to_include as $sub_name) {
                            $or_parts[] = "c.subject = %s";
                            $or_params[] = $sub_name;
                        }
                        $where[] = "(" . implode(" OR ", $or_parts) . ")";
                        $where_values = array_merge($where_values, $or_params);
                    } else {
                        // Subjects 클래스에서 찾지 못한 경우, 과거 동작 유지
                        $where[] = "c.subject LIKE %s";
                        $where_values[] = $subject . '%';
                    }
                } else {
                    // Subjects 클래스가 없는 경우, 과거 동작 유지
                    $where[] = "c.subject LIKE %s";
                    $where_values[] = $subject . '%';
                }
            }

            if (!empty($exam_year)) {
                $where[] = "c.exam_year = %d";
                $where_values[] = (int) $exam_year;
            }

            if (!empty($exam_session)) {
                $where[] = "c.exam_session = %d";
                $where_values[] = (int) $exam_session;
            }

            if (!empty($exam_course)) {
                if (is_numeric($exam_course)) {
                    $exam_course = $exam_course . '교시';
                }
                $where[] = "REPLACE(TRIM(c.exam_course), ' ', '') = %s";
                $where_values[] = str_replace(' ', '', $exam_course);
            }
            
            // 검색 필터 (지문/해설) - 공백 단위 AND 검색
            if (!empty($search)) {
                $search = trim($search);
                $terms = preg_split('/\s+/', $search);
                $term_conditions = array();

                foreach ($terms as $term) {
                    $term = trim($term);
                    if ($term === '') {
                        continue;
                    }

                    $search_like = '%' . $wpdb->esc_like($term) . '%';
                    $term_conditions[] = "(q.content LIKE %s OR q.explanation LIKE %s)";
                    $where_values[] = $search_like;
                    $where_values[] = $search_like;
                }

                if (!empty($term_conditions)) {
                    $where[] = '(' . implode(' AND ', $term_conditions) . ')';
                }
            }
            
            $where_clause = implode(' AND ', $where);
            
            // 총 개수 조회
            $count_sql = "SELECT COUNT(DISTINCT q.question_id)
                         FROM {$questions_table} q
                         INNER JOIN {$categories_table} c ON q.question_id = c.question_id
                         WHERE {$where_clause}";
            
            if (!empty($where_values)) {
                // WordPress prepare는 배열을 직접 받을 수 있음
                $count_query = $wpdb->prepare($count_sql, $where_values);
            } else {
                $count_query = $count_sql;
            }
            
            $total = $wpdb->get_var($count_query);
            
            if ($wpdb->last_error) {
                return Rest::error('query_failed', '쿼리 실행 중 오류: ' . $wpdb->last_error, 500);
            }
            
            // 문제 목록 조회 (년도, 회차, 교시 정보 포함)
            $list_sql = "SELECT DISTINCT q.question_id, q.content, q.answer, q.explanation, q.type, q.difficulty, q.is_active, q.question_image,
                        GROUP_CONCAT(DISTINCT c.subject ORDER BY c.subject SEPARATOR ', ') as subjects,
                        GROUP_CONCAT(DISTINCT c.exam_year ORDER BY c.exam_year SEPARATOR ', ') as exam_years,
                        GROUP_CONCAT(DISTINCT c.exam_session ORDER BY c.exam_session SEPARATOR ', ') as exam_sessions,
                        GROUP_CONCAT(DISTINCT c.exam_course ORDER BY c.exam_course SEPARATOR ', ') as exam_courses
                 FROM {$questions_table} q
                 INNER JOIN {$categories_table} c ON q.question_id = c.question_id
                 WHERE {$where_clause}
                 GROUP BY q.question_id
                 ORDER BY q.question_id DESC
                 LIMIT %d OFFSET %d";
            
            // Subjects 클래스 로드 확인
            if (!class_exists('\PTG\Quiz\Subjects')) {
                $possible_paths = array(
                    WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php',
                    plugin_dir_path(__FILE__) . '../../1200-ptgates-quiz/includes/class-subjects.php',
                );
                foreach ($possible_paths as $subjects_file) {
                    if (file_exists($subjects_file) && is_readable($subjects_file)) {
                        require_once $subjects_file;
                        break;
                    }
                }
            }
            
            if (!empty($where_values)) {
                $query_values = array_merge($where_values, array($per_page, $offset));
                $query = $wpdb->prepare($list_sql, $query_values);
            } else {
                $query = $wpdb->prepare($list_sql, $per_page, $offset);
            }
            
            $questions = $wpdb->get_results($query, ARRAY_A);
            
            if ($wpdb->last_error) {
                return Rest::error('query_failed', '쿼리 실행 중 오류: ' . $wpdb->last_error, 500);
            }
            
            // 각 문제의 content와 explanation 정리 (_x000D_ 제거 및 줄바꿈 정리)
            if (is_array($questions)) {
                foreach ($questions as &$q) {
                    // content 정리
                    if (isset($q['content']) && is_string($q['content'])) {
                        $q['content'] = str_replace('_x000D_', '', $q['content']);
                        $q['content'] = str_replace("\r\n", "\n", $q['content']);
                        $q['content'] = str_replace("\r", "\n", $q['content']);
                        // 선택지 번호 앞의 연속된 줄바꿈 정리
                        $q['content'] = preg_replace('/\n{2,}\s*([①-⑳])/u', "\n$1", $q['content']);
                        // 전체에서 연속된 줄바꿈 정리
                        $q['content'] = preg_replace('/\n{2,}/', "\n", $q['content']);
                    }
                    // explanation 정리
                    if (isset($q['explanation']) && is_string($q['explanation'])) {
                        $q['explanation'] = str_replace('_x000D_', "\n", $q['explanation']);
                        $q['explanation'] = str_replace("\r\n", "\n", $q['explanation']);
                        $q['explanation'] = str_replace("\r", "\n", $q['explanation']);
                        // 연속된 줄바꿈 정리 (해설은 원본 유지 요청으로 주석 처리)
                        // $q['explanation'] = preg_replace('/\n{2,}/', "\n", $q['explanation']);
                    }
                }
                unset($q);
            }
            
            // 각 문제에 과목(대분류) 정보 추가
            if (class_exists('\PTG\Quiz\Subjects') && is_array($questions)) {
                foreach ($questions as &$q) {
                    $main_subjects = array();
                    $subsubjects = array();
                    
                    if (!empty($q['subjects'])) {
                        $subject_list = explode(', ', $q['subjects']);
                        $sessions = \PTG\Quiz\Subjects::get_sessions();
                        
                        foreach ($subject_list as $sub_name) {
                            $found_main = false;
                            foreach ($sessions as $sess) {
                                $main_subjects_list = \PTG\Quiz\Subjects::get_subjects_for_session($sess);
                                foreach ($main_subjects_list as $main_sub) {
                                    $subsubjects_list = \PTG\Quiz\Subjects::get_subsubjects($sess, $main_sub);
                                    if (in_array($sub_name, $subsubjects_list)) {
                                        if (!in_array($main_sub, $main_subjects)) {
                                            $main_subjects[] = $main_sub;
                                        }
                                        $found_main = true;
                                        break;
                                    }
                                }
                                if ($found_main) break;
                            }
                            if (!$found_main) {
                                // Subjects 클래스에서 찾지 못한 경우, 원래 이름 그대로 사용
                                $subsubjects[] = $sub_name;
                            } else {
                                $subsubjects[] = $sub_name;
                            }
                        }
                    }
                    
                    $q['main_subjects'] = implode(', ', array_unique($main_subjects));
                    $q['subsubjects'] = implode(', ', array_unique($subsubjects));
                }
                unset($q);
            }
            
            $response_data = array(
                'questions' => $questions ? $questions : array(),
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => $total > 0 ? ceil($total / $per_page) : 0,
                'applied_filters' => array(
                    'exam_year' => $exam_year,
                    'exam_session' => $exam_session,
                    'exam_course' => $exam_course,
                    'subject' => $subject,
                    'subsubject' => $subsubject,
                    'search' => $search,
                ),
            );
            
            // 캐시 저장 (5분)
            wp_cache_set($cache_key, $response_data, 'ptg_admin', 300);
            
            return Rest::success($response_data);
        } catch (\Exception $e) {
            return Rest::error('server_error', '서버 오류: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 단일 문제 조회
     */
    public static function get_question($request) {
        global $wpdb;
        
        $question_id = $request->get_param('id');
        
        // 캐시 키 생성
        $cache_key = 'ptg_admin_question_' . $question_id;
        
        // 캐시 확인 (10분 유효 - 관리자는 짧은 캐시)
        $cached = wp_cache_get($cache_key, 'ptg_admin');
        if ($cached !== false) {
            return Rest::success($cached);
        }
        
        $questions_table = 'ptgates_questions';
        $categories_table = 'ptgates_categories';
        
        $question = $wpdb->get_row($wpdb->prepare(
            "SELECT q.*, GROUP_CONCAT(DISTINCT c.subject ORDER BY c.subject SEPARATOR ', ') as subjects
             FROM {$questions_table} q
             LEFT JOIN {$categories_table} c ON q.question_id = c.question_id
             WHERE q.question_id = %d
             GROUP BY q.question_id",
            $question_id
        ), ARRAY_A);
        
        if (!$question) {
            return Rest::error('not_found', '문제를 찾을 수 없습니다.', 404);
        }
        
        // content와 explanation 정리 (_x000D_ 제거 및 줄바꿈 정리)
        if (isset($question['content']) && is_string($question['content'])) {
            $question['content'] = str_replace('_x000D_', '', $question['content']);
            $question['content'] = str_replace("\r\n", "\n", $question['content']);
            $question['content'] = str_replace("\r", "\n", $question['content']);
            // 선택지 번호 앞의 연속된 줄바꿈 정리
            $question['content'] = preg_replace('/\n{2,}\s*([①-⑳])/u', "\n$1", $question['content']);
            // 전체에서 연속된 줄바꿈 정리
            $question['content'] = preg_replace('/\n{2,}/', "\n", $question['content']);
        }
        if (isset($question['explanation']) && is_string($question['explanation'])) {
            $question['explanation'] = str_replace('_x000D_', "\n", $question['explanation']);
            $question['explanation'] = str_replace("\r\n", "\n", $question['explanation']);
            $question['explanation'] = str_replace("\r", "\n", $question['explanation']);
            // 연속된 줄바꿈 정리 (해설은 원본 유지 요청으로 주석 처리)
            // $question['explanation'] = preg_replace('/\n{2,}/', "\n", $question['explanation']);
        }
        
        // 캐시 저장 (10분)
        wp_cache_set($cache_key, $question, 'ptg_admin', 600);
        
        return Rest::success($question);
    }
    
    /**
     * 문제 수정
     */
    public static function update_question($request) {
        global $wpdb;
        
        $question_id = $request->get_param('id');
        $questions_table = 'ptgates_questions';
        
        // 기존 문제 확인
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT question_id FROM {$questions_table} WHERE question_id = %d",
            $question_id
        ));
        
        if (!$exists) {
            return Rest::error('not_found', '문제를 찾을 수 없습니다.', 404);
        }
        
        // 업데이트할 필드 준비
        $update_fields = array();
        $update_values = array();
        
        if ($request->has_param('content')) {
            $update_fields[] = 'content = %s';
            $update_values[] = $request->get_param('content');
        }
        
        if ($request->has_param('answer')) {
            $update_fields[] = 'answer = %s';
            $update_values[] = $request->get_param('answer');
        }
        
        if ($request->has_param('explanation')) {
            $update_fields[] = 'explanation = %s';
            $update_values[] = $request->get_param('explanation');
        }
        
        if ($request->has_param('type')) {
            $update_fields[] = 'type = %s';
            $update_values[] = $request->get_param('type');
        }
        
        if ($request->has_param('difficulty')) {
            $update_fields[] = 'difficulty = %d';
            $update_values[] = $request->get_param('difficulty');
        }
        
        if ($request->has_param('is_active')) {
            $update_fields[] = 'is_active = %d';
            $update_values[] = $request->get_param('is_active') ? 1 : 0;
        }
        
        if (empty($update_fields)) {
            return Rest::error('invalid_request', '수정할 필드가 없습니다.', 400);
        }
        
        // updated_at 자동 갱신
        $update_fields[] = 'updated_at = NOW()';
        
        // 쿼리 실행
        $update_values[] = $question_id;
        $query = "UPDATE {$questions_table} SET " . implode(', ', $update_fields) . " WHERE question_id = %d";
        
        $result = $wpdb->query($wpdb->prepare($query, ...$update_values));
        
        if ($result === false) {
            return Rest::error('update_failed', '문제 수정에 실패했습니다.', 500);
        }
        
        // 수정된 문제 반환
        return self::get_question($request);
    }
    
    /**
     * 시험 년도 목록 조회
     */
    public static function get_exam_years($request) {
        global $wpdb;
        try {
            $table = 'ptgates_categories';
            $years = $wpdb->get_col("SELECT DISTINCT exam_year FROM {$table} ORDER BY exam_year DESC");
            if (!$years) {
                $years = array();
            }
            $years = array_values(array_map('intval', array_filter($years, function($year) {
                return $year !== null;
            })));
            return Rest::success($years);
        } catch (\Exception $e) {
            return Rest::error('server_error', '서버 오류: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 시험 회차 목록 조회 (년도 기준)
     */
    public static function get_exam_sessions($request) {
        global $wpdb;
        try {
            $year = (int) $request->get_param('year');
            if (empty($year)) {
                return Rest::error('invalid_year', '유효한 년도를 선택하세요.', 400);
            }

            $table = 'ptgates_categories';
            $sessions = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT exam_session FROM {$table} WHERE exam_year = %d AND exam_session IS NOT NULL ORDER BY exam_session ASC",
                $year
            ));

            if (!$sessions) {
                $sessions = array();
            }

            $sessions = array_values(array_map('intval', array_filter($sessions, function($session) {
                return $session !== null;
            })));

            return Rest::success($sessions);
        } catch (\Exception $e) {
            return Rest::error('server_error', '서버 오류: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 과목 목록 조회 (교시별)
     */
    public static function get_subjects($request) {
        try {
            $session = $request->get_param('session');
            
            // 캐시 키 생성
            $cache_key = 'ptg_admin_subjects_' . ($session ? (int)$session : 'all');
            
            // 캐시 확인 (1시간 유효 - 과목 목록은 자주 변경되지 않음)
            $cached = wp_cache_get($cache_key, 'ptg_admin');
            if ($cached !== false) {
                return Rest::success($cached);
            }
            
            // Subjects 클래스가 없으면 다시 로드 시도
            if (!class_exists('\PTG\Quiz\Subjects')) {
                $possible_paths = array(
                    WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php',
                    plugin_dir_path(__FILE__) . '../../1200-ptgates-quiz/includes/class-subjects.php',
                );
                
                foreach ($possible_paths as $subjects_file) {
                    if (file_exists($subjects_file) && is_readable($subjects_file)) {
                        require_once $subjects_file;
                        break;
                    }
                }
            }
            
            if (!class_exists('\PTG\Quiz\Subjects')) {
                return Rest::error('class_not_found', 'Subjects 클래스를 찾을 수 없습니다. 1200-ptgates-quiz 플러그인이 활성화되어 있는지 확인하세요. 경로: ' . WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php', 500);
            }
            
            // 교시별 과목 목록
            $result = array();
            
            if (!empty($session)) {
                // 특정 교시의 과목만
                $session = (int) $session;
                $subjects = \PTG\Quiz\Subjects::get_subjects_for_session($session);
                if (is_array($subjects)) {
                    foreach ($subjects as $subject) {
                        $subsubjects = \PTG\Quiz\Subjects::get_subsubjects($session, $subject);
                        $result[] = array(
                            'session' => $session,
                            'name' => $subject,
                            'subsubjects' => is_array($subsubjects) ? $subsubjects : array(),
                        );
                    }
                }
            } else {
                // 모든 교시의 과목
                $sessions = \PTG\Quiz\Subjects::get_sessions();
                if (is_array($sessions)) {
                    foreach ($sessions as $sess) {
                        $subjects = \PTG\Quiz\Subjects::get_subjects_for_session($sess);
                        if (is_array($subjects)) {
                            foreach ($subjects as $subject) {
                                $subsubjects = \PTG\Quiz\Subjects::get_subsubjects($sess, $subject);
                                $result[] = array(
                                    'session' => $sess,
                                    'name' => $subject,
                                    'subsubjects' => is_array($subsubjects) ? $subsubjects : array(),
                                );
                            }
                        }
                    }
                }
            }
            
            // 캐시 저장 (1시간)
            wp_cache_set($cache_key, $result, 'ptg_admin', 3600);
            
            return Rest::success($result);
        } catch (\Exception $e) {
            return Rest::error('server_error', '서버 오류: ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Line: ' . $e->getLine() . ')', 500);
        } catch (\Error $e) {
            return Rest::error('server_error', '서버 오류: ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Line: ' . $e->getLine() . ')', 500);
        }
    }
    
    /**
     * 교시 목록 조회
     */
    public static function get_sessions($request) {
        try {
            // 캐시 키 생성
            $cache_key = 'ptg_admin_sessions';
            
            // 캐시 확인 (1시간 유효 - 교시 목록은 자주 변경되지 않음)
            $cached = wp_cache_get($cache_key, 'ptg_admin');
            if ($cached !== false) {
                return Rest::success($cached);
            }
            
            // Subjects 클래스가 없으면 다시 로드 시도
            if (!class_exists('\PTG\Quiz\Subjects')) {
                $possible_paths = array(
                    WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php',
                    plugin_dir_path(__FILE__) . '../../1200-ptgates-quiz/includes/class-subjects.php',
                );
                
                foreach ($possible_paths as $subjects_file) {
                    if (file_exists($subjects_file) && is_readable($subjects_file)) {
                        require_once $subjects_file;
                        break;
                    }
                }
            }
            
            if (!class_exists('\PTG\Quiz\Subjects')) {
                return Rest::error('class_not_found', 'Subjects 클래스를 찾을 수 없습니다. 1200-ptgates-quiz 플러그인이 활성화되어 있는지 확인하세요.', 500);
            }
            
            $sessions = \PTG\Quiz\Subjects::get_sessions();
            $result = array();
            if (is_array($sessions)) {
                foreach ($sessions as $session) {
                    $result[] = array(
                        'id' => $session,
                        'name' => $session . '교시',
                    );
                }
            }
            
            // 캐시 저장 (1시간)
            wp_cache_set($cache_key, $result, 'ptg_admin', 3600);
            
            return Rest::success($result);
        } catch (\Exception $e) {
            return Rest::error('server_error', '서버 오류: ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Line: ' . $e->getLine() . ')', 500);
        } catch (\Error $e) {
            return Rest::error('server_error', '서버 오류: ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Line: ' . $e->getLine() . ')', 500);
        }
    }
}

