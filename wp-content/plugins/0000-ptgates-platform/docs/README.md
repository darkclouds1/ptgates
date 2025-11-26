# PTGates 프로젝트 문서 인덱스

> 이 디렉토리는 ptGates 프로젝트의 모든 계획서, 공통 문서 및 참조 문서를 포함합니다.

---

## 📋 문서 목차

### 1. [프로젝트 아키텍처](./01-architecture.md)
프로젝트 전체 설계서 및 아키텍처 원칙
- 모듈 구조 (0000~5100)
- 아키텍처 원칙 (모듈 독립성, 변경 격리)
- 데이터베이스 구조 개요
- 학습자 중심 UX Flow
- 결제 모델 (Free + Premium)
- 개발 우선순위 및 로드맵
- JavaScript 로드 방식 가이드라인

---

### 2. [데이터베이스 스키마](./02-database-schema.md)
통합 데이터베이스 스키마 문서
- **참조 기준**: `ptgates_schema.txt` (실제 DB 덤프 파일)
- 기본 테이블 (ptgates_questions, ptgates_categories, ptgates_user_results)
- 플랫폼 코어 테이블 (ptgates_user_states 등)
- 모듈별 테이블 (암기카드, 모의고사, 마이노트 등)
- 트리거 및 뷰
- 테이블 관계도
- 개발 시 주의사항

**📌 중요**: 실제 DB 스키마가 필요한 경우 `ptgates_schema.txt` 파일을 참조하세요.

---

### 3. [API 가이드](./03-api-guide.md)
데이터베이스 접근 및 REST API 사용 가이드
- 아키텍처 구조 (3계층 구조)
- JavaScript에서의 API 호출
- WordPress REST API (Backend)
- 데이터베이스 접근 (Repository 패턴)
- 응답 처리 (Rest 클래스)
- 보안 및 권한
- 캐싱 전략
- 전체 흐름 예시

---

### 4. [문항 선택 및 순서 규칙](./04-subject-rules.md)
Study/Quiz 모드의 문항 선택 및 순서 로직 요구사항
- 문항 선택 로직 (비회원/로그인 회원)
- 문항 순서 로직 (랜덤 vs MAP 순서)
- 과목 및 세부 과목 순서 유지 (MAP 순서 반드시 준수)
- 적용 범위 (Study, Quiz, 학습하기, 모의고사)

**참조**: `0000-ptgates-platform/includes/class-subjects.php`의 MAP 상수

---

### 5. [성능 최적화](./05-performance.md)
5100 Dashboard 플러그인 성능 최적화 문서
- 성능 검증 리포트 (발견된 이슈)
- 완료된 최적화 작업
  - 인덱스 추가
  - 쿼리 통합
  - 캐싱 전략 도입
  - 불필요한 데이터 조회 최소화
- 전체 성능 개선 효과
- 추가 권장 사항

---

### 6. [개발 진행 상황](./06-development-status.md)
플랫폼 코어 및 모듈별 개발 완료 보고서
- 완료된 작업 목록
- 실제 DB 구조 확인 완료
- 생성된 파일 구조
- 주요 특징
- 다음 단계 (우선순위별)

---

### 7. [참고 문서](./07-references/)

#### 7.1 [과목 분포](./07-references/subject-distribution.md)
교시별 과목 문항수 출제 경향 (퍼센트)
- 1교시 (총 105문항)
- 2교시 (총 85문항)

**용도**: 퀴즈 과목 분류 및 select 요소에 사용

---

## 🔍 빠른 참조

### 자주 참조하는 문서

1. **아키텍처 이해가 필요한 경우**: [01-architecture.md](./01-architecture.md)
2. **DB 스키마 확인이 필요한 경우**: [02-database-schema.md](./02-database-schema.md) 또는 `ptgates_schema.txt`
3. **API 호출 방법이 필요한 경우**: [03-api-guide.md](./03-api-guide.md)
4. **문항 선택/순서 로직 구현이 필요한 경우**: [04-subject-rules.md](./04-subject-rules.md)
5. **성능 최적화가 필요한 경우**: [05-performance.md](./05-performance.md)

---

## 📌 중요 참조 파일

### 실제 DB 스키마 덤프
- **파일**: `ptgates_schema.txt`
- **용도**: 모든 `ptgates_xxxxx` 테이블의 최종 참조 기준
- **업데이트**: DB 스키마 변경 시 이 파일을 덤프하여 업데이트

### 교시/과목/세부과목 정의
- **파일**: `0000-ptgates-platform/includes/class-subjects.php`
- **용도**: MAP 상수에 교시/과목/세부과목 구조 및 순서 정의
- **위치**: 플랫폼 코어 (모든 모듈에서 사용 가능)

---

## 🔄 문서 업데이트 가이드

### 문서 수정 시
1. 해당 문서 파일 수정
2. 변경 사항을 문서 내 "업데이트 이력" 섹션에 기록
3. 이 README.md의 관련 링크가 유효한지 확인

### 새로운 문서 추가 시
1. 적절한 위치에 문서 작성
2. 이 README.md의 목차에 추가
3. 관련 문서에 크로스 레퍼런스 추가

### 중복 문서 발견 시
1. 중복 내용 확인
2. 하나로 통합하거나 명확히 구분
3. 중복 파일 삭제 후 이 인덱스 업데이트

---

## 📝 문서 버전 관리

각 문서는 독립적으로 버전 관리됩니다. 문서 내에 다음 정보를 포함하세요:
- **최종 업데이트**: YYYY-MM-DD
- **버전**: X.X.X
- **작성자/수정자**: 이름 또는 팀명

---

## 🔗 관련 문서

### 플러그인별 문서
- **9000-ptgates-exam-questions**: `9000-ptgates-exam-questions/README.md` (기출문제 참조용 플러그인, Admin 전용)

### 외부 참조
- WordPress REST API: https://developer.wordpress.org/rest-api/
- WordPress Plugin API: https://developer.wordpress.org/plugins/

---

**최종 업데이트**: 2025-01-XX  
**버전**: 1.0.0  
**유지보수**: PTGates 개발팀
