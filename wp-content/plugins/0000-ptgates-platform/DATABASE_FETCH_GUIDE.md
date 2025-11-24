# PTGates 프로젝트 DB 데이터 Fetch 가이드

## 📋 목차
1. [아키텍처 구조](#아키텍처-구조)
2. [JavaScript에서의 API 호출](#javascript에서의-api-호출)
3. [WordPress REST API (Backend)](#wordpress-rest-api-backend)
4. [데이터베이스 접근 (Repository 패턴)](#데이터베이스-접근-repository-패턴)
5. [응답 처리 (Rest 클래스)](#응답-처리-rest-클래스)
6. [보안 및 권한](#보안-및-권한)
7. [캐싱](#캐싱)
8. [전체 흐름 예시](#전체-흐름-예시)

---

## 아키텍처 구조

### 3계층 구조

```
JavaScript (Frontend) 
    ↓ REST API 호출
WordPress REST API (Backend)
    ↓ Repository 클래스 사용
Database (MySQL)
```

### 주요 컴포넌트

- **Frontend**: `PTGPlatform` JavaScript 헬퍼 객체
- **Backend**: WordPress REST API (`register_rest_route`)
- **Repository**: `PTG\Platform\Repo` 및 `PTG\Platform\LegacyRepo` 클래스
- **Response**: `PTG\Platform\Rest` 클래스 (표준화된 응답)

---

## JavaScript에서의 API 호출

### 기본 구조

- **플랫폼 헬퍼**: `window.PTGPlatform` 객체 사용
- **엔드포인트 형식**: `ptg-quiz/v1/questions/{id}` 또는 `ptg-quiz/v1/questions?param=value`
- **HTTP 메서드**: `GET`, `POST`, `PATCH`

### 호출 예시

#### 1. 단일 문제 조회
```javascript
const endpoint = `ptg-quiz/v1/questions/${questionId}`;
const response = await PTGPlatform.get(endpoint);

if (response && response.success && response.data) {
    const questionData = response.data;
    // questionData 사용
}
```

#### 2. 문제 목록 조회 (쿼리 파라미터)
```javascript
const params = new URLSearchParams();
params.append('year', 2024);
params.append('subject', '물리치료기초');
params.append('limit', 5);
params.append('session', 1);

const endpoint = `ptg-quiz/v1/questions?${params.toString()}`;
const response = await PTGPlatform.get(endpoint);

if (response && response.success && Array.isArray(response.data)) {
    const questionIds = response.data; // question_id 배열
}
```

#### 3. POST 요청 (답안 제출)
```javascript
const response = await PTGPlatform.post(
    `ptg-quiz/v1/questions/${questionId}/attempt`,
    {
        answer: '1',
        elapsed: 120
    }
);

if (response && response.success) {
    const result = response.data;
    // result.is_correct 등 사용
}
```

#### 4. PATCH 요청 (상태 업데이트)
```javascript
const response = await PTGPlatform.patch(
    `ptg-quiz/v1/questions/${questionId}/state`,
    {
        bookmarked: true,
        needs_review: false,
        lastAnswer: '1'
    }
);
```

### 응답 형식

모든 API 응답은 다음 형식을 따릅니다:

```javascript
{
    success: true,        // 성공 여부
    message: "성공",      // 메시지
    data: {              // 실제 데이터
        // ... 데이터 내용
    }
}
```

에러 응답:
```javascript
{
    code: "error_code",
    message: "에러 메시지",
    data: {
        status: 400,
        // 추가 에러 정보
    }
}
```

---

## WordPress REST API (Backend)

### 라우트 등록

각 모듈의 `class-api.php`에서 REST API 라우트를 등록합니다:

```php
// includes/class-api.php
namespace PTG\Quiz;

class API {
    const NAMESPACE = 'ptg-quiz/v1';
    
    public static function register_routes() {
        // 문제 목록 조회
        register_rest_route(self::NAMESPACE, '/questions', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_questions_list'),
            'permission_callback' => '__return_true', // 공개 API
            'args' => array(
                'year' => array(
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'subject' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                // ... 기타 파라미터
            ),
        ));
        
        // 단일 문제 조회
        register_rest_route(self::NAMESPACE, '/questions/(?P<question_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_question'),
            'permission_callback' => '__return_true',
            'args' => array(
                'question_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }
}
```

### 주요 API 엔드포인트 목록

#### Quiz 모듈 (ptg-quiz/v1)

- `GET /wp-json/ptg-quiz/v1/questions` - 문제 목록 조회
- `GET /wp-json/ptg-quiz/v1/questions/{id}` - 단일 문제 조회
- `GET /wp-json/ptg-quiz/v1/questions/{id}/state` - 문제 상태 조회
- `PATCH /wp-json/ptg-quiz/v1/questions/{id}/state` - 문제 상태 업데이트
- `POST /wp-json/ptg-quiz/v1/questions/{id}/attempt` - 답안 제출
- `GET /wp-json/ptg-quiz/v1/explanation/{id}` - 해설 조회
- `GET /wp-json/ptg-quiz/v1/sessions` - 교시 목록
- `GET /wp-json/ptg-quiz/v1/subjects` - 과목 목록
- `GET /wp-json/ptg-quiz/v1/subsubjects` - 세부과목 목록
- `POST /wp-json/ptg-quiz/v1/questions/{id}/drawings` - 드로잉 저장
- `GET /wp-json/ptg-quiz/v1/questions/{id}/drawings` - 드로잉 조회
- `GET /wp-json/ptg-quiz/v1/questions/{id}/memo` - 메모 조회
- `POST /wp-json/ptg-quiz/v1/questions/{id}/memo` - 메모 저장

#### 네임스페이스 규칙

각 모듈은 고유한 REST 네임스페이스를 사용합니다:

- `ptg/v1` - 플랫폼 코어
- `ptg-quiz/v1` - 퀴즈 모듈
- `ptg-study/v1` - 학습 모듈
- `ptg-mynote/v1` - 마이노트 모듈
- `ptg-flash/v1` - 암기카드 모듈
- `ptg-selftest/v1` - 셀프 모의고사 모듈
- `ptg-analytics/v1` - 분석 모듈
- `ptg-review/v1` - 복습 모듈
- `ptg-dash/v1` - 대시보드 모듈

---

## 데이터베이스 접근 (Repository 패턴)

### 플랫폼 Repository 클래스

**파일**: `0000-ptgates-platform/includes/class-repo.php`

```php
namespace PTG\Platform;

class Repo {
    // SELECT 쿼리 (여러 레코드)
    public static function find($table, $where = array(), $args = array())
    
    // SELECT 쿼리 (단일 레코드)
    public static function find_one($table, $where = array())
    
    // INSERT
    public static function insert($table, $data)
    
    // UPDATE
    public static function update($table, $data, $where)
    
    // DELETE
    public static function delete($table, $where)
}
```

### 사용 예시

```php
// 여러 레코드 조회
$results = Repo::find('ptgates_user_states', array(
    'user_id' => 123,
    'bookmarked' => 1
), array(
    'orderby' => 'updated_at',
    'order' => 'DESC',
    'limit' => 10
));

// 단일 레코드 조회
$state = Repo::find_one('ptgates_user_states', array(
    'user_id' => 123,
    'question_id' => 456
));

// 레코드 삽입
$id = Repo::insert('ptgates_user_states', array(
    'user_id' => 123,
    'question_id' => 456,
    'bookmarked' => 1,
    'created_at' => current_time('mysql')
));

// 레코드 업데이트
Repo::update('ptgates_user_states', 
    array('bookmarked' => 0),
    array('user_id' => 123, 'question_id' => 456)
);

// 레코드 삭제
Repo::delete('ptgates_user_states', array(
    'user_id' => 123,
    'question_id' => 456
));
```

### 레거시 테이블 접근

**파일**: `0000-ptgates-platform/includes/class-legacy-repo.php`

기존 테이블(`ptgates_questions`, `ptgates_categories`, `ptgates_user_results`) 접근 전용:

```php
namespace PTG\Platform;

class LegacyRepo {
    // 문제 정보 조회 (categories와 JOIN)
    public static function get_questions_with_categories($args = array())
}
```

### 직접 $wpdb 사용

복잡한 쿼리나 JOIN이 필요한 경우 직접 `$wpdb`를 사용할 수 있습니다:

```php
global $wpdb;

$query = $wpdb->prepare(
    "SELECT q.*, c.exam_year, c.subject 
     FROM {$wpdb->prefix}ptgates_questions q
     INNER JOIN ptgates_categories c ON q.question_id = c.question_id
     WHERE q.question_id = %d AND q.is_active = 1",
    $question_id
);

$question = $wpdb->get_row($query, ARRAY_A);
```

**중요**: 항상 `$wpdb->prepare()`를 사용하여 SQL 인젝션을 방지해야 합니다.

---

## 응답 처리 (Rest 클래스)

**파일**: `0000-ptgates-platform/includes/class-rest.php`

### 성공 응답

```php
namespace PTG\Platform;

class Rest {
    public static function success($data = null, $message = '성공', $status = 200) {
        $response = array(
            'success' => true,
            'message' => $message,
        );
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return new \WP_REST_Response($response, $status);
    }
}
```

### 에러 응답

```php
public static function error($code, $message, $status = 400, $data = array()) {
    return new \WP_Error($code, $message, array_merge(
        array('status' => $status),
        $data
    ));
}

// 편의 메서드
public static function unauthorized($message = '로그인이 필요합니다.') {
    return self::error('unauthorized', $message, 401);
}

public static function forbidden($message = '접근 권한이 없습니다.') {
    return self::error('forbidden', $message, 403);
}

public static function not_found($message = '리소스를 찾을 수 없습니다.') {
    return self::error('not_found', $message, 404);
}
```

### 사용 예시

```php
// 성공 응답
return Rest::success($questionData);

// 에러 응답
if (!$question) {
    return Rest::not_found('문제를 찾을 수 없습니다.');
}

// 권한 체크
$user_id = Permissions::get_user_id_or_error();
if (is_wp_error($user_id)) {
    return $user_id; // 자동으로 에러 응답
}
```

---

## 보안 및 권한

### Nonce 인증

JavaScript에서 `X-WP-Nonce` 헤더로 전송:

```javascript
const headers = {
    'Accept': 'application/json',
    'X-WP-Nonce': config.nonce || ''
};
```

WordPress의 REST API nonce 시스템을 사용합니다.

### 권한 체크

#### 공개 API (로그인 불필요)
```php
'permission_callback' => '__return_true',
```

#### 로그인 필요 API
```php
'permission_callback' => array(__CLASS__, 'check_permission'),

public static function check_permission() {
    return is_user_logged_in();
}
```

#### Permissions 클래스 사용

```php
use PTG\Platform\Permissions;

// 사용자 ID 가져오기 (에러 시 WP_Error 반환)
$user_id = Permissions::get_user_id_or_error();
if (is_wp_error($user_id)) {
    return $user_id;
}

// Nonce 검증
if (!Permissions::verify_nonce($request)) {
    return Rest::unauthorized('유효하지 않은 요청입니다.');
}
```

---

## 캐싱

WordPress Object Cache를 사용하여 성능을 최적화합니다.

### 캐시 저장

```php
// 캐시 키 생성
$cache_key = 'ptg_quiz_question_' . $question_id;

// 캐시 저장 (1시간 = 3600초)
wp_cache_set($cache_key, $response_data, 'ptg_quiz', 3600);
```

### 캐시 조회

```php
// 캐시 조회
$cached = wp_cache_get($cache_key, 'ptg_quiz');
if ($cached !== false) {
    return Rest::success($cached);
}

// 캐시가 없으면 DB 조회 후 저장
$data = /* DB 조회 */;
wp_cache_set($cache_key, $data, 'ptg_quiz', 3600);
return Rest::success($data);
```

### 캐시 삭제

```php
// 특정 캐시 삭제
wp_cache_delete($cache_key, 'ptg_quiz');

// 그룹 전체 삭제
wp_cache_flush_group('ptg_quiz');
```

### 캐시 그룹

- `ptg_quiz` - 퀴즈 관련 캐시
- `ptg_study` - 학습 관련 캐시
- `ptg_platform` - 플랫폼 코어 캐시

---

## 전체 흐름 예시

### 예시 1: 문제 조회

```
1. JavaScript: 
   const response = await PTGPlatform.get('ptg-quiz/v1/questions/123');
   
2. WordPress REST API 라우팅:
   /wp-json/ptg-quiz/v1/questions/123
   → API::get_question($request) 호출
   
3. API 클래스 내부:
   - 캐시 확인
   - LegacyRepo::get_questions_with_categories() 호출
   - 또는 직접 $wpdb 쿼리 실행
   
4. 데이터베이스:
   SELECT * FROM ptgates_questions 
   WHERE question_id = 123
   
5. 데이터 가공:
   - 선택지 파싱
   - 해설 정리
   - 응답 데이터 구성
   
6. 응답 반환:
   return Rest::success($response_data);
   
7. JavaScript에서 사용:
   const questionData = response.data;
   renderQuestion(questionData);
```

### 예시 2: 문제 목록 조회

```
1. JavaScript:
   const params = new URLSearchParams();
   params.append('year', 2024);
   params.append('subject', '물리치료기초');
   params.append('limit', 5);
   const response = await PTGPlatform.get(
       `ptg-quiz/v1/questions?${params.toString()}`
   );
   
2. WordPress REST API:
   /wp-json/ptg-quiz/v1/questions?year=2024&subject=물리치료기초&limit=5
   → API::get_questions_list($request) 호출
   
3. API 클래스:
   - 캐시 키 생성
   - 캐시 확인
   - $wpdb로 복잡한 JOIN 쿼리 실행
   - 필터링 및 정렬
   
4. 데이터베이스:
   SELECT q.question_id
   FROM ptgates_questions q
   INNER JOIN ptgates_categories c ON q.question_id = c.question_id
   WHERE c.exam_year = 2024 
     AND c.subject = '물리치료기초'
     AND q.is_active = 1
   ORDER BY RAND()
   LIMIT 5
   
5. 응답:
   return Rest::success($question_ids); // [123, 456, 789, ...]
   
6. JavaScript:
   const questionIds = response.data;
   // questionIds 배열 사용
```

### 예시 3: 상태 업데이트 (PATCH)

```
1. JavaScript:
   await PTGPlatform.patch(
       `ptg-quiz/v1/questions/123/state`,
       { bookmarked: true, needs_review: false }
   );
   
2. WordPress REST API:
   PATCH /wp-json/ptg-quiz/v1/questions/123/state
   → API::update_question_state($request) 호출
   
3. 권한 체크:
   Permissions::get_user_id_or_error()
   
4. 데이터베이스:
   Repo::update('ptgates_user_states',
       array('bookmarked' => 1, 'needs_review' => 0),
       array('user_id' => $user_id, 'question_id' => 123)
   );
   
5. 응답:
   return Rest::success(array('updated' => true));
```

---

## 주의사항

### 1. SQL 인젝션 방지
- 항상 `$wpdb->prepare()` 사용
- 사용자 입력은 반드시 sanitize
- 컬럼명은 `esc_sql()` 사용

### 2. 권한 체크
- 로그인이 필요한 API는 `check_permission` 구현
- 사용자 ID는 `Permissions::get_user_id_or_error()` 사용

### 3. 에러 처리
- `Rest::error()` 또는 `Rest::success()` 사용
- JavaScript에서 `response.success` 체크 필수

### 4. 캐싱 전략
- 자주 조회되는 데이터는 캐싱
- 업데이트 시 관련 캐시 삭제
- 캐시 키는 고유하게 생성

### 5. 네임스페이스 규칙
- 각 모듈은 고유한 REST 네임스페이스 사용
- 충돌 방지를 위해 모듈별 prefix 사용

---

## 참고 파일

- `0000-ptgates-platform/includes/class-repo.php` - Repository 클래스
- `0000-ptgates-platform/includes/class-legacy-repo.php` - 레거시 테이블 접근
- `0000-ptgates-platform/includes/class-rest.php` - REST 응답 처리
- `0000-ptgates-platform/includes/class-permissions.php` - 권한 관리
- `0000-ptgates-platform/assets/js/platform.js` - JavaScript 헬퍼
- `1200-ptgates-quiz/includes/class-api.php` - Quiz API 예시
- `1200-ptgates-quiz/assets/js/quiz.js` - JavaScript 사용 예시

---

**작성일**: 2024년  
**버전**: 1.0  
**작성자**: PTGates 개발팀

