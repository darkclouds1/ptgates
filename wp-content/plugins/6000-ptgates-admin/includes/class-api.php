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

// Subjects 클래스가 없으면 로드 시도 (최초 로드는 0000-ptgates-platform에서 수행됨)
if (!class_exists('\PTG\Quiz\Subjects')) {
    // 플랫폼 코어를 먼저 시도
    $platform_subjects_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/includes/class-subjects.php';
    if (file_exists($platform_subjects_file) && is_readable($platform_subjects_file)) {
        require_once $platform_subjects_file;
    }
    // 플랫폼 코어가 없으면 기존 위치에서 로드 (호환성)
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
}

class API {
    
    /**
     * REST API 네임스페이스
     */
    const NAMESPACE = 'ptg-admin/v1';
    
    /**
     * 이미지 리사이징 및 최적화
     * 
     * @param string $file_path 원본 파일 경로
     * @param string $target_path 저장할 파일 경로
     * @param int $max_width 최대 너비 (기본값: 500px)
     * @param int $max_height 최대 높이 (기본값: 500px)
     * @param int $quality JPEG 품질 (기본값: 85)
     * @return bool 성공 여부
     */
    private static function resize_and_optimize_image( $file_path, $target_path, $max_width = 500, $max_height = 500, $quality = 85 ) {
        if ( ! file_exists( $file_path ) ) {
            // error_log( '[PTGates Admin] 리사이징 실패: 원본 파일이 없음 - ' . $file_path );
            return false;
        }

        // WordPress 이미지 에디터 사용
        $image = wp_get_image_editor( $file_path );
        
        if ( is_wp_error( $image ) ) {
            // error_log( '[PTGates Admin] 이미지 에디터 로드 실패: ' . $image->get_error_message() );
            return false;
        }

        // 원본 이미지 크기 확인
        $original_size = $image->get_size();
        $original_width = $original_size['width'];
        $original_height = $original_size['height'];
        
        // error_log( sprintf( '[PTGates Admin] 원본 이미지 크기: %dx%d', $original_width, $original_height ) );

        // 리사이징이 필요한지 확인
        $needs_resize = ( $original_width > $max_width || $original_height > $max_height );
        
        if ( $needs_resize ) {
            // 비율 계산
            $ratio = min( $max_width / $original_width, $max_height / $original_height );
            $new_width = intval( $original_width * $ratio );
            $new_height = intval( $original_height * $ratio );
            
            // error_log( sprintf( '[PTGates Admin] 리사이징: %dx%d -> %dx%d', $original_width, $original_height, $new_width, $new_height ) );
            
            // 리사이징 실행
            $resized = $image->resize( $new_width, $new_height, false );
            
            if ( is_wp_error( $resized ) ) {
                // error_log( '[PTGates Admin] 리사이징 실패: ' . $resized->get_error_message() );
                return false;
            }
        } else {
            // error_log( '[PTGates Admin] 리사이징 불필요 (이미 최적 크기)' );
        }

        // JPEG 품질 설정
        $image->set_quality( $quality );
        
        // 파일 저장
        $saved = $image->save( $target_path );
        
        if ( is_wp_error( $saved ) ) {
            // error_log( '[PTGates Admin] 이미지 저장 실패: ' . $saved->get_error_message() );
            return false;
        }
        
        $saved_size = filesize( $target_path );
        $original_file_size = filesize( $file_path );
        $size_reduction = $original_file_size > 0 ? ( 1 - ( $saved_size / $original_file_size ) ) * 100 : 0;
        
        /*
        error_log( sprintf( 
            '[PTGates Admin] 이미지 최적화 완료: 원본 %s -> 저장 %s (%.1f%% 감소)', 
            size_format( $original_file_size ),
            size_format( $saved_size ),
            $size_reduction
        ) );
        */
        
        return true;
    }
    
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
                'question_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // 문제 생성
        register_rest_route(self::NAMESPACE, '/questions', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'create_question'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
            'args' => array(
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
                'subject' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'subsubject' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'exam_year' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'exam_session' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'difficulty' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'default' => 2,
                ),
                'is_active' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => true,
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
        
        // 문제 삭제
        register_rest_route(self::NAMESPACE, '/questions/(?P<id>\\d+)', array(
            'methods' => 'DELETE',
            'callback' => array(__CLASS__, 'delete_question'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
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

        // 과목 카테고리 일괄 업데이트 (Backfill)
        register_rest_route(self::NAMESPACE, '/backfill-categories', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'backfill_subject_categories'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));

        // 원시 과목 목록 조회
        register_rest_route(self::NAMESPACE, '/raw-subjects', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_raw_subjects'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));

        // 과목 매핑 실행
        register_rest_route(self::NAMESPACE, '/subject/map', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'map_subject'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));

        // 교시 설정 업데이트
        register_rest_route(self::NAMESPACE, '/exam-course/update', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'update_exam_course'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));

        // 과목 카테고리(대분류) 생성
        register_rest_route(self::NAMESPACE, '/category/create', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'create_subject_category'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));

        // 과목 카테고리(대분류) 수정
        register_rest_route(self::NAMESPACE, '/category/update', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'update_subject_category'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));

