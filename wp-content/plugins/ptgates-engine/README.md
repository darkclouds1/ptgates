# PTGates Learning Engine

물리치료사 국가고시 문제 학습 시스템 WordPress 플러그인

## 📋 개요

PTGates Learning Engine은 WordPress 기반의 기출문제 학습 시스템입니다. REST API 중심으로 설계되어 향후 React(또는 Next.js) SPA 프론트엔드로 쉽게 전환할 수 있습니다.

## ✨ 주요 기능

- **REST API 기반 구조**: `/wp-json/ptgates/v1/` 엔드포인트 제공
- **필터 기능**: 연도, 과목별 문제 필터링
- **실시간 피드백**: 답안 제출 시 즉시 정오답 피드백
- **해설 제공**: 기본 해설 및 고급 해설 표시
- **학습 기록**: 사용자별 풀이 기록 자동 저장 (로그인 사용자)
- **타이머 기능**: 전체 소요 시간 및 문제별 시간 추적
- **반응형 디자인**: 모바일/태블릿/데스크톱 지원

## 🚀 설치 방법

1. `wp-content/plugins/ptgates-engine` 폴더에 플러그인 파일 업로드
2. WordPress 관리자 → 플러그인 → PTGates Learning Engine 활성화
3. 플러그인 활성화 시 `ptgates_user_results` 테이블 자동 생성

## 📖 사용법

### Shortcode

페이지나 게시물에 다음 shortcode를 추가하세요:

```
[ptgates_quiz]
```

또는 옵션과 함께:

```
[ptgates_quiz year="2024" subject="해부학" limit="20"]
```

**옵션:**
- `year`: 시험 연도 (선택)
- `subject`: 과목명 (선택)
- `limit`: 문제 수 (기본값: 10)

### REST API 엔드포인트

#### 문제 목록 조회
```
GET /wp-json/ptgates/v1/questions?year=2024&subject=해부학&limit=10
```

#### 사용 가능한 연도 목록
```
GET /wp-json/ptgates/v1/years
```

#### 사용 가능한 과목 목록
```
GET /wp-json/ptgates/v1/subjects?year=2024
```

#### 학습 로그 저장
```
POST /wp-json/ptgates/v1/log
Content-Type: application/json
X-WP-Nonce: {nonce}

{
  "question_id": 123,
  "user_answer": "정답",
  "is_correct": true,
  "elapsed_time": 45
}
```

## 📁 파일 구조

```
ptgates-engine/
├── ptgates-engine.php          # 메인 플러그인 파일
├── includes/
│   ├── class-ptg-db.php        # DB 접근 클래스
│   ├── class-ptg-api.php       # REST API 엔드포인트
│   └── class-ptg-logger.php    # 로그 저장 클래스
├── assets/
│   ├── js/
│   │   ├── ptg-main.js         # 메인 로직
│   │   ├── ptg-ui.js           # UI 렌더링
│   │   └── ptg-timer.js        # 타이머 기능
│   └── css/
│       └── style.css           # 스타일시트
├── templates/
│   └── quiz-template.php       # 퀴즈 템플릿
└── README.md
```

## 🗄️ 데이터베이스

플러그인은 다음 테이블을 사용합니다:

- `ptgates_questions`: 문제 데이터 (읽기 전용)
- `ptgates_categories`: 문제 분류 정보 (읽기 전용)
- `ptgates_user_results`: 사용자 학습 기록 (쓰기 전용, 자동 생성)

**주의:** `ptgates_questions`와 `ptgates_categories` 테이블은 별도로 생성되어 있어야 합니다. 이 플러그인은 `ptgates_user_results` 테이블만 자동 생성합니다.

## 🔧 개발 정보

### 기술 스택
- **백엔드**: PHP 8.x, WordPress REST API
- **프론트엔드**: Vanilla JavaScript (ES6+), CSS3
- **데이터베이스**: MySQL (WordPress wpdb)

### 확장성
- REST API 기반 설계로 React/Next.js 등 다른 프론트엔드 프레임워크로 쉽게 전환 가능
- 모듈형 JavaScript 구조로 유지보수 용이
- WordPress 표준 코딩 규칙 준수

## 📝 라이선스

GPL v2 or later

## 🔗 참고

- WordPress REST API 문서: https://developer.wordpress.org/rest-api/
- 데이터베이스 스키마: `database-schema.md` 참조
