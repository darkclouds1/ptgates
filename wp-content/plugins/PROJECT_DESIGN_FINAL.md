# 📦 **ptGates 프로젝트 통합 설계서 (Final v1.0)**

**– Cursor 개발용 핵심 스펙 –**

**– 기출문제 DB 유지 / 생성문항만 노출 모델 –**

**– Free + Premium 단일 결제 플랜 구조 –**

---

## 📋 목차

1. [아키텍처 개요](#1-아키텍처-개요-architecture)
   - [1.1 전체 구조](#11-전체-구조)
   - [1.2 공통 코어](#12-공통-코어)
   - [1.3 아키텍처 원칙](#13-아키텍처-원칙-architecture-principles) ⭐
2. [모듈 구성](#2-모듈-구성-modules-00005100)
3. [데이터베이스 구조](#3-데이터베이스-구조-db-schema)
4. [학습자 중심 UX Flow](#4-학습자-중심-ux-flow-user-journey)
5. [생성문항 기반 학습 전략](#5-생성문항-기반-학습-전략-저작권-회피)
6. [결제 모델](#6-결제-모델-free--premium-단일-플랜)
7. [프리미엄 결제 REST API 구조](#7-프리미엄-결제-rest-api-구조)
8. [매출 목표 및 확장 전략](#8-매출-목표-및-확장-전략)
9. [개발 우선순위](#9-개발-우선순위-sprint-roadmap)
10. [암기카드 설계](#10-암기카드-설계-2200-ptgates-flashcards)
11. [타임존 처리](#15-타임존-처리)
12. [JavaScript 로드 방식 가이드라인](#16-javascript-로드-방식-가이드라인) ⭐

---

# 1. 아키텍처 개요 (Architecture)

## 1.1 전체 구조

* **WordPress 플러그인 모노레포 구조**
* 모듈별 독립 플러그인
* 모든 모듈은 core(0000)만 의존
* REST API 중심 개발
* React 필요 없음 (향후 SPA 확장 가능)

## 1.2 공통 코어

### 📌 **0000-ptgates-platform**

* 역할: 모든 모듈의 기반

* 제공 기능:
  * `class Repo` (DB read/write wrapper)
  * `class Permissions` (free/premium 권한 체크)
  * `class Rest` (REST base wrapper)
  * 공통 CSS/JS/아이콘
  * 공통 숏코드

* user_meta (프리미엄 관리):
  * `ptg_premium_status` = `active` | `expired`
  * `ptg_premium_until` = timestamp
  * `ptg_premium_plan` = `monthly` | `yearly`

## 1.3 아키텍처 원칙 (Architecture Principles)

### 🔒 **모듈 독립성 및 변경 격리 원칙**

**핵심 목표:**
* 각 모듈은 가능한 한 독립적으로 개발/유지/보수/변형/추가
* 새로운 기능 추가 시 다른 모듈에 큰 영향 없도록 설계
* 기능 수정/추가에 튼튼한 코드 구조
* 작은 추가 개발에도 많은 오류 수정이 발생하지 않도록

### 원칙 1: 모듈 간 직접 의존 금지

* ❌ **금지**: 모듈 A가 모듈 B의 클래스/함수를 직접 호출
* ✅ **허용**: 모든 모듈 간 통신은 REST API 또는 플랫폼 코어를 통해서만
* ✅ **예외**: 플랫폼 코어(0000)만 모든 모듈이 의존 가능

```php
// ❌ 잘못된 예시
// 1200-quiz에서 2200-flashcards 직접 호출
$flashcard = new \PTG\Flashcards\Card();

// ✅ 올바른 예시
// REST API를 통한 통신
$response = PTGPlatform::post('ptg-flash/v1/cards', $data);
```

### 원칙 2: 인터페이스 기반 설계

* 플랫폼 코어는 추상 인터페이스 제공
* 각 모듈은 인터페이스만 의존
* 구현체 변경 시에도 인터페이스 유지

```php
// 플랫폼 코어 인터페이스
interface PTG_Repo_Interface {
    public function get($table, $where);
    public function insert($table, $data);
}

// 모듈은 인터페이스만 사용
class Quiz_Handler {
    private $repo; // PTG_Repo_Interface 타입
}
```

### 원칙 3: 데이터 격리

* 각 모듈은 자체 테이블 사용
* 공통 테이블(`ptgates_questions` 등)은 읽기 전용 접근
* 모듈 간 데이터 공유는 REST API 또는 이벤트 시스템
* **테이블 변경 시**: 각 모듈에 영향을 최소화하여 변형 (3.1 섹션 참조)

```sql
-- ✅ 각 모듈 독립 테이블
ptgates_flashcard_sets  -- 2200 전용
ptgates_user_notes     -- 2100 전용
ptgates_user_states    -- 4100 전용

-- ✅ 공통 테이블 (읽기 전용, 변경 가능하나 영향도 분석 필수)
ptgates_questions      -- 모든 모듈 읽기 가능, 쓰기는 플랫폼 코어만
ptgates_categories    -- 모든 모듈 읽기 가능
ptgates_user_results  -- 모든 모듈 읽기 가능
```

### 원칙 4: 버전 관리 및 하위 호환성

* REST API 엔드포인트는 버전 관리 (`/v1/`, `/v2/`)
* 기존 API 변경 시 새 버전 추가, 기존 버전 유지
* 플랫폼 코어 변경 시 하위 호환성 보장

```php
// ✅ 버전 관리 예시
register_rest_route('ptg-quiz/v1', '/questions', ...);  // 기존
register_rest_route('ptg-quiz/v2', '/questions', ...);  // 새 기능
```

### 원칙 5: 옵션/메타 충돌 방지

* 각 모듈은 고유 접두사 사용
* 옵션명: `ptg_{module}_{key}`
* user_meta: `ptg_{module}_{key}`
* 숏코드: `[ptg_{module}]`
* REST base: `ptg-{module}/v1`

```php
// ✅ 올바른 네이밍
update_user_meta($user_id, 'ptg_quiz_last_question', $id);
update_user_meta($user_id, 'ptg_flash_last_review', $date);

// ❌ 잘못된 네이밍 (충돌 위험)
update_user_meta($user_id, 'last_question', $id);
```

### 원칙 6: 에러 처리 및 격리

* 모듈 A의 에러가 모듈 B에 전파되지 않도록
* try-catch로 모듈 경계에서 에러 격리
* 플랫폼 코어는 에러 로깅만, 모듈별 에러는 모듈에서 처리

```php
// ✅ 에러 격리
try {
    $result = PTGPlatform::get('ptg-flash/v1/cards');
} catch (Exception $e) {
    // 이 모듈에서만 에러 처리, 다른 모듈에 영향 없음
    error_log('[Quiz] Flashcard API error: ' . $e->getMessage());
    // 기본값 또는 대체 동작
}
```

### 원칙 7: 테스트 가능한 구조

* 각 모듈은 독립적으로 테스트 가능
* 플랫폼 코어 의존성은 Mock 가능하도록 설계
* 단위 테스트 작성 권장

### 원칙 8: 문서화 및 계약 명시

* 각 모듈의 REST API는 명확한 계약(Contract) 정의
* 요청/응답 스키마 문서화
* 변경 시 변경 로그 유지

### 원칙 9: 점진적 확장

* 새 기능 추가 시 기존 코드 최소 수정
* 플러그인 방식으로 확장 가능한 구조
* Hook/Filter 시스템 활용

```php
// ✅ Hook 시스템 활용
do_action('ptg_quiz_after_answer_check', $question_id, $is_correct);
// 다른 모듈이 필요시 이 Hook에 연결 가능
```

### 원칙 10: 의존성 역전 (Dependency Inversion)

* 상위 모듈이 하위 모듈에 의존하지 않음
* 모두 플랫폼 코어(추상화)에 의존
* 플랫폼 코어는 구체적인 모듈에 의존하지 않음

### 개발 시 체크리스트

새 기능 추가 전 확인:

- [ ] 다른 모듈의 클래스/함수를 직접 호출하지 않는가?
- [ ] REST API 또는 플랫폼 코어를 통해서만 통신하는가?
- [ ] 자체 테이블을 사용하는가? (공통 테이블은 읽기만)
- [ ] 옵션/메타 이름에 모듈 접두사가 있는가?
- [ ] 에러가 다른 모듈로 전파되지 않는가?
- [ ] 기존 API를 변경하지 않고 새 버전을 추가하는가?
- [ ] 변경 사항이 하위 호환성을 유지하는가?

**테이블 변경 시 추가 확인:**

- [ ] 변경 사항이 모든 모듈에 미치는 영향 분석 완료
- [ ] 기존 데이터 마이그레이션 계획 수립
- [ ] 하위 호환성 유지 (기존 컬럼 유지 또는 deprecated 처리)
- [ ] 모든 모듈의 코드 업데이트 계획 수립
- [ ] 테스트 계획 수립 (모든 모듈 테스트)

---

# 2. 모듈 구성 (Modules 0000~5100)

## **1100-ptgates-study**

과목 → 단원 → 개념 로드맵

* 개념 기반 생성문항 (문제 형태 X, 개념 체크용)
* 단원 요약
* 단원 테스트 (생성문항 기반)
* 이론 보기/브라우징

## **1200-ptgates-quiz**

생성 문제 기반 문제풀이 엔진

* 타이머 (1교시 90분/2교시 75분 기본, 무제한 지원)
* 드로잉 (문제 카드 내부 오버레이 캔버스)
* 메모 (패널/바텀시트, 자동 저장 0.8~1.5s 디바운스)
* 북마크 및 복습 필요 표시
* 해설 표시
* "비슷한 유형 다시 풀기"
* 난이도·유형 기반 자동 문제 추천
* **기출문제 제외**: `exam_session >= 1000` (생성문항만 노출)

## **2100-ptgates-mynote**

문제/개념/메모 통합 저장 허브

* 탭: 문제 / 이론·개념 / 암기카드 / 메모 / 노트
* 필터: 전체/오답/북마크, 정렬: 최신순
* 태그 자동 생성
* 모듈 간 연동

## **2200-ptgates-flashcards**

문제 참조 방식 암기카드

* 카드 세트 관리
* 복습 due 날짜 관리
* 자동 반복 알고리즘 (SM-lite)
* **참조 방식**: `source_id`로 `question_id` 참조
  * 앞면 기본값: `ptgates_questions.content`
  * 뒷면 기본값: `ptgates_questions.explanation`
  * 사용자 편집 시: `front_custom`, `back_custom`에 저장

## **3100-ptgates-selftest**

셀프 모의고사 생성기

* 1교시/2교시 구성 자동 조합
* 단원·카테고리 가중치 반영
* 실전 모드 (스크린 락, 타이머)
* 생성문항 기반

## **3200-ptgates-analytics**

학습 분석 엔진

* 취약 단원 분석
* 속도 분석
* 정답률/난이도 통계
* 사용자별 실전 점수 예측

## **4100-ptgates-reviewer**

오늘의 학습/복습 스케줄러

* 오답/북마크/취약개념 기반 추천
* SM 반복 알고리즘
* "오늘 풀 문제" 자동 생성
* 난이도 기반 스케줄: 쉽다=+5일, 보통=+3일, 어렵다=+1일

## **5100-ptgates-dashboard**

개인 학습 허브

* 오늘의 할 일
* 진도율
* 남은 학습
* 모의고사 기록
* 프리미엄 상태 표시

## **6000-ptgates-admin**

문제은행 관리 모듈 (관리자 전용)

* CSV 일괄 삽입 (기존 `/bk/import_exam` 기능)
* 문제 편집/삭제
* 문제 검색/필터링
* 문제 통계 (총 문제 수, 과목별 분포 등)
* 문제 미리보기
* REST API (`ptg-admin/v1/`)

**특징:**
* 관리자 권한 필수 (`current_user_can('manage_options')`)
* 숏코드: `[ptg_admin]` 또는 `[ptg_admin type="import"]`
  * `type`: `import` (CSV 일괄 삽입, 기본값), `list` (문제 목록), `stats` (통계)
* WordPress 관리자 메뉴에서도 접근 가능
* 플랫폼 코어 의존 (`class Repo` 사용)

---

# 3. 데이터베이스 구조 (DB Schema)

## 3.1 기존 테이블 (변경 가능, 모듈 영향 최소화)

### 테이블 변경 정책

**원칙:**
* 필요한 테이블 추가와 변경은 언제든지 가능
* **단, 각 모듈에 영향을 최소화해서 변형해야 함**
* 변경 전 모든 모듈의 영향도 분석 필수
* 하위 호환성 유지 (기존 컬럼은 삭제하지 않고 deprecated 처리)

**변경 시 체크리스트:**
- [ ] 변경 사항이 모든 모듈에 미치는 영향 분석 완료
- [ ] 기존 데이터 마이그레이션 계획 수립
- [ ] 하위 호환성 유지 (기존 컬럼 유지 또는 deprecated 처리)
- [ ] 모든 모듈의 코드 업데이트 계획 수립
- [ ] 테스트 계획 수립 (모든 모듈 테스트)

### `ptgates_questions`
* `question_id` (PK)
* `content` (생성문항 텍스트)
* `answer`
* `explanation`
* `difficulty`
* `type`, `tags`, `meta`
* `is_active`
* `created_at`, `updated_at`

**변경 시 주의사항:**
* `question_id`는 모든 모듈에서 FK로 사용되므로 변경 불가
* `content`, `answer`, `explanation`은 여러 모듈에서 참조하므로 변경 시 영향도 분석 필수

### `ptgates_categories`
* `category_id` (PK)
* `question_id` (FK → ptgates_questions)
* `subject`
* `topic`
* `exam_year` (기출문제 연도, NULL 가능)
* `exam_session` (기출문제 회차, NULL 또는 < 1000: 기출, >= 1000: 생성문항)
* `exam_course` (교시 정보)

**변경 시 주의사항:**
* `question_id` FK는 변경 불가
* `exam_session` 필터링 로직이 여러 모듈에 있으므로 변경 시 모든 모듈 확인 필수

### `ptgates_user_results`
* `result_id` (PK)
* `user_id`
* `question_id` (FK → ptgates_questions)
* `user_answer`
* `is_correct`
* `time_spent`
* `created_at`

**변경 시 주의사항:**
* `question_id` FK는 변경 불가
* 통계/분석 모듈에서 집계에 사용되므로 변경 시 영향도 분석 필수

## 3.2 기출문제 정책

* **DB에는 기출문제 유지** (`exam_session < 1000`)
* **사용자에게는 생성문항만 노출** (`exam_session >= 1000`)
* 기출문제는 내부 분석용으로만 사용 (출제 경향 분석)
* `ptgates-engine` 플러그인은 기출문제 전용 (별도 운영)

## 3.3 새로 추가될 테이블 (모듈별)

### 2200-ptgates-flashcards
```sql
ptgates_flashcard_sets
├── set_id (PK)
├── user_id
├── set_name
└── created_at

ptgates_flashcards
├── card_id (PK)
├── set_id (FK)
├── user_id
├── source_type: 'question' | 'theory' | 'custom'
├── source_id: question_id 또는 theory_id (NULL 가능)
├── front_custom: NULL 또는 사용자 편집본
├── back_custom: NULL 또는 사용자 편집본
├── next_due_date
├── review_count
└── created_at
```

### 2100-ptgates-mynote
```sql
ptgates_user_notes
├── note_id (PK)
├── user_id
├── source_type: 'question' | 'theory' | 'custom'
├── source_id: question_id 또는 theory_id (NULL 가능)
├── content
├── tags
└── created_at
```

### 4100-ptgates-reviewer
```sql
ptgates_user_states
├── state_id (PK)
├── user_id
├── question_id (FK)
├── bookmarked: boolean
├── needs_review: boolean
├── last_answer: string
├── difficulty: 'easy' | 'normal' | 'hard'
└── updated_at
```

---

# 4. 학습자 중심 UX Flow (User Journey)

```
문제풀이 (1200) 
  ↓
해설 확인 
  ↓
북마크/메모 (1200) 
  ↓
복습 스케줄러 (4100) 
  ↓
분석 (3200) 
  ↓
취약 단원 재학습 (1100)
```

모든 모듈이 이 흐름에 맞게 설계됨.

---

# 5. 생성문항 기반 학습 전략 (저작권 회피)

## 원칙

* 기출문제 **원문/선택지** 사용자에게 절대 노출 없음
* 기출의 **출제 경향만 분석** (내부용)
* 모든 사용자 노출 문항은 **ptGates 자체 생성문항**

## 제공 방식

* 생성문항 기반 문제풀이
* 생성문항 기반 단원 테스트
* 생성문항 기반 모의고사
* 생성문항 기반 개념학습

## 장점

* 저작권 0%
* 확장성 무한
* 자체 문제은행 점점 고도화 가능
* B2C/B2B 가격 책정에 유리

---

# 6. 결제 모델 (Free + Premium 단일 플랜)

**WooCommerce 사용 ❌** → **자체 PG(토스/이니시스) 연동 REST 구조**

## Free

* 생성문항 맛보기 5문제/과목
* 모의고사 1회 무료
* 개념 로드맵 30% 공개
* 분석 기능 제한
* 복습 기능 제한
* 광고 포함
* 직접 업그레이드 CTA 표시

## Premium

### 가격

* **1개월: 24,000원**
* **3개월: 59,000원 (D-100 패스)**
* **12개월: 129,000원 (정가)**
* 런칭가 79,000원 가능

### 제공 기능

* 생성 문제 전체
* 모의고사 무제한
* 과목 학습 전체
* 분석 전체
* 복습 스케줄러 전체
* 대시보드 전체
* 광고 제거
* 인쇄/제출용 PDF 리포트
* 점수 예측/커트라인 시뮬레이터

---

# 7. 프리미엄 결제 REST API 구조

## `/ptg/v1/payment/start`

* PG 결제 토큰 생성
* 결제 금액, 플랜 정보 전달

## `/ptg/v1/payment/approve`

* 결제 성공 처리
* `user_meta` 업데이트:
  * `ptg_premium_status` = `active`
  * `ptg_premium_until` = 만료일 timestamp
  * `ptg_premium_plan` = `monthly` | `yearly`
* premium 활성화 이벤트 발동

## `/ptg/v1/payment/cancel`

* 정기결제 해지
* `ptg_premium_status` = `expired`
* 만료일까지는 기능 유지

## `/ptg/v1/payment/status`

* 현재 사용자 프리미엄 상태 조회
* 만료일 확인

---

# 8. 매출 목표 및 확장 전략

## 8.1 B2C 매출 전략

* 응시자 5,000명
* 학생 풀 10,000명
* 전환 목표 8~12%
* 연 1억 ~ 1.5억 가능

## 8.2 B2B 확장 (학과/학원)

* 관리자 대시보드
* 단체 분석 리포트
* 그룹코드 기반 학생 등록
* 1개 학과 150만~300만
* 10개 학과만 확보해도 1.5억 추가 가능

## 8.3 향후 직종 확장

* 작업치료사
* 임상병리사
* 방사선사
* 치위생사

→ 엔진은 그대로 사용, 문제/카테고리만 추가

---

# 9. 개발 우선순위 (Sprint Roadmap)

## Phase 1: 핵심 엔진
1. **0000-ptgates-platform** (DB/REST 유틸/공통)
2. **1200-ptgates-quiz** (문제 풀이 엔진)
3. **4100-ptgates-reviewer** (복습 스케줄러)

## Phase 2: 학습 관리
4. **2100-ptgates-mynote** (마이노트 허브)
5. **2200-ptgates-flashcards** (암기카드)

## Phase 3: 고급 기능
6. **3100-ptgates-selftest** (셀프 모의고사)
7. **3200-ptgates-analytics** (성적 분석)

## Phase 4: 완성
8. **1100-ptgates-study** (이론 보기/단원 테스트)
9. **5100-ptgates-dashboard** (개인 대시보드)

## Phase 5: 관리자 도구
10. **6000-ptgates-admin** (문제은행 관리 모듈)

---

# 10. 암기카드 설계 (2200-ptgates-flashcards)

## 10.1 데이터 구조

### 참조 방식 (Reference-based)

* `source_type`: `'question'` | `'theory'` | `'custom'`
* `source_id`: `question_id` 또는 `theory_id` (NULL 가능)
* `front_custom`: NULL 또는 사용자 편집본
* `back_custom`: NULL 또는 사용자 편집본

### 표시 로직

```javascript
// 앞면 표시
if (card.front_custom) {
    표시할_앞면 = card.front_custom  // 사용자가 편집한 버전
} else {
    표시할_앞면 = ptgates_questions.content (question_id로 조회)
}

// 뒷면 표시
if (card.back_custom) {
    표시할_뒷면 = card.back_custom  // 사용자가 편집한 버전
} else {
    표시할_뒷면 = ptgates_questions.explanation (question_id로 조회)
}
```

## 10.2 생성 흐름

### 1200-ptgates-quiz에서 생성
1. 문제 풀이 중 🃏 버튼 클릭
2. 암기카드 생성 모달 표시
   * 앞면: `ptgates_questions.content` (자동 채워짐, 수정 가능)
   * 뒷면: `ptgates_questions.explanation` (자동 채워짐, 수정 가능)
   * 세트 선택
3. 저장
   * `source_type = 'question'`
   * `source_id = question_id`
   * `front_custom = NULL` (수정 안 했으면)
   * `back_custom = NULL` (수정 안 했으면)

### 1100-ptgates-study에서 생성
1. 이론 보기 중 "암기카드 만들기" 클릭
2. 선택한 텍스트 또는 전체 내용이 앞면/뒷면으로 채워짐
3. 세트 선택 후 저장

## 10.3 복습 스케줄링

* 난이도 선택에 따른 `next_due_date` 갱신:
  * 쉽다: +5일
  * 보통: +3일
  * 어렵다: +1일
* 환경설정으로 일수 조절 가능

---

# 11. 네이밍 규칙

## PHP Namespace
* `PTG\Platform`
* `PTG\Study`
* `PTG\Quiz`
* `PTG\MyNote`
* `PTG\Flashcards`
* `PTG\SelfTest`
* `PTG\Analytics`
* `PTG\Reviewer`
* `PTG\Dashboard`

## 함수 접두사
* `ptg_` (플랫폼)
* `ptg_study_`
* `ptg_quiz_`
* `ptg_mynote_`
* `ptg_flash_`
* `ptg_selftest_`
* `ptg_analytics_`
* `ptg_review_`
* `ptg_dash_`

## REST 네임스페이스
* `ptg/v1` (플랫폼)
* `ptg-study/v1`
* `ptg-quiz/v1`
* `ptg-mynote/v1`
* `ptg-flash/v1`
* `ptg-selftest/v1`
* `ptg-analytics/v1`
* `ptg-review/v1`
* `ptg-dash/v1`

## 숏코드
* `[ptg_study]`
* `[ptg_quiz]`
* `[ptg_mynote]`
* `[ptg_flash]`
* `[ptg_selftest]`
* `[ptg_analytics]`
* `[ptg_review]`
* `[ptg_dashboard]`
* `[ptg_admin]` (관리자 전용)

---

# 12. 보안/권한/성능

## 보안
* 모든 write는 로그인 사용자 + nonce
* 서버에서 `user_id` 강제 (`get_current_user_id()`)
* `$wpdb->prepare` 사용

## 권한 체크
* `class Permissions`에서 free/premium 체크
* 프리미엄 기능은 `ptg_premium_status === 'active'` 확인

## 성능
* 인덱스 활용
* 대용량 목록은 cursor 기반 페이지네이션
* 타임존: DB는 UTC / 앱은 Asia/Seoul

## 파일/옵션 충돌 방지
* 각 모듈 접두사로 등록 (옵션명, 숏코드, REST base)

---

# 13. UI/UX 공통 실무 팁

## 드로잉 영역
* 문제 카드 내부에만 오버레이 캔버스
* 좌표 정규화 (0~1)
* 지우개는 destination-out
* Undo/Redo

## 메모
* 카드 고정영역 두지 말고 패널/바텀시트로 열기
* 열려있을 땐 드로잉 잠시 비활성

## 아이콘 순서 (우상단)
* ☆ (북마크)
* 🔁 (복습)
* 📝 (메모)
* ✏️ (드로잉)
* 🃏 (암기카드) - 향후 추가
* 📓 (노트) - 향후 추가

## 반응형
* PC (최대폭 960~1000px)
* 모바일 폭 100%
* 캔버스 자동 스케일

## 접근성
* 키보드 단축키 (저장/닫기)
* 포커스 트랩

---

# 14. 산출물 (모듈별 공통)

```
<slug>/
 ├── <slug>.php (부트스트랩, 의존성 검사)
 ├── includes/
 │   ├── class-repo.php
 │   ├── class-rest.php
 │   └── class-permissions.php
 ├── assets/
 │   ├── js/*.js
 │   └── css/*.css
 ├── templates/*.php
 └── readme.txt
```

## 플러그인 헤더
* `Requires Plugins: 0000-ptgates-platform` 명시

## Uninstall
* 플랫폼 코어: 플랫폼 전용 테이블만 삭제 옵션
* 기존 3개 테이블 (`ptgates_questions`, `ptgates_categories`, `ptgates_user_results`)은 신중하게 관리
  * 삭제 전 모든 모듈의 영향도 분석 필수
  * 데이터 백업 필수
  * 마이그레이션 계획 수립 필수
* 각 모듈: uninstall 없음 (데이터 유지)

---

# 15. 타임존 처리

* DB는 UTC
* 앱은 Asia/Seoul 기준
* `due_date`는 KST로 계산해 date로 저장

---

# 16. JavaScript 로드 방식 가이드라인

## 16.1 WordPress `wp_enqueue_script` 사용 금지

### ❌ 사용하지 않는 이유

1. **의존성 체인 문제**
   * 복잡한 의존성 구조에서 로드 순서가 꼬일 수 있음
   * 순환 참조 시 예측 불가능한 동작

2. **타이밍 이슈**
   * `wp_enqueue_scripts` 훅은 페이지 로드 초기 실행
   * 숏코드가 나중에 렌더링되면 스크립트가 이미 출력된 후일 수 있음
   * 조건부 로드(`has_shortcode` 체크)가 정확하지 않을 수 있음

3. **캐싱/최적화 플러그인과의 충돌**
   * 캐싱 플러그인이 스크립트를 합치거나 지연 로드하면 의존성 체인 깨짐
   * 버전 파라미터(`?ver=`)가 제거되거나 변경될 수 있음

4. **모듈 독립성 저해**
   * WordPress 큐에 전역 등록되면 모듈 간 간섭 발생
   * 다른 플러그인과 핸들명 충돌 가능성

## 16.2 JavaScript 직접 로드 방식 (권장)

### ✅ 사용 방식

**숏코드 렌더링 시점에 인라인 로더 스크립트로 직접 로드**

```php
// 숏코드 렌더링 시
$loader_script = sprintf(
    '<script id="ptg-module-loader">
        (function(d){
            var cfg = d.defaultView || window;
            cfg.ptgModule = cfg.ptgModule || {};
            cfg.ptgModule.restUrl = %1$s;
            cfg.ptgModule.nonce = %2$s;
            
            var queue = [
                {check: function(){return typeof cfg.PTGPlatform !== "undefined";}, url: %3$s},
                {check: function(){return typeof cfg.PTGModuleUI !== "undefined";}, url: %4$s},
                {check: function(){return typeof cfg.PTGModule !== "undefined";}, url: %5$s}
            ];
            
            function load(i) {
                if (i >= queue.length) return;
                var item = queue[i];
                
                // 이미 로드되었는지 확인
                if (item.check && item.check()) {
                    load(i + 1);
                    return;
                }
                
                // 중복 로드 방지
                var existing = d.querySelector(\'script[data-ptg-src="\' + item.url + \'"]\');
                if (existing) {
                    existing.addEventListener("load", function(){load(i + 1);});
                    return;
                }
                
                // 스크립트 생성 및 로드
                var s = d.createElement("script");
                s.src = item.url + (item.url.indexOf("?") === -1 ? "?ver=1.0.0" : "");
                s.async = false;
                s.setAttribute("data-ptg-src", item.url);
                s.onload = function(){load(i + 1);};
                s.onerror = function(){
                    console.error("[PTG Module] 스크립트를 불러오지 못했습니다:", item.url);
                    load(i + 1);
                };
                (d.head || d.body || d.documentElement).appendChild(s);
            }
            
            if (d.readyState === "loading") {
                d.addEventListener("DOMContentLoaded", function(){load(0);});
            } else {
                load(0);
            }
        })(document);
    </script>',
    wp_json_encode($rest_url),
    wp_json_encode($nonce),
    wp_json_encode($platform_url),
    wp_json_encode($module_ui_url),
    wp_json_encode($module_js_url)
);

echo $loader_script;
```

### 장점

1. **완전한 제어**: 로드 순서와 타이밍을 직접 관리
2. **모듈 독립성**: WordPress 큐와 분리되어 간섭 없음
3. **조건부 로드**: 숏코드 렌더링 시점에만 로드
4. **의존성 체크**: `typeof`로 존재 여부 확인 후 다음 로드
5. **캐싱 플러그인 영향 최소화**: 직접 DOM 조작이라 합치기/지연 로드 영향 적음
6. **디버깅 용이**: 콘솔에서 로드 상태 확인 가능

### 구현 원칙

1. **순차 로드**: 의존성이 있는 스크립트는 순서대로 로드
2. **중복 방지**: `data-ptg-src` 속성으로 이미 로드된 스크립트 체크
3. **에러 처리**: 스크립트 로드 실패 시에도 다음 스크립트 계속 로드
4. **전역 설정 주입**: REST URL, nonce 등은 로더 스크립트에서 전역 변수로 설정

## 16.3 CSS 로드 방식

### ✅ CSS는 `wp_enqueue_style` 사용 가능

CSS는 의존성 체인 영향이 적고, 캐싱 플러그인과의 충돌이 적으므로 WordPress 표준 방식 사용 가능:

```php
public function enqueue_assets() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ptg_module')) {
        wp_enqueue_style(
            'ptg-module-style',
            plugin_dir_url(__FILE__) . 'assets/css/module.css',
            ['ptg-platform-style'], // 플랫폼 스타일 의존성
            '1.0.0'
        );
    }
}
```

## 16.4 재사용 가능한 로더 유틸리티 (선택사항)

플랫폼 코어에 공통 로더 함수 제공 가능:

```javascript
// 0000-ptgates-platform/assets/js/loader.js
(function() {
    'use strict';
    
    window.PTGLoader = window.PTGLoader || {
        loadScripts: function(queue, config, onComplete) {
            let index = 0;
            
            // 전역 설정 주입
            if (config) {
                var cfg = window;
                Object.keys(config).forEach(function(key) {
                    cfg[key] = config[key];
                });
            }
            
            function loadNext() {
                if (index >= queue.length) {
                    if (onComplete) onComplete();
                    return;
                }
                
                const item = queue[index++];
                
                // 이미 로드되었는지 확인
                if (item.check && item.check()) {
                    loadNext();
                    return;
                }
                
                // 중복 로드 방지
                const existing = document.querySelector(`script[data-ptg-src="${item.url}"]`);
                if (existing) {
                    existing.addEventListener('load', loadNext);
                    return;
                }
                
                // 스크립트 생성 및 로드
                const script = document.createElement('script');
                script.src = item.url + (item.url.indexOf('?') === -1 ? '?ver=' + (item.version || Date.now()) : '');
                script.async = false;
                script.setAttribute('data-ptg-src', item.url);
                
                script.onload = loadNext;
                script.onerror = function() {
                    console.error('[PTG Loader] Failed to load:', item.url);
                    loadNext(); // 에러가 나도 다음 스크립트 계속 로드
                };
                
                (document.head || document.body || document.documentElement).appendChild(script);
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', loadNext);
            } else {
                loadNext();
            }
        }
    };
})();
```

## 16.5 체크리스트

JavaScript 로드 구현 시 확인:

- [ ] `wp_enqueue_script`를 사용하지 않는가?
- [ ] 숏코드 렌더링 시점에 인라인 로더로 로드하는가?
- [ ] 의존성 스크립트는 순차적으로 로드하는가?
- [ ] 중복 로드 방지 로직이 있는가? (`data-ptg-src` 속성 체크)
- [ ] 에러 발생 시에도 다음 스크립트가 계속 로드되는가?
- [ ] REST URL, nonce 등 설정이 전역 변수로 주입되는가?
- [ ] CSS는 `wp_enqueue_style`을 사용하는가?

---

# 📌 이 문서가 포함하는 내용

* 전체 모듈 구조
* 기능 흐름
* 생성문항 기반 운영 전략
* Free/Premium 결제 모델
* REST 결제 시스템
* 매출 목표 구조
* B2C/B2B 확장 전략
* 로드맵
* 암기카드 상세 설계
* 네이밍 규칙
* 보안/성능 가이드
* **아키텍처 원칙 (모듈 독립성 및 변경 격리)** ⭐
* **JavaScript 로드 방식 가이드라인** ⭐

**→ ptGates의 기술/기획/운영을 모두 아우르는 통합 스펙 v1.0**

---

**최종 업데이트:** 2024년
**버전:** 1.0.0
**상태:** 통합 완료 (사용자 결정 사항 반영)

