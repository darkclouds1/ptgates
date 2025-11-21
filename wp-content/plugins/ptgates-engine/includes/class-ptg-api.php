<?php
/**
 * PTGates REST API 클래스
 * 
 * WordPress REST API 엔드포인트 등록 및 처리
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class PTG_API {
    
    /**
     * REST API 네임스페이스
     */
    const NAMESPACE = 'ptgates/v1';
    
    /**
     * REST API 라우트 등록
     */
    public static function register_routes() {
        // 문제 목록 조회
        register_rest_route(self::NAMESPACE, '/questions', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_questions'),
            'permission_callback' => '__return_true', // 공개 API
            'args' => array(
                'year' => array(
                    'description' => '시험 연도',
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'subject' => array(
                    'description' => '과목명',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'session' => array(
                    'description' => '교시 (1 또는 2)',
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'full_session' => array(
                    'description' => '해당 교시 전체 문항 반환 여부',
                    'type' => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'default' => false,
                ),
                'limit' => array(
                    'description' => '가져올 문제 개수',
                    'type' => 'integer',
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ),
                'offset' => array(
                    'description' => '오프셋',
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // 사용 가능한 연도 목록
        register_rest_route(self::NAMESPACE, '/years', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_years'),
            'permission_callback' => '__return_true',
            'args' => array(
                'nocache' => array(
                    'description' => '캐시 방지 파라미터',
                    'type' => 'string',
                    'default' => '',
                ),
            ),
        ));
        
        // 사용 가능한 과목 목록
        register_rest_route(self::NAMESPACE, '/subjects', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_subjects'),
            'permission_callback' => '__return_true',
            'args' => array(
                'year' => array(
                    'description' => '연도 필터',
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // 단일 문제 조회 (ID로)
        register_rest_route(self::NAMESPACE, '/question/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_question_by_id'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'description' => '문제 ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // 학습 로그 저장
        register_rest_route(self::NAMESPACE, '/log', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'save_log'),
            'permission_callback' => array(__CLASS__, 'check_log_permission'),
            'args' => array(
                'question_id' => array(
                    'description' => '문제 ID',
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
                'user_answer' => array(
                    'description' => '사용자 답안',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'is_correct' => array(
                    'description' => '정답 여부',
                    'type' => 'boolean',
                    'required' => true,
                ),
                'elapsed_time' => array(
                    'description' => '소요 시간 (초)',
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }
    
    /**
     * 문제 목록 조회 콜백
     * 
     * @param WP_REST_Request $request 요청 객체
     * @return WP_REST_Response|WP_Error
     */
    public static function get_questions($request) {
        try {
            $args = array(
                'year' => $request->get_param('year'),
                'subject' => $request->get_param('subject'),
                'session' => $request->get_param('session'),
                'full_session' => $request->get_param('full_session'),
                'limit' => $request->get_param('limit'),
                'offset' => $request->get_param('offset'),
            );
            
            // 빈 값 제거
            $args = array_filter($args, function($value) {
                return $value !== null && $value !== '';
            });
            
            $questions = PTG_DB::get_questions($args);
            
            return rest_ensure_response($questions);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PTG API Error (get_questions): ' . $e->getMessage());
                error_log($e->getTraceAsString());
            }
            return new WP_Error('server_error', '서버 오류가 발생했습니다.', array('status' => 500));
        }
    }
    
    /**
     * 사용 가능한 연도 목록 조회 콜백
     * 
     * @param WP_REST_Request $request 요청 객체
     * @return WP_REST_Response
     */
    public static function get_years($request) {
        try {
            $years = PTG_DB::get_available_years();
            
            // 디버깅: API 응답 확인 (개발 환경에서만)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PTGates API get_years response: ' . print_r($years, true));
            }
            
            $response = rest_ensure_response($years);
            
            // REST API 응답에 캐시 방지 헤더 추가
            $response->header('Cache-Control', 'no-cache, must-revalidate, max-age=0');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
            
            return $response;
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PTG API Error (get_years): ' . $e->getMessage());
                error_log($e->getTraceAsString());
            }
            return new WP_Error('server_error', '서버 오류가 발생했습니다.', array('status' => 500));
        }
    }
    
    /**
     * 사용 가능한 과목 목록 조회 콜백
     * 
     * @param WP_REST_Request $request 요청 객체
     * @return WP_REST_Response
     */
    public static function get_subjects($request) {
        try {
            $year = $request->get_param('year');
            $subjects = PTG_DB::get_available_subjects($year);
            return rest_ensure_response($subjects);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PTG API Error (get_subjects): ' . $e->getMessage());
                error_log($e->getTraceAsString());
            }
            return new WP_Error('server_error', '서버 오류가 발생했습니다.', array('status' => 500));
        }
    }
    
    /**
     * 단일 문제 조회 콜백 (ID로)
     * 
     * @param WP_REST_Request $request 요청 객체
     * @return WP_REST_Response|WP_Error
     */
    public static function get_question_by_id($request) {
        $question_id = $request->get_param('id');
        
        // 먼저 해당 ID의 문제 조회
        $question = PTG_DB::get_question_by_id($question_id);
        
        // 문제가 없거나 기출이 아닌 경우, 기출이 아닌 문제 중 랜덤으로 하나 선택
        if (!$question) {
            // 문제가 없으면 기출이 아닌 문제 중 랜덤으로 하나 선택
            $question = PTG_DB::get_random_non_exam_question();
        } else {
            // 문제가 있지만 기출이 아닌 경우 확인
            // 기출이 아닌 문제: exam_session >= 1000 또는 (exam_session IS NULL AND exam_year IS NULL)
            $session = isset($question['session']) ? intval($question['session']) : null;
            $year = isset($question['year']) ? intval($question['year']) : null;
            
            $is_non_exam = false;
            if ($session !== null && $session >= 1000) {
                $is_non_exam = true;
            } elseif ($session === null && $year === null) {
                $is_non_exam = true;
            }
            
            if ($is_non_exam) {
                // 기출이 아닌 문제이므로 랜덤으로 하나 선택
                $question = PTG_DB::get_random_non_exam_question();
            }
        }
        
        if (!$question) {
            return new WP_Error(
                'no_question_found',
                '문제를 찾을 수 없습니다.',
                array('status' => 404)
            );
        }
        
        return rest_ensure_response($question);
    }
    
    /**
     * 학습 로그 저장 콜백
     * 
     * @param WP_REST_Request $request 요청 객체
     * @return WP_REST_Response|WP_Error
     */
    public static function save_log($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_Error(
                'unauthorized',
                '로그인이 필요합니다.',
                array('status' => 401)
            );
        }
        
        $data = array(
            'question_id' => $request->get_param('question_id'),
            'user_answer' => $request->get_param('user_answer'),
            'is_correct' => $request->get_param('is_correct') ? 1 : 0,
            'elapsed_time' => $request->get_param('elapsed_time'),
        );
        
        $result = PTG_Logger::save_result($user_id, $data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'result_id' => $result
        ));
    }
    
    /**
     * 로그 저장 권한 확인
     * 
     * @return bool
     */
    public static function check_log_permission() {
        // 로그인한 사용자만 로그 저장 가능
        return is_user_logged_in();
    }
}
