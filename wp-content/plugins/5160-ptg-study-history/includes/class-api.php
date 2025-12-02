<?php
namespace PTG\StudyHistory;

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
        register_rest_route(self::REST_NAMESPACE, '/study-history', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_study_history'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
    }

    public static function get_study_history($request) {
        $user_id = get_current_user_id();
        
        // 캐싱: 5분간 저장
        $cache_key = 'ptg_study_history_' . $user_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $records = self::build_study_history($user_id);
        
        set_transient($cache_key, $records, 5 * MINUTE_IN_SECONDS);
        
        return rest_ensure_response($records);
    }

    /**
     * 과목별 학습 기록 데이터를 생성
     */
    private static function build_study_history($user_id) {
        // 과목별 총계는 날짜 제한 없이 전체 데이터 사용
        $study_totals = self::fetch_subject_totals($user_id, 'study_count');
        $quiz_totals  = self::fetch_subject_totals($user_id, 'quiz_count');

        return [
            'subjects' => self::format_subject_totals($study_totals, $quiz_totals),
        ];
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
                'id'          => 'ptg-law',
                'description' => '의료법 · 의료기사법 · 노인복지법 · 장애인복지법 · 건보법',
            ],
        ];
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
