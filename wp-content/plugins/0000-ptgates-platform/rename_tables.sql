-- PTGates 테이블명 변경 쿼리
-- wp_ptgates_* → ptgates_* (prefix 제거)
-- 실행 전 백업 권장!

-- 1. 플랫폼 코어 테이블
DROP TABLE IF EXISTS `ptgates_exam_sessions`;
RENAME TABLE `wp_ptgates_exam_sessions` TO `ptgates_exam_sessions`;

DROP TABLE IF EXISTS `ptgates_exam_session_items`;
RENAME TABLE `wp_ptgates_exam_session_items` TO `ptgates_exam_session_items`;

DROP TABLE IF EXISTS `ptgates_user_states`;
RENAME TABLE `wp_ptgates_user_states` TO `ptgates_user_states`;

DROP TABLE IF EXISTS `ptgates_user_notes`;
RENAME TABLE `wp_ptgates_user_notes` TO `ptgates_user_notes`;

DROP TABLE IF EXISTS `ptgates_user_drawings`;
RENAME TABLE `wp_ptgates_user_drawings` TO `ptgates_user_drawings`;

DROP TABLE IF EXISTS `ptgates_review_schedule`;
RENAME TABLE `wp_ptgates_review_schedule` TO `ptgates_review_schedule`;

DROP TABLE IF EXISTS `ptgates_highlights`;
RENAME TABLE `wp_ptgates_highlights` TO `ptgates_highlights`;

DROP TABLE IF EXISTS `ptgates_exam_presets`;
RENAME TABLE `wp_ptgates_exam_presets` TO `ptgates_exam_presets`;

-- 2. 플래시카드 테이블
DROP TABLE IF EXISTS `ptgates_flashcard_sets`;
RENAME TABLE `wp_ptgates_flashcard_sets` TO `ptgates_flashcard_sets`;

DROP TABLE IF EXISTS `ptgates_flashcards`;
RENAME TABLE `wp_ptgates_flashcards` TO `ptgates_flashcards`;

-- 참고: 기존 테이블 (이미 prefix 없음)
-- ptgates_questions
-- ptgates_categories
-- ptgates_user_results
