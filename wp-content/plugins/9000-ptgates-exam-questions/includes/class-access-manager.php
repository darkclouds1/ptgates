<?php
/**
 * PTGates Access Manager
 * 
 * Handles access control and usage limits for different modules based on user status.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PTG_Access_Manager {

    const LIMIT_QUIZ_GUEST_TOTAL = 10;
    const LIMIT_QUIZ_FREE_DAILY = 30;
    const LIMIT_REVIEWER_FREE_DAILY = 3;
    
    /**
     * Check if user has access to a specific module/action.
     * 
     * @param string $module 'quiz', 'study', 'reviewer'
     * @param int $user_id User ID (0 for guest)
     * @param mixed $context Additional context (e.g., content ID for study)
     * @return bool|WP_Error True if allowed, WP_Error if denied.
     */
    public static function check_access($module, $user_id = 0, $context = null) {
        // 1. Check for pt_admin first (Bypass all limits)
        if ($user_id > 0 && class_exists('\\PTG\\Platform\\Permissions')) {
            if (\PTG\Platform\Permissions::is_pt_admin($user_id)) {
                return true;
            }
        }

        $is_premium = self::is_premium($user_id);
        
        // Premium users bypass all limits
        if ($is_premium) {
            return true;
        }

        if ($user_id === 0) {
            return self::check_guest_access($module, $context);
        } else {
            return self::check_free_user_access($module, $user_id, $context);
        }
    }

    /**
     * Check access for guests.
     */
    private static function check_guest_access($module, $context) {
        switch ($module) {
            case 'quiz':
                // Guest Quiz Limit: 10 questions total
                // This relies on client-side cookie or session tracking passed to server?
                // Since this is a server-side check, we check a cookie if available.
                $count = isset($_COOKIE['ptg_guest_quiz_count']) ? intval($_COOKIE['ptg_guest_quiz_count']) : 0;
                if ($count >= self::LIMIT_QUIZ_GUEST_TOTAL) {
                    return new WP_Error('limit_reached', '비회원 무료 체험 10문제가 모두 소진되었습니다. 로그인 후 이용해주세요.', ['code' => 'guest_limit']);
                }
                return true;
            
            case 'study':
                // Guest Study: Allow list, block viewer
                // Context implies 'view_content' or similar
                if ($context === 'view_content') {
                    return new WP_Error('login_required', '학습 콘텐츠 상세 내용은 로그인 후 이용 가능합니다.', ['code' => 'login_required']);
                }
                return true; // Allow listing

            default:
                return true;
        }
    }

    /**
     * Check access for free users.
     */
    private static function check_free_user_access($module, $user_id, $context) {
        $today = date('Ymd');

        switch ($module) {
            case 'quiz':
                // Free Quiz Limit: 30 questions daily
                $count = (int) get_user_meta($user_id, "_ptg_daily_usage_quiz_{$today}", true);
                if ($count >= self::LIMIT_QUIZ_FREE_DAILY) {
                    return new WP_Error('limit_reached', '일일 무료 문제 풀이 한도(30문제)를 초과했습니다. 무제한 이용을 위해 프리미엄 멤버십을 구독하세요.', ['code' => 'daily_limit']);
                }
                return true;

            case 'reviewer':
                // Free Reviewer Limit: 3 questions daily
                $count = (int) get_user_meta($user_id, "_ptg_daily_usage_reviewer_{$today}", true);
                if ($count >= self::LIMIT_REVIEWER_FREE_DAILY) {
                    return new WP_Error('limit_reached', '일일 복습 한도(3문제)를 초과했습니다.', ['code' => 'daily_limit']);
                }
                return true;

            case 'study':
                // Free Study: Allow only 30% of content
                // Assuming context is an ID or index. 
                // Implementation: If context is numeric ID, we check if it falls in the allowed range.
                // For simplicity, let's assume we pass a 'percentage_check' flag or similar if the logic is complex.
                // Or if context is the 'order' of the content.
                
                // If context is 'view_content' and we have an ID, we need to know if it's in the first 30%.
                // Since we don't have the full list here, we might return a warning or handle it in the study plugin.
                // However, the prompt says "Study: 1100-ptgates-study에서 개념 로드맵의 30%만 콘텐츠를 로드".
                // This suggests the filtering happens at the query level.
                // So check_access might just return "restricted" status to the caller.
                return true; // The study plugin should handle the 30% logic using is_premium() check.

            default:
                return true;
        }
    }

    /**
     * Increment usage count for a module.
     */
    public static function increment_usage($module, $user_id, $amount = 1) {
        if ($user_id === 0) {
            if ($module === 'quiz') {
                $current = isset($_COOKIE['ptg_guest_quiz_count']) ? intval($_COOKIE['ptg_guest_quiz_count']) : 0;
                $new_count = $current + $amount;
                setcookie('ptg_guest_quiz_count', $new_count, time() + 86400 * 30, COOKIEPATH, COOKIE_DOMAIN);
                $_COOKIE['ptg_guest_quiz_count'] = $new_count;
            }
            return; 
        }

        if (self::is_premium($user_id)) {
            return; // Don't track premium usage (optimization)
        }

        $today = date('Ymd');
        $key = "_ptg_daily_usage_{$module}_{$today}";
        $current = (int) get_user_meta($user_id, $key, true);
        update_user_meta($user_id, $key, $current + $amount);
    }

    /**
     * Check if user is premium.
     */
    public static function is_premium($user_id) {
        if (!$user_id) return false;

        // Check for pt_admin (Super Admin) - Treat as premium
        if (class_exists('\\PTG\\Platform\\Permissions')) {
            if (\PTG\Platform\Permissions::is_pt_admin($user_id)) {
                return true;
            }
        }

        $status = get_user_meta($user_id, 'ptg_premium_status', true);
        return $status === 'active';
    }
    
    /**
     * Get remaining quota for free users.
     */
    public static function get_remaining_quota($module, $user_id) {
        if (!$user_id) return 0;
        if (self::is_premium($user_id)) return 999999;
        
        $today = date('Ymd');
        $key = "_ptg_daily_usage_{$module}_{$today}";
        $current = (int) get_user_meta($user_id, $key, true);
        
        switch ($module) {
            case 'quiz': return max(0, self::LIMIT_QUIZ_FREE_DAILY - $current);
            case 'reviewer': return max(0, self::LIMIT_REVIEWER_FREE_DAILY - $current);
            default: return 0;
        }
    }
}
