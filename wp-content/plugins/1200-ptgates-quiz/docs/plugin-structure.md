# 1200-ptgates-quiz 플러그인 구조 및 모드 설명

이 문서는 **1200-ptgates-quiz** 플러그인의 두 가지 핵심 모드인 **실전|Quiz**와 **모의시험**의 구조적 차이와 동작 로직을 설명합니다.

## 1. 개요 및 진입점

두 모드는 모두 동일한 `[ptg_quiz]` 숏코드를 기반으로 동작하지만, URL 파라미터 `mode`에 따라 PHP 템플릿 단계에서부터 UI와 로직이 분기됩니다.

| 모드         | URL 패턴     | 설명                                          |
| :----------- | :----------- | :-------------------------------------------- | ------------------------------------ |
| \*\*실전     | Quiz\*\*     | `?mode=` (없음)                               | 기본 모드. 과목별 문제 풀이 및 학습. |
| **모의시험** | `?mode=mock` | 시험 모드. 회차별 모의고사 응시 및 결과 제출. |

## 2. 화면 렌더링 (PHP)

파일: `templates/quiz-template.php`
`$is_mock_mode` 변수를 통해 헤더, 필터, 버튼 등 UI 요소를 조건부 렌더링합니다.

### 차이점 상세

1.  **헤더 타이틀**: `실전|Quiz` vs `모의시험`
2.  **필터 섹션**:
    - **실전|Quiz**: 교시, 과목, 세부과목, 문항 수 선택.
    - **모의시험**: 회차(Round), 교시(Course), 응시 모드(학습/시험) 선택.
3.  **과목 그리드**: 실전|Quiz 모드에서만 노출되며, 모의시험 모드에서는 숨겨짐.
4.  **시작 버튼**:
    - **초기화 ID**: `#ptg-quiz-start-btn` vs `#ptg-quiz-mock-start-btn`
    - **기능**: '조회' vs '시험 시작'

## 3. JavaScript 로직 분리

Core 엔진인 `quiz.js`는 두 모드에서 공통으로 사용되지만, 초기화 및 제어 방식이 다릅니다.

### 실전|Quiz 모드

- **Controller**: `quiz.js` (자체 처리)
- **동작**:
  - 사용자가 필터 선택 후 '조회' 버튼 클릭.
  - `loadQuestionsList()` 함수가 직접 실행되어 필터 조건에 맞는 문제를 로드.

### 모의시험 모드

- **Controller**: `assets/js/mock-quiz.js`
- **동작**:
  - `MockQuiz` 객체가 페이지 로드 시 초기화됨.
  - API (`/wp-json/ptg-quiz/v1/sessions/mock`)를 호출하여 회차 정보를 동적으로 로드.
  - '시험 시작' 버튼 클릭 시:
    1.  회차, 교시, 모드 값을 수집.
    2.  타이머 시간 설정 (1교시 90분, 2교시 75분).
    3.  **`PTGQuiz.startQuizWithParams(params)`**를 호출하여 `quiz.js`에 실행 위임.

## 4. 실행 및 결과 처리 (quiz.js Core)

`quiz.js`는 `QuizState.mode`에 따라 내부 동작을 다르게 수행합니다.

### A. 학습 모드 (`learning`)

- **목적**: 빠른 학습 및 피드백.
- **특징**:
  - 문제 풀이 즉시 정답/오답 확인 가능.
  - 해설 즉시 열람 가능.

### B. 시험 모드 (`mock_exam`)

- **목적**: 실제 시험 환경 시뮬레이션.
- **특징**:
  - **정답 확인 불가**: '정답 확인' 버튼이 숨겨짐.
  - **제출 프로세스**:
    - 마지막 문제 도달 시 '종료/제출' 버튼 활성화.
    - 제출 시 `submitMockExam()` 함수 실행.
    - 서버로 답안 전송 및 채점.
    - **결과 브리핑**: 리다이렉트 없이 현재 화면에 즉시 점수와 합격 여부(`PASS`/`FAIL`)를 표시.

## 5. 데이터베이스 스키마 및 과목 구조

프로젝트의 데이터 로직은 두 개의 주요 테이블을 사용하여 과목 체계를 관리합니다.

### A. ptgates_subject (과목 정의 마스터)

- **역할**: 과목 및 세부과목의 이름, 순서, 문항 수 등 **메타데이터의 기준(Source of Truth)**입니다.
- **주요 컬럼**:
  - `course_no` (INT): 교시 (1교시, 2교시, 3교시)
  - `category` (VARCHAR): 대분류 과목명 (Main Subject, 예: 물리치료 기초, 물리치료 진단평가)
  - `subcategory` (VARCHAR): 세부 과목명 (Subsubject, 예: 해부생리, 운동심리)
  - `questions` (INT): 해당 과목/세부과목의 배정 문항 수

### B. ptgates_categories (문항 매핑)

- **역할**: 개별 문제(`question_id`)가 어떤 과목/세부과목에 속하는지 연결하는 매핑 테이블입니다.
- **주요 컬럼**:
  - `question_id` (BIGINT): 문제 ID
  - `subject` (VARCHAR): **세부 과목명** (SubsubjectName) - `ptgates_subject.subcategory`와 매칭
  - `subject_category` (VARCHAR): **대분류 과목명** (MainSubjectName) - `ptgates_subject.category`와 매칭

> **중요 개발 규칙**:
>
> 1.  과목명, 교시별 구조, 문항 수 정보를 참조할 때는 반드시 `ptgates_subject` 테이블을 기준으로 해야 합니다.
> 2.  모의고사 결과 집계 시, 대분류(Main Subject) 기준으로 점수를 산출하기 위해서는 `ptgates_categories.subject_category` 컬럼을 사용해야 합니다. (`subject` 컬럼은 세부과목이므로 집계 단위가 너무 잘게 쪼개짐)
