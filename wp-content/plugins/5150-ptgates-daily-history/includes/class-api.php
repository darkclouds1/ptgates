<?php
namespace PTG\DailyHistory;

use PTG\Quiz\Subjects;

if (!defined('ABSPATH')) {
    exit;
}

// 정적 과목 정의 클래스 로드 (호환성)
if (!class_exists('\PTG\Quiz\Subjects')) {
    $platform_subjects_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/includes/class-subjects.php';
    if (file_exists($platform_subjects_file) && is_readable($platform_subjects_file)) {
        require_once $platform_subjects_file;
    }
    if (!class_exists('\PTG\Quiz\Subjects')) {
        $subjects_file = WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php';
        if (file_exists($subjects_file) && is_readable($subjects_file)) {
            require_once $subjects_file;
        }
    }
}

class API {
    const REST_NAMESPACE = 'ptgates/v1';

    public static function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/daily-history', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_daily_history'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
    }

    public static function get_daily_history($request) {
        $user_id = get_current_user_id();
        
        // 캐싱: 5분간 저장
        $cache_key = 'ptg_daily_history_' . $user_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $records = self::build_learning_records($user_id);
        
        set_transient($cache_key, $records, 5 * MINUTE_IN_SECONDS);
        
        return rest_ensure_response($records);
    }

    /**
     * 학습 기록(Study/Quiz) 요약 데이터를 생성
     */
    private static function build_learning_records($user_id) {
        $study_rows = self::fetch_learning_rows($user_id, 'study_count', 'last_study_date');
        $quiz_rows  = self::fetch_learning_rows($user_id, 'quiz_count', 'last_quiz_date');

        return [
            'study'    => self::format_learning_rows($study_rows),
            'quiz'     => self::format_learning_rows($quiz_rows),
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
}
