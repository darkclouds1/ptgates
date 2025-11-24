# 5100 Dashboard 플러그인 성능 검증 리포트

## 📊 검증 일시
2025-01-XX

## 🔍 검증 범위
- 데이터베이스 쿼리 최적화
- API 응답 성능
- JavaScript 렌더링 성능
- 메모리 사용량
- 캐싱 전략

---

## ⚠️ 발견된 성능 이슈

### 1. **다중 쿼리 실행 (Critical)**
**위치:** `includes/class-api.php::get_summary()`

**문제:**
- 한 번의 API 호출에서 **최소 7개 이상의 개별 쿼리** 실행
  1. `review_count` 조회
  2. `bookmark_count` 조회
  3. `total_questions` 조회
  4. `solved_questions` 조회
  5. `recent_activity` 조회
  6. `fetch_learning_rows()` (study) - 복잡한 JOIN 쿼리
  7. `fetch_learning_rows()` (quiz) - 복잡한 JOIN 쿼리
  8. `fetch_subject_totals()` (study) - 복잡한 JOIN 쿼리
  9. `fetch_subject_totals()` (quiz) - 복잡한 JOIN 쿼리

**영향:**
- 각 쿼리마다 네트워크 왕복 시간 발생
- 데이터베이스 부하 증가
- API 응답 시간 지연 (예상: 200-500ms)

**권장 해결책:**
```php
// 쿼리 통합 예시
$wpdb->get_results($wpdb->prepare("
    SELECT 
        COUNT(CASE WHEN needs_review = 1 THEN 1 END) as review_count,
        COUNT(CASE WHEN bookmarked = 1 THEN 1 END) as bookmark_count
    FROM {$table_states} 
    WHERE user_id = %d
", $user_id));
```

---

### 2. **복잡한 JOIN 쿼리 최적화 필요 (High)**
**위치:** `includes/class-api.php::fetch_learning_rows()`, `fetch_subject_totals()`

**문제:**
```sql
SELECT 
    DATE(s.{$date_column}) AS record_date,
    c.subject AS subsubject_name,
    SUM(s.{$count_column}) AS total_count
FROM {$states_table} s
INNER JOIN {$questions_table} q ON s.question_id = q.question_id
INNER JOIN {$categories_table} c ON q.question_id = c.question_id
WHERE s.user_id = %d
  AND s.{$count_column} > 0
  AND s.{$date_column} IS NOT NULL
GROUP BY record_date, subsubject_name
ORDER BY record_date DESC, total_count DESC
LIMIT 200
```

**잠재적 문제:**
- 3개 테이블 JOIN으로 인한 성능 저하
- `DATE()` 함수 사용으로 인덱스 활용 불가
- `GROUP BY` + `ORDER BY` 조합으로 임시 테이블 생성 가능

**권장 해결책:**
1. **인덱스 확인 및 추가:**
   ```sql
   -- ptgates_user_states 테이블
   CREATE INDEX idx_user_count_date ON ptgates_user_states(user_id, study_count, last_study_date);
   CREATE INDEX idx_user_count_date_quiz ON ptgates_user_states(user_id, quiz_count, last_quiz_date);
   
   -- ptgates_questions 테이블
   CREATE INDEX idx_question_id_active ON ptgates_questions(question_id, is_active);
   
   -- ptgates_categories 테이블
   CREATE INDEX idx_category_question_subject ON ptgates_categories(question_id, subject);
   ```

2. **쿼리 최적화:**
   - `DATE()` 함수 대신 날짜 범위 조건 사용 고려
   - 필요한 컬럼만 SELECT

---

### 3. **캐싱 전략 부재 (High)**
**위치:** 전체 API

**문제:**
- 사용자별 대시보드 데이터가 매 요청마다 재계산됨
- 동일한 데이터를 반복 조회

**영향:**
- 불필요한 데이터베이스 부하
- 응답 시간 지연

**권장 해결책:**
```php
// WordPress Transients API 활용
$cache_key = 'ptg_dashboard_summary_' . $user_id;
$cached = get_transient($cache_key);

if ($cached !== false) {
    return rest_ensure_response($cached);
}

// 데이터 조회 및 계산
$data = [/* ... */];

// 5분간 캐싱 (학습 기록 업데이트 시 삭제)
set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
```

**캐시 무효화 시점:**
- 사용자가 문제를 풀었을 때
- 북마크/복습 상태 변경 시
- Study/Quiz 진행 시

---

### 4. **불필요한 데이터 조회 (Medium)**
**위치:** `includes/class-api.php::get_summary()`

**문제:**
- `recent_activity`에서 `q.content` 전체를 조회하지만 30자만 사용
- `fetch_learning_rows()`에서 LIMIT 200이지만 실제로는 7개 날짜만 사용

