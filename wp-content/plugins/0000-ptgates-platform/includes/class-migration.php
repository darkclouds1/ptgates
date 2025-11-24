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
        
        // 디버깅: 마이그레이션 시작 로그
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (function_exists('ptg_error_log_kst')) {
                ptg_error_log_kst('[PTG Platform] 마이그레이션 시작');
            } else {
                error_log('[PTG Platform] 마이그레이션 시작');
            }
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 1. 교시 세션 테이블
        self::create_exam_sessions_table($charset_collate);
        
        // 2. 세션 항목 테이블
        self::create_exam_session_items_table($charset_collate);
        
        // 3. 사용자 상태 테이블
        self::create_user_states_table($charset_collate);
        
        // 4. 사용자 드로잉 테이블
        self::create_user_drawings_table($charset_collate);
        
        // 6. 복습 스케줄 테이블
        self::create_review_schedule_table($charset_collate);
        
        // 7. 하이라이트 테이블
        self::create_highlights_table($charset_collate);
        
        // 8. 모의고사 프리셋 테이블
        self::create_exam_presets_table($charset_collate);

        // 9. 개인/기관 멤버십 현황 테이블
        self::create_user_member_table($charset_collate);

        // 10. 결제 이력 테이블
        self::create_billing_history_table($charset_collate);

        // 11. B2B 기관/학과 정보 테이블
        self::create_organization_table($charset_collate);

        // 12. 기관-사용자 연결 테이블
        self::create_org_member_link_table($charset_collate);
        
        // 13. ptgates_user_states 테이블 트리거 생성
        self::create_user_states_triggers();
        
        // 정상 케이스는 로그를 남기지 않음
    }
    
    /**
     * 교시 세션 테이블 생성
     */
    private static function create_exam_sessions_table($charset_collate) {
        global $wpdb;
        
        $table_name = 'ptgates_exam_sessions';
        
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
        
        $table_name = 'ptgates_exam_session_items';
        $sessions_table = 'ptgates_exam_sessions';
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
        
        $table_name = 'ptgates_user_states';
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
     * 사용자 드로잉 테이블 생성
     */
    private static function create_user_drawings_table($charset_collate) {
        global $wpdb;
        
        $table_name = 'ptgates_user_drawings';
        
        // 외래키 제약 조건 없이 테이블 생성 (외래키는 나중에 추가 가능)
        // 외래키 제약 조건이 테이블 생성 실패의 원인이 될 수 있으므로 제거
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `drawing_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `question_id` bigint(20) unsigned NOT NULL,
            `is_answered` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '답안 제출 여부 (0: 미제출, 1: 제출)',
            `device_type` enum('pc','tablet','mobile') NOT NULL DEFAULT 'pc' COMMENT '기기 타입 (pc: 데스크톱/노트북, tablet: 태블릿, mobile: 스마트폰)',
            `format` enum('json','svg') NOT NULL DEFAULT 'json',
            `data` longtext NOT NULL,
            `width` int(10) unsigned DEFAULT NULL,
            `height` int(10) unsigned DEFAULT NULL,
            `device` varchar(100) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`drawing_id`),
            UNIQUE KEY `uq_user_question_answered_device` (`user_id`,`question_id`,`is_answered`,`device_type`),
            KEY `idx_user_question` (`user_id`,`question_id`),
            KEY `idx_user_question_answered` (`user_id`,`question_id`,`is_answered`),
            KEY `idx_user_question_device` (`user_id`,`question_id`,`device_type`),
            KEY `idx_question_id` (`question_id`)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // dbDelta 실행
        $result = dbDelta($sql);
        
        // 기존 테이블에 is_answered 컬럼 추가 (마이그레이션)
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'is_answered'
        ));
        
        if (empty($column_exists)) {
            $alter_sql = "ALTER TABLE `{$table_name}` 
                ADD COLUMN `is_answered` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '답안 제출 여부 (0: 미제출, 1: 제출)' AFTER `question_id`,
                ADD KEY `idx_user_question_answered` (`user_id`,`question_id`,`is_answered`)";
            
            $wpdb->query($alter_sql);
            
            // 디버깅: 컬럼 추가 확인
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst('[PTG Platform] is_answered 컬럼 추가 오류: ' . $wpdb->last_error);
                    } else {
                        error_log('[PTG Platform] is_answered 컬럼 추가 오류: ' . $wpdb->last_error);
                    }
                }
                // 정상 케이스는 로그를 남기지 않음
            }
        }
        
        // 기존 테이블에 device_type 컬럼 추가 (마이그레이션)
        $device_type_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'device_type'
        ));
        
        if (empty($device_type_exists)) {
            // device_type 컬럼 추가 (기본값: 'pc')
            $alter_sql = "ALTER TABLE `{$table_name}` 
                ADD COLUMN `device_type` enum('pc','tablet','mobile') NOT NULL DEFAULT 'pc' COMMENT '기기 타입 (pc: 데스크톱/노트북, tablet: 태블릿, mobile: 스마트폰)' AFTER `is_answered`,
                ADD KEY `idx_user_question_device` (`user_id`,`question_id`,`device_type`)";
            
            $wpdb->query($alter_sql);
            
            // 디버깅: 컬럼 추가 확인
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst('[PTG Platform] device_type 컬럼 추가 오류: ' . $wpdb->last_error);
                    } else {
                        error_log('[PTG Platform] device_type 컬럼 추가 오류: ' . $wpdb->last_error);
                    }
                }
                // 정상 케이스는 로그를 남기지 않음
            }
        }
        
        // 기존 UNIQUE KEY 제거 (device_type이 추가되기 전)
        $old_unique_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW INDEX FROM `{$table_name}` WHERE Key_name = %s",
            'uq_user_question_answered'
        ));
        
        if (!empty($old_unique_exists)) {
            // 기존 UNIQUE KEY 제거
            $drop_sql = "ALTER TABLE `{$table_name}` DROP INDEX `uq_user_question_answered`";
            $wpdb->query($drop_sql);
            
            // 디버깅: 기존 UNIQUE KEY 제거 확인
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst('[PTG Platform] 기존 UNIQUE KEY 제거 오류: ' . $wpdb->last_error);
                    } else {
                        error_log('[PTG Platform] 기존 UNIQUE KEY 제거 오류: ' . $wpdb->last_error);
                    }
                }
                // 정상 케이스는 로그를 남기지 않음
            }
        }
        
        // 새로운 UNIQUE KEY 추가 (device_type 포함)
        $new_unique_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW INDEX FROM `{$table_name}` WHERE Key_name = %s",
            'uq_user_question_answered_device'
        ));
        
        if (empty($new_unique_exists)) {
            // 기존에 중복된 레코드가 있을 수 있으므로 먼저 정리
            // (user_id, question_id, is_answered) 조합에서 각 device_type별로 최신 1개만 남기고 나머지 삭제
            // device_type이 없는 경우 기본값 'pc'로 설정
            $cleanup_sql = "UPDATE `{$table_name}` SET `device_type` = 'pc' WHERE `device_type` IS NULL OR `device_type` = ''";
            $wpdb->query($cleanup_sql);
            
            // 중복 레코드 정리 (device_type 포함)
            $cleanup_sql = "DELETE d1 FROM `{$table_name}` d1
                INNER JOIN (
                    SELECT MAX(`drawing_id`) as `max_id`, `user_id`, `question_id`, `is_answered`, `device_type`
                    FROM `{$table_name}`
                    GROUP BY `user_id`, `question_id`, `is_answered`, `device_type`
                    HAVING COUNT(*) > 1
                ) d2 ON d1.`user_id` = d2.`user_id` 
                    AND d1.`question_id` = d2.`question_id`
                    AND d1.`is_answered` = d2.`is_answered`
                    AND d1.`device_type` = d2.`device_type`
                    AND d1.`drawing_id` < d2.`max_id`";
            
            $wpdb->query($cleanup_sql);
            
            // 새로운 UNIQUE 제약 조건 추가 (device_type 포함)
            $unique_sql = "ALTER TABLE `{$table_name}` 
                ADD UNIQUE KEY `uq_user_question_answered_device` (`user_id`,`question_id`,`is_answered`,`device_type`)";
            
            $wpdb->query($unique_sql);
            
            // 디버깅: 새로운 UNIQUE 제약 조건 추가 확인
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst('[PTG Platform] 새로운 UNIQUE 제약 조건 추가 오류: ' . $wpdb->last_error);
                    } else {
                        error_log('[PTG Platform] 새로운 UNIQUE 제약 조건 추가 오류: ' . $wpdb->last_error);
                    }
                }
                // 정상 케이스는 로그를 남기지 않음
            }
        }
        
        // 디버깅: 테이블 생성 결과 확인 (오류 케이스만)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $existing = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
            if ($existing === $table_name) {
                // 정상 케이스는 로그를 남기지 않음
            } else {
                if (function_exists('ptg_error_log_kst')) {
                    ptg_error_log_kst('[PTG Platform] ⚠️ 드로잉 테이블 생성 실패: ' . $table_name);
                } else {
                    error_log('[PTG Platform] ⚠️ 드로잉 테이블 생성 실패: ' . $table_name);
                }
                if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst('[PTG Platform] SQL 오류: ' . $wpdb->last_error);
                        ptg_error_log_kst('[PTG Platform] SQL 쿼리: ' . $wpdb->last_query);
                    } else {
                        error_log('[PTG Platform] SQL 오류: ' . $wpdb->last_error);
                        error_log('[PTG Platform] SQL 쿼리: ' . $wpdb->last_query);
                    }
                }
            }
        }
    }
    
    /**
     * 복습 스케줄 테이블 생성
     */
    private static function create_review_schedule_table($charset_collate) {
        global $wpdb;
        
        $table_name = 'ptgates_review_schedule';
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
     * 하이라이트 테이블 생성
     */
    private static function create_highlights_table($charset_collate) {
        global $wpdb;
        
        $table_name = 'ptgates_highlights';
        
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
        
        $table_name = 'ptgates_exam_presets';
        
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

    /**
     * 개인/기관 멤버십 현황 테이블 생성
     */
    private static function create_user_member_table($charset_collate) {
        global $wpdb;

        $table_name = 'ptgates_user_member';

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `membership_source` varchar(20) NOT NULL DEFAULT 'individual' COMMENT '멤버십 획득 경로 (individual, b2b)',
            `org_id` bigint(20) unsigned NULL COMMENT '소속된 기관의 org_id (b2b 멤버십인 경우)',
            `member_grade` varchar(50) NOT NULL DEFAULT 'basic' COMMENT '회원의 현재 멤버십 등급 (basic, premium, trial 등)',
            `billing_status` varchar(20) NOT NULL DEFAULT 'active' COMMENT '결제 상태 (active, expired, pending, cancelled)',
            `billing_expiry_date` datetime NULL COMMENT '멤버십 만료일',
            `total_payments_krw` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '누적 결제 금액',
            `exam_count_total` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '총 생성 가능한 모의고사 횟수',
            `exam_count_used` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '사용한 모의고사 횟수',
            `study_count_total` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '총 학습 가능한 횟수 또는 시간 (플랜에 따라 정의)',
            `study_count_used` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '사용한 학습 횟수',
            `last_login` datetime NULL COMMENT '마지막 플러그인 학습/접속 시간',
            `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '계정 활성화 상태 (1=활성, 0=비활성/정지)',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_id` (`user_id`),
            KEY `member_grade_status` (`member_grade`, `billing_status`),
            KEY `org_id_idx` (`org_id`)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * 결제 이력 테이블 생성
     */
    private static function create_billing_history_table($charset_collate) {
        global $wpdb;

        $table_name = 'ptgates_billing_history';

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL COMMENT '결제 행위를 수행한 사용자 ID (개인 또는 기관 담당자)',
            `order_id` varchar(100) NOT NULL COMMENT '결제 시스템(PG사)의 고유 주문 번호',
            `pg_transaction_id` varchar(100) NULL COMMENT 'PG사에서 부여한 실제 거래 ID (영수증 ID)',
            `transaction_type` varchar(50) NOT NULL COMMENT '트랜잭션 유형 (purchase, renewal, refund, cancellation 등)',
            `product_name` varchar(255) NOT NULL COMMENT '결제한 상품/멤버십 이름',
            `payment_method` varchar(50) NOT NULL COMMENT '결제 수단 (card, transfer, kakao/naverpay 등)',
            `amount` decimal(10, 2) NOT NULL COMMENT '결제 금액',
            `currency` varchar(10) NOT NULL DEFAULT 'KRW',
            `status` varchar(20) NOT NULL COMMENT '결제 처리 상태 (paid, failed, refunded, pending)',
            `transaction_date` datetime NOT NULL COMMENT '결제 또는 트랜잭션 발생 시점',
            `memo` text NULL COMMENT '관리자용 특이사항 메모',
            PRIMARY KEY (`id`),
            UNIQUE KEY `order_id_unique` (`order_id`),
            KEY `user_id` (`user_id`),
            KEY `transaction_date` (`transaction_date`)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * B2B 기관/학과 정보 테이블 생성
     */
    private static function create_organization_table($charset_collate) {
        global $wpdb;

        $table_name = 'ptgates_organization';

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `org_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `org_name` varchar(255) NOT NULL COMMENT '기관/학과 공식 명칭',
            `contact_user_id` bigint(20) unsigned NULL COMMENT '기관 담당 관리자의 wp_users.ID',
            `org_email` varchar(100) NULL,
            `org_type` varchar(50) NOT NULL DEFAULT 'university' COMMENT '기관 유형 (university, school, company 등)',
            `member_limit` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '허용된 최대 등록 학생/사용자 수',
            `members_registered` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '현재 등록된 학생/사용자 수',
            `billing_plan` varchar(50) NOT NULL COMMENT '기관에 적용된 B2B 멤버십 플랜',
            `plan_expiry_date` datetime NULL COMMENT '기관 멤버십 만료일',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`org_id`),
            UNIQUE KEY `org_name` (`org_name`),
            KEY `contact_user_id` (`contact_user_id`)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * 기관-사용자 연결 테이블 생성
     */
    private static function create_org_member_link_table($charset_collate) {
        global $wpdb;

        $table_name = 'ptgates_org_member_link';

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `org_id` bigint(20) unsigned NOT NULL,
            `user_id` bigint(20) unsigned NOT NULL,
            `assignment_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '기관 멤버십에 할당된 시점',
            `expiry_date` datetime NULL COMMENT 'B2B 혜택 만료일 (사용자별)',
            `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '현재 B2B 혜택 적용 여부',
            PRIMARY KEY (`id`),
            UNIQUE KEY `org_user_unique` (`org_id`, `user_id`),
            KEY `user_id` (`user_id`)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * ptgates_user_states 테이블 트리거 생성
     * study_count 변경 시 last_study_date 자동 업데이트
     * quiz_count 변경 시 last_quiz_date 자동 업데이트
     */
    private static function create_user_states_triggers() {
        global $wpdb;
        
        $table_name = 'ptgates_user_states';
        
        // 테이블 존재 확인
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        if ($table_exists !== $table_name) {
            // 테이블이 없으면 트리거 생성 불가
            return;
        }
        
        // study_count 변경 시 last_study_date 업데이트 트리거 (UPDATE)
        $trigger_study_update = 'ptgates_update_last_study_date';
        $trigger_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TRIGGERS 
            WHERE TRIGGER_SCHEMA = %s 
            AND TRIGGER_NAME = %s",
            DB_NAME,
            $trigger_study_update
        ));
        
        if (!$trigger_exists) {
            // UPDATE 트리거 생성
            $sql_study_update = "CREATE TRIGGER `{$trigger_study_update}`
                BEFORE UPDATE ON `{$table_name}`
                FOR EACH ROW
                BEGIN
                    IF NEW.study_count != OLD.study_count THEN
                        SET NEW.updated_at = NOW();
                        SET NEW.last_study_date = NEW.updated_at;
                    END IF;
                END";
            
            $wpdb->query($sql_study_update);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst('[PTG Platform] study_count UPDATE 트리거 생성 오류: ' . $wpdb->last_error);
                    } else {
                        error_log('[PTG Platform] study_count UPDATE 트리거 생성 오류: ' . $wpdb->last_error);
                    }
                }
            }
        }
        
        // study_count 설정 시 last_study_date 업데이트 트리거 (INSERT)
        $trigger_study_insert = 'ptgates_insert_last_study_date';
        $trigger_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TRIGGERS 
            WHERE TRIGGER_SCHEMA = %s 
            AND TRIGGER_NAME = %s",
            DB_NAME,
            $trigger_study_insert
        ));
        
        if (!$trigger_exists) {
            // INSERT 트리거 생성
            $sql_study_insert = "CREATE TRIGGER `{$trigger_study_insert}`
                BEFORE INSERT ON `{$table_name}`
                FOR EACH ROW
                BEGIN
                    IF NEW.study_count > 0 THEN
                        SET NEW.updated_at = NOW();
                        SET NEW.last_study_date = NEW.updated_at;
                    END IF;
                END";
            
            $wpdb->query($sql_study_insert);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst('[PTG Platform] study_count INSERT 트리거 생성 오류: ' . $wpdb->last_error);
                    } else {
                        error_log('[PTG Platform] study_count INSERT 트리거 생성 오류: ' . $wpdb->last_error);
                    }
                }
            }
        }
        
        // quiz_count 변경 시 last_quiz_date 업데이트 트리거 (UPDATE)
        $trigger_quiz_update = 'ptgates_update_last_quiz_date';
        $trigger_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TRIGGERS 
            WHERE TRIGGER_SCHEMA = %s 
            AND TRIGGER_NAME = %s",
            DB_NAME,
            $trigger_quiz_update
        ));
        
        if (!$trigger_exists) {
            // UPDATE 트리거 생성
            $sql_quiz_update = "CREATE TRIGGER `{$trigger_quiz_update}`
                BEFORE UPDATE ON `{$table_name}`
                FOR EACH ROW
                BEGIN
                    IF NEW.quiz_count != OLD.quiz_count THEN
                        SET NEW.updated_at = NOW();
                        SET NEW.last_quiz_date = NEW.updated_at;
                    END IF;
                END";
            
            $wpdb->query($sql_quiz_update);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst('[PTG Platform] quiz_count UPDATE 트리거 생성 오류: ' . $wpdb->last_error);
                    } else {
                        error_log('[PTG Platform] quiz_count UPDATE 트리거 생성 오류: ' . $wpdb->last_error);
                    }
                }
            }
        }
        
        // quiz_count 설정 시 last_quiz_date 업데이트 트리거 (INSERT)
        $trigger_quiz_insert = 'ptgates_insert_last_quiz_date';
        $trigger_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TRIGGERS 
            WHERE TRIGGER_SCHEMA = %s 
            AND TRIGGER_NAME = %s",
            DB_NAME,
            $trigger_quiz_insert
        ));
        
        if (!$trigger_exists) {
            // INSERT 트리거 생성
            $sql_quiz_insert = "CREATE TRIGGER `{$trigger_quiz_insert}`
                BEFORE INSERT ON `{$table_name}`
                FOR EACH ROW
                BEGIN
                    IF NEW.quiz_count > 0 THEN
                        SET NEW.updated_at = NOW();
                        SET NEW.last_quiz_date = NEW.updated_at;
                    END IF;
                END";
            
            $wpdb->query($sql_quiz_insert);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (isset($wpdb->last_error) && !empty($wpdb->last_error)) {
                    if (function_exists('ptg_error_log_kst')) {
                        ptg_error_log_kst('[PTG Platform] quiz_count INSERT 트리거 생성 오류: ' . $wpdb->last_error);
                    } else {
                        error_log('[PTG Platform] quiz_count INSERT 트리거 생성 오류: ' . $wpdb->last_error);
                    }
                }
            }
        }
    }
}

