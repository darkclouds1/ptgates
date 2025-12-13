<?php
namespace PTG\DashboardNew;

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
    const REST_NAMESPACE = 'ptg-dash-new/v1';

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
        $option_key = 'ptg_dashboard_new_indexes_added';
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
        
        // 사용자 데이터 초기화
        $result3 = \register_rest_route(self::REST_NAMESPACE, '/reset-user-data', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'reset_user_data'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);

        // 탭 컨텐츠 로드 (Lazy Loading)
        $result4 = \register_rest_route(self::REST_NAMESPACE, '/tab-content', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_tab_content'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'tab' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return in_array($param, ['stats', 'subject', 'date']);
                    }
                ]
            ]
        ]);
        
        // 정상 로그는 debug.log에 기록하지 않음 (성공 시 로그 제거)
        // 실패 시에만 로그 기록
        if (!$result && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Dashboard New] REST route 등록 실패: ptg-dash-new/v1/summary');
        }
        if (!$result2 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Dashboard New] REST route 등록 실패: ptg-dash-new/v1/bookmarks');
        }
        if (!$result3 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Dashboard New] REST route 등록 실패: ptg-dash-new/v1/reset-user-data');
        }
    }

    /**
     * 탭 컨텐츠 로드 콜백
     */
    public static function get_tab_content($request) {
        $tab = $request->get_param('tab');
        $html = '';

        switch ($tab) {
            case 'stats':
                $html = do_shortcode('[ptg_analytics]');
                break;
            case 'subject':
                $html = do_shortcode('[ptg_study_history]');
                break;
            case 'date':
                $html = do_shortcode('[ptg_daily_history]');
                break;
        }

        return rest_ensure_response(['html' => $html]);
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
        // 출력 버퍼 시작
        ob_start();
        $start_time = microtime(true);
        
        try {
            $user_id = get_current_user_id();
            
            if (!$user_id) {
                return new \WP_Error(
                    'unauthorized',
                    '로그인이 필요합니다.',
                    ['status' => 401]
                );
            }

            // 캐싱: 5분간 캐시 유지 (캐시 무시 파라미터 확인)
            $cache_key = 'ptg_dashboard_new_summary_' . $user_id;
            $force_refresh = $request->get_param('force_refresh') === '1' || $request->get_param('force_refresh') === true;
            
            if (!$force_refresh) {
                $cache_start = microtime(true);
                $cached = get_transient($cache_key);
                $cache_dur = (microtime(true) - $cache_start) * 1000;
                
                if ($cached !== false) {
                    ob_end_clean();
                    $response = rest_ensure_response($cached);
                    
                    // Timing Calculation
                    global $ptg_server_timings;
                    $api_start = $start_time;
                    $now = microtime(true);
                    
                    $boot_dur = isset($ptg_server_timings['plugins_loaded']) ? ($ptg_server_timings['plugins_loaded'] - $ptg_server_timings['start']) * 1000 : 0;
                    $plugin_dur = (isset($ptg_server_timings['setup_theme']) && isset($ptg_server_timings['plugins_loaded'])) ? ($ptg_server_timings['setup_theme'] - $ptg_server_timings['plugins_loaded']) * 1000 : 0;
                    $theme_dur = (isset($ptg_server_timings['init']) && isset($ptg_server_timings['setup_theme'])) ? ($ptg_server_timings['init'] - $ptg_server_timings['setup_theme']) * 1000 : 0;
                    $wp_total = ($api_start - $ptg_server_timings['start']) * 1000;
                    $api_total = ($now - $api_start) * 1000;

                    $response->header('X-PTG-Cache', 'HIT');
                    $response->header('Server-Timing', 
                        'boot;desc="WP Boot";dur=' . $boot_dur . 
                        ', plugins;desc="Plugins";dur=' . $plugin_dur . 
                        ', theme;desc="Theme";dur=' . $theme_dur . 
                        ', wp;desc="WP Overhead";dur=' . $wp_total .
                        ', cache;desc="Transient Read";dur=' . $cache_dur . 
                        ', api;desc="API Exec";dur=' . $api_total
                    );
                    return $response;
                }
            }
        
        // 1. Premium Status
        // Priority: ptgates_user_member table > user_meta
        
        // Default from user_meta
        $premium_status = get_user_meta($user_id, 'ptg_premium_status', true);
        $premium_until = get_user_meta($user_id, 'ptg_premium_until', true);
        
        // Check ptgates_user_member table
        global $wpdb;
        $member_table = self::get_table_name('ptgates_user_member');
        if (self::table_exists($member_table)) {
            $member_data = $wpdb->get_row($wpdb->prepare(
                "SELECT member_grade, billing_expiry_date FROM $member_table WHERE user_id = %d",
                $user_id
            ));
            
            if ($member_data) {
                // Map member_grade to premium_status
                // Grades: basic, premium, trial, pt_admin
                $grade = $member_data->member_grade;
                
                if ($grade === 'premium' || $grade === 'trial' || $grade === 'pt_admin') {
                    $premium_status = 'active';
                } else {
                    $premium_status = 'free'; // basic maps to free/basic
                }
                
                // Override expiry if present
                if (!empty($member_data->billing_expiry_date)) {
                    $premium_until = $member_data->billing_expiry_date;
                }
                
                // Store grade for label logic
                $db_grade = $grade;
            }
        }
        
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

        $grade_label = 'Basic';
        $is_admin = false;

        // Determine Label
        if (isset($db_grade)) {
            // If we have explicit DB grade, use it
            if ($db_grade === 'pt_admin') {
                $grade_label = 'Admin';
                $is_admin = true;
                $premium_status = 'active';
            } elseif ($db_grade === 'premium') {
                $grade_label = 'Premium';
            } elseif ($db_grade === 'trial') {
                $grade_label = 'Trial';
            } else {
                $grade_label = 'Basic';
            }
        } else {
            // Fallback to meta-based logic
            if (class_exists('\PTG\Platform\Permissions') && \PTG\Platform\Permissions::is_pt_admin($user_id)) {
                $grade_label = 'Admin';
                $premium_status = 'active';
                $is_admin = true;
            } elseif ($premium_status === 'active') {
                $grade_label = 'Premium';
            } elseif (!empty($premium_status) && $premium_status !== 'free') {
                $grade_label = ucfirst($premium_status);
            }
        }

        // 'free' status in DB should be displayed as 'Basic'
        if ($grade_label === 'Free') {
            $grade_label = 'Basic';
        }

        $premium_data = [
            'status' => $premium_status === 'active' ? 'active' : 'free',
            'grade'  => $grade_label,
            'expiry' => $is_admin ? null : $expiry_date
        ];

        // 2. Today's Tasks (Reviewer integration) & Bookmarks (쿼리 통합)
        // global $wpdb; // Already declared above
        $review_count = 0;
        $bookmark_count = 0;
        
        // 테이블 이름 확인 (Prefix 지원)
        $table_states = self::get_table_name('ptgates_user_states');
        $table_schedule = self::get_table_name('ptgates_review_schedule');
        
        $bookmark_count = 0;
        $review_count = 0;

        if (self::table_exists($table_states)) {
            $wpdb->suppress_errors(true);
            
            // 1. 북마크 카운트 (기존 로직 유지)
            $bookmark_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_states WHERE user_id = %d AND bookmarked = 1",
                $user_id
            ));
            
            // 2. 복습 카운트 (UNION: 기존 needs_review + 스케줄된 복습)
            /*
             * "Review Only and Wrong Answers Only... OR condition"
             * Dashboard 표시용으로는 "복습해야 할 문제 수"를 보여줘야 함.
             * -> needs_review=1 OR (scheduled AND due_date <= TODAY)
             * 중복 제거를 위해 UNION 사용
             */
            $today = current_time('Y-m-d');
            
            $query_union = "
                SELECT question_id FROM $table_states WHERE user_id = %d AND needs_review = 1
            ";
            $args = [$user_id];
            
            if (self::table_exists($table_schedule)) {
                $query_union .= " UNION SELECT question_id FROM $table_schedule WHERE user_id = %d AND status = 'scheduled' AND due_date <= %s";
                $args[] = $user_id;
                $args[] = $today;
            }
            
            $final_query = "SELECT COUNT(*) FROM ($query_union) as combined";
            
            $review_count = (int)$wpdb->get_var($wpdb->prepare($final_query, ...$args));
            
            $wpdb->suppress_errors(false);
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



        // 5. Additional Stats for Dashboard (Study, Flashcards, My Note)
        $study_progress_count = 0;
        $flashcard_total = 0;
        $flashcard_due = 0;
        $mynote_count = 0;

        // Study Progress (study_count > 0) 및 Quiz Count (quiz_count > 0)
        $quiz_progress_count = 0;
        if (self::table_exists($table_states)) {
            $wpdb->suppress_errors(true);
            $study_progress_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_states WHERE user_id = %d AND study_count > 0",
                $user_id
            ));
            $quiz_progress_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_states WHERE user_id = %d AND quiz_count > 0",
                $user_id
            ));
            $wpdb->suppress_errors(false);
        }

        // Flashcards (Total & Due) - Only "Flashcard Only" sets
        // Logic: set_id = 1 OR set_name NOT LIKE '% [R]' AND set_name NOT LIKE '%[랜덤]%'
        $table_flashcards = self::get_table_name('ptgates_flashcards');
        $table_sets = self::get_table_name('ptgates_flashcard_sets');
        
        if (self::table_exists($table_flashcards) && self::table_exists($table_sets)) {
            $wpdb->suppress_errors(true);
            $flashcard_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(c.card_id) as total,
                    COUNT(CASE WHEN (c.next_due_date IS NULL OR c.next_due_date <= CURDATE()) THEN 1 END) as due
                 FROM $table_flashcards c
                 JOIN $table_sets s ON c.set_id = s.set_id
                 WHERE c.user_id = %d
                 AND (s.user_id = %d OR s.set_id = 1)",
                $user_id,
                $user_id
            ));
            $wpdb->suppress_errors(false);
            
            if ($flashcard_stats) {
                $flashcard_total = (int)$flashcard_stats->total;
                $flashcard_due = (int)$flashcard_stats->due;
            }
        }

        // My Note Count
        $table_memos = self::get_table_name('ptgates_user_memos');
        if (self::table_exists($table_memos)) {
            $wpdb->suppress_errors(true);
            $mynote_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_memos WHERE user_id = %d",
                $user_id
            ));
            $wpdb->suppress_errors(false);
        }

        // 6. Billing History
        $billing_history = [];
        $table_billing = self::get_table_name('ptgates_billing_history');
        if (self::table_exists($table_billing)) {
            $wpdb->suppress_errors(true);
            $billing_history = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_billing WHERE user_id = %d ORDER BY transaction_date DESC",
                $user_id
            ));
            $wpdb->suppress_errors(false);
        }

        // Limit Calculation (Migrated from template for performance)
        $limits = [
            'study' => 10,
            'quiz' => 0,
            'flashcard' => 0
        ];

        // 1. Study Limits
        $study_conf = get_option('ptg_conf_study', []);
        $study_limit_guest = isset($study_conf['LIMIT_GUEST_VIEW']) ? (int)$study_conf['LIMIT_GUEST_VIEW'] : 10;
        $study_limit_free = isset($study_conf['LIMIT_FREE_VIEW']) ? (int)$study_conf['LIMIT_FREE_VIEW'] : 20;

        $limits['study'] = $study_limit_guest;
        if ($grade_label === 'Basic' || $grade_label === 'Trial') {
             $limits['study'] = $study_limit_free;
        } elseif ($grade_label === 'Premium' || $grade_label === 'Admin') {
             $limits['study'] = 999999;
        }
        
        // 2. Quiz Limits
        $quiz_conf = get_option('ptg_conf_quiz', []);
        $quiz_limit_basic = isset($quiz_conf['LIMIT_QUIZ_QUESTIONS']) ? (int)$quiz_conf['LIMIT_QUIZ_QUESTIONS'] : 20;
        $quiz_limit_trial = isset($quiz_conf['LIMIT_TRIAL_QUESTIONS']) ? (int)$quiz_conf['LIMIT_TRIAL_QUESTIONS'] : 50;

        if ($grade_label === 'Basic') {
            $limits['quiz'] = $quiz_limit_basic;
        } elseif ($grade_label === 'Trial') {
            $limits['quiz'] = $quiz_limit_trial;
        } elseif ($grade_label === 'Premium' || $grade_label === 'Admin') {
            $limits['quiz'] = 999999;
        }

        // 3. Flashcards Limits
        $flash_conf = get_option('ptg_conf_flash', []);
        $flash_limit_basic = isset($flash_conf['LIMIT_BASIC_CARDS']) ? (int)$flash_conf['LIMIT_BASIC_CARDS'] : 20;
        $flash_limit_trial = isset($flash_conf['LIMIT_TRIAL_CARDS']) ? (int)$flash_conf['LIMIT_TRIAL_CARDS'] : 50;
        
        if ($grade_label === 'Basic') {
            $limits['flashcard'] = $flash_limit_basic;
        } elseif ($grade_label === 'Trial') {
            $limits['flashcard'] = $flash_limit_trial;
        } elseif ($grade_label === 'Premium' || $grade_label === 'Admin') {
            $limits['flashcard'] = 999999;
        }

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
                // New Data
                'study_progress' => $study_progress_count,
                'quiz_progress' => $quiz_progress_count,
                'flashcard' => [
                    'total' => $flashcard_total,
                    'due' => $flashcard_due
                ],
                'mynote_count' => $mynote_count,
                'billing_history' => $billing_history,
                'limits' => $limits,
                'limits' => $limits,
                'available_years' => (function() {
                    if (class_exists('PTG_DB')) {
                        return \PTG_DB::get_available_years();
                    }
                    $file = WP_PLUGIN_DIR . '/9000-ptgates-exam-questions/includes/class-ptg-db.php';
                    if (file_exists($file)) {
                        require_once $file;
                        if (class_exists('PTG_DB')) {
                            return \PTG_DB::get_available_years();
                        }
                    }
                    return [];
                })()
            ];

            // 캐싱: 60초간 저장 (사용자 요청: 60~120초)
            set_transient($cache_key, $response_data, 60);
            
            $response = rest_ensure_response($response_data);
            
            // Timing Calculation
            global $ptg_server_timings;
            $api_start = $start_time;
            $now = microtime(true);
            
            $boot_dur = isset($ptg_server_timings['plugins_loaded']) ? ($ptg_server_timings['plugins_loaded'] - $ptg_server_timings['start']) * 1000 : 0;
            $plugin_dur = (isset($ptg_server_timings['setup_theme']) && isset($ptg_server_timings['plugins_loaded'])) ? ($ptg_server_timings['setup_theme'] - $ptg_server_timings['plugins_loaded']) * 1000 : 0;
            $theme_dur = (isset($ptg_server_timings['init']) && isset($ptg_server_timings['setup_theme'])) ? ($ptg_server_timings['init'] - $ptg_server_timings['setup_theme']) * 1000 : 0;
            $wp_total = ($api_start - $ptg_server_timings['start']) * 1000;
            $api_total = ($now - $api_start) * 1000;

            $response->header('X-PTG-Cache', 'MISS');
            $response->header('Server-Timing', 
                'boot;desc="WP Boot";dur=' . $boot_dur . 
                ', plugins;desc="Plugins";dur=' . $plugin_dur . 
                ', theme;desc="Theme";dur=' . $theme_dur . 
                ', wp;desc="WP Overhead";dur=' . $wp_total .
                ', calc;desc="Calculation";dur=' . $api_total . 
                ', api;desc="API Exec";dur=' . $api_total
            );
            
            return $response;
            
        } catch (\Throwable $e) {
            // 출력 버퍼 정리
            ob_end_clean();
            
            // 오류 로깅 (디버그 모드에서만)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Dashboard New API Error: ' . $e->getMessage());
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
     * 사용자 데이터 초기화 (ptgates_user_states, ptgates_user_results 삭제)
     */
    public static function reset_user_data($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new \WP_Error('unauthorized', '로그인이 필요합니다.', ['status' => 401]);
        }
        
        // 프리미엄 또는 Admin 멤버십 확인 (status === "active" AND (grade === "Premium" OR grade === "Admin"))
        $is_premium_or_admin = false;
        $premium_status = 'free';
        $grade_label = 'Basic';
        
        // ptgates_user_member 테이블 확인
        global $wpdb;
        $member_table = self::get_table_name('ptgates_user_member');
        if (self::table_exists($member_table)) {
            $member_data = $wpdb->get_row($wpdb->prepare(
                "SELECT member_grade, billing_expiry_date FROM $member_table WHERE user_id = %d",
                $user_id
            ));
            
            if ($member_data) {
                $grade = $member_data->member_grade;
                
                if ($grade === 'premium' || $grade === 'trial' || $grade === 'pt_admin') {
                    $premium_status = 'active';
                }
                
                if ($grade === 'pt_admin') {
                    $grade_label = 'Admin';
                } elseif ($grade === 'premium') {
                    $grade_label = 'Premium';
                }
            }
        }
        
        // Admin 권한 확인
        if (class_exists('\PTG\Platform\Permissions') && \PTG\Platform\Permissions::is_pt_admin($user_id)) {
            $premium_status = 'active';
            $grade_label = 'Admin';
        }
        
        // 프리미엄 상태 확인 (user_meta)
        if ($premium_status !== 'active') {
            $meta_premium_status = get_user_meta($user_id, 'ptg_premium_status', true);
            if ($meta_premium_status === 'active') {
                $premium_status = 'active';
                if ($grade_label === 'Basic') {
                    $grade_label = 'Premium';
                }
            }
        }
        
        // 조건 확인: status === "active" AND (grade === "Premium" OR grade === "Admin")
        if ($premium_status === 'active' && ($grade_label === 'Premium' || $grade_label === 'Admin')) {
            $is_premium_or_admin = true;
        }
        
        if (!$is_premium_or_admin) {
            return new \WP_Error('forbidden', '프리미엄 멤버 또는 Admin 멤버십만 사용할 수 있습니다.', ['status' => 403]);
        }
        
        // 트랜잭션 시작
        $wpdb->query('START TRANSACTION');
        
        try {
            // ptgates_user_states 삭제
            $states_table = self::get_table_name('ptgates_user_states');
            if (self::table_exists($states_table)) {
                $deleted_states = $wpdb->delete(
                    $states_table,
                    ['user_id' => $user_id],
                    ['%d']
                );
                
                if ($deleted_states === false) {
                    throw new \Exception('ptgates_user_states 삭제 실패');
                }
            }
            
            // ptgates_user_results 삭제
            $results_table = self::get_table_name('ptgates_user_results');
            if (self::table_exists($results_table)) {
                $deleted_results = $wpdb->delete(
                    $results_table,
                    ['user_id' => $user_id],
                    ['%d']
                );
                
                if ($deleted_results === false) {
                    throw new \Exception('ptgates_user_results 삭제 실패');
                }
            }
            
            // ptgates_flashcards 삭제 (해당 사용자의 모든 카드)
            $flashcards_table = self::get_table_name('ptgates_flashcards');
            $deleted_flashcards = 0;
            if (self::table_exists($flashcards_table)) {
                $deleted_flashcards = $wpdb->delete(
                    $flashcards_table,
                    ['user_id' => $user_id],
                    ['%d']
                );
                
                if ($deleted_flashcards === false) {
                    throw new \Exception('ptgates_flashcards 삭제 실패');
                }
            }
            
            // ptgates_flashcard_sets 삭제 (해당 사용자가 만든 세트만, set_id = 1은 공용 세트이므로 제외)
            $flashcard_sets_table = self::get_table_name('ptgates_flashcard_sets');
            $deleted_flashcard_sets = 0;
            if (self::table_exists($flashcard_sets_table)) {
                // 사용자가 만든 세트만 삭제 (set_id = 1은 공용 세트이므로 제외)
                $deleted_flashcard_sets = $wpdb->query($wpdb->prepare(
                    "DELETE FROM $flashcard_sets_table WHERE user_id = %d AND set_id != 1",
                    $user_id
                ));
                
                if ($deleted_flashcard_sets === false) {
                    throw new \Exception('ptgates_flashcard_sets 삭제 실패');
                }
            }
            
            // ptgates_user_memos 삭제 (마이노트 메모)
            $memos_table = self::get_table_name('ptgates_user_memos');
            $deleted_memos = 0;
            if (self::table_exists($memos_table)) {
                $deleted_memos = $wpdb->delete(
                    $memos_table,
                    ['user_id' => $user_id],
                    ['%d']
                );
                
                if ($deleted_memos === false) {
                    throw new \Exception('ptgates_user_memos 삭제 실패');
                }
            }
            
            // ptgates_user_drawings 삭제 (드로잉 데이터)
            $drawings_table = self::get_table_name('ptgates_user_drawings');
            $deleted_drawings = 0;
            if (self::table_exists($drawings_table)) {
                $deleted_drawings = $wpdb->delete(
                    $drawings_table,
                    ['user_id' => $user_id],
                    ['%d']
                );
                
                if ($deleted_drawings === false) {
                    throw new \Exception('ptgates_user_drawings 삭제 실패');
                }
            }
            
            // 트랜잭션 커밋
            $wpdb->query('COMMIT');
            
            // 모든 관련 캐시 삭제
            $cache_key = 'ptg_dashboard_new_summary_' . $user_id;
            delete_transient($cache_key);
            
            // wp_cache도 삭제 (혹시 사용되는 경우 대비)
            if (function_exists('wp_cache_delete')) {
                wp_cache_delete($cache_key, 'ptg_dashboard_new');
            }
            
            return rest_ensure_response([
                'success' => true,
                'message' => '데이터가 성공적으로 초기화되었습니다.',
                'deleted_states' => isset($deleted_states) ? $deleted_states : 0,
                'deleted_results' => isset($deleted_results) ? $deleted_results : 0,
                'deleted_flashcards' => $deleted_flashcards,
                'deleted_flashcard_sets' => $deleted_flashcard_sets,
                'deleted_memos' => $deleted_memos,
                'deleted_drawings' => $deleted_drawings,
            ]);
            
        } catch (\Exception $e) {
            // 트랜잭션 롤백
            $wpdb->query('ROLLBACK');
            
            return new \WP_Error('reset_failed', '데이터 초기화 중 오류가 발생했습니다: ' . $e->getMessage(), ['status' => 500]);
        }
    }
}
