# PTGates 실제 데이터베이스 구조 (확인 완료)

## 기존 테이블 구조 (변경 금지)

### 1. ptgates_questions
```sql
CREATE TABLE `ptgates_questions` (
  `question_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content` longtext NOT NULL COMMENT '문제 본문 전체 (지문, 보기, 이미지 경로 등 포함)',
  `answer` varchar(255) NOT NULL COMMENT '정답 (객관식 번호, 주관식 답)',
  `explanation` longtext DEFAULT NULL COMMENT '문제 해설 내용',
  `type` varchar(50) NOT NULL COMMENT '문제 유형 (예: 객관식, 주관식)',
  `difficulty` int(1) unsigned DEFAULT 2 COMMENT '난이도 (1:하, 2:중, 3:상)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '사용 여부 (1:활성, 0:비활성)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`question_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_type` (`type`),
  KEY `idx_difficulty` (`difficulty`)
) ENGINE=InnoDB AUTO_INCREMENT=570 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. ptgates_categories ✅ 실제 구조 확인됨
```sql
CREATE TABLE `ptgates_categories` (
  `category_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL COMMENT 'ptgates_questions 테이블의 외래키',
  `exam_year` int(4) unsigned NOT NULL COMMENT '시험 시행 연도 (예: 2024)',
  `exam_session` int(2) unsigned DEFAULT NULL COMMENT '시험 회차 (예: 52)',
  `exam_course` varchar(50) NOT NULL COMMENT '교시 구분 (예: 1교시, 2교시)',
  `subject` varchar(100) NOT NULL COMMENT '과목명 (예: 해부학, 물리치료학)',
  `source_company` varchar(100) DEFAULT NULL COMMENT '문제 출처 (요청하신 회사별 구분용)',
  PRIMARY KEY (`category_id`),
  KEY `idx_question_id` (`question_id`),
  KEY `idx_exam_meta` (`exam_year`,`exam_session`,`exam_course`),
  KEY `idx_subject` (`subject`),
  KEY `idx_year_subject` (`exam_year`,`subject`),
  KEY `idx_question_id_fast` (`question_id`),
  CONSTRAINT `ptgates_categories_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `ptgates_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=570 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**중요 사항:**
- `category_id`가 **Primary Key**입니다 (명세에는 없었지만 실제로는 PK)
- `question_id`는 Foreign Key (ptgates_questions 참조)
- 한 문제에 여러 분류 정보가 있을 수 있습니다 (1:N 관계)

### 3. ptgates_user_results
```sql
CREATE TABLE `ptgates_user_results` (
  `result_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `user_answer` varchar(255) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `elapsed_time` int(10) unsigned DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`result_id`),
  KEY `idx_user_question` (`user_id`,`question_id`),
  KEY `question_id` (`question_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_correct` (`user_id`,`is_correct`),
  KEY `idx_attempted_at` (`attempted_at`),
  CONSTRAINT `ptgates_user_results_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `ptgates_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 플랫폼 코어 테이블 (이미 존재하는 테이블)

다음 테이블들은 이미 생성되어 있습니다:
- `ptgates_exam_sessions`
- `ptgates_exam_session_items`
- `ptgates_user_states`
- `ptgates_user_notes`
- `ptgates_user_drawings`
- `ptgates_review_schedule`

## ptgates_user_states
```sql
CREATE TABLE `ptgates_user_states` (
  `user_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `bookmarked` tinyint(1) NOT NULL DEFAULT 0,
  `needs_review` tinyint(1) NOT NULL DEFAULT 0,
  `study_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '과목/Study 해설 보기 횟수',
  `quiz_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '학습/Quiz 진행 횟수',
  `last_result` enum('correct','wrong') DEFAULT NULL,
  `last_answer` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_study_date` datetime DEFAULT NULL COMMENT '마지막 과목/Study 진행 일시',
  `last_quiz_date` datetime DEFAULT NULL COMMENT '마지막 학습/Quiz 진행 일시',
  PRIMARY KEY (`user_id`,`question_id`),
  KEY `idx_flags` (`bookmarked`,`needs_review`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
```

## 개발 시 주의사항

1. **ptgates_categories 접근 시:**
   - `category_id`로 직접 조회 가능
   - `question_id`로 조회 시 여러 레코드가 반환될 수 있음 (1:N 관계)
   - JOIN 시 `ptgates_questions.question_id = ptgates_categories.question_id` 사용

2. **인덱스 활용:**
   - `exam_year`, `exam_session`, `exam_course` 복합 인덱스 활용
   - `exam_year`, `subject` 복합 인덱스 활용
   - 쿼리 최적화 시 인덱스 활용 고려

3. **외래키 제약:**
   - CASCADE DELETE 설정되어 있으므로 questions 삭제 시 관련 categories도 자동 삭제됨

