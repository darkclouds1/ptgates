<?php
/**
 * PTGates Quiz Handler Class
 * 
 * 문제 풀이 로직 처리
 */

namespace PTG\Quiz;

use PTG\Platform\LegacyRepo;

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class Quiz_Handler {
    
    /**
     * 문제 정보 가져오기 (categories 포함)
     * 
     * @param int $question_id 문제 ID
     * @return array|null 문제 정보 또는 null
     */
    public static function get_question($question_id) {
        $questions = LegacyRepo::get_questions_with_categories(array(
            'question_id' => absint($question_id),
            'limit' => 1
        ));
        
        return !empty($questions) ? $questions[0] : null;
    }
    
    /**
     * 사용자 상태 정보 가져오기
     * 
     * @param int $user_id 사용자 ID
     * @param int $question_id 문제 ID
     * @return array|null 상태 정보 또는 null
     */
    public static function get_user_state($user_id, $question_id) {
        return \PTG\Platform\Repo::find_one('ptgates_user_states', array(
            'user_id' => absint($user_id),
            'question_id' => absint($question_id)
        ));
    }
}

