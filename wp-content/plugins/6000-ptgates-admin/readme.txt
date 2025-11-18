=== PTGates Admin ===
Contributors: ptgates
Tags: admin, question bank, import, management
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

문제은행 관리 모듈 (관리자 전용) - CSV 일괄 삽입, 문제 편집/삭제 기능

== Description ==

PTGates Admin은 문제은행을 관리하기 위한 관리자 전용 모듈입니다. 다음 기능을 제공합니다:

* CSV 일괄 삽입 (기존 `/bk/import_exam` 기능)
* 문제 편집/삭제
* 문제 검색/필터링
* 문제 통계 (총 문제 수, 과목별 분포 등)
* 문제 미리보기
* REST API (`ptg-admin/v1/`)

== Installation ==

1. PTGates Platform 플러그인이 활성화되어 있어야 합니다
2. 플러그인 파일을 `wp-content/plugins/6000-ptgates-admin/` 디렉토리에 업로드
3. WordPress 관리자 → 플러그인 → PTGates Admin 활성화

== Usage ==

### 숏코드

페이지나 게시물에 다음 숏코드를 추가하세요:

```
[ptg_admin]
```

또는 타입 지정:

```
[ptg_admin type="import"]
```

**옵션:**
* `type`: `import` (CSV 일괄 삽입, 기본값), `list` (문제 목록), `stats` (통계)

**주의:** 관리자 권한(`manage_options`)이 필요합니다. 권한이 없는 사용자는 경고 메시지만 표시됩니다.

### 웹 인터페이스 (관리자 메뉴)

WordPress 관리자 페이지 → PTGates 문제 → CSV 일괄 삽입

1. CSV 파일 선택
2. 시작 버튼 클릭하여 데이터 삽입

### CLI

```bash
php wp-content/plugins/6000-ptgates-admin/includes/class-import.php [파일경로]
```

파일경로 생략 시 같은 폴더의 `exam_data.csv` 사용

== CSV 구조 ==

필수 컬럼:
* `content`: 문제 본문 전체 (지문, 보기, 이미지 경로 등 포함)
* `answer`: 정답 (객관식 번호, 주관식 답)
* `exam_course`: 교시 구분 (예: 1교시, 2교시)
* `subject`: 과목명 (예: 해부학, 물리치료학)

선택 컬럼:
* `explanation`: 문제 해설 내용
* `type`: 문제 유형 (예: 객관식, 주관식)
* `difficulty`: 난이도 (1:하, 2:중, 3:상, 기본값: 2)
* `exam_year`: 시험 시행 연도 (예: 2024)
* `exam_session`: 시험 회차 (예: 52)
* `source_company`: 문제 출처

== REST API ==

네임스페이스: `ptg-admin/v1`

(향후 구현 예정)

== Changelog ==

= 1.0.0 =
* 최초 릴리스
* CSV 일괄 삽입 기능 (기존 `/bk/import_exam` 기능 이전)
* CLI 지원
* WordPress 관리자 페이지 통합

== Upgrade Notice ==

= 1.0.0 =
최초 릴리스입니다.

