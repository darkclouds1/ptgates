<?php
namespace PTG\Dashboard;

use PTG\Quiz\Subjects;

if (!defined('ABSPATH')) {
    exit;
}

// 정적 과목 정의 클래스 로드 (최초 로드는 0000-ptgates-platform에서 수행됨)
// 주의: 호환성을 위해 이 코드는 유지하지만, 플랫폼 코어가 활성화되면 자동으로 로드됨
if (!class_exists('\PTG\Quiz\Subjects')) {
    // 플랫폼 코어에서 로드 시도
    $platform_subjects_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/includes/class-subjects.php';
    if (file_exists($platform_subjects_file) && is_readable($platform_subjects_file)) {
        require_once $platform_subjects_file;
    }
    // 플랫폼 코어가 없으면 기존 위치에서 로드 (호환성)
    if (!class_exists('\PTG\Quiz\Subjects')) {
        $subjects_file = WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php';
        if (file_exists($subjects_file) && is_readable($subjects_file)) {
            require_once $subjects_file;
        }
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

            // review_count 관련 인덱스
            $has_review_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.columns 
                 WHERE table_schema = %s AND table_name = %s AND column_name = 'review_count'",
                DB_NAME,
                $states_table
            ));
            if ((int)$has_review_count > 0) {
                $add_index_if_not_exists($states_table, 'idx_user_review_count_date', 'user_id, review_count, last_review_date');
            }
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
        
        // 북마크 목록 조회
        $result2 = \register_rest_route(self::REST_NAMESPACE, '/bookmarks', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_bookmarks'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
        
        // 정상 로그는 debug.log에 기록하지 않음 (성공 시 로그 제거)
        // 실패 시에만 로그 기록
        if (!$result && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Dashboard] REST route 등록 실패: ptg-dash/v1/summary');
        }
        if (!$result2 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Dashboard] REST route 등록 실패: ptg-dash/v1/bookmarks');
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

        $allowed_counts = ['study_count', 'quiz_count', 'review_count'];
        $allowed_dates  = ['last_study_date', 'last_quiz_date', 'last_review_date'];

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

        $allowed_counts = ['study_count', 'quiz_count', 'review_count'];
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

    /**
     * 북마크 목록 조회
     * 
     * 정렬 기준:
     * 1. 북마크 설정 시점 최신순 (updated_at DESC)
     * 2. 과목은 공통 MAP 순서
     * 3. 동일 과목 내 랜덤
     */
    public static function get_bookmarks($request) {
        global $wpdb;
        
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

            $page = absint($request->get_param('page')) ?: 1;
            $per_page = absint($request->get_param('per_page')) ?: 20;
            $offset = ($page - 1) * $per_page;

            // 테이블 이름 확인 (prefix 유무 고려)
            $states_table = self::get_table_name('ptgates_user_states');
            $questions_table = self::get_table_name('ptgates_questions');
            $categories_table = self::get_table_name('ptgates_categories');

            // 디버깅: 테이블 이름 확인
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Dashboard] 테이블 이름 - states: ' . $states_table . ', questions: ' . $questions_table . ', categories: ' . $categories_table);
            }

            if (!self::table_exists($states_table) || !self::table_exists($questions_table) || !self::table_exists($categories_table)) {
                ob_end_clean();
                return rest_ensure_response([
                    'items' => [],
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_pages' => 0,
                ]);
            }

            $wpdb->suppress_errors(true);

            // 1. 전체 북마크 수 조회 (bookmarked = 1 또는 bookmarked = '1' 모두 확인)
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$states_table} s
                 INNER JOIN {$questions_table} q ON s.question_id = q.question_id
                 WHERE s.user_id = %d AND (s.bookmarked = 1 OR s.bookmarked = '1') AND q.is_active = 1",
                $user_id
            ));

            // 디버깅: 북마크 개수 로그
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Dashboard] 북마크 총 개수: ' . $total . ', 사용자 ID: ' . $user_id);
            }

            if ($total === 0) {
                ob_end_clean();
                return rest_ensure_response([
                    'items' => [],
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_pages' => 0,
                ]);
            }

            // 2. 공통 MAP 순서 가져오기
            $subject_order = [];
            if (class_exists('\PTG\Quiz\Subjects')) {
                $map = \PTG\Quiz\Subjects::MAP;
                foreach ($map as $session => $session_data) {
                    if (isset($session_data['subjects']) && is_array($session_data['subjects'])) {
                        foreach (array_keys($session_data['subjects']) as $subject) {
                            if (!in_array($subject, $subject_order, true)) {
                                $subject_order[] = $subject;
                            }
                        }
                    }
                }
            }

            // 3. 북마크 문제 ID와 북마크 일자, 과목 조회 (과목별로 그룹화하기 위해)
            $bookmarked_questions = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT
                    s.question_id,
                    s.updated_at as bookmarked_at,
                    c.subject
                 FROM {$states_table} s
                 INNER JOIN {$questions_table} q ON s.question_id = q.question_id
                 LEFT JOIN {$categories_table} c ON s.question_id = c.question_id
                 WHERE s.user_id = %d AND (s.bookmarked = 1 OR s.bookmarked = '1') AND q.is_active = 1
                 ORDER BY s.updated_at DESC",
                $user_id
            ), ARRAY_A);

            // 디버깅: 조회된 북마크 문제 개수 로그
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Dashboard] 조회된 북마크 문제 개수: ' . count($bookmarked_questions));
            }

            // 4. 과목별로 그룹화하고 MAP 순서대로 정렬
            // 주의: DB의 c.subject는 실제로는 세부과목, MAP에서 상위 과목을 찾아야 함
            $grouped_by_subject = [];
            foreach ($bookmarked_questions as $item) {
                $subsubject_name = !empty($item['subject']) ? trim($item['subject']) : '';
                
                // MAP에서 세부과목명으로 상위 과목 찾기
                $subject_name = '기타';
                if (!empty($subsubject_name) && class_exists('\PTG\Quiz\Subjects')) {
                    $found_subject = \PTG\Quiz\Subjects::get_subject_from_subsubject($subsubject_name);
                    if (!empty($found_subject)) {
                        $subject_name = $found_subject;
                    }
                }
                
                if (!isset($grouped_by_subject[$subject_name])) {
                    $grouped_by_subject[$subject_name] = [];
                }
                $grouped_by_subject[$subject_name][] = [
                    'question_id' => (int) $item['question_id'],
                    'bookmarked_at' => $item['bookmarked_at'],
                    'subsubject' => $subsubject_name, // 세부과목 저장
                ];
            }

            // 디버깅: 그룹화 결과 확인
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Dashboard] 그룹화된 과목 개수: ' . count($grouped_by_subject));
                foreach ($grouped_by_subject as $subj => $q_list) {
                    error_log('[PTG Dashboard] 과목 "' . $subj . '": ' . count($q_list) . '개 문제');
                }
            }

            // 5. 과목 순서에 따라 정렬하고, 동일 과목 내에서는 북마크 최신순 유지하되, 문제는 나중에 랜덤화
            $ordered_question_ids = [];
            
            // MAP 순서에 따라 처리
            if (!empty($subject_order)) {
                foreach ($subject_order as $subject) {
                    if (isset($grouped_by_subject[$subject])) {
                        // 동일 과목 내에서 랜덤 섞기 (페이지네이션을 위해 시드 고정)
                        $questions = $grouped_by_subject[$subject];
                        // 랜덤 섞기 (페이지네이션을 위해 시드 고정)
                        mt_srand($page); // 페이지별로 다른 랜덤 순서
                        shuffle($questions);
                        foreach ($questions as $q) {
                            $ordered_question_ids[] = $q['question_id'];
                        }
                    }
                }
            }
            
            // MAP에 없는 과목도 추가
            foreach ($grouped_by_subject as $subject => $questions) {
                if (empty($subject_order) || !in_array($subject, $subject_order, true)) {
                    mt_srand($page);
                    shuffle($questions);
                    foreach ($questions as $q) {
                        $ordered_question_ids[] = $q['question_id'];
                    }
                }
            }

            // ordered_question_ids가 비어있으면 북마크된 모든 문제 ID를 직접 사용 (fallback)
            if (empty($ordered_question_ids)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Dashboard] ordered_question_ids가 비어있어 bookmarked_questions에서 직접 사용');
                }
                // 북마크된 모든 문제 ID를 최신순으로 정렬
                foreach ($bookmarked_questions as $item) {
                    $ordered_question_ids[] = (int) $item['question_id'];
                }
            }

            // 디버깅: 정렬된 question_id 개수 확인
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Dashboard] 정렬된 question_id 개수: ' . count($ordered_question_ids) . ', offset: ' . $offset . ', per_page: ' . $per_page);
                if (!empty($ordered_question_ids)) {
                    error_log('[PTG Dashboard] 첫 5개 question_id: ' . implode(', ', array_slice($ordered_question_ids, 0, 5)));
                }
            }

            // 6. 페이지네이션 적용
            
            $paged_question_ids = array_slice($ordered_question_ids, $offset, $per_page);

            // 디버깅: 페이지네이션 후 question_id 개수 확인
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Dashboard] 페이지네이션 후 question_id 개수: ' . count($paged_question_ids));
                if (!empty($paged_question_ids)) {
                    error_log('[PTG Dashboard] 페이지네이션된 question_id: ' . implode(', ', array_slice($paged_question_ids, 0, 5)) . (count($paged_question_ids) > 5 ? '...' : ''));
                }
            }

            if (empty($paged_question_ids)) {
                // 디버깅: 왜 빈 배열인지 로그
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Dashboard] 페이지네이션 결과가 비어있음. ordered_question_ids: ' . count($ordered_question_ids) . ', offset: ' . $offset . ', per_page: ' . $per_page);
                    error_log('[PTG Dashboard] offset >= count 체크: ' . ($offset >= count($ordered_question_ids) ? 'true' : 'false'));
                }
                
                // offset이 범위를 벗어난 경우에만 빈 배열 반환
                if (!empty($ordered_question_ids) && $offset >= count($ordered_question_ids)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[PTG Dashboard] offset이 범위를 벗어남. 빈 배열 반환');
                    }
                    ob_end_clean();
                    return rest_ensure_response([
                        'items' => [],
                        'total' => $total,
                        'page' => $page,
                        'per_page' => $per_page,
                        'total_pages' => (int) ceil($total / $per_page),
                    ]);
                }
                
                // 그 외의 경우 (ordered_question_ids가 비어있거나 다른 이유로 비어있는 경우) fallback 사용
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Dashboard] fallback 쿼리 실행. ordered_question_ids: ' . count($ordered_question_ids) . ', offset: ' . $offset);
                }
                
                // 북마크된 모든 문제를 최신순으로 직접 조회 (페이지네이션 적용)
                $fallback_query = $wpdb->prepare(
                    "SELECT 
                        q.question_id,
                        q.content,
                        q.answer,
                        q.explanation,
                        q.type,
                        q.difficulty,
                        c.exam_year,
                        c.exam_session,
                        c.exam_course,
                        c.subject,
                        s.updated_at as bookmarked_at
                     FROM {$questions_table} q
                     LEFT JOIN {$categories_table} c ON q.question_id = c.question_id
                     INNER JOIN {$states_table} s ON q.question_id = s.question_id AND s.user_id = %d
                     WHERE (s.bookmarked = 1 OR s.bookmarked = '1') AND q.is_active = 1
                     ORDER BY s.updated_at DESC
                     LIMIT %d OFFSET %d",
                    $user_id,
                    $per_page,
                    $offset
                );
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Dashboard] Fallback 쿼리 실행: ' . $fallback_query);
                }
                
                $questions = $wpdb->get_results($fallback_query, ARRAY_A);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Dashboard] Fallback 쿼리 결과 개수: ' . count($questions));
                    if ($wpdb->last_error) {
                        error_log('[PTG Dashboard] Fallback 쿼리 에러: ' . $wpdb->last_error);
                    }
                }
                
                // 문제 데이터 포맷팅
                $items = [];
                foreach ($questions as $q) {
                    // 문제 지문과 선택지 분리
                    $content = $q['content'] ?? '';
                    $content = str_replace('_x000D_', '', $content);
                    $content = str_replace("\r\n", "\n", $content);
                    $content = str_replace("\r", "\n", $content);
                    
                    // 선택지 추출
                    $options = [];
                    $question_text = $content;
                    
                    if (preg_match_all('/([①-⑳])/u', $content, $number_matches, PREG_OFFSET_CAPTURE)) {
                        $option_ranges = [];
                        for ($idx = 0; $idx < count($number_matches[0]); $idx++) {
                            $start_pos = $number_matches[0][$idx][1];
                            $end_pos = ($idx < count($number_matches[0]) - 1) 
                                ? $number_matches[0][$idx + 1][1] 
                                : strlen($content);
                            
                            $option_text = trim(substr($content, $start_pos, $end_pos - $start_pos));
                            $option_text = preg_replace('/\n{2,}/', ' ', $option_text);
                            $option_text = preg_replace('/\n/', ' ', $option_text);
                            
                            if (!empty($option_text)) {
                                $options[] = $option_text;
                                $option_ranges[] = ['start' => $start_pos, 'end' => $end_pos];
                            }
                        }
                        
                        // 문제 본문에서 선택지 제거
                        if (!empty($option_ranges)) {
                            $question_parts = [];
                            $last_pos = 0;
                            foreach ($option_ranges as $range) {
                                if ($range['start'] > $last_pos) {
                                    $question_parts[] = substr($content, $last_pos, $range['start'] - $last_pos);
                                }
                                $last_pos = $range['end'];
                            }
                            if ($last_pos < strlen($content)) {
                                $question_parts[] = substr($content, $last_pos);
                            }
                            $question_text = trim(implode('', $question_parts));
                        }
                    }

                    // 해설 정리
                    $explanation = $q['explanation'] ?? '';
                    if (!empty($explanation)) {
                        $explanation = str_replace('_x000D_', '', $explanation);
                        $explanation = str_replace("\r\n", "\n", $explanation);
                        $explanation = str_replace("\r", "\n", $explanation);
                        $explanation = preg_replace('/\n{2,}/', "\n", $explanation);
                    }

                    // 문제 ID 포맷 (간단하게 id-{question_id})
                    $question_id_display = 'id-' . $q['question_id'];
                    
                    // DB의 c.subject는 실제로는 세부과목, MAP에서 상위 과목 찾기
                    $subsubject_name = !empty($q['subject']) ? trim($q['subject']) : '';
                    $subject_name = '';
                    if (!empty($subsubject_name) && class_exists('\PTG\Quiz\Subjects')) {
                        $found_subject = \PTG\Quiz\Subjects::get_subject_from_subsubject($subsubject_name);
                        if (!empty($found_subject)) {
                            $subject_name = $found_subject;
                        }
                    }

                    $items[] = [
                        'question_id' => (int) $q['question_id'],
                        'question_id_display' => $question_id_display,
                        'bookmarked_at' => $q['bookmarked_at'],
                        'question_text' => $question_text,
                        'options' => $options,
                        'answer' => $q['answer'] ?? '',
                        'explanation' => $explanation,
                        'subject' => $subject_name, // MAP에서 찾은 상위 과목
                        'subsubject' => $subsubject_name, // DB의 c.subject (세부과목)
                        'exam_year' => $q['exam_year'] ? (int) $q['exam_year'] : null,
                        'exam_session' => $q['exam_session'] ? (int) $q['exam_session'] : null,
                    ];
                }
                
                $wpdb->suppress_errors(false);
                ob_end_clean();
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Dashboard] Fallback 결과 반환. items 개수: ' . count($items));
                }
                
                return rest_ensure_response([
                    'items' => $items,
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_pages' => (int) ceil($total / $per_page),
                ]);
            }

            // 7. 문제 상세 정보 조회
            $placeholders = implode(',', array_fill(0, count($paged_question_ids), '%d'));
            $query = $wpdb->prepare(
                "SELECT 
                    q.question_id,
                    q.content,
                    q.answer,
                    q.explanation,
                    q.type,
                    q.difficulty,
                    c.exam_year,
                    c.exam_session,
                    c.exam_course,
                    c.subject,
                    s.updated_at as bookmarked_at
                 FROM {$questions_table} q
                 LEFT JOIN {$categories_table} c ON q.question_id = c.question_id
                 INNER JOIN {$states_table} s ON q.question_id = s.question_id AND s.user_id = %d
                 WHERE q.question_id IN ({$placeholders})
                   AND q.is_active = 1
                   AND (s.bookmarked = 1 OR s.bookmarked = '1')
                 ORDER BY FIELD(q.question_id, {$placeholders})",
                $user_id,
                ...$paged_question_ids,
                ...$paged_question_ids
            );

            $questions = $wpdb->get_results($query, ARRAY_A);

            // 디버깅: 조회된 문제 개수 확인
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PTG Dashboard] 최종 조회된 문제 개수: ' . count($questions));
                if (!empty($questions)) {
                    error_log('[PTG Dashboard] 첫 번째 문제 ID: ' . ($questions[0]['question_id'] ?? 'N/A'));
                } else {
                    error_log('[PTG Dashboard] 쿼리 결과가 비어있음. paged_question_ids: ' . (empty($paged_question_ids) ? 'empty' : implode(', ', array_slice($paged_question_ids, 0, 5))));
                    error_log('[PTG Dashboard] 쿼리 에러: ' . ($wpdb->last_error ?: 'none'));
                }
            }

            // 쿼리 결과가 비어있으면 fallback 사용
            if (empty($questions) && !empty($paged_question_ids)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Dashboard] 경고: paged_question_ids는 있지만 쿼리 결과가 비어있어 fallback 사용');
                    error_log('[PTG Dashboard] 쿼리 에러: ' . ($wpdb->last_error ?: 'none'));
                }
                
                // Fallback: 북마크된 모든 문제를 최신순으로 직접 조회
                $fallback_query = $wpdb->prepare(
                    "SELECT 
                        q.question_id,
                        q.content,
                        q.answer,
                        q.explanation,
                        q.type,
                        q.difficulty,
                        c.exam_year,
                        c.exam_session,
                        c.exam_course,
                        c.subject,
                        s.updated_at as bookmarked_at
                     FROM {$questions_table} q
                     LEFT JOIN {$categories_table} c ON q.question_id = c.question_id
                     INNER JOIN {$states_table} s ON q.question_id = s.question_id AND s.user_id = %d
                     WHERE (s.bookmarked = 1 OR s.bookmarked = '1') AND q.is_active = 1
                     ORDER BY s.updated_at DESC
                     LIMIT %d OFFSET %d",
                    $user_id,
                    $per_page,
                    $offset
                );
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Dashboard] Fallback 쿼리 실행 (쿼리 실패 시)');
                }
                
                $questions = $wpdb->get_results($fallback_query, ARRAY_A);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PTG Dashboard] Fallback 쿼리 결과 개수: ' . count($questions));
                    if ($wpdb->last_error) {
                        error_log('[PTG Dashboard] Fallback 쿼리 에러: ' . $wpdb->last_error);
                    }
                }
            }

            // 8. 문제 데이터 포맷팅
            $items = [];
            foreach ($questions as $q) {
                // 문제 지문과 선택지 분리
                $content = $q['content'] ?? '';
                $content = str_replace('_x000D_', '', $content);
                $content = str_replace("\r\n", "\n", $content);
                $content = str_replace("\r", "\n", $content);
                
                // 선택지 추출
                $options = [];
                $question_text = $content;
                
                if (preg_match_all('/([①-⑳])/u', $content, $number_matches, PREG_OFFSET_CAPTURE)) {
                    $option_ranges = [];
                    for ($idx = 0; $idx < count($number_matches[0]); $idx++) {
                        $start_pos = $number_matches[0][$idx][1];
                        $end_pos = ($idx < count($number_matches[0]) - 1) 
                            ? $number_matches[0][$idx + 1][1] 
                            : strlen($content);
                        
                        $option_text = trim(substr($content, $start_pos, $end_pos - $start_pos));
                        $option_text = preg_replace('/\n{2,}/', ' ', $option_text);
                        $option_text = preg_replace('/\n/', ' ', $option_text);
                        
                        if (!empty($option_text)) {
                            $options[] = $option_text;
                            $option_ranges[] = ['start' => $start_pos, 'end' => $end_pos];
                        }
                    }
                    
                    // 문제 본문에서 선택지 제거
                    if (!empty($option_ranges)) {
                        $question_parts = [];
                        $last_pos = 0;
                        foreach ($option_ranges as $range) {
                            if ($range['start'] > $last_pos) {
                                $question_parts[] = substr($content, $last_pos, $range['start'] - $last_pos);
                            }
                            $last_pos = $range['end'];
                        }
                        if ($last_pos < strlen($content)) {
                            $question_parts[] = substr($content, $last_pos);
                        }
                        $question_text = trim(implode('', $question_parts));
                    }
                }

                // 해설 정리
                $explanation = $q['explanation'] ?? '';
                if (!empty($explanation)) {
                    $explanation = str_replace('_x000D_', '', $explanation);
                    $explanation = str_replace("\r\n", "\n", $explanation);
                    $explanation = str_replace("\r", "\n", $explanation);
                    $explanation = preg_replace('/\n{2,}/', "\n", $explanation);
                }

                // 문제 ID 포맷 (간단하게 id-{question_id})
                $question_id_display = 'id-' . $q['question_id'];
                
                // DB의 c.subject는 실제로는 세부과목, MAP에서 상위 과목 찾기
                $subsubject_name = !empty($q['subject']) ? trim($q['subject']) : '';
                $subject_name = '';
                if (!empty($subsubject_name) && class_exists('\PTG\Quiz\Subjects')) {
                    $found_subject = \PTG\Quiz\Subjects::get_subject_from_subsubject($subsubject_name);
                    if (!empty($found_subject)) {
                        $subject_name = $found_subject;
                    }
                }

                $items[] = [
                    'question_id' => (int) $q['question_id'],
                    'question_id_display' => $question_id_display,
                    'bookmarked_at' => $q['bookmarked_at'],
                    'question_text' => $question_text,
                    'options' => $options,
                    'answer' => $q['answer'] ?? '',
                    'explanation' => $explanation,
                    'subject' => $subject_name, // MAP에서 찾은 상위 과목
                    'subsubject' => $subsubject_name, // DB의 c.subject (세부과목)
                    'exam_year' => $q['exam_year'] ? (int) $q['exam_year'] : null,
                    'exam_session' => $q['exam_session'] ? (int) $q['exam_session'] : null,
                ];
            }

            $wpdb->suppress_errors(false);
            ob_end_clean();

            return rest_ensure_response([
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => (int) ceil($total / $per_page),
            ]);

        } catch (\Throwable $e) {
            ob_end_clean();
            $wpdb->suppress_errors(false);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Bookmarks API Error: ' . $e->getMessage());
                error_log($e->getTraceAsString());
            }
            
            return new \WP_Error(
                'server_error',
                '서버 오류가 발생했습니다: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}

