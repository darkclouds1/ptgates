<?php
/**
 * PTGates Quiz REST API 클래스
 * 
 * REST API 네임스페이스: ptg-quiz/v1
 */

namespace PTG\Quiz;

use PTG\Platform\Permissions;
use PTG\Platform\Rest;
use PTG\Platform\Repo;
use PTG\Platform\LegacyRepo;
use PTG\Quiz\Subjects;

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 정적 과목 정의 클래스가 아직 로드되지 않았다면 현재 디렉터리에서 로드
// 주의: 최초 로드는 0000-ptgates-platform에서 수행되므로, 이 코드는 호환성을 위해 유지
if (!class_exists('\PTG\Quiz\Subjects')) {
    $subjects_file = __DIR__ . '/class-subjects.php';
    if (file_exists($subjects_file) && is_readable($subjects_file)) {
        require_once $subjects_file;
    }
}

class API {
    
    /**
     * REST API 네임스페이스
     */
    const NAMESPACE = 'ptg-quiz/v1';
    
    /**
     * REST API 라우트 등록
     */
    public static function register_routes() {
        // 문제 목록 조회 (필터 조건으로 여러 문제 조회)
        register_rest_route(self::NAMESPACE, '/questions', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_questions_list'),
            'permission_callback' => '__return_true', // 공개 API
            'args' => array(
                'year' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
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
                'limit' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'default' => 5, // 기본값 5문제
                ),
                'session' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'full_session' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                ),
                'bookmarked' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                ),
                'needs_review' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                ),
                'wrong_only' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                ),
            ),
        ));
        
        // 문제 조회
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_question'),
            'permission_callback' => '__return_true', // 공개 API
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // 문제 상태 조회
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)/state', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_question_state'),
            'permission_callback' => '__return_true', // 공개 API (비로그인 허용)
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // 통합 사용자 상태 조회 (북마크, 복습, 메모, 암기카드)
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)/user-status', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_user_status'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // 문제 상태 업데이트 (북마크, 복습 필요, 마지막 답안)
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)/state', array(
            'methods' => 'PATCH',
            'callback' => array(__CLASS__, 'update_question_state'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'bookmarked' => array(
                    'type' => 'boolean',
                ),
                'needs_review' => array(
                    'type' => 'boolean',
                ),
                'last_answer' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // 문제 풀이 시도 (정식 경로)
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)/attempt', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'attempt_question'),
            'permission_callback' => '__return_true', // 공개 API (비로그인 허용)
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'answer' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'elapsed' => array(
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        // 호환 경로: /questions/{id}/attempt (파라미터명이 id인 경우)
        register_rest_route(self::NAMESPACE, '/questions/(?P<id>\d+)/attempt', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => function($request){ $request->set_param('question_id', absint($request->get_param('id'))); return self::attempt_question($request); },
            'permission_callback' => '__return_true', // 공개 API (비로그인 허용)
        ));
        
        // 드로잉 목록 조회
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)/drawings', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_drawings'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'is_answered' => array(
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ),
                'device_type' => array(
                    'type' => 'string',
                    'default' => 'pc',
                    'sanitize_callback' => function($param) {
                        $allowed = array('pc', 'tablet', 'mobile');
                        return in_array($param, $allowed) ? $param : 'pc';
                    }
                ),
            ),
        ));
        
        // 드로잉 저장
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)/drawings', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'save_drawing'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'format' => array(
                    'type' => 'string',
                    'default' => 'json',
                ),
                'data' => array(
                    'required' => false, // 함수에서 empty 체크를 하고 있음
                    'type' => 'string',
                ),
                'width' => array(
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'height' => array(
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'device' => array(
                    'type' => 'string',
                ),
                'is_answered' => array(
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ),
                'device_type' => array(
                    'type' => 'string',
                    'default' => 'pc',
                ),
            ),
        ));

        // 메모 조회
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)/memo', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_memo'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // 메모 저장
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)/memo', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'update_memo'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'content' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ));

        // 메모 조회
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)/memo', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_memo'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // 메모 저장
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)/memo', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'update_memo'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'content' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ));

        
        // 해설 조회
        register_rest_route(self::NAMESPACE, '/explanation/(?P<question_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_explanation'),
            'permission_callback' => '__return_true', // 공개 API
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // 교시별 과목 목록 조회
        register_rest_route(self::NAMESPACE, '/subjects', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_subjects'),
            'permission_callback' => '__return_true', // 공개 API
            'args' => array(
                'session' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // 교시 목록 조회 (ptGates_subject 기반)
        register_rest_route(self::NAMESPACE, '/sessions', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_sessions'),
            'permission_callback' => '__return_true', // 공개 API
        ));
        
        // 세부과목 목록 조회 (ptGates_subject 기반)
        register_rest_route(self::NAMESPACE, '/subsubjects', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_subsubjects'),
            'permission_callback' => '__return_true', // 공개 API
            'args' => array(
                'session' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'subject' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }
    
    /**
     * 권한 확인 콜백
     */
    public static function check_permission($request) {
        return Permissions::rest_check_user_and_nonce($request);
    }
    
    /**
     * 문제 목록 조회 (필터 조건으로 여러 문제 조회)
     */
    public static function get_questions_list($request) {
        global $wpdb;

        $year = $request->get_param('year');
        $subject = $request->get_param('subject');
        $subsubject = $request->get_param('subsubject');
        $limit = absint($request->get_param('limit')) ?: 5; // 기본값 5문제
        $session = $request->get_param('session');
        $full_session = $request->get_param('full_session') === true || $request->get_param('full_session') === 'true';
        $bookmarked = $request->get_param('bookmarked') === true || $request->get_param('bookmarked') === 'true';
        $needs_review = $request->get_param('needs_review') === true || $request->get_param('needs_review') === 'true';
        $wrong_only = $request->get_param('wrong_only') === true || $request->get_param('wrong_only') === 'true';

        // 모듈 결정 (퀴즈 vs 복습)
        $module = ($needs_review || $wrong_only) ? 'reviewer' : 'quiz';

        // 1. 권한/한도 체크
        $current_user_id = get_current_user_id();
        if (class_exists('PTG_Access_Manager')) {
            $access_check = \PTG_Access_Manager::check_access($module, $current_user_id);
            if (is_wp_error($access_check)) {
                return Rest::error($access_check->get_error_code(), $access_check->get_error_message(), $access_check->get_error_data());
            }
        }

        // 고급 퀴즈 유형 접근 권한 확인 (Basic 등급 제한)
        $can_advanced = true;
        if (class_exists('\PTG\Platform\Permissions')) {
            $can_advanced = \PTG\Platform\Permissions::can_access_feature('advanced_quiz', $current_user_id);
        }
        
        // 캐시 키 생성 (북마크/복습 필터는 사용자별로 다르므로 user_id 포함)
        $user_id = 0;
        if ($bookmarked || $needs_review || $wrong_only) {
            $user_id = get_current_user_id();
            if (!$user_id) {
                // 로그인하지 않은 경우 빈 배열 반환
                return Rest::success(array());
            }
        }
        
        $cache_params = array(
            'year' => $year,
            'subject' => $subject,
            'subsubject' => $subsubject,
            'limit' => $limit,
            'session' => $session,
            'full_session' => $full_session,
            'bookmarked' => $bookmarked,
            'needs_review' => $needs_review,
            'wrong_only' => $wrong_only,
            'user_id' => $user_id,
        );
        $cache_key = 'ptg_quiz_questions_' . md5(serialize($cache_params));
        
        // 랜덤 정렬 여부 확인 (복습이 아니고, 전체 교시 풀이가 아닌 경우)
        $is_random = !$needs_review && !(!empty($session) && $full_session);

        // 캐시 확인 (30분 유효 - 문제 목록은 자주 변경될 수 있음)
        // 랜덤 정렬인 경우 캐시 사용 안 함 (매번 새로운 문제)
        $cached = false;
        if (!$is_random) {
            $cached = wp_cache_get($cache_key, 'ptg_quiz');
        }
        
        if ($cached !== false) {
            // 캐시된 결과 반환 전 사용량 증가
            if (class_exists('PTG_Access_Manager')) {
                \PTG_Access_Manager::increment_usage($module, $current_user_id, count($cached));
            }
            return Rest::success($cached);
        }
        
        // 기출문제 제외 (exam_session >= 1000)
        // 1200-ptgates-quiz는 실전모의학습이므로 기출문제 제외
        // 기출문제는 exam_session < 1000이므로, 기출문제를 제외하려면 exam_session >= 1000
        $questions_table = 'ptgates_questions';
        $categories_table = 'ptgates_categories';
        $states_table = 'ptgates_user_states';
        
        $where = array("q.is_active = 1");
        // 기출문제 제외: exam_session >= 1000 (기출문제는 exam_session < 1000)
        $where[] = "c.exam_session >= 1000";

        // 고급 퀴즈 유형 필터링 (Basic 등급은 고급 유형 제외)
        if (!$can_advanced) {
            // TODO: DB의 type 컬럼 값을 정확히 확인하여 필터링 필요
            // 현재는 'multiple_choice', 'short_answer'가 표준 유형이라고 가정
            // $where[] = "q.type IN ('multiple_choice', 'short_answer')";
            // 또는 이미지 포함 여부 확인
            // $where[] = "q.content NOT LIKE '%<img%'";
        }
        
        // 북마크 또는 복습 필터가 있으면 JOIN 추가
        $join_clause = '';
        if ($bookmarked || $needs_review || $wrong_only) {
            $join_clause = $wpdb->prepare(
                "INNER JOIN `{$states_table}` s ON q.question_id = s.question_id AND s.user_id = %d",
                $user_id
            );
            
            if ($bookmarked) {
                $where[] = "s.bookmarked = 1";
            }
            
            // 복습 필요 OR 틀린 문제 (둘 다 선택 시 합집합)
            if ($needs_review && $wrong_only) {
                $where[] = "(s.needs_review = 1 OR s.last_result = 'wrong')";
            } elseif ($needs_review) {
                $where[] = "s.needs_review = 1";
            } elseif ($wrong_only) {
                $where[] = "s.last_result = 'wrong'";
            }
        }
        
        // 연도 필터
        if (!empty($year)) {
            $where[] = $wpdb->prepare("c.exam_year = %d", absint($year));
        }
        
        // 과목/세부과목 필터
        // - subsubject가 있으면 이를 포함하는 상위 과목군을 Subjects::MAP에서 역으로 찾아서 OR 조건으로 필터링
        // - subsubject가 없고 subject가 있으면 subject로 LIKE 필터링
        if (!empty($subsubject)) {
            // 세부과목이 지정되면 해당 세부과목만 정확히 매칭 (DB의 subject 컬럼이 세부과목명을 가질 수 있음)
            $sub_name = trim(sanitize_text_field($subsubject));
            // 정확히 일치하거나, 일부 접두/접미 텍스트가 붙은 형태까지 포괄
            $like = '%' . $wpdb->esc_like($sub_name) . '%';
            $where[] = $wpdb->prepare("(c.subject = %s OR c.subject LIKE %s)", $sub_name, $like);
        } elseif (!empty($subject)) {
            $subject_filter = trim(sanitize_text_field($subject));
            
            // 세부과목 미선택 시: Subjects 맵을 사용해 상위 과목의 모든 세부과목을 OR로 포함
            $subs_to_include = array();
            if (!empty($session)) {
                // 지정된 교시에서 해당 과목의 세부과목
                $subs_to_include = Subjects::get_subsubjects((int) $session, $subject_filter);
            } else {
                // 교시 미지정 시: 모든 교시를 훑어서 해당 과목의 세부과목을 합침
                $all_sessions = Subjects::get_sessions();
                foreach ($all_sessions as $sess) {
                    $subs = Subjects::get_subsubjects((int) $sess, $subject_filter);
                    if (!empty($subs)) {
                        $subs_to_include = array_merge($subs_to_include, $subs);
                    }
                }
                $subs_to_include = array_values(array_unique($subs_to_include));
            }
            
            if (!empty($subs_to_include)) {
                // 세부과목 목록을 정확/유사 매칭 OR 조건으로 생성
                $or_parts = array();
                $or_params = array();
                foreach ($subs_to_include as $sub_name) {
                    $or_parts[] = "(c.subject = %s OR c.subject LIKE %s)";
                    $or_params[] = $sub_name;
                    $or_params[] = '%' . $wpdb->esc_like($sub_name) . '%';
                }
                $where[] = $wpdb->prepare("(" . implode(" OR ", $or_parts) . ")", ...$or_params);
            } else {
                // 맵에서 과목을 찾지 못한 경우, 과거 동작을 유지: 상위 과목명으로 prefix LIKE
                $where[] = $wpdb->prepare("c.subject LIKE %s", $subject_filter . '%');
            }
        }
        
        // 교시 필터
        if (!empty($session)) {
            $session_val = absint($session);
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
        
        // 실전 모의고사: 전체 교시 풀이 시 과목별 비율 적용
        // 1교시: 물리치료 기초 60문항 + 물리치료 진단평가 45문항 = 105문항
        // 2교시: 물리치료 중재 65문항 + 의료관계법규 20문항 = 85문항
        if (!empty($session) && $full_session) {
            // 모의고사 생성 한도 체크 및 차감 (Permissions 클래스 사용)
            // Permissions::check_and_deduct_exam_count는 기본적으로 1일 1회 제한을 체크함
            if (class_exists('\PTG\Platform\Permissions')) {
                $check_result = \PTG\Platform\Permissions::check_and_deduct_exam_count($current_user_id, true);
                if (is_wp_error($check_result)) {
                    // 403 Forbidden + 메시지
                    return Rest::error($check_result->get_error_code(), $check_result->get_error_message(), array('status' => 403));
                }
            }

            $question_ids = self::get_session_questions_by_ratio($session, $where_clause, $join_clause);
            
            return Rest::success($question_ids);
        }
        
        // --- General Quiz Usage Limit Check (Server-side) ---
        // 일반 퀴즈 (모의고사가 아닌 경우)
        // Permissions 클래스나 Access Manager를 통해 하루 사용량을 체크해야 함
        // 현재 DB 스키마에 일반 퀴즈 카운트 전용 테이블이 명시적으로 없으므로,
        // User Meta를 사용하여 간단히 구현 (ptg_quiz_usage_YYYYMMDD)
        
        // 프리미엄 회원은 체크 건너뜀
        $is_premium = false;
        if (class_exists('\PTG\Platform\Permissions')) {
            $is_premium = \PTG\Platform\Permissions::can_access_feature('premium_content', $current_user_id);
        }

        if (!$is_premium && empty($session)) {
            $today = date('Ymd', current_time('timestamp')); // Asia/Seoul 기준 (WP 설정 따름)
            $meta_key = 'ptg_quiz_usage_' . $today;
            $current_usage = (int) get_user_meta($current_user_id, $meta_key, true);
            
            // 제한 설정 (상수 또는 기본값)
            // ptgates-quiz.php의 상수를 가져오거나 하드코딩 (플러그인 메인 파일 로드 순서 주의)
            // 여기서는 안전하게 하드코딩 또는 옵션 조회. 
            // 하지만 ptgates-quiz.php가 로드된 상태이므로 상수 사용 가능할 수도 있음.
            // 안전하게 기본값 사용: Basic 20, Trial 30
            $limit_quiz = 20;
            
            // Trial 체크
            if (class_exists('\PTG\Platform\Repo')) {
                $member = \PTG\Platform\Repo::find_one('ptgates_user_member', array('user_id' => $current_user_id));
                if ($member && $member['member_grade'] === 'trial') {
                    $limit_quiz = 30;
                }
            }

            // 요청한 문제 수 (limit 파라미터)
            $requested_amount = $limit;

            if ($current_usage + $requested_amount > $limit_quiz) {
                return Rest::error('usage_limit_exceeded', '하루 퀴즈 제한 횟수를 초과했습니다. 프리미엄으로 업그레이드하세요.', array('status' => 403));
            }

            // 사용량 증가 (여기서 증가시키면, 실제 문제 반환 전에 증가됨. 
            // 만약 뒤에서 에러나면? 하지만 트랜잭션 복잡하므로 여기서 증가)
            update_user_meta($current_user_id, $meta_key, $current_usage + $requested_amount);
        }
        // ----------------------------------------------------

        // LIMIT 및 OFFSET
        
        // LIMIT 및 OFFSET
        $limit_clause = '';
        if (empty($full_session)) {
            $limit_clause = $wpdb->prepare("LIMIT %d", $limit);
        }
        
        // 정렬: 복습 모드인 경우 오래된순, 전체 교시인 경우 고정 순서(문항 ID 오름차순), 그 외 무작위
        if ($needs_review) {
            $order_clause = "ORDER BY s.last_quiz_date ASC";
        } elseif (!empty($session) && $full_session) {
            $order_clause = "ORDER BY q.question_id ASC";
        } else {
            // Smart Random (로그인 사용자 대상)
            // 우선순위: 1. 오답(wrong), 2. 미학습(NULL), 3. 정답(correct), 4. 나머지 랜덤
            if ($current_user_id) {
                // JOIN 추가 (이미 $join_clause 변수가 있음, 여기에 추가)
                $join_clause .= " LEFT JOIN ptgates_user_states us ON q.question_id = us.question_id AND us.user_id = " . absint($current_user_id);
                
                $order_clause = "
                    ORDER BY 
                    CASE 
                        WHEN us.last_result = 'wrong' THEN 1 
                        WHEN us.last_result IS NULL THEN 2 
                        ELSE 3 
                    END ASC, 
                    RAND()
                ";
            } else {
                $order_clause = "ORDER BY RAND()";
            }
        }
        
        $query = "
            SELECT q.question_id
            FROM {$questions_table} q
            INNER JOIN {$categories_table} c ON q.question_id = c.question_id
            {$join_clause}
            WHERE {$where_clause}
            {$order_clause}
            {$limit_clause}
        ";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (empty($results)) {
            return Rest::success(array());
        }
        
        // question_id만 배열로 반환 (연속 퀴즈용)
        $question_ids = array_map(function($row) {
            return (int) $row['question_id'];
        }, $results);
        
        // 캐시 저장 (30분)
        // 랜덤 정렬인 경우 캐시 저장 안 함
        if (!$is_random) {
            wp_cache_set($cache_key, $question_ids, 'ptg_quiz', 1800);
        }
        
        // 사용량 증가
        if (class_exists('PTG_Access_Manager')) {
            \PTG_Access_Manager::increment_usage($module, $current_user_id, count($question_ids));
        }

        return Rest::success($question_ids);
    }
    
    /**
     * 교시별 과목 비율에 따른 문제 목록 조회 (실전 모의고사용)
     * 
     * 1교시: 물리치료 기초 60문항 + 물리치료 진단평가 45문항 = 105문항
     * 2교시: 물리치료 중재 65문항 + 의료관계법규 20문항 = 85문항
     * 
     * @param int $session 교시 (1 또는 2)
     * @param string $where_clause WHERE 절 조건
     * @param string $join_clause JOIN 절
     * @return array question_id 배열
     */
    private static function get_session_questions_by_ratio($session, $where_clause, $join_clause) {
        global $wpdb;
        
        $questions_table = 'ptgates_questions';
        $categories_table = 'ptgates_categories';
        
        $question_ids = array();
        
        if ($session == 1) {
            // 1교시: 물리치료 기초 60문항 + 물리치료 진단평가 45문항
            $subjects = array(
                '물리치료 기초' => 60,
                '물리치료 진단평가' => 45
            );
        } else if ($session == 2) {
            // 2교시: 물리치료 중재 65문항 + 의료관계법규 20문항
            $subjects = array(
                '물리치료 중재' => 65,
                '의료관계법규' => 20
            );
        } else {
            // 잘못된 교시
            return array();
        }
        
        
        // 각 과목별로 문제 가져오기
        foreach ($subjects as $subject_name => $limit_count) {
            $subject_where = $where_clause . ' AND ' . $wpdb->prepare("c.subject LIKE %s", $subject_name . '%');
            
            $query = "
                SELECT q.question_id
                FROM {$questions_table} q
                INNER JOIN {$categories_table} c ON q.question_id = c.question_id
                {$join_clause}
                WHERE {$subject_where}
                ORDER BY RAND()
                LIMIT %d
            ";
            
            $query = $wpdb->prepare($query, $limit_count);
            $results = $wpdb->get_results($query, ARRAY_A);
            
            if (!empty($results)) {
                foreach ($results as $row) {
                    $question_ids[] = (int) $row['question_id'];
                }
            }
        }
        
        // 문제 ID 배열을 섞어서 무작위 순서로 반환
        shuffle($question_ids);
        
        return $question_ids;
    }
    
    /**
     * 문제 조회
     */
    public static function get_question($request) {
        global $wpdb;
        
        $question_id = absint($request->get_param('question_id'));
        
        // question_id가 0이면 JavaScript에서 기본값으로 처리하도록 함
        // API에서는 에러를 반환하지 않고 조용히 무시 (JavaScript에서 처리)
        // if ($question_id === 0) {
        //     return Rest::error('invalid_question_id', '문제 ID가 지정되지 않았습니다. 필터 조건을 사용하거나 유효한 문제 ID를 지정해 주세요.', 400);
        // }
        
        
        // 먼저 문제가 존재하는지 확인 (카테고리 정보 없이)
        // 레거시 테이블은 prefix 없이 직접 사용
        $questions_table = 'ptgates_questions';
        $question_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$questions_table} WHERE question_id = %d",
            $question_id
        ));
        
        if (!$question_exists) {
            return Rest::not_found('문제를 찾을 수 없습니다. (ID: ' . $question_id . ')');
        }
        
        // 캐시 키 생성
        $cache_key = 'ptg_quiz_question_' . $question_id;
        
        // 캐시 확인 (1시간 유효)
        $cached = wp_cache_get($cache_key, 'ptg_quiz');
        if ($cached !== false) {
            return Rest::success($cached);
        }
        
        // 카테고리와 조인하여 문제 조회
        $questions = LegacyRepo::get_questions_with_categories(array(
            'question_id' => $question_id,
            'limit' => 1
        ));
        
        if (empty($questions)) {
            // 문제는 존재하지만 카테고리 정보가 없거나 비활성화된 경우
            $is_active = $wpdb->get_var($wpdb->prepare(
                "SELECT is_active FROM {$questions_table} WHERE question_id = %d",
                $question_id
            ));
            
            // 레거시 테이블은 prefix 없이 직접 사용
            $categories_table = 'ptgates_categories';
            $category_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$categories_table} WHERE question_id = %d",
                $question_id
            ));
            
            $reason = '';
            if (!$is_active) {
                $reason = '문제가 비활성화되어 있습니다.';
            } elseif (!$category_exists) {
                $reason = '문제의 카테고리 정보가 없습니다.';
            } else {
                $reason = '알 수 없는 이유로 문제를 불러올 수 없습니다.';
            }
            
            return Rest::not_found('문제를 찾을 수 없습니다. ' . $reason);
        }
        
        $question = $questions[0];
        
        // 문제 본문에서 선택지 파싱 (ptgates-engine 스타일)
        $content = $question['content'];
        // _x000D_ 제거 및 줄바꿈 정규화
        $content = str_replace('_x000D_', '', $content);
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
        // 연속된 줄바꿈을 하나로 정리
        $content = preg_replace('/\n{2,}/', "\n", $content);
        
        $options = array();
        $question_text = $content;
        
        // 원형 숫자(①~⑳) 또는 괄호 숫자로 시작하는 선택지 추출
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
                // 연속된 줄바꿈을 공백으로 정리 (선택지는 한 줄로 표시)
                $option_text = preg_replace('/\n{2,}/', ' ', $option_text); // 연속된 줄바꿈을 공백으로
                $option_text = preg_replace('/\n/', ' ', $option_text); // 단일 줄바꿈도 공백으로
                
                if (!empty($option_text)) {
                    $options[] = $option_text;
                    $option_ranges[] = array('start' => $start_pos, 'end' => $end_pos);
                }
            }
            
            // 문제 본문에서 옵션 부분 제거
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
            }
        }
        
        // 해설도 정리
        $explanation = $question['explanation'];
        if (!empty($explanation)) {
            $explanation = str_replace('_x000D_', '', $explanation);
            $explanation = str_replace("\r\n", "\n", $explanation);
            $explanation = str_replace("\r", "\n", $explanation);
            // 연속된 줄바꿈을 하나로 정리
            $explanation = preg_replace('/\n{2,}/', "\n", $explanation);
        }
        
        $response_data = array(
            'question_id' => (int) $question['question_id'],
            'content' => $question_text, // 파싱된 지문만 반환
            'question_text' => $question_text, // 호환성을 위해 추가
            'options' => $options, // 파싱된 선택지 배열
            'answer' => $question['answer'],
            'explanation' => $explanation,
            'type' => $question['type'],
            'difficulty' => (int) $question['difficulty'],
            'exam_year' => (int) $question['exam_year'],
            'exam_session' => $question['exam_session'] ? (int) $question['exam_session'] : null,
            'exam_course' => $question['exam_course'],
            'subject' => $question['subject'],
            'source_company' => $question['source_company'],
        );
        
        // 캐시 저장 (1시간)
        wp_cache_set($cache_key, $response_data, 'ptg_quiz', 3600);
        
        return Rest::success($response_data);
    }
    
    /**
     * 문제 상태 조회
     */
    public static function get_question_state($request) {
        global $wpdb;
        // DB 오류가 응답에 섞이지 않도록 억제
        $wpdb->suppress_errors(true);

        $user_id = get_current_user_id();
        
        // 비로그인 사용자는 기본 상태 반환
        if (!$user_id) {
            return Rest::success(array(
                'bookmarked' => false,
                'needs_review' => false,
                'last_result' => null,
                'last_answer' => null
            ));
        }
        
        $question_id = absint($request->get_param('question_id'));

        global $wpdb;
        // 테이블 보장 (성능을 위해 매 요청마다 확인하지 않음 - 활성화 시 생성됨)
        $states_table = 'ptgates_user_states';

        // 안전한 조회
        $state = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$states_table}` WHERE `user_id` = %d AND `question_id` = %d LIMIT 1",
                $user_id,
                $question_id
            ),
            ARRAY_A
        );

        if (!$state) {
            // 기본 상태 반환
            return Rest::success(array(
                'bookmarked' => false,
                'needs_review' => false,
                'last_result' => null,
                'last_answer' => null
            ));
        }
        
        return Rest::success(array(
            'bookmarked' => (bool) $state['bookmarked'],
            'needs_review' => (bool) $state['needs_review'],
            'last_result' => $state['last_result'],
            'last_answer' => $state['last_answer']
        ));
    }
    
    /**
     * 문제 상태 업데이트
     */
    public static function update_question_state($request) {
        global $wpdb;
        // DB 오류가 응답에 섞이지 않도록 억제
        $wpdb->suppress_errors(true);

        $user_id = Permissions::get_user_id_or_error();
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $question_id = absint($request->get_param('question_id'));
        
        // 문제 존재 확인
        $question = LegacyRepo::get_questions_with_categories(array(
            'question_id' => $question_id,
            'limit' => 1
        ));
        
        if (empty($question)) {
            return Rest::not_found('문제를 찾을 수 없습니다.');
        }
        
        // 기존 상태 조회 또는 생성
        global $wpdb;
        $states_table = 'ptgates_user_states';
        
        // 테이블 존재 확인 및 자동 생성 (안전장치)
        $existing_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $states_table));
        if ($existing_table !== $states_table) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS `{$states_table}` (
                `user_id` bigint(20) unsigned NOT NULL,
                `question_id` bigint(20) unsigned NOT NULL,
                `bookmarked` tinyint(1) NOT NULL DEFAULT 0,
                `needs_review` tinyint(1) NOT NULL DEFAULT 0,
                `study_count` int(11) unsigned NOT NULL DEFAULT 0,
                `quiz_count` int(11) unsigned NOT NULL DEFAULT 0,
                `last_result` enum('correct','wrong') DEFAULT NULL,
                `last_answer` varchar(255) DEFAULT NULL,
                `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                `last_study_date` datetime DEFAULT NULL,
                `last_quiz_date` datetime DEFAULT NULL,
                PRIMARY KEY (`user_id`,`question_id`),
                KEY `idx_flags` (`bookmarked`,`needs_review`)
            ) {$charset_collate};";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        $existing_state = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$states_table}` WHERE `user_id` = %d AND `question_id` = %d LIMIT 1",
                $user_id,
                $question_id
            ),
            ARRAY_A
        );
        
        $update_data = array();
        
        if ($request->has_param('bookmarked')) {
            $update_data['bookmarked'] = $request->get_param('bookmarked') ? 1 : 0;
        }
        
        if ($request->has_param('needs_review')) {
            $update_data['needs_review'] = $request->get_param('needs_review') ? 1 : 0;
        }
        
        if ($request->has_param('last_answer')) {
            $update_data['last_answer'] = $request->get_param('last_answer');
        }
        
        if ($existing_state) {
            $wpdb->update(
                $states_table,
                $update_data,
                array('user_id' => $user_id, 'question_id' => $question_id)
            );
        } else {
            $insert_data = array_merge(
                array('user_id' => $user_id, 'question_id' => $question_id),
                $update_data
            );
            $wpdb->insert($states_table, $insert_data);
        }
        
        return Rest::success(array(
            'question_id' => $question_id,
            'updated' => array_keys($update_data)
        ), '상태가 업데이트되었습니다.');
    }
    
    /**
     * 문제 풀이 시도
     */
    public static function attempt_question($request) {
        global $wpdb;
        // DB 오류 HTML 출력 방지
        $wpdb->suppress_errors(true);

        $user_id = get_current_user_id();
        
        $question_id = absint($request->get_param('question_id'));
        $user_answer = $request->get_param('answer');
        $elapsed = absint($request->get_param('elapsed'));
        $skip_count_update = $request->get_param('skip_count_update') === true || $request->get_param('skip_count_update') === 'true';

        // 상태 테이블 보장
        $states_table = 'ptgates_user_states';
        $existing = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $states_table));
        if ($existing !== $states_table) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS `{$states_table}` (
                `user_id` bigint(20) unsigned NOT NULL,
                `question_id` bigint(20) unsigned NOT NULL,
                `bookmarked` tinyint(1) NOT NULL DEFAULT 0,
                `needs_review` tinyint(1) NOT NULL DEFAULT 0,
                `study_count` int(11) unsigned NOT NULL DEFAULT 0,
                `quiz_count` int(11) unsigned NOT NULL DEFAULT 0,
                `last_result` enum('correct','wrong') DEFAULT NULL,
                `last_answer` varchar(255) DEFAULT NULL,
                `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                `last_study_date` datetime DEFAULT NULL,
                `last_quiz_date` datetime DEFAULT NULL,
                PRIMARY KEY (`user_id`,`question_id`),
                KEY `idx_flags` (`bookmarked`,`needs_review`)
            ) {$charset_collate};";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        // 문제 정보 조회
        $questions = LegacyRepo::get_questions_with_categories(array(
            'question_id' => $question_id,
            'limit' => 1
        ));
        
        if (empty($questions)) {
            return Rest::not_found('문제를 찾을 수 없습니다.');
        }
        
        $question = $questions[0];
        $correct_answer = $question['answer'];
        
        // 정답 여부 확인
        $is_correct = (trim($user_answer) === trim($correct_answer));
        
        // 비로그인 사용자는 결과만 반환하고 종료
        if (!$user_id) {
            return Rest::success(array(
                'is_correct' => $is_correct,
                'result_id' => 0,
                'correct_answer' => $correct_answer
            ), '풀이가 완료되었습니다.');
        }

        // 결과 저장
        $result_id = LegacyRepo::save_user_result($user_id, array(
            'question_id' => $question_id,
            'user_answer' => $user_answer,
            'is_correct' => $is_correct,
            'elapsed_time' => $elapsed
        ));
        
        if (!$result_id) {
            return Rest::server_error('결과 저장에 실패했습니다.');
        }
        
        // 사용자 상태 업데이트
        global $wpdb;
        $states_table = 'ptgates_user_states';
        $existing_state = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$states_table}` WHERE `user_id` = %d AND `question_id` = %d LIMIT 1",
                $user_id,
                $question_id
            ),
            ARRAY_A
        );
        
        $current_time_utc = current_time('mysql', true);
        
        // skip_count_update가 true이면 quiz_count를 증가시키지 않음
        if ($skip_count_update) {
            // quiz_count는 업데이트하지 않고, last_result와 last_answer만 업데이트
            $state_update = array(
                'last_result'    => $is_correct ? 'correct' : 'wrong',
                'last_answer'    => $user_answer,
                'updated_at'     => $current_time_utc,
            );
            
            if ($existing_state) {
                // skip_count_update가 true면 quiz_count는 업데이트하지 않음
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE `{$states_table}` 
                        SET `last_result` = %s, 
                            `last_answer` = %s 
                        WHERE `user_id` = %d AND `question_id` = %d",
                        $is_correct ? 'correct' : 'wrong',
                        $user_answer,
                        $user_id,
                        $question_id
                    )
                );
            } else {
                // 레코드가 없으면 새로 생성 (quiz_count는 0으로 시작)
                $state_insert = array_merge(
                    array(
                        'user_id'        => $user_id,
                        'question_id'    => $question_id,
                        'bookmarked'     => 0,
                        'needs_review'   => 0,
                        'study_count'    => 0,
                        'last_study_date'=> null,
                        'quiz_count'     => 0,
                        'last_quiz_date' => null,
                        'updated_at'     => $current_time_utc,
                    ),
                    $state_update
                );
                $wpdb->insert(
                    $states_table, 
                    $state_insert,
                    array(
                        '%d', // user_id
                        '%d', // question_id
                        '%d', // bookmarked
                        '%d', // needs_review
                        '%d', // study_count
                        '%s', // last_study_date
                        '%d', // quiz_count
                        '%s', // last_quiz_date
                        '%s', // updated_at
                        '%s', // last_result
                        '%s', // last_answer
                    )
                );
            }
        } else {
            // quiz_count를 증가시킴 (기존 로직)
            $next_quiz_count  = $existing_state ? ( (int) $existing_state['quiz_count'] + 1 ) : 1;

            // 트리거가 자동으로 last_quiz_date를 업데이트하므로 배열에서 제외
            $state_update = array(
                'last_result'    => $is_correct ? 'correct' : 'wrong',
                'last_answer'    => $user_answer,
                'quiz_count'     => $next_quiz_count,
                // last_quiz_date는 트리거가 자동으로 설정하므로 제외
                // updated_at도 ON UPDATE current_timestamp()로 자동 갱신되므로 제외
            );

            if ($existing_state) {
                // 트리거가 자동으로 last_quiz_date를 업데이트하므로 quiz_count만 업데이트
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE `{$states_table}` 
                        SET `last_result` = %s, 
                            `last_answer` = %s, 
                            `quiz_count` = %d 
                        WHERE `user_id` = %d AND `question_id` = %d",
                        $is_correct ? 'correct' : 'wrong',
                        $user_answer,
                        $next_quiz_count,
                        $user_id,
                        $question_id
                    )
                );
            } else {
                // INSERT 시 트리거가 자동으로 last_quiz_date를 설정하므로 명시적으로 설정하지 않음
                $state_insert = array_merge(
                    array(
                        'user_id'        => $user_id,
                        'question_id'    => $question_id,
                        'bookmarked'     => 0,
                        'needs_review'   => 0,
                        'study_count'    => 0,
                        'last_study_date'=> null,
                        // updated_at과 last_quiz_date는 INSERT 트리거가 자동으로 설정
                    ),
                    $state_update
                );

                $wpdb->insert(
                    $states_table, 
                    $state_insert,
                    array(
                        '%d', // user_id
                        '%d', // question_id
                        '%d', // bookmarked
                        '%d', // needs_review
                        '%d', // study_count
                        '%s', // last_study_date
                        '%s', // updated_at
                        '%s', // last_result
                        '%s', // last_answer
                        '%d', // quiz_count
                        '%s', // last_quiz_date
                    )
                );
            }
        }
        
        return Rest::success(array(
            'is_correct' => $is_correct,
            'result_id' => $result_id,
            'correct_answer' => $correct_answer
        ), '풀이가 완료되었습니다.');
    }
    
    /**
     * 드로잉 목록 조회
     */
    public static function get_drawings($request) {
        $user_id = Permissions::get_user_id_or_error();
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $question_id = absint($request->get_param('question_id'));
        
        try {
            // 테이블 존재 확인 및 자동 생성 (안전장치)
            global $wpdb;
            $table_name = 'ptgates_user_drawings';
            $existing_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
            
            if ($existing_table !== $table_name) {
                // 테이블이 없으면 즉시 생성
                require_once(ABSPATH . 'wp-content/plugins/0000-ptgates-platform/includes/class-migration.php');
                \PTG\Platform\Migration::run_migrations();
                
                // 재확인
                $existing_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
                if ($existing_table !== $table_name) {
                    // 테이블 생성 실패 시 빈 배열 반환
                    return Rest::success(array());
                }
            }
            
            // 임시: Repo::find 대신 직접 쿼리 사용 (디버깅 목적)
            global $wpdb;
            $table_name = 'ptgates_user_drawings';
            
            // 답안 제출 여부 확인 (해설이 있으면 제출된 것으로 간주)
            $is_answered = absint($request->get_param('is_answered'));
            if ($is_answered === 0) {
                // 답안 미제출 시에도 파라미터가 없으면 기본값 0 사용
                $is_answered = 0;
            }
            
            // 기기 타입 확인 (pc, tablet, mobile)
            $device_type = sanitize_text_field($request->get_param('device_type'));
            if (!in_array($device_type, array('pc', 'tablet', 'mobile'))) {
                // 기본값: pc
                $device_type = 'pc';
            }
            
            // 하나의 퀴즈에 대한 한 유저의 답안 제출 전/후 드로잉은 각 기기 타입별로 각각 1개만 존재해야 함
            // 현재 상태와 기기 타입에 맞는 드로잉만 가져오기 (PC, Tablet, Mobile 각각 답안 제출 전/후 2개씩, 총 6개)
            $sql = $wpdb->prepare(
                "SELECT * FROM `{$table_name}` WHERE `user_id` = %d AND `question_id` = %d AND `is_answered` = %d AND `device_type` = %s ORDER BY `drawing_id` DESC LIMIT 1",
                $user_id,
                $question_id,
                $is_answered,
                $device_type
            );
            $drawings = $wpdb->get_results($sql, ARRAY_A);
            
            return Rest::success($drawings ? $drawings : array());
        } catch (\Exception $e) {
            // SQL 에러 발생 시 빈 배열 반환 (에러 로그는 실제 에러 발생 시에만 남김)
            return Rest::success(array()); // 빈 배열 반환 (에러 대신)
        }
    }
    
    /**
     * 드로잉 저장
     */
    public static function save_drawing($request) {
        $user_id = Permissions::get_user_id_or_error();
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $question_id = absint($request->get_param('question_id'));
        
        // 문제 존재 확인
        $question = LegacyRepo::get_questions_with_categories(array(
            'question_id' => $question_id,
            'limit' => 1
        ));
        
        if (empty($question)) {
            return Rest::not_found('문제를 찾을 수 없습니다.');
        }
        
        try {
            // 테이블 존재 확인 및 자동 생성 (안전장치)
            global $wpdb;
            $table_name = 'ptgates_user_drawings';
            $existing_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
            
            if ($existing_table !== $table_name) {
                // 테이블이 없으면 즉시 생성
                require_once(ABSPATH . 'wp-content/plugins/0000-ptgates-platform/includes/class-migration.php');
                \PTG\Platform\Migration::run_migrations();
                
                // 재확인
                $existing_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
                if ($existing_table !== $table_name) {
                    return Rest::error('데이터베이스 테이블을 생성할 수 없습니다. 플러그인을 재활성화해 주세요.', 500);
                }
            }
            
            // 요청 파라미터 검증 및 정리
            $format = sanitize_text_field($request->get_param('format'));
            if (!in_array($format, array('json', 'svg'))) {
                $format = 'json'; // 기본값
            }
            
            $data = $request->get_param('data');
            if (empty($data)) {
                return Rest::error('validation_error', '드로잉 데이터가 없습니다.', 400);
            }
            
            // JSON 문자열인 경우 그대로 사용, 배열인 경우 JSON으로 변환
            if (is_array($data)) {
                $data = json_encode($data);
            }
            
            $width = absint($request->get_param('width'));
            $height = absint($request->get_param('height'));
            
            $device = $request->get_param('device');
            if (is_array($device)) {
                $device = json_encode($device);
            } elseif (empty($device)) {
                $device = null;
            } else {
                // 문자열인 경우 100자로 제한 (데이터베이스 제약 조건)
                $device = sanitize_text_field($device);
                if (strlen($device) > 100) {
                    $device = substr($device, 0, 97) . '...'; // 100자로 제한
                }
            }
            
            // 빈 드로잉인지 확인 (빈 문자열이거나 empty 플래그가 있는 경우)
            $is_empty = false;
            if (is_string($data)) {
                $data_obj = json_decode($data, true);
                if ($data_obj && (isset($data_obj['empty']) && $data_obj['empty'] === true) || (empty($data_obj['data']))) {
                    $is_empty = true;
                }
            }
            
            // 답안 제출 여부 확인 (빈 드로잉 삭제 시에도 필요)
            $is_answered = $request->get_param('is_answered');
            if ($is_answered === null) {
                $is_answered = false;
            }
            $is_answered = (bool) $is_answered ? 1 : 0;
            
            // 기기 타입 확인 (pc, tablet, mobile)
            $device_type = sanitize_text_field($request->get_param('device_type'));
            if (!in_array($device_type, array('pc', 'tablet', 'mobile'))) {
                // 기본값: pc
                $device_type = 'pc';
            }
            
            // 빈 드로잉인 경우 해당 퀴즈의 해당 유저의 해당 상태와 기기 타입의 드로잉만 삭제
            if ($is_empty) {
                // 해당 user_id, question_id, is_answered, device_type에 해당하는 드로잉 레코드만 삭제
                $result = Repo::delete('ptgates_user_drawings', array(
                    'user_id' => $user_id,
                    'question_id' => $question_id,
                    'is_answered' => $is_answered,
                    'device_type' => $device_type
                ));
                
                if ($result === false) {
                    throw new \Exception('드로잉 삭제 실패');
                }
                
                // 삭제 성공 (로그 제거)
                return Rest::success(array(
                    'drawing_id' => null,
                    'deleted' => true,
                    'deleted_count' => $result
                ), '드로잉이 삭제되었습니다.');
            }
            
            // 빈 드로잉이 아닌 경우: 답안 제출 여부와 기기 타입은 이미 위에서 확인됨
            // 하나의 퀴즈에 대한 한 유저의 답안 제출 전/후 드로잉은 각 기기 타입별로 각각 1개만 존재해야 함
            // 속도 개선: 기존 레코드가 있으면 업데이트, 없으면 삽입 (삭제-삽입보다 빠름)
            // PC, Tablet, Mobile 각각 답안 제출 전/후 2개씩, 총 6개
            $existing_drawing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$table_name}` WHERE `user_id` = %d AND `question_id` = %d AND `is_answered` = %d AND `device_type` = %s ORDER BY `drawing_id` DESC LIMIT 1",
                    $user_id,
                    $question_id,
                    $is_answered,
                    $device_type
                ),
                ARRAY_A
            );
            
            $drawing_data = array(
                'user_id' => $user_id,
                'question_id' => $question_id,
                'is_answered' => $is_answered,
                'device_type' => $device_type, // 기기 타입 (pc, tablet, mobile)
                'format' => $format,
                'data' => $data,
                'width' => $width > 0 ? $width : null,
                'height' => $height > 0 ? $height : null,
                'device' => $device,
            );
            
            if ($existing_drawing) {
                // 기존 레코드 업데이트 (속도 개선: 삭제-삽입 대신 업데이트)
                $result = Repo::update('ptgates_user_drawings', $drawing_data, array(
                    'drawing_id' => $existing_drawing['drawing_id']
                ));
                if ($result === false) {
                    throw new \Exception('드로잉 업데이트 실패');
                }
                $drawing_id = $existing_drawing['drawing_id'];
                
                // 업데이트 성공 (로그 제거)
            } else {
                // 새 드로잉 데이터 삽입
                $drawing_id = Repo::insert('ptgates_user_drawings', $drawing_data);
                if ($drawing_id === false) {
                    $error_msg = '드로잉 저장 실패';
                    if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                        $error_msg .= ': ' . $wpdb->last_error;
                        error_log('[PTG Quiz] SQL 오류: ' . $wpdb->last_error);
                        error_log('[PTG Quiz] SQL 쿼리: ' . $wpdb->last_query);
                    }
                    throw new \Exception($error_msg);
                }
                
                // 저장 성공 (로그 제거)
            }
            
            return Rest::success(array(
                'drawing_id' => $drawing_id
            ), '드로잉이 저장되었습니다.');
        } catch (\Exception $e) {
            // SQL 에러 발생 시 상세 에러 로그
            global $wpdb;
            $error_message = $e->getMessage();
            error_log('[PTG Quiz] 드로잉 저장 오류: ' . $error_message);
            if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                error_log('[PTG Quiz] SQL 오류: ' . $wpdb->last_error);
                error_log('[PTG Quiz] SQL 쿼리: ' . $wpdb->last_query);
                $error_message = $wpdb->last_error . ' (' . $error_message . ')';
            }
            return Rest::error('save_error', '드로잉 저장에 실패했습니다: ' . $error_message, 500);
        } catch (\Error $e) {
            // PHP 7+ Fatal Error 처리
            global $wpdb;
            $error_message = $e->getMessage();
            error_log('[PTG Quiz] 드로잉 저장 치명적 오류: ' . $error_message);
            if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                error_log('[PTG Quiz] SQL 오류: ' . $wpdb->last_error);
                $error_message = $wpdb->last_error;
            }
            return Rest::error('fatal_error', '드로잉 저장 중 오류가 발생했습니다: ' . $error_message, 500);
        }
    }
    
    /**
     * 드로잉 삭제
     */
    public static function delete_drawing($request) {
        $user_id = Permissions::get_user_id_or_error();
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $question_id = absint($request->get_param('question_id'));
        
        try {
            // 테이블 존재 확인
            global $wpdb;
            $table_name = 'ptgates_user_drawings';
            $existing_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
            
            if ($existing_table !== $table_name) {
                return Rest::success(array('deleted' => false), '삭제할 드로잉이 없습니다.');
            }
            
            // 해당 user_id와 question_id에 해당하는 모든 드로잉 레코드 삭제
            $result = Repo::delete('ptgates_user_drawings', array(
                'user_id' => $user_id,
                'question_id' => $question_id
            ));
            
            if ($result === false) {
                throw new \Exception('드로잉 삭제 실패');
            }
            
            
            return Rest::success(array(
                'deleted' => true,
                'deleted_count' => $result
            ), $result > 0 ? '드로잉이 삭제되었습니다.' : '삭제할 드로잉이 없습니다.');
        } catch (\Exception $e) {
            error_log('[PTG Quiz] 드로잉 삭제 오류: ' . $e->getMessage());
            return Rest::error('delete_error', '드로잉 삭제에 실패했습니다: ' . $e->getMessage(), 500);
        }
        }
        /**
     * 메모 조회
     */
    public static function get_memo($request) {
        global $wpdb;
        $user_id = Permissions::get_user_id_or_error();
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $question_id = absint($request->get_param('question_id'));
        $table_name = 'ptgates_user_memos';
        
        // 테이블 확인
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return Rest::success(['content' => '']);
        }

        $memo = $wpdb->get_var($wpdb->prepare(
            "SELECT content FROM $table_name WHERE user_id = %d AND question_id = %d",
            $user_id, $question_id
        ));

        return Rest::success(['content' => $memo ? $memo : '']);
    }

    /**
     * 메모 저장
     */
    public static function update_memo($request) {
        global $wpdb;
        $user_id = Permissions::get_user_id_or_error();
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $question_id = absint($request->get_param('question_id'));
        $content = $request->get_param('content');
        $table_name = 'ptgates_user_memos';

        // 테이블 생성
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                question_id bigint(20) NOT NULL,
                content longtext NOT NULL,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY user_question (user_id, question_id)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND question_id = %d",
            $user_id, $question_id
        ));

        if ($existing) {
            $wpdb->update(
                $table_name,
                ['content' => $content],
                ['id' => $existing]
            );
        } else {
            $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'question_id' => $question_id,
                    'content' => $content
                ]
            );
        }

        return Rest::success(['success' => true]);
    }
    
    /**
     * 해설 조회
     */
    public static function get_explanation($request) {
        $question_id = absint($request->get_param('question_id'));
        
        // 캐시 키 생성
        $cache_key = 'ptg_quiz_explanation_' . $question_id;
        
        // 캐시 확인 (1시간 유효)
        $cached = wp_cache_get($cache_key, 'ptg_quiz');
        if ($cached !== false) {
            return Rest::success($cached);
        }
        
        $questions = LegacyRepo::get_questions_with_categories(array(
            'question_id' => $question_id,
            'limit' => 1
        ));
        
        if (empty($questions)) {
            return Rest::not_found('문제를 찾을 수 없습니다.');
        }
        
        $question = $questions[0];

        // 과목명이 비어있으면 보강 조회
        $subject = isset($question['subject']) ? $question['subject'] : null;
        if (!$subject) {
            global $wpdb;
            $subject = $wpdb->get_var($wpdb->prepare('SELECT subject FROM ptgates_categories WHERE question_id = %d LIMIT 1', $question_id));
        }
        
        // 해설 정리
        $explanation = $question['explanation'];
        if (!empty($explanation)) {
            $explanation = str_replace('_x000D_', '', $explanation);
            $explanation = str_replace("\r\n", "\n", $explanation);
            $explanation = str_replace("\r", "\n", $explanation);
            // 연속된 줄바꿈을 하나로 정리
            $explanation = preg_replace('/\n{2,}/', "\n", $explanation);

            // 동영상 강의 접근 권한 확인
            $can_video = true;
            if (class_exists('\PTG\Platform\Permissions')) {
                $can_video = \PTG\Platform\Permissions::can_access_feature('video_lecture', get_current_user_id());
            }

            if (!$can_video) {
                // iframe (유튜브 등) 및 video 태그 제거
                $explanation = preg_replace('/<iframe.*?<\/iframe>/is', '', $explanation);
                $explanation = preg_replace('/<video.*?<\/video>/is', '', $explanation);
                // 동영상 링크 패턴 제거 (선택 사항)
                // $explanation = preg_replace('/https?:\/\/(www\.)?(youtube\.com|youtu\.be)\/[^\s]*/', '', $explanation);
                
                // 안내 메시지 추가
                $explanation .= "\n\n[안내] 동영상 강의는 Trial 또는 Premium 멤버십에서만 제공됩니다.";
            }
        }
        
        $response_data = array(
            'question_id' => $question_id,
            'explanation' => $explanation,
            'answer' => $question['answer'],
            'subject' => $subject ? $subject : null
        );
        
        // 캐시 저장 (1시간)
        wp_cache_set($cache_key, $response_data, 'ptg_quiz', 3600);
        
        return Rest::success($response_data);
    }
    
    /**
     * 교시별 과목 목록 조회
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_subjects($request) {
        $session = (int) $request->get_param('session');

        if ($session > 0) {
            $subjects = Subjects::get_subjects_for_session($session);
        } else {
            // 교시가 지정되지 않으면 모든 교시의 과목을 합쳐서 반환
            $subjects = [];
            foreach (Subjects::get_sessions() as $sess) {
                $subjects = array_merge($subjects, Subjects::get_subjects_for_session($sess));
            }
            $subjects = array_values(array_unique($subjects));
        }

        return Rest::success($subjects);
    }
    
    /**
     * 교시 목록 반환 (ptGates_subject의 course_no DISTINCT)
     */
    public static function get_sessions($request) {
        // 정적 설정 기반 교시 목록 반환
        return Rest::success(Subjects::get_sessions());
    }
    
    /**
     * 세부과목 목록 반환 (ptGates_subject의 subcategory DISTINCT)
     */
    public static function get_subsubjects($request) {
        $session = absint($request->get_param('session'));
        $subject = sanitize_text_field($request->get_param('subject'));
        if (!$session || empty($subject)) {
            return Rest::success(array());
        }

        $subs = Subjects::get_subsubjects($session, $subject);
        return Rest::success($subs);
    }
    /**
     * 통합 사용자 상태 조회 (북마크, 복습, 메모, 암기카드)
     */
    public static function get_user_status($request) {
        global $wpdb;
        
        $question_id = absint($request->get_param('question_id'));
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return Rest::error('unauthorized', '로그인이 필요합니다.', 401);
        }
        
        // 1. 북마크 및 복습 상태 (ptgates_user_states)
        // 2. 메모 상태 (ptgates_user_memos)
        // 3. 암기카드 상태 (ptgates_flashcards)
        
        // 각 테이블 이름
        $table_states = 'ptgates_user_states';
        $table_memos = 'ptgates_user_memos';
        $table_flashcards = 'ptgates_flashcards';
        
        // 단일 쿼리로 모든 상태 조회
        // 서브쿼리를 사용하여 각 상태의 존재 여부(COUNT > 0)를 1/0으로 반환
        $query = $wpdb->prepare("
            SELECT 
                (SELECT COUNT(*) FROM {$table_states} WHERE user_id = %d AND question_id = %d AND bookmarked = 1) as bookmarked,
                (SELECT COUNT(*) FROM {$table_states} WHERE user_id = %d AND question_id = %d AND needs_review = 1) as needs_review,
                (SELECT COUNT(*) FROM {$table_memos} WHERE user_id = %d AND question_id = %d) as has_memo,
                (SELECT COUNT(*) FROM {$table_flashcards} WHERE user_id = %d AND source_id = %d AND source_type = 'question') as has_flashcard
        ", $user_id, $question_id, $user_id, $question_id, $user_id, $question_id, $user_id, $question_id);
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        if (!$result) {
            // 쿼리 실패 시 기본값 반환
            return Rest::success(array(
                'bookmark' => false,
                'review' => false,
                'memo' => false,
                'flashcard' => false
            ));
        }
        
        return Rest::success(array(
            'bookmark' => (bool) $result['bookmarked'],
            'review' => (bool) $result['needs_review'],
            'memo' => (bool) $result['has_memo'],
            'flashcard' => (bool) $result['has_flashcard']
        ));
    }
}

