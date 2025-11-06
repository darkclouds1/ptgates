# PTGates 확장 데이터베이스 스키마

이 문서는 PTGates Learning Engine 플러그인의 확장 기능을 위한 추가 데이터베이스 테이블 스키마와 사용 용도를 설명합니다.

---

## 1. 시험 세션 (1교시/2교시 전체 풀기 + 타이머)

### 1.1 ptgates_exam_sessions

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

**무제한 모드 설정:**
- `time_limit_minutes`를 `NULL`로 설정
- `is_unlimited`를 `1`로 설정

### 1.2 ptgates_exam_session_items

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

---

## 2. 문제별 개인화 상태 (북마크/복습 필요/최근 결과·답)

### ptgates_user_states

문항별 사용자 상태(북마크/복습/최근 결과·답)를 저장합니다.

```sql
CREATE TABLE `ptgates_user_states` (
  `user_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `bookmarked` tinyint(1) NOT NULL DEFAULT 0,
  `needs_review` tinyint(1) NOT NULL DEFAULT 0,
  `last_result` enum('correct','wrong') DEFAULT NULL COMMENT '최근 시도 결과',
  `last_answer` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`question_id`),
  KEY `idx_flags` (`bookmarked`,`needs_review`),
  KEY `idx_last_result` (`last_result`),
  CONSTRAINT `fk_states_question` FOREIGN KEY (`question_id`) REFERENCES `ptgates_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='문항별 사용자 상태(북마크/복습/최근 결과·답)';
```

**참고:** 채점 시 `ptgates_user_results` 입력과 함께 `ptgates_user_states`도 갱신해야 합니다.

---

## 3. 메모 (텍스트 노트)

### ptgates_user_notes

문항별 사용자 텍스트 메모(노트 아이콘)를 저장합니다.

```sql
CREATE TABLE `ptgates_user_notes` (
  `note_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `text` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`note_id`),
  KEY `idx_user_question` (`user_id`,`question_id`),
  CONSTRAINT `fk_notes_question` FOREIGN KEY (`question_id`) REFERENCES `ptgates_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='문항별 사용자 텍스트 메모(노트 아이콘)';
```

---

## 4. 드로잉 (펜 필기 데이터)

### ptgates_user_drawings

문항별 사용자 드로잉(펜 필기 저장)을 저장합니다.

```sql
CREATE TABLE `ptgates_user_drawings` (
  `drawing_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `format` enum('json','svg') NOT NULL DEFAULT 'json' COMMENT '저장 포맷',
  `data` longtext NOT NULL COMMENT '펜 스트로크 데이터(JSON paths 또는 SVG path)',
  `width` int(10) unsigned DEFAULT NULL COMMENT '캔버스 폭(px)',
  `height` int(10) unsigned DEFAULT NULL COMMENT '캔버스 높이(px)',
  `device` varchar(100) DEFAULT NULL COMMENT '예: Apple Pencil, S Pen',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`drawing_id`),
  KEY `idx_user_question` (`user_id`,`question_id`),
  CONSTRAINT `fk_drawings_question` FOREIGN KEY (`question_id`) REFERENCES `ptgates_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='문항별 사용자 드로잉(펜 필기 저장)';
```

**참고:** JSON 포맷 사용 시 클라이언트에서 압축(gzip) 권장합니다.

---

## 5. 복습 스케줄 (오늘의 문제, 1~5일 후 재등장)

### ptgates_review_schedule

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

**앱 로직 팁:**
- 사용자가 정답 확인 후 1~5일 후 중 택1 → `due_date = CURRENT_DATE + INTERVAL N DAY`
- 매일 첫 접속/자정 배치에서 `status='pending' AND due_date=오늘`을 "오늘의 문제" 큐로 로딩
- 풀면 `status='done'`, 보여만 주고 넘어가면 `status='shown'` 등으로 관리 가능

---

## 6. 빠른 조회를 위한 뷰 (선택)

### ptgates_today_queue

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

## 7. 자주 쓰는 쿼리 예시

### 7.1 오답 & 복습 필요 조합으로 N문항 뽑기

```sql
SELECT q.question_id, q.content
FROM ptgates_questions q
JOIN ptgates_user_states s
  ON s.question_id = q.question_id AND s.user_id = :uid
WHERE s.last_result = 'wrong' AND s.needs_review = 1
ORDER BY q.question_id
LIMIT :n;
```

### 7.2 북마크 또는 복습 필요

```sql
SELECT q.question_id, q.content
FROM ptgates_questions q
JOIN ptgates_user_states s
  ON s.question_id = q.question_id AND s.user_id = :uid
WHERE s.bookmarked = 1 OR s.needs_review = 1
LIMIT :n;
```

### 7.3 오늘의 문제 가져오기

```sql
SELECT * FROM ptgates_today_queue WHERE user_id = :uid;
```

### 7.4 세션 시작/종료 관리 (무제한 포함)

#### 세션 시작
```sql
UPDATE ptgates_exam_sessions
SET status='active', started_at=NOW(),
    ends_at = CASE WHEN is_unlimited=1 OR time_limit_minutes IS NULL
                   THEN NULL
                   ELSE DATE_ADD(NOW(), INTERVAL time_limit_minutes MINUTE)
              END
WHERE session_id=:sid AND status='pending';
```

#### 세션 제출
```sql
UPDATE ptgates_exam_sessions
SET status='submitted', submitted_at=NOW()
WHERE session_id=:sid AND status IN ('active','expired');
```

---

## 테이블 관계도

```
ptgates_questions (기본 문제 테이블)
  ├── ptgates_exam_session_items (세션 내 문항)
  ├── ptgates_user_states (사용자 상태)
  ├── ptgates_user_notes (메모)
  ├── ptgates_user_drawings (드로잉)
  └── ptgates_review_schedule (복습 스케줄)

ptgates_exam_sessions (시험 세션)
  └── ptgates_exam_session_items (세션 내 문항)

ptgates_user_results (기존 결과 테이블)
  └── ptgates_review_schedule (복습 스케줄의 origin_result_id)
```

---

## 구현 시 주의사항

1. **외래 키 제약 조건:** 모든 테이블은 `ptgates_questions`와 `ptgates_user_results`를 참조하므로, 기존 테이블이 먼저 생성되어 있어야 합니다.

2. **무제한 모드:** `ptgates_exam_sessions`에서 무제한 모드는 `time_limit_minutes=NULL` 및 `is_unlimited=1`로 설정합니다.

3. **복습 스케줄:** 서버 시간대와 클라이언트 시간대가 다를 경우, `due_date`를 클라이언트에서 미리 계산하여 저장하는 방식을 권장합니다.

4. **드로잉 데이터:** JSON 포맷 사용 시 클라이언트에서 압축(gzip)을 권장합니다.

5. **채점 시 상태 갱신:** `ptgates_user_results`에 결과를 저장할 때 `ptgates_user_states`도 함께 갱신해야 합니다.

