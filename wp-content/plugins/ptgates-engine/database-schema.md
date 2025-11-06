# PTGates 데이터베이스 스키마

## 1. ptgates_questions 테이블
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
  PRIMARY KEY (`question_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_type` (`type`),
  KEY `idx_difficulty` (`difficulty`)
) ENGINE=InnoDB AUTO_INCREMENT=570 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='물리치료사 국가고시 문제의 핵심 데이터 저장';
```

### 주요 컬럼
- `question_id`: 문제 고유 ID (Primary Key)
- `content`: 문제 본문 전체 (지문, 보기, 이미지 경로 등 포함)
- `answer`: 정답 (객관식 번호 또는 주관식 답)
- `explanation`: 문제 해설 내용
- `type`: 문제 유형 (객관식, 주관식 등)
- `difficulty`: 난이도 (1:하, 2:중, 3:상)
- `is_active`: 사용 여부 (1:활성, 0:비활성)

---

## 2. ptgates_categories 테이블
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
  CONSTRAINT `ptgates_categories_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `ptgates_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=570 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='문제의 분류 정보(연도, 과목, 출처 등) 저장';
```

### 주요 컬럼
- `category_id`: 분류 고유 ID (Primary Key)
- `question_id`: ptgates_questions 테이블의 외래키
- `exam_year`: 시험 시행 연도
- `exam_session`: 시험 회차 (선택)
- `exam_course`: 교시 구분
- `subject`: 과목명
- `source_company`: 문제 출처 (회사별 구분용)

### 관계
- `question_id`는 `ptgates_questions.question_id`를 참조하며, CASCADE DELETE 적용

---

## 3. ptgates_user_results 테이블
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

### 주요 컬럼
- `result_id`: 결과 고유 ID (Primary Key)
- `user_id`: 워드프레스 사용자 ID (Ultimate Member 연동)
- `question_id`: 풀이한 문제 ID
- `user_answer`: 사용자가 선택/입력한 답
- `is_correct`: 정답 여부 (1:정답, 0:오답)
- `elapsed_time`: 문제 풀이에 소요된 시간 (초)
- `attempted_at`: 풀이를 시도한 일시

### 관계
- `question_id`는 `ptgates_questions.question_id`를 참조하며, CASCADE DELETE 적용

---

## 테이블 관계도

```
ptgates_questions (1)
    │
    ├── ptgates_categories (N) ── question_id (FK)
    │
    └── ptgates_user_results (N) ── question_id (FK)
                │
                └── user_id (WordPress Users)
```

## 참고사항

- 모든 테이블은 `utf8mb4` 문자셋과 `utf8mb4_unicode_ci` 콜레이션을 사용합니다.
- `ptgates_questions` 테이블에서 문제를 삭제하면 관련된 `ptgates_categories`와 `ptgates_user_results` 레코드도 자동으로 삭제됩니다 (CASCADE DELETE).
- `user_id`는 WordPress의 사용자 ID를 사용하며, Ultimate Member 플러그인과 연동됩니다.
