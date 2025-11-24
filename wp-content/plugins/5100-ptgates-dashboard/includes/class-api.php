<?php
namespace PTG\Dashboard;

use PTG\Quiz\Subjects;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\PTG\Quiz\Subjects')) {
    $subjects_file = WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php';
    if (file_exists($subjects_file) && is_readable($subjects_file)) {
        require_once $subjects_file;
    }
}

class API {
    const REST_NAMESPACE = 'ptg-dash/v1';

    /**
     * mbstring이 없는 서버에서도 안전하게 부분 문자열을 자르기 위한 헬퍼
     */
    private static function safe_substr($text, $length) {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $length, 'UTF-8');
        }
        return substr($text, 0, $length);
    }

    /**
     * JSON 인코딩을 위한 안전한 문자열 변환
     */
    private static function safe_string($str) {
        if (!is_string($str)) return $str;
        return mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    }

    /**
     * 테이블 이름 확인 (Prefix 유무 고려)
     */
    private static function get_table_name($base_name) {
        global $wpdb;
        
        // 1. 정확한 이름으로 확인
        if (self::table_exists($base_name)) {
            return $base_name;
        }
        
        // 2. Prefix 붙여서 확인
        $prefixed = $wpdb->prefix . $base_name;
        if (self::table_exists($prefixed)) {
            return $prefixed;
        }
        
        // 3. 기본값 반환
        return $base_name;
    }

    /**
     * rest_api_init 훅에 라우트 등록 콜백을 연결
     */
    public static function boot() {
        static $bootstrapped = false;

        if ($bootstrapped) {
            return;
        }

        $bootstrapped = true;

        \add_action('rest_api_init', [__CLASS__, 'register_routes']);
        \add_action('admin_init', [__CLASS__, 'maybe_add_indexes']);
    }

    /**
     * 성능 최적화를 위한 인덱스 추가 (한 번만 실행)
     */
    public static function maybe_add_indexes() {
        $option_key = 'ptg_dashboard_indexes_added';
        if (get_option($option_key)) {
            return; // 이미 추가됨
        }

        global $wpdb;
        $wpdb->suppress_errors(true);

        // 인덱스 존재 여부 확인 후 추가하는 헬퍼 함수
        $add_index_if_not_exists = function($table, $index_name, $columns) use ($wpdb) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.statistics 
                 WHERE table_schema = %s AND table_name = %s AND index_name = %s",
                DB_NAME,
                $table,
                $index_name
            ));
            
            if ((int)$existing === 0) {
                $wpdb->query("ALTER TABLE `{$table}` ADD INDEX `{$index_name}` ({$columns})");
            }
        };

        // 1. ptgates_user_states 테이블 인덱스
        $states_table = self::get_table_name('ptgates_user_states');
        if (self::table_exists($states_table)) {
            // study_count 관련 인덱스 (컬럼 존재 확인 필요)
            $has_study_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.columns 
                 WHERE table_schema = %s AND table_name = %s AND column_name = 'study_count'",
                DB_NAME,
                $states_table
            ));
            if ((int)$has_study_count > 0) {
                $add_index_if_not_exists($states_table, 'idx_user_study_count_date', 'user_id, study_count, last_study_date');
            }
            
            // quiz_count 관련 인덱스
            $has_quiz_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.columns 
                 WHERE table_schema = %s AND table_name = %s AND column_name = 'quiz_count'",
                DB_NAME,
                $states_table
            ));
            if ((int)$has_quiz_count > 0) {
                $add_index_if_not_exists($states_table, 'idx_user_quiz_count_date', 'user_id, quiz_count, last_quiz_date');
            }
            
            // 플래그 조합 인덱스
            $add_index_if_not_exists($states_table, 'idx_user_flags', 'user_id, bookmarked, needs_review');
        }

        // 2. ptgates_categories 테이블 인덱스
        $categories_table = self::get_table_name('ptgates_categories');
        if (self::table_exists($categories_table)) {
            $add_index_if_not_exists($categories_table, 'idx_question_subject', 'question_id, subject');
        }

        // 3. ptgates_questions 테이블 인덱스
        $questions_table = self::get_table_name('ptgates_questions');
        if (self::table_exists($questions_table)) {
            $add_index_if_not_exists($questions_table, 'idx_question_active', 'question_id, is_active');
        }

        // 4. ptgates_user_results 테이블 인덱스
        $results_table = self::get_table_name('ptgates_user_results');
        if (self::table_exists($results_table)) {
            $add_index_if_not_exists($results_table, 'idx_user_created', 'user_id, created_at');
        }

        $wpdb->suppress_errors(false);

        // 인덱스 추가 완료 플래그 설정
        update_option($option_key, true);
    }

    /**
     * REST 라우트를 등록합니다.
     */
    public static function register_routes() {
        $result = \register_rest_route(self::REST_NAMESPACE, '/summary', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_summary'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
        
        // 정상 로그는 debug.log에 기록하지 않음 (성공 시 로그 제거)
        // 실패 시에만 로그 기록
        if (!$result && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Dashboard] REST route 등록 실패: ptg-dash/v1/summary');
        }
    }

    /**
     * 테이블 존재 여부 안전하게 확인
     */
    private static function table_exists($table_name) {
        global $wpdb;
        
        // 오류 출력 억제
        $wpdb->suppress_errors(true);
        $show_errors = $wpdb->show_errors(false);
        
        // information_schema 사용 (더 안전)
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ));
        
        // 오류 복원
        $wpdb->suppress_errors(false);
        if ($show_errors) {
            $wpdb->show_errors(true);
        }
        
        return (int)$result > 0;
    }

    public static function get_summary($request) {
        // 출력 버퍼 시작 - HTML 오류 메시지가 JSON에 포함되지 않도록
        ob_start();
        
        try {
            $user_id = get_current_user_id();
            
            if (!$user_id) {
                return new \WP_Error(
                    'unauthorized',
                    '로그인이 필요합니다.',
                    ['status' => 401]
                );
            }

            // 캐싱: 5분간 캐시 유지
            $cache_key = 'ptg_dashboard_summary_' . $user_id;
            $cached = get_transient($cache_key);
            
            if ($cached !== false) {
                ob_end_clean();
                return rest_ensure_response($cached);
            }
        
        // 1. Premium Status
        $premium_status = get_user_meta($user_id, 'ptg_premium_status', true);
        $premium_until = get_user_meta($user_id, 'ptg_premium_until', true);
        
        $expiry_date = null;
        if ($premium_until) {
            if (is_numeric($premium_until)) {
                $expiry_date = date('Y-m-d', (int)$premium_until);
            } else {
                $timestamp = strtotime($premium_until);
                if ($timestamp !== false) {
                    $expiry_date = date('Y-m-d', $timestamp);
                }
            }
        }

        $premium_data = [
            'status' => $premium_status === 'active' ? 'active' : 'free',
            'expiry' => $expiry_date
        ];

        // 2. Today's Tasks (Reviewer integration) & Bookmarks (쿼리 통합)
        global $wpdb;
        $review_count = 0;
        $bookmark_count = 0;
        
        // 테이블 이름 확인 (Prefix 지원)
        $table_states = self::get_table_name('ptgates_user_states');
        
        if (self::table_exists($table_states)) {
            $wpdb->suppress_errors(true);
            // 두 개의 COUNT 쿼리를 하나로 통합
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(CASE WHEN needs_review = 1 THEN 1 END) as review_count,
                    COUNT(CASE WHEN bookmarked = 1 THEN 1 END) as bookmark_count
                FROM $table_states 
                WHERE user_id = %d",
                $user_id
            ), ARRAY_A);
            $wpdb->suppress_errors(false);
            
            if ($stats && !$wpdb->last_error) {
                $review_count = (int)($stats['review_count'] ?? 0);
                $bookmark_count = (int)($stats['bookmark_count'] ?? 0);
            }
        }

        // 3. Progress (Overall)
        $table_results = self::get_table_name('ptgates_user_results');
        $table_questions = self::get_table_name('ptgates_questions');
        
        $total_questions = 0;
        $solved_questions = 0;
        
        if (self::table_exists($table_questions)) {
            $wpdb->suppress_errors(true);
            $total_questions = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_questions WHERE is_active = 1");
            $wpdb->suppress_errors(false);
        }
        
        if (self::table_exists($table_results)) {
            $wpdb->suppress_errors(true);
            $solved_questions = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT question_id) FROM $table_results WHERE user_id = %d",
                $user_id
            ));
            $wpdb->suppress_errors(false);
        }
        
        $progress = [
            'solved' => $solved_questions,
            'total' => $total_questions,
            'percent' => $total_questions > 0 ? round(($solved_questions / $total_questions) * 100, 1) : 0
        ];

        // 4. Recent Activity (불필요한 데이터 조회 최소화)
        $recent_activity = [];
        
        if (self::table_exists($table_results) && self::table_exists($table_questions)) {
            $wpdb->suppress_errors(true);
            // content 전체 대신 SUBSTRING으로 50자만 조회
            $recent_results = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    r.result_id,
                    r.is_correct,
                    r.created_at,
                    SUBSTRING(REPLACE(REPLACE(q.content, '<', ''), '>', ''), 1, 50) as content_preview
                 FROM $table_results r
                 JOIN $table_questions q ON r.question_id = q.question_id
                 WHERE r.user_id = %d
                 ORDER BY r.created_at DESC
                 LIMIT 5",
                $user_id
            ));
            $wpdb->suppress_errors(false);

            if ($recent_results && !$wpdb->last_error) {
                foreach ($recent_results as $row) {
                    $content = strip_tags($row->content_preview ?? '');
                    $content = self::safe_string($content);
                    
                    $recent_activity[] = [
                        'id' => $row->result_id,
                        'question_summary' => self::safe_substr($content, 30) . '...',
                        'is_correct' => (bool)$row->is_correct,
                        'date' => $row->created_at
                    ];
                }
            }
        }

        $learning_records = self::build_learning_records($user_id);

            // 출력 버퍼 정리 (오류 메시지 제거)
            ob_end_clean();
            
            $response_data = [
                'user_name' => wp_get_current_user()->display_name,
                'premium' => $premium_data,
                'today_reviews' => (int)$review_count,
                'bookmarks' => [
                    'count' => (int)$bookmark_count,
                ],
                'progress' => $progress,
                'recent_activity' => $recent_activity,
                'learning_records' => $learning_records,
            ];

            // 캐싱: 5분간 저장
            set_transient($cache_key, $response_data, 5 * MINUTE_IN_SECONDS);
            
            return rest_ensure_response($response_data);
            
        } catch (\Throwable $e) {
            // 출력 버퍼 정리
            ob_end_clean();
            
            // 오류 로깅 (디버그 모드에서만)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Dashboard API Error: ' . $e->getMessage());
                error_log($e->getTraceAsString());
            }
            
            return new \WP_Error(
                'server_error',
                '서버 오류가 발생했습니다: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * 학습 기록(Study/Quiz) 요약 데이터를 생성
     */
    private static function build_learning_records($user_id) {
        $study_rows = self::fetch_learning_rows($user_id, 'study_count', 'last_study_date');
        $quiz_rows  = self::fetch_learning_rows($user_id, 'quiz_count', 'last_quiz_date');

        // 과목별 총계는 날짜 제한 없이 전체 데이터 사용
        $study_totals = self::fetch_subject_totals($user_id, 'study_count');
        $quiz_totals  = self::fetch_subject_totals($user_id, 'quiz_count');

        return [
            'study'    => self::format_learning_rows($study_rows),
            'quiz'     => self::format_learning_rows($quiz_rows),
            'subjects' => self::format_subject_totals($study_totals, $quiz_totals),
        ];
    }

    /**
     * 원시 학습 기록 데이터를 조회합니다.
     */
    private static function fetch_learning_rows($user_id, $count_column, $date_column) {
        global $wpdb;

        $allowed_counts = ['study_count', 'quiz_count'];
        $allowed_dates  = ['last_study_date', 'last_quiz_date'];

        if (!in_array($count_column, $allowed_counts, true) || !in_array($date_column, $allowed_dates, true)) {
            return [];
        }

        $states_table     = self::get_table_name('ptgates_user_states');
        $questions_table  = self::get_table_name('ptgates_questions');
        $categories_table = self::get_table_name('ptgates_categories');

        if (
            ! self::table_exists($states_table) ||
            ! self::table_exists($questions_table) ||
            ! self::table_exists($categories_table)
        ) {
            return [];
        }

        $wpdb->suppress_errors(true);
        $sql = $wpdb->prepare(
            "SELECT 
                DATE(s.{$date_column}) AS record_date,
                c.subject AS subsubject_name,
                SUM(s.{$count_column}) AS total_count
            FROM {$states_table} s
            INNER JOIN {$questions_table} q ON s.question_id = q.question_id
            INNER JOIN {$categories_table} c ON q.question_id = c.question_id
            WHERE s.user_id = %d
              AND s.{$count_column} > 0
              AND s.{$date_column} IS NOT NULL
            GROUP BY record_date, subsubject_name
            ORDER BY record_date DESC, total_count DESC
            LIMIT %d",
            $user_id,
            200
        );
        $rows = $wpdb->get_results($sql);
        $wpdb->suppress_errors(false);

        return ($rows && !$wpdb->last_error) ? $rows : [];
    }

    /**
     * 원시 학습 기록을 날짜/과목 구조로 변환합니다.
     */
    private static function format_learning_rows($rows) {
        if (empty($rows)) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            if (empty($row->record_date)) {
                continue;
            }

            $date         = $row->record_date;
            $subsubject   = $row->subsubject_name ?: '미분류';
            $parent       = class_exists('\PTG\Quiz\Subjects')
                ? (Subjects::get_subject_from_subsubject($subsubject) ?? $subsubject)
                : $subsubject;
            $count        = (int) $row->total_count;

            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            if (!isset($grouped[$date][$parent])) {
                $grouped[$date][$parent] = [
                    'subject' => $parent,
                    'total' => 0,
                    'subsubjects' => [],
                ];
            }

            $grouped[$date][$parent]['total'] += $count;
            $grouped[$date][$parent]['subsubjects'][] = [
                'name' => $subsubject,
                'count' => $count,
            ];
        }

        // 날짜 최신순 정렬
        krsort($grouped);

        $result = [];
        foreach ($grouped as $date => $subjects) {
            $subject_list = array_values($subjects);

            foreach ($subject_list as &$subject_meta) {
                usort(
                    $subject_meta['subsubjects'],
                    function($a, $b) {
                        return $b['count'] <=> $a['count'];
                    }
                );
            }
            unset($subject_meta);

            usort(
                $subject_list,
                function($a, $b) {
                    return $b['total'] <=> $a['total'];
                }
            );

            $result[] = [
                'date' => $date,
                'subjects' => $subject_list,
            ];
        }

        return array_slice($result, 0, 7);
    }

    /**
     * 과목별 전체 총계를 조회합니다 (날짜 제한 없음)
     */
    private static function fetch_subject_totals($user_id, $count_column) {
        global $wpdb;

        $allowed_counts = ['study_count', 'quiz_count'];
        if (!in_array($count_column, $allowed_counts, true)) {
            return [];
        }

        $states_table     = self::get_table_name('ptgates_user_states');
        $questions_table  = self::get_table_name('ptgates_questions');
        $categories_table = self::get_table_name('ptgates_categories');

        if (
            ! self::table_exists($states_table) ||
            ! self::table_exists($questions_table) ||
            ! self::table_exists($categories_table)
        ) {
            return [];
        }

        $wpdb->suppress_errors(true);
        $sql = $wpdb->prepare(
            "SELECT 
                c.subject AS subsubject_name,
                SUM(s.{$count_column}) AS total_count
            FROM {$states_table} s
            INNER JOIN {$questions_table} q ON s.question_id = q.question_id
            INNER JOIN {$categories_table} c ON q.question_id = c.question_id
            WHERE s.user_id = %d
              AND s.{$count_column} > 0
            GROUP BY subsubject_name
            ORDER BY total_count DESC",
            $user_id
        );
        $rows = $wpdb->get_results($sql);
        $wpdb->suppress_errors(false);

        return ($rows && !$wpdb->last_error) ? $rows : [];
    }

    /**
     * 세부과목 기준 학습 누적치를 세션/과목 구조로 변환
     */
    private static function format_subject_totals($study_rows, $quiz_rows) {
        if (!class_exists('\PTG\Quiz\Subjects')) {
            return [];
        }

        $map          = Subjects::MAP;
        $subject_meta = self::get_subject_meta();
        $structure    = [];
        $subject_index = [];

        foreach ($map as $session => $session_data) {
            $session_int = (int) $session;
            $structure[$session_int] = [
                'session' => $session_int,
                'session_label' => $session_int . '교시',
                'subjects' => []
            ];

            if (empty($session_data['subjects']) || !is_array($session_data['subjects'])) {
                continue;
            }

            foreach ($session_data['subjects'] as $subject_name => $subject_data) {
                $category_id = isset($subject_meta[$subject_name]['id']) ? $subject_meta[$subject_name]['id'] : sanitize_title($subject_name);
                $description = $subject_meta[$subject_name]['description'] ?? '';

                $subject_entry = [
                    'id' => $category_id,
                    'name' => $subject_name,
                    'description' => $description,
                    'study_total' => 0,
                    'quiz_total' => 0,
                    'subsubjects' => []
                ];

                if (!empty($subject_data['subs']) && is_array($subject_data['subs'])) {
                    foreach ($subject_data['subs'] as $sub_name => $_) {
                        $subject_entry['subsubjects'][$sub_name] = [
                            'name'  => $sub_name,
                            'study' => 0,
                            'quiz'  => 0,
                        ];
                    }
                }

                $structure[$session_int]['subjects'][$subject_name] = $subject_entry;
                $subject_index[$subject_name] = [
                    'session' => $session_int,
                    'key'     => $subject_name,
                ];
            }
        }

        $apply_counts = function($rows, $type) use (&$structure, $subject_index) {
            foreach ($rows as $row) {
                $subsubject = $row->subsubject_name ?: '';
                $subject_name = Subjects::get_subject_from_subsubject($subsubject);

                if (!$subject_name || !isset($subject_index[$subject_name])) {
                    continue;
                }

                $session = $subject_index[$subject_name]['session'];
                $subject_key = $subject_index[$subject_name]['key'];

                if (!isset($structure[$session]['subjects'][$subject_key]['subsubjects'][$subsubject])) {
                    $structure[$session]['subjects'][$subject_key]['subsubjects'][$subsubject] = [
                        'name'  => $subsubject,
                        'study' => 0,
                        'quiz'  => 0,
                    ];
                }

                $count = (int) $row->total_count;
                $structure[$session]['subjects'][$subject_key]['subsubjects'][$subsubject][$type] += $count;
                $structure[$session]['subjects'][$subject_key]["{$type}_total"] += $count;
            }
        };

        $apply_counts($study_rows, 'study');
        $apply_counts($quiz_rows, 'quiz');

        $result = [];
        foreach ($structure as $session_data) {
            $subjects = [];
            foreach ($session_data['subjects'] as $subject_entry) {
                $subject_entry['subsubjects'] = array_values($subject_entry['subsubjects']);
                $subjects[] = $subject_entry;
            }
            $session_data['subjects'] = $subjects;
            $result[] = $session_data;
        }

        return $result;
    }

    /**
     * 대시보드 캐시 무효화 (다른 플러그인에서 호출 가능)
     * 
     * @param int $user_id 사용자 ID
     */
    public static function invalidate_cache($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if ($user_id) {
            $cache_key = 'ptg_dashboard_summary_' . $user_id;
            delete_transient($cache_key);
        }
    }

    /**
     * 과목 메타 정보 반환
     */
    private static function get_subject_meta() {
        return [
            '물리치료 기초'   => [
                'id'          => 'ptg-foundation',
                'description' => '해부생리 · 운동학 · 물리적 인자치료 · 공중보건학',
            ],
            '물리치료 진단평가' => [
                'id'          => 'ptg-assessment',
                'description' => '근골격 · 신경계 · 원리 · 심폐혈관 · 기타 · 임상의사결정',
            ],
            '물리치료 중재'   => [
                'id'          => 'ptg-intervention',
                'description' => '근골격 · 신경계 · 심폐혈관 · 림프·피부 · 문제해결',
            ],
            '의료관계법규'    => [
                'id'          => 'ptg-medlaw',
                'description' => '의료법 · 의료기사법 · 노인복지법 · 장애인복지법 · 건보법',
            ],
        ];
    }
}

