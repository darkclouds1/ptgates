# PTGates 데이터베이스 스키마 통합 문서

> **참조 기준**: 이 문서의 스키마는 실제 데이터베이스 덤프 파일(`ptgates_schema.txt`)을 기준으로 작성되었습니다.
> 최신 스키마 구조가 필요한 경우 `ptgates_schema.txt` 파일을 참조하세요.

---

## 📋 목차

1. [기본 테이블 (기존 테이블, 변경 금지)](#1-기본-테이블-기존-테이블-변경-금지)
2. [플랫폼 코어 테이블](#2-플랫폼-코어-테이블)
3. [모듈별 테이블](#3-모듈별-테이블)
4. [트리거 및 뷰](#4-트리거-및-뷰)
5. [테이블 관계도](#5-테이블-관계도)
6. [개발 시 주의사항](#6-개발-시-주의사항)

---

## 1. 기본 테이블 (기존 테이블, 변경 금지)

### 1.1 ptgates_questions

문제의 핵심 데이터를 저장하는 테이블입니다.

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
  `question_image` varchar(255) DEFAULT NULL COMMENT '문제 이미지 파일명 (예: 2921.jpg)',
  PRIMARY KEY (`question_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_type` (`type`),
  KEY `idx_difficulty` (`difficulty`),
  KEY `idx_question_active` (`question_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='물리치료사 국가고시 문제의 핵심 데이터 저장';
```

**주요 컬럼:**
- `question_id`: 문제 고유 ID (Primary Key)
- `content`: 문제 본문 전체 (지문, 보기, 이미지 경로 등 포함)
- `answer`: 정답 (객관식 번호 또는 주관식 답)
- `explanation`: 문제 해설 내용
- `type`: 문제 유형 (객관식, 주관식 등)
- `difficulty`: 난이도 (1:하, 2:중, 3:상)
- `is_active`: 사용 여부 (1:활성, 0:비활성)
- `question_image`: 문제 이미지 파일명

**변경 시 주의사항:**
- `question_id`는 모든 모듈에서 FK로 사용되므로 변경 불가
- `content`, `answer`, `explanation`은 여러 모듈에서 참조하므로 변경 시 영향도 분석 필수

---

### 1.2 ptgates_categories

문제의 분류 정보(연도, 과목, 출처 등)를 저장하는 테이블입니다.

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
  KEY `idx_question_subject` (`question_id`,`subject`),
  CONSTRAINT `ptgates_categories_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `ptgates_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='문제의 분류 정보(연도, 과목, 출처 등) 저장';
```

**주요 컬럼:**
- `category_id`: 분류 고유 ID (Primary Key) ⚠️ **실제 DB에서는 PK입니다**
- `question_id`: ptgates_questions 테이블의 외래키
- `exam_year`: 시험 시행 연도
- `exam_session`: 시험 회차 (NULL 또는 < 1000: 기출, >= 1000: 생성문항)
- `exam_course`: 교시 구분
- `subject`: 과목명
- `source_company`: 문제 출처 (회사별 구분용)

**중요 사항:**
- `category_id`가 **Primary Key**입니다
- 한 문제에 여러 분류 정보가 있을 수 있습니다 (1:N 관계)
- `exam_session` 필터링 로직이 여러 모듈에 있으므로 변경 시 모든 모듈 확인 필수

**기출문제 정책:**
- `exam_session < 1000`: 기출문제 (DB에 유지, 내부 분석용)
- `exam_session >= 1000`: 생성문항 (사용자 노출용)

---

### 1.3 ptgates_user_results

사용자별 문제 풀이 기록을 저장하는 테이블입니다.

```sql
CREATE TABLE `ptgates_user_results` (
  `result_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT '워드프레스 사용자 ID (Ultimate Member 연동)',
  `question_id` bigint(20) unsigned NOT NULL COMMENT '풀이한 문제 ID',
  `user_answer` varchar(255) DEFAULT NULL COMMENT '사용자가 선택/입력한 답',
  `is_correct` tinyint(1) NOT NULL COMMENT '정답 여부 (1:정답, 0:오답)',
  `elapsed_time` int(10) unsigned DEFAULT NULL COMMENT '문제 풀이에 소요된 시간 (초)',
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '풀이를 시도한 일시',
  PRIMARY KEY (`result_id`),
  KEY `idx_user_question` (`user_id`,`question_id`),
  KEY `question_id` (`question_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_correct` (`user_id`,`is_correct`),
  KEY `idx_attempted_at` (`attempted_at`),
  CONSTRAINT `ptgates_user_results_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `ptgates_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자별 문제 풀이 기록 저장';
```

**주요 컬럼:**
- `result_id`: 결과 고유 ID (Primary Key)
- `user_id`: 워드프레스 사용자 ID
- `question_id`: 풀이한 문제 ID
- `user_answer`: 사용자가 선택/입력한 답
- `is_correct`: 정답 여부 (1:정답, 0:오답)
- `elapsed_time`: 문제 풀이에 소요된 시간 (초)
- `attempted_at`: 풀이를 시도한 일시

**변경 시 주의사항:**
- `question_id` FK는 변경 불가
- 통계/분석 모듈에서 집계에 사용되므로 변경 시 영향도 분석 필수

---

## 2. 플랫폼 코어 테이블

### 2.1 ptgates_user_states

문항별 사용자 상태(북마크/복습/학습 횟수/최근 결과·답)를 저장하는 테이블입니다.

```sql
CREATE TABLE `ptgates_user_states` (
  `user_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `bookmarked` tinyint(1) NOT NULL DEFAULT 0,
  `needs_review` tinyint(1) NOT NULL DEFAULT 0,
  `study_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '과목/Study 해설 보기 횟수',
  `quiz_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '학습/Quiz 진행 횟수',
`review_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '복습(Reviewer) 진행 횟수',
  `last_result` enum('correct','wrong') DEFAULT NULL,
  `last_answer` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_study_date` datetime DEFAULT NULL COMMENT '마지막 과목/Study 진행 일시',
  `last_quiz_date` datetime DEFAULT NULL COMMENT '마지막 학습/Quiz 진행 일시',
`last_review_date` datetime DEFAULT NULL COMMENT '마지막 복습(Reviewer) 진행 일시',
  PRIMARY KEY (`user_id`,`question_id`),
  KEY `idx_flags` (`bookmarked`,`needs_review`),
  KEY `idx_user_study_count_date` (`user_id`,`study_count`,`last_study_date`),
  KEY `idx_user_quiz_count_date` (`user_id`,`quiz_count`,`last_quiz_date`),
KEY `idx_user_review_count_date` (`user_id`,`review_count`,`last_review_date`),
  KEY `idx_user_flags` (`user_id`,`bookmarked`,`needs_review`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

**자동 업데이트 트리거:**
- `ptgates_update_last_study_date`: `study_count` 변경 시 `last_study_date`와 `updated_at` 자동 업데이트
- `ptgates_update_last_quiz_date`: `quiz_count` 변경 시 `last_quiz_date`와 `updated_at` 자동 업데이트
- `ptgates_update_last_review_date`: `review_count` 변경 시 `last_review_date`와 `updated_at` 자동 업데이트
- `ptgates_insert_last_study_date`: INSERT 시 `study_count > 0`이면 자동 설정
- `ptgates_insert_last_quiz_date`: INSERT 시 `quiz_count > 0`이면 자동 설정
- `ptgates_insert_last_review_date`: INSERT 시 `review_count > 0`이면 자동 설정

---

## 3. 모듈별 테이블

### 3.1 3100-ptgates-selftest (셀프 모의고사)

#### ptgates_exam_sessions
교시별 전체 풀기 세션 관리 및 타이머/진행 상태를 저장합니다.

```sql
CREATE TABLE `ptgates_exam_sessions` (
  `session_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'WP 사용자 ID',
  `exam_course` varchar(50) NOT NULL COMMENT '교시 구분 (예: 1교시, 2교시)',
  `time_limit_minutes` int(10) unsigned DEFAULT NULL COMMENT '분 단위 제한 시간 (NULL = 무제한)',
  `is_unlimited` tinyint(1) NOT NULL DEFAULT 0 COMMENT '무제한 모드(1=무제한, 0=타이머 사용)',
  `status` enum('pending','active','submitted','expired') NOT NULL DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_course_status` (`exam_course`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='교시별 전체풀기 세션(타이머/진행상태)';
```

#### ptgates_exam_session_items
세션 내 문항 구성 및 사용자 응답을 저장합니다.

```sql
CREATE TABLE `ptgates_exam_session_items` (
  `item_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `order_index` int(10) unsigned NOT NULL COMMENT '세션 내 문항 순서',
  `user_answer` varchar(255) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `elapsed_time` int(10) unsigned DEFAULT NULL COMMENT '초 단위(문항별)',
  `answered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `uq_session_question` (`session_id`,`question_id`),
  KEY `idx_session_order` (`session_id`,`order_index`),
  KEY `idx_question` (`question_id`),
  CONSTRAINT `fk_es_items_session` FOREIGN KEY (`session_id`) REFERENCES `ptgates_exam_sessions` (`session_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_es_items_question` FOREIGN KEY (`question_id`) REFERENCES `ptgates_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='세션 내 문항 구성 및 사용자 응답';
```

#### ptgates_exam_presets
모의고사 프리셋 설정을 저장합니다.

```sql
CREATE TABLE `ptgates_exam_presets` (
  `preset_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(100) NOT NULL,
  `filters_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`filters_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`preset_id`),
  KEY `idx_user_title` (`user_id`,`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

### 3.2 2200-ptgates-flashcards (암기카드)

#### ptgates_flashcard_sets
암기카드 세트를 저장합니다.

```sql
CREATE TABLE `ptgates_flashcard_sets` (
  `set_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `set_name` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`set_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### ptgates_flashcards
암기카드를 저장합니다. 문제 참조 방식(`source_id`로 `question_id` 참조).

```sql
CREATE TABLE `ptgates_flashcards` (
  `card_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `set_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `source_type` varchar(50) DEFAULT 'custom',
  `source_id` bigint(20) DEFAULT NULL,
  `front_custom` longtext DEFAULT NULL,
  `back_custom` longtext DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `review_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`card_id`),
  KEY `set_id` (`set_id`),
  KEY `user_id` (`user_id`),
  KEY `next_due_date` (`next_due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.3 2100-ptgates-mynote (마이노트)

#### ptgates_user_notes
사용자 노트를 저장합니다.

```sql
CREATE TABLE `ptgates_user_notes` (
  `note_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `ref_type` enum('question','theory','notebook') NOT NULL DEFAULT 'question',
  `ref_id` bigint(20) unsigned NOT NULL COMMENT 'question_id 또는 이론ID 등',
  `text` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`note_id`),
  KEY `idx_user_ref` (`user_id`,`ref_type`,`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

#### ptgates_user_memos
사용자 메모를 저장합니다 (레거시 테이블, `ptgates_user_notes`로 통합 권장).

```sql
CREATE TABLE `ptgates_user_memos` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `question_id` bigint(20) NOT NULL,
  `content` longtext NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_question` (`user_id`,`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

### 3.4 1200-ptgates-quiz (문제 풀이)

#### ptgates_user_drawings
문항별 사용자 드로잉(펜 필기 저장)을 저장합니다.

```sql
CREATE TABLE `ptgates_user_drawings` (
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
  KEY `idx_question_id` (`question_id`),
  KEY `idx_user_question_answered` (`user_id`,`question_id`,`is_answered`),
  KEY `idx_user_question_device` (`user_id`,`question_id`,`device_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 3.5 4100-ptgates-reviewer (복습 스케줄러)

#### ptgates_review_schedule
스페이싱 복습 스케줄(오늘의 문제 큐)을 관리합니다.

```sql
CREATE TABLE `ptgates_review_schedule` (
  `schedule_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `origin_result_id` bigint(20) unsigned DEFAULT NULL COMMENT '처음 예약을 만든 시도의 result_id',
  `due_date` date NOT NULL COMMENT '재노출 예정일(현지 기준은 앱 레이어에서 처리)',
  `status` enum('pending','shown','done','skipped') NOT NULL DEFAULT 'pending',
  `shown_at` datetime DEFAULT NULL COMMENT '오늘의 문제로 실제 노출된 시각',
  `done_at` datetime DEFAULT NULL COMMENT '복습 완료 시각',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`schedule_id`),
  KEY `idx_user_due` (`user_id`,`due_date`),
  KEY `idx_user_status_due` (`user_id`,`status`,`due_date`),
  KEY `idx_question` (`question_id`),
  KEY `idx_origin_result` (`origin_result_id`),
  CONSTRAINT `fk_rs_question` FOREIGN KEY (`question_id`) REFERENCES `ptgates_questions` (`question_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rs_origin_result` FOREIGN KEY (`origin_result_id`) REFERENCES `ptgates_user_results` (`result_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='스페이싱 복습 스케줄(오늘의 문제 큐)';
```

---

### 3.6 1100-ptgates-study (이론 학습)

#### ptgates_highlights
이론 하이라이트를 저장합니다.

```sql
CREATE TABLE `ptgates_highlights` (
  `highlight_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `theory_id` bigint(20) unsigned NOT NULL,
  `range_json` text NOT NULL,
  `color` varchar(16) DEFAULT '#FFF59D',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`highlight_id`),
  KEY `idx_user_theory` (`user_id`,`theory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

### 3.7 기타 (B2B, 결제, 과목)

#### ptgates_subject
교시/과목/세부과목 정적 정의 테이블.

```sql
CREATE TABLE `ptgates_subject` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_no` tinyint(3) unsigned NOT NULL COMMENT '교시 (1,2)',
  `category` varchar(100) NOT NULL COMMENT '상위 과목군',
  `subcategory` varchar(100) DEFAULT NULL COMMENT '세부 과목(합계행은 NULL)',
  `questions` int(10) unsigned NOT NULL COMMENT '문항 수',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_course_category_subcategory` (`course_no`,`category`,`subcategory`),
  KEY `idx_course_category` (`course_no`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

#### ptgates_organization
B2B 기관 정보를 저장합니다.

```sql
CREATE TABLE `ptgates_organization` (
  `org_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_name` varchar(255) NOT NULL COMMENT '기관/학과 공식 명칭',
  `contact_user_id` bigint(20) unsigned DEFAULT NULL COMMENT '기관 담당 관리자의 wp_users.ID',
  `org_email` varchar(100) DEFAULT NULL,
  `org_type` varchar(50) NOT NULL DEFAULT 'university' COMMENT '기관 유형 (university, school, company 등)',
  `member_limit` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '허용된 최대 등록 학생/사용자 수',
  `members_registered` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '현재 등록된 학생/사용자 수',
  `billing_plan` varchar(50) NOT NULL COMMENT '기관에 적용된 B2B 멤버십 플랜',
  `plan_expiry_date` datetime DEFAULT NULL COMMENT '기관 멤버십 만료일',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`org_id`),
  UNIQUE KEY `org_name` (`org_name`),
  KEY `contact_user_id` (`contact_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

#### ptgates_org_member_link
B2B 기관-회원 연결을 저장합니다.

```sql
CREATE TABLE `ptgates_org_member_link` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `assignment_date` datetime NOT NULL DEFAULT current_timestamp() COMMENT '기관 멤버십에 할당된 시점',
  `expiry_date` datetime DEFAULT NULL COMMENT 'B2B 혜택 만료일 (사용자별)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '현재 B2B 혜택 적용 여부',
  PRIMARY KEY (`id`),
  UNIQUE KEY `org_user_unique` (`org_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

#### ptgates_user_member
사용자 멤버십 정보를 저장합니다.

```sql
CREATE TABLE `ptgates_user_member` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `membership_source` varchar(20) NOT NULL DEFAULT 'individual' COMMENT '멤버십 획득 경로 (individual, b2b)',
  `org_id` bigint(20) unsigned DEFAULT NULL COMMENT '소속된 기관의 org_id (b2b 멤버십인 경우)',
  `member_grade` varchar(50) NOT NULL DEFAULT 'basic' COMMENT '회원의 현재 멤버십 등급 (basic, premium, trial 등)',
  `billing_status` varchar(20) NOT NULL DEFAULT 'active' COMMENT '결제 상태 (active, expired, pending, cancelled)',
  `billing_expiry_date` datetime DEFAULT NULL COMMENT '멤버십 만료일',
  `total_payments_krw` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '누적 결제 금액',
  `exam_count_total` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '총 생성 가능한 모의고사 횟수',
  `exam_count_used` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '사용한 모의고사 횟수',
  `study_count_total` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '총 학습 가능한 횟수 또는 시간 (플랜에 따라 정의)',
  `study_count_used` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '사용한 학습 횟수',
  `last_login` datetime DEFAULT NULL COMMENT '마지막 플러그인 학습/접속 시간',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '계정 활성화 상태 (1=활성, 0=비활성/정지)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `member_grade_status` (`member_grade`,`billing_status`),
  KEY `org_id_idx` (`org_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

#### ptgates_billing_history
결제 내역을 저장합니다.

```sql
CREATE TABLE `ptgates_billing_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT '결제 행위를 수행한 사용자 ID (개인 또는 기관 담당자)',
  `order_id` varchar(100) NOT NULL COMMENT '결제 시스템(PG사)의 고유 주문 번호',
  `pg_transaction_id` varchar(100) DEFAULT NULL COMMENT 'PG사에서 부여한 실제 거래 ID (영수증 ID)',
  `transaction_type` varchar(50) NOT NULL COMMENT '트랜잭션 유형 (purchase, renewal, refund, cancellation 등)',
  `product_name` varchar(255) NOT NULL COMMENT '결제한 상품/멤버십 이름',
  `payment_method` varchar(50) NOT NULL COMMENT '결제 수단 (card, transfer, kakao/naverpay 등)',
  `amount` decimal(10,2) NOT NULL COMMENT '결제 금액',
  `currency` varchar(10) NOT NULL DEFAULT 'KRW',
  `status` varchar(20) NOT NULL COMMENT '결제 처리 상태 (paid, failed, refunded, pending)',
  `transaction_date` datetime NOT NULL COMMENT '결제 또는 트랜잭션 발생 시점',
  `memo` text DEFAULT NULL COMMENT '관리자용 특이사항 메모',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id_unique` (`order_id`),
  KEY `user_id` (`user_id`),
  KEY `transaction_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

## 4. 트리거 및 뷰

### 4.1 트리거

#### ptgates_update_last_study_date
`ptgates_user_states` 테이블의 `study_count`가 변경될 때 `last_study_date`와 `updated_at`을 자동으로 업데이트합니다.

```sql
CREATE TRIGGER `ptgates_update_last_study_date`
BEFORE UPDATE ON `ptgates_user_states`
FOR EACH ROW
BEGIN
    IF NEW.study_count != OLD.study_count THEN
        SET NEW.last_study_date = NOW();
    END IF;
END;
```

#### ptgates_update_last_quiz_date
`ptgates_user_states` 테이블의 `quiz_count`가 변경될 때 `last_quiz_date`와 `updated_at`을 자동으로 업데이트합니다.

```sql
CREATE TRIGGER `ptgates_update_last_quiz_date`
BEFORE UPDATE ON `ptgates_user_states`
FOR EACH ROW
BEGIN
    IF NEW.quiz_count != OLD.quiz_count THEN
        SET NEW.last_quiz_date = NOW();
    END IF;
END;
```

#### ptgates_insert_last_study_date
`ptgates_user_states` 테이블에 INSERT 시 `study_count > 0`이면 `last_study_date`와 `updated_at`을 자동으로 설정합니다.

```sql
CREATE TRIGGER `ptgates_insert_last_study_date`
BEFORE INSERT ON `ptgates_user_states`
FOR EACH ROW
BEGIN
    IF NEW.study_count > 0 THEN
        SET NEW.updated_at = NOW();
        SET NEW.last_study_date = NEW.updated_at;
    END IF;
END;
```

#### ptgates_insert_last_quiz_date
`ptgates_user_states` 테이블에 INSERT 시 `quiz_count > 0`이면 `last_quiz_date`와 `updated_at`을 자동으로 설정합니다.

```sql
CREATE TRIGGER `ptgates_insert_last_quiz_date`
BEFORE INSERT ON `ptgates_user_states`
FOR EACH ROW
BEGIN
    IF NEW.quiz_count > 0 THEN
        SET NEW.updated_at = NOW();
        SET NEW.last_quiz_date = NEW.updated_at;
    END IF;
END;
```

### 4.2 뷰

#### ptgates_today_queue
오늘의 문제 큐를 빠르게 조회하기 위한 뷰입니다.

```sql
CREATE OR REPLACE VIEW `ptgates_today_queue` AS
SELECT
  rs.schedule_id, rs.user_id, rs.question_id, rs.due_date, rs.status,
  q.content, q.answer, q.explanation, q.type, q.difficulty
FROM ptgates_review_schedule rs
JOIN ptgates_questions q ON q.question_id = rs.question_id
WHERE rs.status = 'pending' AND rs.due_date = CURRENT_DATE();
```

**참고:** 서버가 UTC이고 KST 기준 "오늘"을 쓰려면 앱에서 `due_date`를 KST로 미리 계산하여 넣는 방식을 권장합니다.

---

## 5. 테이블 관계도

```
ptgates_questions (기본 문제 테이블)
  ├── ptgates_categories (N) ── question_id (FK)
  ├── ptgates_user_results (N) ── question_id (FK)
  ├── ptgates_user_states (N) ── question_id (FK)
  ├── ptgates_exam_session_items (N) ── question_id (FK)
  ├── ptgates_flashcards (N) ── source_id (참조)
  ├── ptgates_user_notes (N) ── ref_id (참조)
  ├── ptgates_user_memos (N) ── question_id (FK)
  ├── ptgates_user_drawings (N) ── question_id (FK)
  └── ptgates_review_schedule (N) ── question_id (FK)

ptgates_exam_sessions (시험 세션)
  └── ptgates_exam_session_items (N) ── session_id (FK)

ptgates_user_results (기존 결과 테이블)
  └── ptgates_review_schedule (N) ── origin_result_id (FK)

ptgates_flashcard_sets (암기카드 세트)
  └── ptgates_flashcards (N) ── set_id (FK)

ptgates_organization (B2B 기관)
  ├── ptgates_org_member_link (N) ── org_id (FK)
  └── ptgates_user_member (N) ── org_id (FK, 간접 참조)
```

---

## 6. 개발 시 주의사항

### 6.1 기본 테이블 변경 시

**절대 변경 불가:**
- `ptgates_questions.question_id` (모든 모듈에서 FK 사용)
- `ptgates_categories.question_id` (FK)
- `ptgates_user_results.question_id` (FK)

**변경 시 모든 모듈 영향도 분석 필수:**
- `ptgates_questions.content`, `answer`, `explanation`
- `ptgates_categories.exam_session` (기출문제 정책 필터링 로직)

### 6.2 인덱스 활용

**복합 인덱스 활용:**
- `ptgates_categories`: `idx_exam_meta` (`exam_year`, `exam_session`, `exam_course`)
- `ptgates_categories`: `idx_year_subject` (`exam_year`, `subject`)
- `ptgates_user_states`: `idx_user_study_count_date` (`user_id`, `study_count`, `last_study_date`)
- `ptgates_user_states`: `idx_user_quiz_count_date` (`user_id`, `quiz_count`, `last_quiz_date`)

### 6.3 외래키 제약 조건

**CASCADE DELETE 적용:**
- `ptgates_questions` 삭제 시 관련 테이블 자동 삭제
- `ptgates_exam_sessions` 삭제 시 `ptgates_exam_session_items` 자동 삭제

**SET NULL 적용:**
- `ptgates_user_results` 삭제 시 `ptgates_review_schedule.origin_result_id`는 NULL로 변경

### 6.4 트리거 활용

**자동 업데이트:**
- `study_count` 변경 시 `last_study_date` 자동 업데이트
- `quiz_count` 변경 시 `last_quiz_date` 자동 업데이트

**PHP 코드에서:**
- 트리거가 자동으로 처리하므로 `last_study_date`/`last_quiz_date`를 명시적으로 업데이트할 필요 없음
- `study_count`/`quiz_count`만 업데이트하면 됨

### 6.5 타임존 처리

- **DB**: UTC 저장
- **앱**: KST(Asia/Seoul) 기준 처리
- `due_date`는 KST로 계산하여 date로 저장

### 6.6 기출문제 정책

- **DB에는 기출문제 유지**: `exam_session < 1000`
- **사용자에게는 생성문항만 노출**: `exam_session >= 1000`
- **기출문제는 내부 분석용으로만 사용** (출제 경향 분석)
- **`9000-ptgates-exam-questions` 플러그인**: 기출문제 참조용 (Admin 전용)

### 6.7 문자셋 및 콜레이션

- 모든 테이블은 `utf8mb4` 문자셋과 `utf8mb4_unicode_ci` (또는 `utf8mb4_unicode_520_ci`) 콜레이션을 사용합니다.

---

## 📌 참고 파일

- **실제 DB 스키마 덤프**: `ptgates_schema.txt` (이 파일의 최종 참조 기준)
- **마이그레이션 코드**: `0000-ptgates-platform/includes/class-migration.php`

---

**최종 업데이트:** 2025-01-XX  
**버전:** 1.0.0  
**기준:** `ptgates_schema.txt` (2025-11-25 덤프 기준)
