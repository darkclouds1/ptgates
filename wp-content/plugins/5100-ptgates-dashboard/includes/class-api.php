<?php
namespace PTG\Dashboard;

if (!defined('ABSPATH')) {
    exit;
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

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Dashboard] REST API bootstrapped');
        }
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
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PTG Dashboard] REST route registered: ptg-dash/v1/summary - ' . ($result ? 'success' : 'failed'));
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

        // 2. Today's Tasks (Reviewer integration)
        global $wpdb;
        $review_count = 0;
        
        // 테이블 이름 확인 (Prefix 지원)
        $table_states = self::get_table_name('ptgates_user_states');
        
        if (self::table_exists($table_states)) {
            $wpdb->suppress_errors(true);
            $review_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_states WHERE user_id = %d AND needs_review = 1",
                $user_id
            ));
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

        // 4. Recent Activity
        $recent_activity = [];
        
        if (self::table_exists($table_results) && self::table_exists($table_questions)) {
            $wpdb->suppress_errors(true);
            $recent_results = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*, q.content 
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
                    $content = strip_tags($row->content);
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

            // 출력 버퍼 정리 (오류 메시지 제거)
            ob_end_clean();
            
            return rest_ensure_response([
                'user_name' => wp_get_current_user()->display_name,
                'premium' => $premium_data,
                'today_reviews' => (int)$review_count,
                'progress' => $progress,
                'recent_activity' => $recent_activity
            ]);
            
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
}

