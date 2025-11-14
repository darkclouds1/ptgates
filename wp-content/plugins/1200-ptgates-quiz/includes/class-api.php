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

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
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
        // 디버깅: 라우트 등록 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Quiz] REST API 라우트 등록 시작: ' . self::NAMESPACE);
        }
        
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
            'permission_callback' => array(__CLASS__, 'check_permission'),
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
            'permission_callback' => array(__CLASS__, 'check_permission'),
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
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('json', 'svg'),
                ),
                'data' => array(
                    'required' => true,
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
                    'sanitize_callback' => 'sanitize_text_field',
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
        
        // 디버깅: 라우트 등록 완료 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Quiz] REST API 라우트 등록 완료: ' . self::NAMESPACE);
        }
    }
    
    /**
     * 권한 확인 콜백
     */
    public static function check_permission($request) {
        return Permissions::rest_check_user_and_nonce($request);
    }
    
    /**
     * 문제 조회
     */
    public static function get_question($request) {
        global $wpdb;
        
        $question_id = absint($request->get_param('question_id'));
        
        // 디버깅: 문제 ID 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Quiz] 문제 조회 요청: question_id=' . $question_id);
        }
        
        // 먼저 문제가 존재하는지 확인 (카테고리 정보 없이)
        // 레거시 테이블은 prefix 없이 직접 사용
        $questions_table = 'ptgates_questions';
        $question_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$questions_table} WHERE question_id = %d",
            $question_id
        ));
        
        if (!$question_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Quiz] 문제 ID ' . $question_id . '가 데이터베이스에 존재하지 않습니다.');
            }
            return Rest::not_found('문제를 찾을 수 없습니다. (ID: ' . $question_id . ')');
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
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Quiz] 문제 ID ' . $question_id . ' 조회 실패: ' . $reason);
            }
            
            return Rest::not_found('문제를 찾을 수 없습니다. ' . $reason);
        }
        
        $question = $questions[0];
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Quiz] 문제 ID ' . $question_id . ' 조회 성공');
        }
        
        // 문제 본문에서 선택지 파싱 (ptgates-engine 스타일)
        $content = $question['content'];
        $options = array();
        $question_text = $content;
        
        // 디버깅: 원본 콘텐츠 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Quiz API] 원본 콘텐츠: ' . substr($content, 0, 200));
        }
        
        // 원형 숫자(①~⑳) 또는 괄호 숫자로 시작하는 선택지 추출
        if (preg_match_all('/([①-⑳]|\([0-9]+\))\s*/u', $content, $number_matches, PREG_OFFSET_CAPTURE)) {
            $option_ranges = array();
            
            // 디버깅: 매칭된 원형 숫자 확인
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Quiz API] 원형 숫자 매칭 개수: ' . count($number_matches[0]));
            }
            
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
                
                // 디버깅: 각 선택지 확인
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Quiz API] 선택지 ' . ($idx + 1) . ': ' . substr($option_text, 0, 50));
                }
                
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
        } else {
            // 원형 숫자를 찾을 수 없는 경우
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Quiz API] 원형 숫자를 찾을 수 없음');
            }
        }
        
        // 디버깅: 최종 파싱 결과 확인
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Quiz API] 파싱 결과 - 지문: ' . substr($question_text, 0, 100));
            error_log('[PTG Quiz API] 파싱 결과 - 선택지 개수: ' . count($options));
            foreach ($options as $idx => $opt) {
                error_log('[PTG Quiz API] 선택지 ' . ($idx + 1) . ': ' . substr($opt, 0, 50));
            }
        }
        
        return Rest::success(array(
            'question_id' => (int) $question['question_id'],
            'content' => $question_text, // 파싱된 지문만 반환
            'question_text' => $question_text, // 호환성을 위해 추가
            'options' => $options, // 파싱된 선택지 배열
            'answer' => $question['answer'],
            'explanation' => $question['explanation'],
            'type' => $question['type'],
            'difficulty' => (int) $question['difficulty'],
            'exam_year' => (int) $question['exam_year'],
            'exam_session' => $question['exam_session'] ? (int) $question['exam_session'] : null,
            'exam_course' => $question['exam_course'],
            'subject' => $question['subject'],
            'source_company' => $question['source_company'],
        ));
    }
    
    /**
     * 문제 상태 조회
     */
    public static function get_question_state($request) {
        global $wpdb;
        // DB 오류가 응답에 섞이지 않도록 억제
        $wpdb->suppress_errors(true);

        $user_id = Permissions::get_user_id_or_error();
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $question_id = absint($request->get_param('question_id'));

        global $wpdb;
        // 테이블 보장
        $states_table = $wpdb->prefix . 'ptgates_user_states';
        $existing = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $states_table));
        if ($existing !== $states_table) {
            // 최소 스키마 생성 (외래키 없이)
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS `{$states_table}` (
                `user_id` bigint(20) unsigned NOT NULL,
                `question_id` bigint(20) unsigned NOT NULL,
                `bookmarked` tinyint(1) NOT NULL DEFAULT 0,
                `needs_review` tinyint(1) NOT NULL DEFAULT 0,
                `last_result` varchar(10) DEFAULT NULL,
                `last_answer` varchar(255) DEFAULT NULL,
                `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`user_id`,`question_id`),
                KEY `idx_flags` (`bookmarked`,`needs_review`)
            ) {$charset_collate};";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

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
        $states_table = $wpdb->prefix . 'ptgates_user_states';
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

        $user_id = Permissions::get_user_id_or_error();
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $question_id = absint($request->get_param('question_id'));
        $user_answer = $request->get_param('answer');
        $elapsed = absint($request->get_param('elapsed'));

        // 상태 테이블 보장
        $states_table = $wpdb->prefix . 'ptgates_user_states';
        $existing = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $states_table));
        if ($existing !== $states_table) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS `{$states_table}` (
                `user_id` bigint(20) unsigned NOT NULL,
                `question_id` bigint(20) unsigned NOT NULL,
                `bookmarked` tinyint(1) NOT NULL DEFAULT 0,
                `needs_review` tinyint(1) NOT NULL DEFAULT 0,
                `last_result` varchar(10) DEFAULT NULL,
                `last_answer` varchar(255) DEFAULT NULL,
                `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
        $states_table = $wpdb->prefix . 'ptgates_user_states';
        $existing_state = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$states_table}` WHERE `user_id` = %d AND `question_id` = %d LIMIT 1",
                $user_id,
                $question_id
            ),
            ARRAY_A
        );
        
        $state_data = array(
            'user_id' => $user_id,
            'question_id' => $question_id,
            'last_result' => $is_correct ? 'correct' : 'wrong',
            'last_answer' => $user_answer,
        );
        
        if ($existing_state) {
            $wpdb->update($states_table, $state_data, array(
                'user_id' => $user_id,
                'question_id' => $question_id
            ));
        } else {
            $wpdb->insert($states_table, $state_data);
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
        
        $drawings = Repo::find_by_user('ptgates_user_drawings', $user_id, array(
            'question_id' => $question_id
        ), array(
            'orderby' => 'created_at',
            'order' => 'DESC'
        ));
        
        return Rest::success($drawings);
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
        
        // 기존 드로잉 업데이트 또는 새로 생성
        $existing_drawing = Repo::find_one('ptgates_user_drawings', array(
            'user_id' => $user_id,
            'question_id' => $question_id
        ));
        
        $drawing_data = array(
            'user_id' => $user_id,
            'question_id' => $question_id,
            'format' => $request->get_param('format'),
            'data' => $request->get_param('data'),
            'width' => $request->get_param('width'),
            'height' => $request->get_param('height'),
            'device' => $request->get_param('device'),
        );
        
        if ($existing_drawing) {
            Repo::update('ptgates_user_drawings', $drawing_data, array(
                'drawing_id' => $existing_drawing['drawing_id']
            ));
            $drawing_id = $existing_drawing['drawing_id'];
        } else {
            $drawing_id = Repo::insert('ptgates_user_drawings', $drawing_data);
        }
        
        return Rest::success(array(
            'drawing_id' => $drawing_id
        ), '드로잉이 저장되었습니다.');
    }
    
    /**
     * 해설 조회
     */
    public static function get_explanation($request) {
        $question_id = absint($request->get_param('question_id'));
        
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
        
        return Rest::success(array(
            'question_id' => $question_id,
            'explanation' => $question['explanation'],
            'answer' => $question['answer'],
            'subject' => $subject ? $subject : null
        ));
    }
}

