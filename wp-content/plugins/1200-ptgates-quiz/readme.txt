=== PTGates Quiz ===
Contributors: ptgates
Tags: learning, education, quiz, exam
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

문제 풀이 모듈 - 문제 카드, 선택지, 정답확인, 해설, 드로잉, 메모, 북마크 기능

== Description ==

PTGates Quiz는 문제 풀이를 위한 핵심 모듈입니다. 다음 기능을 제공합니다:

* 문제 카드 표시 및 선택지 입력
* 타이머 기능 (1교시 90분/2교시 75분 기본, 무제한 지원)
* 드로잉 기능 (문제 카드 내부에 오버레이 캔버스)
* 메모 기능 (패널/바텀시트)
* 북마크 및 복습 필요 표시
* 실시간 정답 확인 및 해설 표시
* 자동 저장 (드로잉 1~2초 디바운스, 메모 0.8~1.5초 디바운스)

== Installation ==

1. PTGates Platform 플러그인이 활성화되어 있어야 합니다
2. 플러그인 파일을 `wp-content/plugins/1200-ptgates-quiz/` 디렉토리에 업로드
3. WordPress 관리자 → 플러그인 → PTGates Quiz 활성화

== Usage ==

숏코드 사용:

```
[ptg_quiz question_id="123"]
```

옵션:
* `question_id`: 문제 ID (필수)
* `timer`: 타이머 시간 (분, 기본값: 90)
* `unlimited`: 무제한 모드 ("true" 또는 "1")

== REST API ==

네임스페이스: `ptg-quiz/v1`

* `PATCH /questions/{qid}/state` - 문제 상태 업데이트 (북마크, 복습 필요 등)
* `POST /questions/{qid}/attempt` - 문제 풀이 시도
* `GET /questions/{qid}/drawings` - 드로잉 목록 조회
* `POST /questions/{qid}/drawings` - 드로잉 저장
* `GET /explanation/{qid}` - 해설 조회

== Changelog ==

= 1.0.0 =
* 최초 릴리스
* 문제 풀이 UI 구현
* 타이머 기능
* 드로잉 기능 (기본)
* 메모 기능
* 북마크 및 복습 필요 기능

== Upgrade Notice ==

= 1.0.0 =
최초 릴리스입니다.

