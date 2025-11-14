<?php
/**
 * PTGates Platform Migration Class
 * 
 * 데이터베이스 마이그레이션 관리
 */

namespace PTG\Platform;

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class Migration {
    
    /**
     * 마이그레이션 실행
     */
    public static function run_migrations() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 1. 교시 세션 테이블
        self::create_exam_sessions_table($charset_collate);
        
        // 2. 세션 항목 테이블
        self::create_exam_session_items_table($charset_collate);
        
        // 3. 사용자 상태 테이블
        self::create_user_states_table($charset_collate);
        
        // 4. 사용자 메모 테이블
        self::create_user_notes_table($charset_collate);
        
        // 5. 사용자 드로잉 테이블
        self::create_user_drawings_table($charset_collate);
        
        // 6. 복습 스케줄 테이블
        self::create_review_schedule_table($charset_collate);
        
        // 7. 암기카드 세트 테이블
        self::create_flashcard_sets_table($charset_collate);
        
        // 8. 암기카드 테이블
        self::create_flashcards_table($charset_collate);
        
        // 9. 하이라이트 테이블
        self::create_highlights_table($charset_collate);
        
        // 10. 모의고사 프리셋 테이블
        self::create_exam_presets_table($charset_collate);
    }
    
    /**
     * 교시 세션 테이블 생성
     */
    private static function create_exam_sessions_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ptgates_exam_sessions';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `session_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `exam_course` varchar(50) NOT NULL,
            `time_limit_minutes` int(10) unsigned DEFAULT NULL,
            `is_unlimited` tinyint(1) NOT NULL DEFAULT 0,
            `status` enum('pending','active','submitted','expired') NOT NULL DEFAULT 'pending',
            `started_at` datetime DEFAULT NULL,
            `ends_at` datetime DEFAULT NULL,
            `submitted_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`session_id`),
            KEY `idx_user_status` (`user_id`,`status`),
            KEY `idx_course_status` (`exam_course`,`status`)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 세션 항목 테이블 생성
     */
    private static function create_exam_session_items_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ptgates_exam_session_items';
        $sessions_table = $wpdb->prefix . 'ptgates_exam_sessions';
        // 레거시 문제 테이블은 prefix 없이 고정
        $questions_table = 'ptgates_questions';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `item_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `session_id` bigint(20) unsigned NOT NULL,
            `question_id` bigint(20) unsigned NOT NULL,
            `order_index` int(10) unsigned NOT NULL,
            `user_answer` varchar(255) DEFAULT NULL,
            `is_correct` tinyint(1) DEFAULT NULL,
            `elapsed_time` int(10) unsigned DEFAULT NULL,
            `answered_at` datetime DEFAULT NULL,
            PRIMARY KEY (`item_id`),
            UNIQUE KEY `uq_session_question` (`session_id`,`question_id`),
            KEY `idx_session_order` (`session_id`,`order_index`),
            KEY `idx_question` (`question_id`),
            CONSTRAINT `fk_es_items_session` FOREIGN KEY (`session_id`) REFERENCES `{$sessions_table}` (`session_id`) ON DELETE CASCADE,
            CONSTRAINT `fk_es_items_question` FOREIGN KEY (`question_id`) REFERENCES `{$questions_table}` (`question_id`) ON DELETE CASCADE
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 사용자 상태 테이블 생성
     */
    private static function create_user_states_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ptgates_user_states';
        // 레거시 문제 테이블은 prefix 없이 고정
        $questions_table = 'ptgates_questions';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `user_id` bigint(20) unsigned NOT NULL,
            `question_id` bigint(20) unsigned NOT NULL,
            `bookmarked` tinyint(1) NOT NULL DEFAULT 0,
            `needs_review` tinyint(1) NOT NULL DEFAULT 0,
            `last_result` enum('correct','wrong') DEFAULT NULL,
            `last_answer` varchar(255) DEFAULT NULL,
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`user_id`,`question_id`),
            KEY `idx_flags` (`bookmarked`,`needs_review`),
            KEY `idx_last_result` (`last_result`),
            CONSTRAINT `fk_states_question` FOREIGN KEY (`question_id`) REFERENCES `{$questions_table}` (`question_id`) ON DELETE CASCADE
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 사용자 메모 테이블 생성
     */
    private static function create_user_notes_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ptgates_user_notes';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `note_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `ref_type` enum('question','theory','notebook') NOT NULL DEFAULT 'question',
            `ref_id` bigint(20) unsigned NOT NULL COMMENT 'question_id 또는 이론ID 등',
            `text` longtext NOT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`note_id`),
            KEY `idx_user_ref` (`user_id`,`ref_type`,`ref_id`)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 사용자 드로잉 테이블 생성
     */
    private static function create_user_drawings_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ptgates_user_drawings';
        // 레거시 문제 테이블은 prefix 없이 고정
        $questions_table = 'ptgates_questions';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `drawing_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `question_id` bigint(20) unsigned NOT NULL,
            `format` enum('json','svg') NOT NULL DEFAULT 'json',
            `data` longtext NOT NULL,
            `width` int(10) unsigned DEFAULT NULL,
            `height` int(10) unsigned DEFAULT NULL,
            `device` varchar(100) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`drawing_id`),
            KEY `idx_user_question` (`user_id`,`question_id`),
            CONSTRAINT `fk_drawings_question` FOREIGN KEY (`question_id`) REFERENCES `{$questions_table}` (`question_id`) ON DELETE CASCADE
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 복습 스케줄 테이블 생성
     */
    private static function create_review_schedule_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ptgates_review_schedule';
        // 레거시 테이블은 prefix 없이 고정
        $questions_table = 'ptgates_questions';
        $results_table = 'ptgates_user_results';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `schedule_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `question_id` bigint(20) unsigned NOT NULL,
            `origin_result_id` bigint(20) unsigned DEFAULT NULL,
            `due_date` date NOT NULL,
            `status` enum('pending','shown','done','skipped') NOT NULL DEFAULT 'pending',
            `shown_at` datetime DEFAULT NULL,
            `done_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`schedule_id`),
            KEY `idx_user_due` (`user_id`,`due_date`),
            KEY `idx_user_status_due` (`user_id`,`status`,`due_date`),
            KEY `idx_question` (`question_id`),
            CONSTRAINT `fk_rs_question` FOREIGN KEY (`question_id`) REFERENCES `{$questions_table}` (`question_id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rs_origin_result` FOREIGN KEY (`origin_result_id`) REFERENCES `{$results_table}` (`result_id`) ON DELETE SET NULL
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 암기카드 세트 테이블 생성
     */
    private static function create_flashcard_sets_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ptgates_flashcard_sets';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `set_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `name` varchar(100) NOT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`set_id`),
            KEY `idx_user_name` (`user_id`,`name`)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 암기카드 테이블 생성
     */
    private static function create_flashcards_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ptgates_flashcards';
        $sets_table = $wpdb->prefix . 'ptgates_flashcard_sets';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `card_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `set_id` bigint(20) unsigned DEFAULT NULL,
            `ref_type` enum('question','theory') NOT NULL,
            `ref_id` bigint(20) unsigned NOT NULL,
            `front_text` longtext NOT NULL,
            `back_text` longtext NOT NULL,
            `ease` tinyint(3) unsigned DEFAULT 2,
            `reviews` int(10) unsigned DEFAULT 0,
            `last_reviewed_at` datetime DEFAULT NULL,
            `next_due_date` date DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`card_id`),
            KEY `idx_user_set_due` (`user_id`,`set_id`,`next_due_date`),
            CONSTRAINT `fk_flashcards_set` FOREIGN KEY (`set_id`) REFERENCES `{$sets_table}` (`set_id`) ON DELETE SET NULL
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 하이라이트 테이블 생성
     */
    private static function create_highlights_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ptgates_highlights';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `highlight_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `theory_id` bigint(20) unsigned NOT NULL,
            `range_json` text NOT NULL,
            `color` varchar(16) DEFAULT '#FFF59D',
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`highlight_id`),
            KEY `idx_user_theory` (`user_id`,`theory_id`)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 모의고사 프리셋 테이블 생성
     */
    private static function create_exam_presets_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ptgates_exam_presets';
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `preset_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `title` varchar(100) NOT NULL,
            `filters_json` json NOT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`preset_id`),
            KEY `idx_user_title` (`user_id`,`title`)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