**권장 해결책:**
```php
// content 대신 SUBSTRING 사용
"SELECT r.*, SUBSTRING(q.content, 1, 50) as content_preview 
 FROM $table_results r
 JOIN $table_questions q ON r.question_id = q.question_id
 ..."
```

---

### 5. **JavaScript 렌더링 최적화 (Low)**
**위치:** `assets/js/dashboard.js`

**현재 상태:**
- ✅ 단일 AJAX 호출
- ✅ 에러 핸들링 적절
- ⚠️ 대량 데이터 렌더링 시 성능 이슈 가능

**권장 개선:**
- 가상 스크롤링 (대량 리스트의 경우)
- 디바운싱 (이벤트 핸들러)

---

## 📈 성능 벤치마크 (예상)

### 현재 상태 (예상)
- **API 응답 시간:** 200-500ms (데이터 양에 따라)
- **데이터베이스 쿼리 수:** 9-10개
- **메모리 사용량:** 보통
- **네트워크 전송량:** 50-200KB (JSON)

### 최적화 후 (예상)
- **API 응답 시간:** 50-150ms (캐시 히트 시 < 10ms)
- **데이터베이스 쿼리 수:** 3-4개
- **메모리 사용량:** 보통
- **네트워크 전송량:** 30-100KB (JSON)

---

## ✅ 긍정적인 부분

1. **쿼리 제한:**
   - `fetch_learning_rows()`에서 LIMIT 200 사용
   - `recent_activity`에서 LIMIT 5 사용

2. **에러 처리:**
   - `suppress_errors()` 적절히 사용
   - 테이블 존재 여부 확인

3. **보안:**
   - `$wpdb->prepare()` 사용
   - 권한 체크 (`is_user_logged_in()`)

4. **코드 구조:**
   - 함수 분리로 가독성 좋음
   - 네임스페이스 사용

---

## 🎯 우선순위별 개선 권장사항

### 🔴 Critical (즉시 개선)
1. **쿼리 통합** - 다중 쿼리를 하나로 통합
2. **인덱스 추가** - JOIN 쿼리 성능 향상

### 🟡 High (단기 개선)
3. **캐싱 전략 도입** - Transients API 활용
4. **불필요한 데이터 조회 최소화**

### 🟢 Medium (중기 개선)
5. **쿼리 최적화** - DATE() 함수 대체
6. **JavaScript 렌더링 최적화**

---

## 📝 검증 방법

### 1. 쿼리 프로파일링
```php
// wp-config.php에 추가
define('SAVEQUERIES', true);

// 디버깅
global $wpdb;
print_r($wpdb->queries);
```

### 2. 응답 시간 측정
```javascript
console.time('dashboard-load');
// API 호출
console.timeEnd('dashboard-load');
```

### 3. 데이터베이스 EXPLAIN
```sql
EXPLAIN SELECT ... -- 각 쿼리 실행 계획 확인
```

---

## 🔧 즉시 적용 가능한 최적화

### 1. 쿼리 통합 예시
```php
// get_summary() 내부
$wpdb->suppress_errors(true);
$stats = $wpdb->get_row($wpdb->prepare("
    SELECT 
        COUNT(CASE WHEN needs_review = 1 THEN 1 END) as review_count,
        COUNT(CASE WHEN bookmarked = 1 THEN 1 END) as bookmark_count
    FROM {$table_states} 
    WHERE user_id = %d
", $user_id));
$wpdb->suppress_errors(false);

$review_count = (int)($stats->review_count ?? 0);
$bookmark_count = (int)($stats->bookmark_count ?? 0);
```

### 2. 인덱스 추가 SQL
```sql
-- ptgates_user_states
ALTER TABLE ptgates_user_states 
ADD INDEX idx_user_study (user_id, study_count, last_study_date),
ADD INDEX idx_user_quiz (user_id, quiz_count, last_quiz_date),
ADD INDEX idx_user_flags (user_id, bookmarked, needs_review);

-- ptgates_categories
ALTER TABLE ptgates_categories 
ADD INDEX idx_question_subject (question_id, subject);
```

---

## 📌 결론

현재 대시보드 플러그인은 **기능적으로는 정상 작동**하지만, **성능 최적화 여지가 많습니다**.

**즉시 개선 시 예상 효과:**
- API 응답 시간: **50-70% 단축**
- 데이터베이스 부하: **40-60% 감소**
- 사용자 경험: **체감 속도 향상**

**권장 작업 순서:**
1. 인덱스 추가 (즉시, 위험도 낮음)
2. 쿼리 통합 (단기, 위험도 낮음)
3. 캐싱 도입 (단기, 위험도 중간)
4. 쿼리 최적화 (중기, 위험도 중간)

