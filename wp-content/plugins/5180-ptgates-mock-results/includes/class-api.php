<?php
namespace PTG\Mock\Results;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class API {

    public static function register_routes() {
        register_rest_route( 'ptg-mock/v1', '/submit', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'submit_result' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ] );

        register_rest_route( 'ptg-mock/v1', '/history', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_history' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ] );

        register_rest_route( 'ptg-mock/v1', '/result/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_result_detail' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ] );
    }

    /**
     * 모의고사 결과 제출 (POST)
     * 
     * Payload: {
     *   session_code: 1001,
     *   start_time: '...',
     *   end_time: '...',
     *   answers: [
     *     { question_id: 123, is_correct: true, user_answer: '1' },
     *     ...
     *   ],
     *   question_ids: [123, 124, 125] // 전체 문제 ID 목록 (미응시 문제 포함)
     * }
     */
    public static function submit_result( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        $session_code = isset($params['session_code']) ? intval($params['session_code']) : 0;
        
        // [FIX] Force KST Timezone
        try {
            $tz_kst = new \DateTimeZone('Asia/Seoul');
            
            if (isset($params['start_time'])) {
                $s_dt = new \DateTime($params['start_time']); // Input is ISO (UTC)
                $s_dt->setTimezone($tz_kst);
                $start_time = $s_dt->format('Y-m-d H:i:s');
            } else {
                $start_time = (new \DateTime('now', $tz_kst))->format('Y-m-d H:i:s');
            }

            if (isset($params['end_time'])) {
                $e_dt = new \DateTime($params['end_time']);
                $e_dt->setTimezone($tz_kst);
                $end_time = $e_dt->format('Y-m-d H:i:s');
            } else {
                $end_time = (new \DateTime('now', $tz_kst))->format('Y-m-d H:i:s');
            }
            
            $created_at_kst = (new \DateTime('now', $tz_kst))->format('Y-m-d H:i:s');
            
        } catch (\Exception $e) {
            // Fallback just in case
            $start_time = current_time('mysql');
            $end_time = current_time('mysql');
            $created_at_kst = current_time('mysql');
        }

        $answers = isset($params['answers']) ? $params['answers'] : []; // [ {question_id, is_correct}, ... ]
        
        // [FIX] Ensure user_answer is integer (1~5)
        foreach ($answers as &$ans) {
            if (isset($ans['user_answer'])) {
                $ans['user_answer'] = intval($ans['user_answer']);
            }
        }
        unset($ans);

        $all_question_ids = isset($params['question_ids']) ? $params['question_ids'] : [];

        if ( empty( $session_code ) || empty( $all_question_ids ) ) {
            return new \WP_Error( 'invalid_data', '필수 데이터가 누락되었습니다.', [ 'status' => 400 ] );
        }

        // 1. Fetch Question Metadata (Subjects) from DB
        $table_questions = 'ptgates_questions';
        $table_categories = 'ptgates_categories';
        
        // ID 목록이 너무 많으면 청크로 나눠서 조회해야 할 수도 있음 (일단 200문제 내외 가정)
        $ids_placeholder = implode(',', array_map('intval', $all_question_ids));
        
        // [FIX] Extract exam_course securely
        $exam_course = isset($params['exam_course']) ? sanitize_text_field($params['exam_course']) : '';

        // [FIX] ptgates_questions 테이블에는 subject가 없고 ptgates_categories에 있음.
        // 또한, 특정 회차/교시에 해당하는 과목만 가져와야 함 (문제가 여러 회차에 중복될 경우 오분류 방지)
        
        $sql = "
            SELECT q.question_id as id, c.subject as subject 
            FROM $table_questions q
            JOIN $table_categories c ON q.question_id = c.question_id
            WHERE q.question_id IN ($ids_placeholder)
        ";
        
        // session_code와 exam_course가 있으면 해당 회차/교시의 분류만 선택
        // (Mock Exam은 특정 회차/교시를 응시하는 것이므로 반드시 일치해야 함)
        if (!empty($session_code)) {
            $sql .= $wpdb->prepare(" AND c.exam_session = %d", $session_code);
        }
        if (!empty($exam_course)) {
            // [FIX] Enforce strict '1교시' format
            // If input is '1', convert to '1교시'. If already '1교시', keep it.
            if (is_numeric($exam_course)) {
                $exam_course = $exam_course . '교시';
            } else if (strpos($exam_course, '교시') === false) {
                 // If some other format, force append (or handle logic, but mostly numeric is expected)
                 $exam_course = $exam_course . '교시';
            }
            
            $sql .= $wpdb->prepare(" AND c.exam_course = %s", $exam_course);
        }
        
        $questions_meta = $wpdb->get_results( $sql );
        
        // [DEBUG LOGGING]
        $log_entry = "--- Submit Result Debug ---\n";
        $log_entry .= "Session: $session_code, Course: $exam_course\n";
        $log_entry .= "SQL: $sql\n";
        $log_entry .= "Count: " . count($questions_meta) . "\n";
        if (!empty($questions_meta)) {
            $log_entry .= "Sample (First): " . print_r($questions_meta[0], true) . "\n";
        }
        error_log($log_entry);
        // [END DEBUG]

        $subject_map = []; // id -> subject
        foreach($questions_meta as $q) {
            $subject_map[$q->id] = $q->subject;
        }

        // 2. Aggregate Results per Subject
        // answers 배열을 맵으로 변환 for faster lookup
        $answer_map = [];
        foreach($answers as $ans) {
            $qid = intval($ans['question_id']);
            $answer_map[$qid] = $ans; // { is_correct: bool ... }
        }

        $stats = []; // subject -> { total, correct }

        foreach($all_question_ids as $qid) {
            $qid = intval($qid);
            $subject = isset($subject_map[$qid]) ? $subject_map[$qid] : '미분류';
            
            if (!isset($stats[$subject])) {
                $stats[$subject] = ['total' => 0, 'correct' => 0];
            }
            
            $stats[$subject]['total']++;
            
            // 정답 여부 확인
            if (isset($answer_map[$qid]) && $answer_map[$qid]['is_correct'] == true) {
                $stats[$subject]['correct']++;
            }
        }

        // 3. Calculate Scores (WITHOUT Token Generation yet)
        // [FIX] Map Subject -> Category using ptgates_subject table
        $table_subject_map = 'ptgates_subject';
        $su_rows = $wpdb->get_results("SELECT category, subcategory FROM $table_subject_map");
        $sub_to_cat = [];
        if (!empty($su_rows)) {
            foreach($su_rows as $r) {
                if (!empty($r->subcategory)) {
                    $sub_to_cat[$r->subcategory] = $r->category;
                }
            }
        }

        $total_score_sum = 0;
        $subject_count = count($stats);
        $has_fail = false;
        
        $processed_results = [];

        foreach($stats as $subj => $data) {
            $total = $data['total'];
            $correct = $data['correct'];
            // 100점 만점 환산
            $score = ($total > 0) ? round(($correct / $total) * 100, 1) : 0;
            
            // Determine Category
            $category_name = isset($sub_to_cat[$subj]) ? $sub_to_cat[$subj] : $subj; // Default to self if not found

            $processed_results[] = [
                'category' => $category_name, // [NEW] Main Category
                'subject' => $subj,       // Detailed Subject
                'total' => $total,
                'correct' => $correct,
                'score' => $score,
                // 'review_token' => ... // [FIX] Token generation deferred until history_id exists
            ];

            $total_score_sum += $score; // This is sum of subject %
            if ($score < 40) {
                $has_fail = true;
            }
        }


        // 전체 평균 점수 (전체 정답 수 / 전체 문항 수)
        // 기존: $avg_score = ($subject_count > 0) ? round($total_score_sum / $subject_count, 1) : 0;
        // 변경: 전체 문항 대비 정답률
        $all_questions_count = count($all_question_ids);
        $all_correct_count = 0;
        foreach($processed_results as $pr) {
            $all_correct_count += $pr['correct'];
        }
        
        $avg_score = ($all_questions_count > 0) ? round(($all_correct_count / $all_questions_count) * 100, 1) : 0;

        // 합격 기준: 총점 60 이상, 과락(40점 미만) 없음
        $is_pass = ( $avg_score >= 60 && !$has_fail ) ? 1 : 0;


        // 4. Save to DB (History First)
        $table_history = 'ptgates_mock_history';
        $inserted = $wpdb->insert( $table_history, [
            'user_id' => $user_id,
            'session_code' => $session_code,
            'course_no' => $exam_course, // [FIX] Save as string (e.g. '1교시') not intval
            'exam_type' => 'mock',
            'start_time' => $start_time,
            'end_time' => $end_time,
            'total_score' => $avg_score,
            'is_pass' => $is_pass,
            'answers_json' => json_encode($answers, JSON_UNESCAPED_UNICODE),
            'created_at' => $created_at_kst
        ] );

        if ( ! $inserted ) {
            return new \WP_Error( 'db_error', '결과 저장에 실패했습니다.', [ 'status' => 500 ] );
        }

        $history_id = $wpdb->insert_id;

        // 5. Generate Tokens and Insert Details (Now that we have history_id)
        $table_results = 'ptgates_mock_results';
        
        // Use reference to update original array directly
        foreach ( $processed_results as &$res ) {
            // [FIX] Generate token now with valid history_id
            $res['review_token'] = self::generate_review_token($history_id, $res['subject'], $user_id);
            
            $is_fail = ($res['score'] < 40) ? 1 : 0;
            $wpdb->insert( $table_results, [
                'history_id' => $history_id,
                'subject_name' => $res['subject'],
                'score' => $res['score'],
                'question_count' => $res['total'],
                'correct_count' => $res['correct'],
                'is_fail' => $is_fail
            ] );
        }
        unset($res); // Break reference

        return [
            'success' => true,
            'history_id' => $history_id,
            'is_pass' => $is_pass,
            'score' => $avg_score,
            'subjects' => $processed_results // [NEW] Return subject breakdown (with tokens) for frontend briefing
        ];
    }

    /**
     * Generate secure token for review link
     */
    public static function generate_review_token($history_id, $subject, $user_id) {
        $payload = json_encode([
            'hid' => $history_id,
            'sub' => $subject,
            'uid' => $user_id,
            'exp' => time() + (86400 * 7) // Valid for 7 days
        ]);
        
        // [FIX] Use SHA-256 to ensure exactly 32 bytes binary key
        $key = hash('sha256', wp_salt('auth'), true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($payload, 'aes-256-cbc', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    public static function get_history( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = 'ptgates_mock_history';
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
            $user_id
        ) );

        return $results;
    }

    public static function get_result_detail( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $history_id = $request['id'];

        $table_h = 'ptgates_mock_history';
        $table_r = 'ptgates_mock_results';

        // Verify ownership
        $history = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_h WHERE history_id = %d AND user_id = %d",
            $history_id, $user_id
        ) );

        if ( ! $history ) {
            return new \WP_Error( 'not_found', '결과를 찾을 수 없습니다.', [ 'status' => 404 ] );
        }

        $details = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_r WHERE history_id = %d",
            $history_id
        ) );

        return Rest::success([
            'history' => $history,
            'subjects' => $results,
            'answers' => $answers_json
        ]);
    }

    /**
     * 템플릿 렌더링용 데이터 조회 헬퍼
     */
    public static function get_mock_result_full_data($history_id) {
        global $wpdb;
        $history_id = intval($history_id);
        
        $table_h = 'ptgates_mock_history';
        $table_r = 'ptgates_mock_results';
        $table_q = 'ptgates_questions';

        // 1. History
        $history = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_h WHERE history_id = %d", $history_id));
        if (!$history) return null;

        // 2. Subject Results
        $subjects = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_r WHERE history_id = %d", $history_id));

        // [FIX] Map Subject -> Category using ptgates_subject table (Same logic as submit_result)
        $table_subject_map = 'ptgates_subject';
        $su_rows = $wpdb->get_results("SELECT category, subcategory FROM $table_subject_map");
        $sub_to_cat = [];
        if (!empty($su_rows)) {
            foreach($su_rows as $r) {
                if (!empty($r->subcategory)) {
                    $sub_to_cat[$r->subcategory] = $r->category;
                }
            }
        }

        // Attach category to each subject
        if (!empty($subjects)) {
            foreach ($subjects as $subj) {
                $subj->category = isset($sub_to_cat[$subj->subject_name]) ? $sub_to_cat[$subj->subject_name] : $subj->subject_name;
            }
        }

        // 3. Questions Details
        $user_answers = [];
        if (!empty($history->answers_json)) {
            $user_answers = json_decode($history->answers_json, true);
        }

        $questions_data = [];
        if (!empty($user_answers)) {
            $q_ids = array_map(function($a) { return intval($a['question_id']); }, $user_answers);
            
            if (!empty($q_ids)) {
                $ids_str = implode(',', $q_ids);
                // Fetch content
                $raw_questions = $wpdb->get_results("SELECT * FROM $table_q WHERE id IN ($ids_str)");
                
                // Map by ID
                $q_map = [];
                foreach($raw_questions as $q) {
                    $q_map[$q->id] = $q;
                }

                // Combine User Answer + Question Content
                foreach($user_answers as $ans) {
                    $qid = $ans['question_id'];
                    if (isset($q_map[$qid])) {
                        $q_content = $q_map[$qid];
                        $questions_data[] = [
                            'id' => $qid,
                            'user_answer' => $ans['user_answer'],
                            'is_correct' => $ans['is_correct'],
                            'question' => $q_content->question,
                            'subject' => $q_content->subject,
                            // Choices
                            'choice_1' => $q_content->choice_1,
                            'choice_2' => $q_content->choice_2,
                            'choice_3' => $q_content->choice_3,
                            'choice_4' => $q_content->choice_4,
                            'choice_5' => $q_content->choice_5,
                            'answer' => $q_content->answer, // Correct answer
                            'explanation' => $q_content->explanation
                        ];
                    }
                }
            }
        }

        return [
            'history' => $history,
            'subjects' => $subjects,
            'questions' => $questions_data
        ];
    }



    /**
     * Get Aggregated Dashboard Stats for User
     */
    public static function get_dashboard_stats($user_id) {
        global $wpdb;
        $user_id = intval($user_id);
        $table_h = 'ptgates_mock_history';
        $table_r = 'ptgates_mock_results';

        // 1. Basic KPIs
        $kpi_data = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_exams,
                AVG(total_score) as avg_score,
                MAX(total_score) as best_score,
                SUM(CASE WHEN is_pass = 1 THEN 1 ELSE 0 END) as pass_count
            FROM $table_h 
            WHERE user_id = %d AND exam_type = 'mock'
        ", $user_id));

        $total_exams = $kpi_data->total_exams ?? 0;
        $pass_rate = ($total_exams > 0) ? round(($kpi_data->pass_count / $total_exams) * 100, 1) : 0;
        
        // 2. Trend Data (Last 10)
        $history_latest = $wpdb->get_results($wpdb->prepare("
            SELECT session_code, course_no, total_score, created_at 
            FROM $table_h 
            WHERE user_id = %d AND exam_type = 'mock'
            ORDER BY created_at DESC 
            LIMIT 10
        ", $user_id));
        $trend_data = array_reverse($history_latest);

        // 3. Subject Weakness (Radar Chart)
        $table_subject_map = 'ptgates_subject';
        $su_rows = $wpdb->get_results("SELECT category, subcategory FROM $table_subject_map");
        $sub_to_cat = [];
        if (!empty($su_rows)) {
            foreach($su_rows as $r) {
                if (!empty($r->subcategory)) {
                    $sub_to_cat[$r->subcategory] = $r->category;
                }
            }
        }

        $subj_results = $wpdb->get_results($wpdb->prepare("
            SELECT r.subject_name, r.score
            FROM $table_r r
            JOIN $table_h h ON r.history_id = h.history_id
            WHERE h.user_id = %d AND h.exam_type = 'mock'
        ", $user_id));

        $cat_stats = []; 
        if (!empty($subj_results)) {
            foreach($subj_results as $row) {
                $cat = isset($sub_to_cat[$row->subject_name]) ? $sub_to_cat[$row->subject_name] : $row->subject_name;
                if (!isset($cat_stats[$cat])) {
                    $cat_stats[$cat] = ['sum' => 0, 'count' => 0];
                }
                $cat_stats[$cat]['sum'] += $row->score;
                $cat_stats[$cat]['count']++;
            }
        }

        $radar_data = [];
        foreach($cat_stats as $cat => $data) {
            $radar_data[$cat] = ($data['count'] > 0) ? round($data['sum'] / $data['count'], 1) : 0;
        }

        return [
            'kpi' => [
                'total' => $total_exams,
                'avg' => round($kpi_data->avg_score, 1),
                'best' => $kpi_data->best_score ?? 0,
                'pass_rate' => $pass_rate
            ],
            'trend' => $trend_data,
            'radar' => $radar_data
        ];
    }
    /**
     * Delete Mock History (and related results)
     * Security: Ensures user_id matches.
     */
    public static function delete_mock_history($history_id, $user_id) {
        global $wpdb;
        $history_id = intval($history_id);
        $user_id = intval($user_id);

        $table_h = 'ptgates_mock_history';
        $table_r = 'ptgates_mock_results';

        // 1. Verify Ownership
        $check = $wpdb->get_var($wpdb->prepare(
            "SELECT history_id FROM $table_h WHERE history_id = %d AND user_id = %d",
            $history_id, $user_id
        ));

        if (!$check) {
            return new \WP_Error('permission_denied', '삭제 권한이 없거나 존재하지 않는 이력입니다.');
        }

        // 2. Delete (Transaction recommended or just sequential)
        // Delete details first
        $wpdb->delete($table_r, ['history_id' => $history_id], ['%d']);
        
        // Delete history
        $deleted = $wpdb->delete($table_h, ['history_id' => $history_id], ['%d']);

        if ($deleted === false) {
            return new \WP_Error('db_error', '삭제 중 오류가 발생했습니다.');
        }

        return true;
    }
}