        // 세부 과목 생성
        register_rest_route(self::NAMESPACE, '/subject/create', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'create_subject'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));

        // 세부 과목 수정
        register_rest_route(self::NAMESPACE, '/subject/update', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'update_subject'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));

        // 세부 과목 삭제
        register_rest_route(self::NAMESPACE, '/subject/delete', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'delete_subject'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
        ));

        // 기본 데이터 시딩 (초기화)
        register_rest_route(self::NAMESPACE, '/seed-defaults', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'seed_default_subjects'),
            'permission_callback' => array(__CLASS__, 'check_admin_permission'),
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
            $question_id = $request->get_param('question_id');
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
                'question_id' => $question_id,
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

            if (!empty($question_id)) {
                $where[] = "q.question_id = %d";
                $where_values[] = (int) $question_id;
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
                        MAX(c.question_no) as question_no,
                        GROUP_CONCAT(DISTINCT c.subject ORDER BY c.subject SEPARATOR ', ') as subjects,
                        GROUP_CONCAT(DISTINCT c.exam_year ORDER BY c.exam_year SEPARATOR ', ') as exam_years,
                        GROUP_CONCAT(DISTINCT c.exam_session ORDER BY c.exam_session SEPARATOR ', ') as exam_sessions,
                        GROUP_CONCAT(DISTINCT c.exam_course ORDER BY c.exam_course SEPARATOR ', ') as exam_courses
                 FROM {$questions_table} q
                 INNER JOIN {$categories_table} c ON q.question_id = c.question_id
                 WHERE {$where_clause}
                 GROUP BY q.question_id
                 ORDER BY q.question_id ASC
                 LIMIT %d OFFSET %d";
            
            // Subjects 클래스 로드 확인 (최초 로드는 0000-ptgates-platform에서 수행됨)
            if (!class_exists('\PTG\Quiz\Subjects')) {
                // 플랫폼 코어를 먼저 시도
                $platform_subjects_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/includes/class-subjects.php';
                if (file_exists($platform_subjects_file) && is_readable($platform_subjects_file)) {
                    require_once $platform_subjects_file;
                }
                // 플랫폼 코어가 없으면 기존 위치에서 로드 (호환성)
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
            
            // DB 내용을 그대로 표시 (변환 작업 제거)
            
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
        
        // DB 내용을 그대로 표시 (변환 작업 제거)
        
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
            // REST API의 get_param()은 이미 wp_unslash()를 처리하지만, 명시적으로 처리하여 안전하게 저장
            $update_values[] = wp_unslash($request->get_param('content'));
        }
        
        if ($request->has_param('answer')) {
            $update_fields[] = 'answer = %s';
            $update_values[] = wp_unslash($request->get_param('answer'));
        }
        
        if ($request->has_param('explanation')) {
            $update_fields[] = 'explanation = %s';
            $update_values[] = wp_unslash($request->get_param('explanation'));
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
     * 문제 삭제
     */
    public static function delete_question($request) {
        global $wpdb;
        
        $question_id = $request->get_param('id');
        $questions_table = 'ptgates_questions';
        $categories_table = 'ptgates_categories';
        
        // 기존 문제 확인 및 이미지 정보 가져오기
        $question = $wpdb->get_row($wpdb->prepare(
            "SELECT question_id, question_image FROM {$questions_table} WHERE question_id = %d",
            $question_id
        ), ARRAY_A);
        
        if (!$question) {
            return Rest::error('not_found', '문제를 찾을 수 없습니다.', 404);
        }
        
        // category info retrieval (for image path)
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT exam_year, exam_session FROM {$categories_table} WHERE question_id = %d LIMIT 1",
            $question_id
        ), ARRAY_A);
        
        // 0. 사용자 데이터 테이블에서 삭제 (외래키 제약조건이 없거나 확실하지 않은 경우를 대비해 명시적 삭제)
        $user_tables = ['ptgates_user_drawings', 'ptgates_user_memos', 'ptgates_user_states', 'ptgates_user_results'];
        foreach ($user_tables as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
                $wpdb->delete($table, array('question_id' => $question_id), array('%d'));
            }
        }
        
        // 1. 카테고리 테이블에서 삭제
        $cat_result = $wpdb->delete(
            $categories_table,
            array('question_id' => $question_id),
            array('%d')
        );
        
        // 2. 문제 테이블에서 삭제
        $result = $wpdb->delete(
            $questions_table,
            array('question_id' => $question_id),
            array('%d')
        );
        
        if ($result === false) {
            return Rest::error('delete_failed', '문제 삭제에 실패했습니다.', 500);
        }
        
        // 3. 이미지 파일 삭제 (있는 경우)
        if (!empty($question['question_image']) && $category) {
            $upload_dir = wp_upload_dir();
            $image_path = $upload_dir['basedir'] . '/ptgates-questions/' . 
                         $category['exam_year'] . '/' . 
                         $category['exam_session'] . '/' . 
                         $question['question_image'];
            
            if (file_exists($image_path)) {
                @unlink($image_path);
            }
        }
        
        // 캐시 삭제
        wp_cache_delete('ptg_admin_question_' . $question_id, 'ptg_admin');
        
        return Rest::success(array(
            'deleted' => true,
            'question_id' => $question_id,
            'message' => '문제가 성공적으로 삭제되었습니다.'
        ));
    }

    /**
     * 문제 생성
     */
    public static function create_question($request) {
        global $wpdb;
        
        $questions_table = 'ptgates_questions';
        $categories_table = 'ptgates_categories';
        
        // REST API의 get_param()은 이미 wp_unslash()를 처리하지만, 명시적으로 처리하여 안전하게 저장
        $content = wp_unslash($request->get_param('content') ?: '');
        $answer = wp_unslash($request->get_param('answer') ?: '');
        $explanation = wp_unslash($request->get_param('explanation') ?: '');
        $subject = $request->get_param('subject');
        $subsubject = $request->get_param('subsubject');
        $exam_year = $request->get_param('exam_year');
        $exam_session = $request->get_param('exam_session');
        $difficulty = $request->get_param('difficulty') ?: 2;
        $is_active = $request->get_param('is_active') !== false ? 1 : 0;
        
        // content/explanation 줄바꿈 처리
        // 줄바꿈 정규화 (\r\n, \r -> \n)
        $content = str_replace( array( "\r\n", "\r" ), "\n", $content );
        
        // 동그라미 숫자 앞에 줄바꿈이 없으면 추가 (선택지 내부 줄바꿈은 보존)
        $content = preg_replace( '/(?<!\n)([①-⑳])/u', "\n$1", $content );
        
        // 연속된 줄바꿈 정리 (3개 이상 -> 2개로, 단 동그라미 숫자 앞의 줄바꿈은 유지)
        $content = preg_replace( '/\n{3,}/u', "\n\n", $content );
        // $explanation = str_replace( array( "\r\n", "\r", "\n" ), '', $explanation );
        // $explanation = preg_replace( '/\s*(\(오답\s*해설\))/u', "\n$1", $explanation ); 
        
        // 1. 문제 테이블 삽입
        $result = $wpdb->insert(
            $questions_table,
            array(
                'content' => $content,
                'answer' => $answer,
                'explanation' => $explanation,
                'type' => 'multiple_choice', // 기본값
                'difficulty' => $difficulty,
                'is_active' => $is_active,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );
        
        if ($result === false) {
            return Rest::error('insert_failed', '문제 생성에 실패했습니다: ' . $wpdb->last_error, 500);
        }
        
        $question_id = $wpdb->insert_id;
        
        // 2. 카테고리 테이블 삽입
        // DB 구조상 subject 컬럼에 세부과목이 저장됨
        $final_subject = $subsubject ? $subsubject : $subject;
        
        // subject_category (대분류) 찾기
        $subject_category = '';
        if (class_exists('\PTG\Quiz\Subjects')) {
            $subject_category = \PTG\Quiz\Subjects::get_subject_from_subsubject($final_subject);
        }
        // 찾지 못했다면 빈 문자열 또는 NULL (여기서는 빈 문자열)
        if ($subject_category === null) {
            $subject_category = '';
        }

        $cat_result = $wpdb->insert(
            $categories_table,
            array(
                'question_id' => $question_id,
                'subject' => $final_subject,
                'subject_category' => $subject_category,
                'exam_year' => $exam_year,
                'exam_session' => $exam_session,
                'exam_year' => $exam_year,
                'exam_session' => $exam_session,
                'exam_course' => (function() use ($wpdb, $final_subject) {
                    // ptgates_subject_config 테이블에서 교시 정보 조회
                    $table_config = 'ptgates_subject_config';
                    $course = $wpdb->get_var($wpdb->prepare(
                        "SELECT exam_course FROM {$table_config} WHERE subject = %s LIMIT 1",
                        $final_subject
                    ));
                    
                    if ($course) {
                        // DB에 '3' 또는 '3교시' 형태로 저장되어 있을 수 있음
                        // 숫자인 경우 '교시' 접미사 추가
                        if (is_numeric($course)) {
                            return $course . '교시';
                        }
                        return $course;
                    }
                    
                    // 매핑 정보가 없으면 기본값
                    return '1교시';
                })()
            ),
            array( '%d', '%s', '%s', '%d', '%d', '%s' )
        );
        
        if ($cat_result === false) {
            // 롤백? (MySQL MyISAM이면 불가, InnoDB면 트랜잭션 필요하지만 여기선 생략)
            // 에러 로그만 남김
            // error_log('Failed to insert category for question ' . $question_id . ': ' . $wpdb->last_error);
        }
        
        // 이미지 처리 (파일 업로드는 별도 엔드포인트나 multipart/form-data로 처리해야 함)
        // WordPress REST API에서는 $request->get_file_params()를 사용해야 함
        $file_params = $request->get_file_params();
        
        // 디버깅: 파일 파라미터 확인
        // error_log( '[PTGates Admin] File params: ' . print_r( $file_params, true ) );
        // error_log( '[PTGates Admin] $_FILES 내용: ' . print_r( $_FILES, true ) );
        
        // REST API의 get_file_params() 또는 $_FILES 사용
        if ( ! empty( $file_params['question_image']['name'] ) ) {
            $file = $file_params['question_image'];
        } elseif ( ! empty( $_FILES['question_image']['name'] ) ) {
            $file = $_FILES['question_image'];
        } else {
            $file = null;
        }
        
        if ( $file && ! empty( $file['name'] ) ) {
            
            // 확장자 추출 (여러 방법 시도)
            $ext = '';
            $filename_lower = strtolower( $file['name'] );
            
            // 방법 1: pathinfo 사용
            $ext_from_pathinfo = pathinfo( $file['name'], PATHINFO_EXTENSION );
            if ( ! empty( $ext_from_pathinfo ) ) {
                $ext = strtolower( $ext_from_pathinfo );
            } else {
                // 방법 2: 파일명에서 직접 추출
                $parts = explode( '.', $file['name'] );
                if ( count( $parts ) > 1 ) {
                    $ext = strtolower( end( $parts ) );
                }
            }
            
            // 디버깅 로그
            // error_log( '[PTGates Admin] 이미지 업로드 시도 - 파일명: ' . $file['name'] . ', 확장자: ' . $ext . ', MIME: ' . ( isset( $file['type'] ) ? $file['type'] : '없음' ) );
            
            $allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif' );
            
            // 확장자로 검증 (MIME 타입은 브라우저마다 다를 수 있으므로 확장자 우선)
            $is_valid = ! empty( $ext ) && in_array( $ext, $allowed_extensions );
            
            // MIME 타입도 추가 검증 (선택적)
            if ( ! $is_valid && ! empty( $file['type'] ) ) {
                $mime_type = strtolower( $file['type'] );
                $allowed_mime_types = array( 'image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/x-png' );
                $is_valid = in_array( $mime_type, $allowed_mime_types );
                // MIME 타입이 유효하면 확장자 추정
                if ( $is_valid && empty( $ext ) ) {
                    if ( strpos( $mime_type, 'jpeg' ) !== false ) {
                        $ext = 'jpg';
                    } elseif ( strpos( $mime_type, 'png' ) !== false ) {
                        $ext = 'png';
                    } elseif ( strpos( $mime_type, 'gif' ) !== false ) {
                        $ext = 'gif';
                    }
                }
            }
            
            if ( $is_valid && ! empty( $ext ) ) {
                $upload_dir = wp_upload_dir();
                $target_dir = $upload_dir['basedir'] . '/ptgates-questions/' . $exam_year . '/' . $exam_session;
                
                if ( ! file_exists( $target_dir ) ) {
                    wp_mkdir_p( $target_dir );
                }
                
                // 확장자를 소문자로 통일
                $filename = $question_id . '.' . $ext;
                $target_file = $target_dir . '/' . $filename;
                
                // 임시 파일로 먼저 이동
                $temp_file = $target_dir . '/temp_' . $filename;
                
                if ( move_uploaded_file( $file['tmp_name'], $temp_file ) ) {
                    // 이미지 리사이징 및 최적화
                    if ( self::resize_and_optimize_image( $temp_file, $target_file, 500, 500, 85 ) ) {
                        // 리사이징 성공 시 임시 파일 삭제
                        if ( file_exists( $temp_file ) ) {
                            unlink( $temp_file );
                        }
                        // error_log( '[PTGates Admin] 이미지 리사이징 및 저장 완료: ' . $target_file );
                        
                        $wpdb->update(
                            $questions_table,
                            array( 'question_image' => $filename ),
                            array( 'question_id' => $question_id ),
                            array( '%s' ),
                            array( '%d' )
                        );
                    } else {
                        // 리사이징 실패 시 원본 파일 사용 (하위 호환성)
                        // error_log( '[PTGates Admin] 리사이징 실패, 원본 파일 사용' );
                        if ( file_exists( $temp_file ) ) {
                            if ( rename( $temp_file, $target_file ) ) {
                                $wpdb->update(
                                    $questions_table,
                                    array( 'question_image' => $filename ),
                                    array( 'question_id' => $question_id ),
                                    array( '%s' ),
                                    array( '%d' )
                                );
                            } else {
                                // error_log( '[PTGates Admin] 원본 파일 이동도 실패: ' . $target_file );
                            }
                        }
                    }
                } else {
                    // error_log( '[PTGates Admin] 파일 이동 실패: ' . $temp_file );
                }
            } else {
                // error_log( '[PTGates Admin] 이미지 검증 실패 - 파일명: ' . $file['name'] . ', 확장자: ' . $ext . ', MIME: ' . ( isset( $file['type'] ) ? $file['type'] : '없음' ) );
                return Rest::error('invalid_file_type', '허용되지 않는 파일 형식입니다. (jpg, png, gif 만 가능)', 400);
            }
        }
        
        // 생성된 문제 반환 (ID 포함)
        $request->set_param('id', $question_id);
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
    /**
     * 과목 목록 조회 (관리자용 전체 설정)
     */
    public static function get_subjects($request) {
        global $wpdb;
        try {
            // 1. 교시 설정 조회
            $courses = $wpdb->get_results("SELECT * FROM ptgates_exam_course_config ORDER BY exam_course ASC", ARRAY_A);
            
            // 2. 과목 설정 조회
            $subjects = $wpdb->get_results("SELECT * FROM ptgates_subject_config ORDER BY sort_order ASC", ARRAY_A);
            
            // [Fix] 누락된 교시 자동 등록 (ptgates_subject_config에는 있는데 ptgates_exam_course_config에 없는 경우)
            if (!empty($subjects)) {
                $existing_courses = !empty($courses) ? array_column($courses, 'exam_course') : array();
                $subject_courses = array_unique(array_column($subjects, 'exam_course'));
                $missing_courses = array_diff($subject_courses, $existing_courses);

                if (!empty($missing_courses)) {
                    foreach ($missing_courses as $missing_course) {
                        // 해당 교시의 총 문항 수 계산
                        $total = 0;
                        foreach ($subjects as $subj) {
                            if ($subj['exam_course'] === $missing_course) {
                                $total += (int)$subj['question_count'];
                            }
                        }

                        // DB에 등록
                        $wpdb->insert('ptgates_exam_course_config', array(
                            'exam_course' => $missing_course,
                            'total_questions' => $total,
                            'is_active' => 1
                        ));
                        
                        // 현재 응답 리스트에 추가 (새로고침 없이 즉시 반영)
                        $courses[] = array(
                            'id' => $wpdb->insert_id,
                            'exam_course' => $missing_course,
                            'total_questions' => $total,
                            'is_active' => 1
                        );
                    }
                    
                    // 순서 정렬 (문자열 기준)
                    usort($courses, function($a, $b) {
                        return strcmp($a['exam_course'], $b['exam_course']);
                    });
                }
            }
            
            // 3. 카테고리(대분류) 목록 추출 (중복 제거)
            $categories = $wpdb->get_results("SELECT DISTINCT subject_category, exam_course FROM ptgates_subject_config ORDER BY sort_order ASC", ARRAY_A);

            return Rest::success(array(
                'courses' => $courses,
                'subjects' => $subjects,
                'categories' => $categories
            ));
        } catch (\Exception $e) {
            return Rest::error('server_error', '서버 오류: ' . $e->getMessage(), 500);
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
            
            // Subjects 클래스가 없으면 다시 로드 시도 (최초 로드는 0000-ptgates-platform에서 수행됨)
            if (!class_exists('\PTG\Quiz\Subjects')) {
                // 플랫폼 코어를 먼저 시도
                $platform_subjects_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/includes/class-subjects.php';
                if (file_exists($platform_subjects_file) && is_readable($platform_subjects_file)) {
                    require_once $platform_subjects_file;
                }
                // 플랫폼 코어가 없으면 기존 위치에서 로드 (호환성)
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
            }
            
            if (!class_exists('\PTG\Quiz\Subjects')) {
                return Rest::error('class_not_found', 'Subjects 클래스를 찾을 수 없습니다. 0000-ptgates-platform 또는 1200-ptgates-quiz 플러그인이 활성화되어 있는지 확인하세요.', 500);
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

    /**
     * 과목/카테고리 코드 업데이트 (Code Mapping Backfill)
     * ptgates_categories의 누락된 코드를 ptgates_subject_config 참조하여 업데이트
     */
    public static function backfill_subject_categories($request) {
        global $wpdb;
        
        $categories_table = 'ptgates_categories';
        $config_table = 'ptgates_subject_config';
        
        // 1. 코드가 누락된 항목 조회 (subject_category_code 또는 subject_code가 NULL/Empty)
        $rows = $wpdb->get_results("
            SELECT category_id, subject_category, subject 
            FROM {$categories_table} 
            WHERE (subject_category_code IS NULL OR subject_category_code = '')
               OR (subject_code IS NULL OR subject_code = '')
        ");
        
        if (!$rows) {
            return Rest::success(array('message' => '업데이트할 코드가 누락된 항목이 없습니다.', 'count' => 0));
        }
        
        $updated_count = 0;
        $failed_count = 0;
        
        foreach ($rows as $row) {
            // 2. Config 테이블에서 매칭되는 코드 조회
            // subject만 일치하면 매핑 (사용자 요청: 세부과목명이 일치하면 자동 매핑)
            $config = $wpdb->get_row($wpdb->prepare(
                "SELECT subject_category_code, subject_code, subject_category 
                 FROM {$config_table} 
                 WHERE subject = %s
                 LIMIT 1",
                $row->subject
            ));
            
            if ($config) {
                // 3. 코드 및 카테고리 업데이트
                $update_data = array();
                
                // 코드가 있으면 업데이트
                if (!empty($config->subject_category_code)) {
                    $update_data['subject_category_code'] = $config->subject_category_code;
                }
                if (!empty($config->subject_code)) {
                    $update_data['subject_code'] = $config->subject_code;
                }
                
                // 카테고리 명도 정규화된 이름으로 업데이트 (자동 보정)
                if (!empty($config->subject_category)) {
                    $update_data['subject_category'] = $config->subject_category;
                }
                
                if (!empty($update_data)) {
                    $result = $wpdb->update(
                        $categories_table,
                        $update_data,
                        array('category_id' => $row->category_id),
                        null, // format inferred
                        array('%d')
                    );
                    
                    if ($result !== false) {
                        $updated_count++;
                    } else {
                        $failed_count++;
                    }
                } else {
                     $failed_count++; // Config는 찾았으나 코드가 비어있음
                }
            } else {
                // 매핑을 찾지 못한 경우
                $failed_count++;
            }
        }
        
        return Rest::success(array(
            'message' => "{$updated_count}개의 항목의 코드가 업데이트되었습니다. (실패/미매칭: {$failed_count})",
            'count' => $updated_count,
            'failed' => $failed_count
        ));
    }

    /**
     * 교시 설정 업데이트
     */
    public static function update_exam_course($request) {
        global $wpdb;
        $id = $request->get_param('id');
        $total_questions = $request->get_param('total_questions');
        
        if (!$id || $total_questions === null) {
            return Rest::error('invalid_param', '필수 파라미터 누락', 400);
        }
        
        $result = $wpdb->update(
            'ptgates_exam_course_config',
            array('total_questions' => $total_questions),
            array('id' => $id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            return Rest::error('db_error', '업데이트 실패', 500);
        }
        
        return Rest::success(array('message' => '업데이트 성공'));
    }

    /**
     * 과목 카테고리(대분류) 생성
     */
    public static function create_subject_category($request) {
        return Rest::success(array('message' => '카테고리 생성은 세부과목 추가 시 자동 처리됩니다.'));
    }

    /**
     * 과목 카테고리(대분류) 수정
     */
    public static function update_subject_category($request) {
        global $wpdb;
        $old_name = $request->get_param('old_name');
        $new_name = $request->get_param('new_name');
        $exam_course = $request->get_param('exam_course');
        
        if (!$old_name || !$new_name) {
            return Rest::error('invalid_param', '필수 파라미터 누락', 400);
        }
        
        $where = array('subject_category' => $old_name);
        $where_format = array('%s');
        
        if ($exam_course) {
            $where['exam_course'] = $exam_course;
            $where_format[] = '%s';
        }
        
        $result = $wpdb->update(
            'ptgates_subject_config',
            array('subject_category' => $new_name),
            $where,
            array('%s'),
            $where_format
        );
        
        if ($result === false) {
            return Rest::error('db_error', '업데이트 실패', 500);
        }
        
        return Rest::success(array('message' => '카테고리 수정 성공', 'affected' => $result));
    }

    /**
     * 세부 과목 생성
     */
    public static function create_subject($request) {
        global $wpdb;
        
        $data = array(
            'exam_course' => $request->get_param('exam_course'),
            'subject_category' => $request->get_param('subject_category'),
            'subject' => $request->get_param('subject'),
            'subject_code' => $request->get_param('subject_code'),
            'question_count' => $request->get_param('question_count'),
            'sort_order' => $request->get_param('sort_order') ?: 0,
            'is_active' => 1
        );
        
        if (empty($data['exam_course']) || empty($data['subject']) || empty($data['subject_code'])) {
            return Rest::error('invalid_param', '필수 파라미터 누락', 400);
        }
        
        $result = $wpdb->insert('ptgates_subject_config', $data);
        
        if ($result === false) {
            return Rest::error('db_error', '생성 실패: ' . $wpdb->last_error, 500);
        }
        
        return Rest::success(array('id' => $wpdb->insert_id, 'message' => '과목 생성 성공'));
    }

    /**
     * 세부 과목 수정
     */
    public static function update_subject($request) {
        global $wpdb;
        
        $id = $request->get_param('config_id');
        if (!$id) {
            return Rest::error('invalid_param', 'ID 누락', 400);
        }
        
        $data = array();
        $params = ['exam_course', 'subject_category', 'subject', 'subject_code', 'question_count', 'sort_order', 'is_active'];
        
        foreach ($params as $param) {
            if ($request->has_param($param)) {
                $data[$param] = $request->get_param($param);
            }
        }
        
        if (empty($data)) {
            return Rest::error('no_data', '수정할 데이터가 없습니다.', 400);
        }
        
        $result = $wpdb->update(
            'ptgates_subject_config',
            $data,
            array('config_id' => $id)
        );
        
        if ($result === false) {
            return Rest::error('db_error', '수정 실패', 500);
        }
        
        return Rest::success(array('message' => '과목 수정 성공'));
    }

    /**
     * 세부 과목 삭제 (Soft Delete)
     */
    public static function delete_subject($request) {
        global $wpdb;
        
        $id = $request->get_param('config_id');
        if (!$id) {
            return Rest::error('invalid_param', 'ID 누락', 400);
        }
        
        // Soft delete: is_active = 0
        $result = $wpdb->update(
            'ptgates_subject_config',
            array('is_active' => 0),
            array('config_id' => $id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            return Rest::error('db_error', '삭제 실패', 500);
        }
        
        return Rest::success(array('message' => '과목 삭제 성공'));
    }
    /**
     * 기본 데이터 시딩 (초기화)
     */
    public static function seed_default_subjects($request) {
        global $wpdb;
        
        // 1. 기존 데이터 확인
        $course_count = $wpdb->get_var("SELECT COUNT(*) FROM ptgates_exam_course_config");
        if ($course_count > 0) {
            return Rest::error('already_exists', '이미 데이터가 존재합니다.', 400);
        }
        
        // 2. 기본 맵 정의 (1200-ptgates-quiz/includes/class-subjects.php 참조)
        $map = [
            1 => [
                'total'    => 105,
                'subjects' => [
                    '물리치료 기초' => [
                        'total' => 60,
                        'subs'  => [
                            '해부생리학'      => 22,
                            '운동학'         => 12,
                            '물리적 인자치료' => 16,
                            '공중보건학'     => 10,
                        ],
                    ],
                    '물리치료 진단평가' => [
                        'total' => 45,
                        'subs'  => [
                            '근골격계 물리치료 진단평가' => 10,
                            '신경계 물리치료 진단평가'   => 16,
                            '진단평가 원리'              => 6,
                            '심폐혈관계 검사 및 평가'    => 4,
                            '기타 계통 검사'             => 2,
                            '임상의사결정'              => 7,
                        ],
                    ],
                ],
            ],
            2 => [
                'total'    => 85,
                'subjects' => [
                    '물리치료 중재' => [
                        'total' => 65,
                        'subs'  => [
                            '근골격계 중재'     => 28,
                            '신경계 중재'       => 25,
                            '심폐혈관계 중재'   => 5,
                            '림프, 피부계 중재' => 2,
                            '물리치료 문제해결' => 5,
                        ],
                    ],
                    '의료관계법규' => [
                        'total' => 20,
                        'subs'  => [
                            '의료법'         => 5,
                            '의료기사법'     => 5,
                            '노인복지법'     => 4,
                            '장애인복지법'   => 3,
                            '국민건강보험법' => 3,
                        ],
                    ],
                ],
            ],
            3 => [ // 3교시 (실기) - 추가 추정
                'total' => 80,
                'subjects' => [
                    '실기' => [
                        'total' => 80,
                        'subs' => [
                            '근골격계' => 40,
                            '신경계' => 40, 
                        ]
                    ]
                ]
            ]
        ];
        
        // 3. 데이터 삽입
        $inserted_courses = 0;
        $inserted_subjects = 0;
        
        foreach ($map as $session_num => $data) {
            // 교시 삽입
            $course_name = $session_num . '교시';
            $wpdb->insert(
                'ptgates_exam_course_config',
                array(
                    'exam_course' => $course_name,
                    'total_questions' => $data['total'],
                    'is_active' => 1
                )
            );
            $inserted_courses++;
            
            $sort_order = 1;
            foreach ($data['subjects'] as $main_subject => $sub_data) {
                foreach ($sub_data['subs'] as $sub_subject => $count) {
                    // 코드 생성 (임시)
                    $code = 'PT_' . $session_num . '_' . mb_substr($sub_subject, 0, 2, 'UTF-8') . '_' . rand(100, 999);
                    
                    $wpdb->insert(
                        'ptgates_subject_config',
                        array(
                            'exam_course' => $course_name,
                            'subject_category' => $main_subject,
                            'subject' => $sub_subject,
                            'subject_code' => $code,
                            'question_count' => $count,
                            'sort_order' => $sort_order++,
                            'is_active' => 1
                        )
                    );
                    $inserted_subjects++;
                }
            }
        }
        
        return Rest::success(array(
            'message' => "초기화 완료: 교시 {$inserted_courses}개, 과목 {$inserted_subjects}개 생성됨.",
            'courses' => $inserted_courses,
            'subjects' => $inserted_subjects
        ));
    }

    /**
     * 원시 과목 목록 조회 (ptgates_categories)
     */
    public static function get_raw_subjects() {
        global $wpdb;
        
        $results = $wpdb->get_results("
            SELECT subject, COUNT(*) as count, GROUP_CONCAT(question_id) as question_ids
            FROM ptgates_categories
            WHERE exam_session >= 1000
            AND (
                subject_category_code IS NULL OR subject_category_code = ''
                OR subject_code IS NULL OR subject_code = ''
            )
            GROUP BY subject
            ORDER BY subject ASC
        ");
        
        return Rest::success($results);
    }

    /**
     * 과목 매핑 실행
     */
    public static function map_subject($request) {
        global $wpdb;
        
        $params = $request->get_json_params();
        $old_subject = sanitize_text_field($params['old_subject'] ?? '');
        $new_subject_id = absint($params['new_subject_id'] ?? 0);

        if (empty($old_subject) || empty($new_subject_id)) {
            return Rest::error('invalid_params', 'Invalid parameters');
        }

        // 정식 과목 정보 조회
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM ptgates_subject_config WHERE config_id = %d",
            $new_subject_id
        ));

        if (!$config) {
            return Rest::error('not_found', 'Official subject not found');
        }

        // ptgates_categories 업데이트
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE ptgates_categories
             SET subject = %s,
                 subject_code = %s,
                 subject_category = %s,
                 subject_category_code = %s
             WHERE subject = %s
             AND exam_session >= 1000",
            $config->subject,
            $config->subject_code,
            $config->subject_category,
            !empty($config->subject_category_code) ? $config->subject_category_code : '', // Handle potential null
            $old_subject
        ));

        if ($updated === false) {
            return Rest::error('db_error', 'Database update failed');
        }

        return Rest::success([
            'message' => "성공적으로 매핑되었습니다. ({$updated}개 문항 업데이트)",
            'updated_count' => $updated
        ]);
    }
}

