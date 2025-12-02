<?php
namespace PTG\Bookmarks;

if (!defined('ABSPATH')) {
    exit;
}

class API {

    public static function register_routes() {
        $controller = new self();
        $controller->register();
    }

    public function register() {
        $namespace = 'ptg-bookmarks/v1';

        register_rest_route($namespace, '/bookmarks', [
            'methods' => 'GET',
            'callback' => [$this, 'get_bookmarks'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public function check_permission() {
        return is_user_logged_in();
    }

    /**
     * 북마크 목록 조회
     */
    public function get_bookmarks($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('unauthorized', '로그인이 필요합니다.', ['status' => 401]);
        }

        $page = absint($request->get_param('page')) ?: 1;
        $per_page = absint($request->get_param('per_page')) ?: 20;
        $offset = ($page - 1) * $per_page;

        $states_table = self::get_table_name('ptgates_user_states');
        $questions_table = self::get_table_name('ptgates_questions');
        $categories_table = self::get_table_name('ptgates_categories');

        if (!self::table_exists($states_table) || !self::table_exists($questions_table) || !self::table_exists($categories_table)) {
            return rest_ensure_response([
                'items' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => 0,
            ]);
        }

        $wpdb->suppress_errors(true);

        try {
            // 1. 전체 개수 조회
            $count_sql = "
                SELECT COUNT(*)
                FROM {$states_table} s
                JOIN {$questions_table} q ON s.question_id = q.question_id
                WHERE s.user_id = %d
                AND s.bookmarked = 1
                AND q.is_active = 1
            ";
            
            $total_items = $wpdb->get_var($wpdb->prepare($count_sql, $user_id));
            $total_pages = ceil($total_items / $per_page);

            if ($total_items == 0) {
                return rest_ensure_response([
                    'items' => [],
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_pages' => 0,
                ]);
            }

            // 2. 목록 조회
            // 정렬: updated_at DESC (최신 북마크 순)
            
            $sql = "
                SELECT 
                    s.question_id,
                    s.updated_at as bookmarked_at,
                    q.content,
                    q.answer,
                    q.explanation,
                    c.subject,
                    c.exam_year,
                    c.exam_session
                FROM {$states_table} s
                JOIN {$questions_table} q ON s.question_id = q.question_id
                LEFT JOIN {$categories_table} c ON q.question_id = c.question_id
                WHERE s.user_id = %d
                AND s.bookmarked = 1
                AND q.is_active = 1
                ORDER BY s.updated_at DESC
                LIMIT %d OFFSET %d
            ";

            $results = $wpdb->get_results($wpdb->prepare($sql, $user_id, $per_page, $offset));

            $items = [];
            foreach ($results as $row) {
                // 문제 지문과 선택지 분리 및 포맷팅
                $content = $row->content ?? '';
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
                $explanation = $row->explanation ?? '';
                if (!empty($explanation)) {
                    $explanation = str_replace('_x000D_', '', $explanation);
                    $explanation = str_replace("\r\n", "\n", $explanation);
                    $explanation = str_replace("\r", "\n", $explanation);
                    $explanation = preg_replace('/\n{2,}/', "\n", $explanation);
                }

                // 과목명 처리 (DB의 subject는 세부과목일 수 있음)
                $subsubject_name = !empty($row->subject) ? trim($row->subject) : '';
                $subject_name = $subsubject_name; // 기본값
                
                // 상위 과목 찾기 (PTG\Quiz\Subjects 클래스가 있다면 사용)
                if (!empty($subsubject_name) && class_exists('\PTG\Quiz\Subjects')) {
                    $found_subject = \PTG\Quiz\Subjects::get_subject_from_subsubject($subsubject_name);
                    if (!empty($found_subject)) {
                        $subject_name = $found_subject;
                    }
                }

                $items[] = [
                    'question_id' => (int)$row->question_id,
                    'question_id_display' => 'id-' . $row->question_id,
                    'bookmarked_at' => $row->bookmarked_at,
                    'question_text' => $question_text,
                    'options' => $options,
                    'answer' => $row->answer,
                    'explanation' => $explanation,
                    'subject' => $subject_name,
                    'subsubject' => $subsubject_name,
                ];
            }

            return rest_ensure_response([
                'items' => $items,
                'total' => (int)$total_items,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => $total_pages,
            ]);

        } catch (\Exception $e) {
            return new \WP_Error('db_error', $e->getMessage(), ['status' => 500]);
        }
    }

    private static function get_table_name($table_name) {
        // The tables seem to be named exactly as 'ptgates_...' without the WP prefix in this environment.
        // If we use $wpdb->prefix, it might prepend 'wp_' (e.g. wp_ptgates_...) which doesn't exist.
        return $table_name;
    }

    private static function table_exists($table_name) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
    }
}
