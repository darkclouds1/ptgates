=== PTGates Platform ===
Contributors: ptgates
Tags: learning, education, quiz, exam
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

PTGates 플랫폼 코어 - 모든 모듈의 필수 의존성

== Description ==

PTGates Platform은 PTGates 학습 시스템의 핵심 플랫폼 코어입니다. 모든 기능 모듈의 기반이 되는 공통 기능을 제공합니다:

* 데이터베이스 스키마 관리
* 공통 Repository 클래스 (데이터베이스 접근)
* 권한 관리 및 Nonce 검증
* 공통 REST API 응답 처리
* 공통 JavaScript 헬퍼 함수
* 타임존 관리 (UTC/KST)

== Installation ==

1. 플러그인 파일을 `wp-content/plugins/0000-ptgates-platform/` 디렉토리에 업로드
2. WordPress 관리자 → 플러그인 → PTGates Platform 활성화
3. 활성화 시 필요한 데이터베이스 테이블이 자동 생성됩니다

== Frequently Asked Questions ==

= 다른 모듈을 사용하려면 반드시 필요하나요? =

네, 모든 기능 모듈의 필수 의존성입니다.

= 언인스톨 시 데이터가 삭제되나요? =

플랫폼 전용 테이블만 삭제됩니다. 기존 문제 데이터 테이블(ptgates_questions, ptgates_categories, ptgates_user_results)은 삭제되지 않습니다.

== Changelog ==

= 1.0.0 =
* 최초 릴리스
* 데이터베이스 마이그레이션 시스템
* 공통 Repository 클래스
* 권한 관리 클래스
* REST API 공통 응답 처리
* 공통 JavaScript 헬퍼

== Upgrade Notice ==

= 1.0.0 =
최초 릴리스입니다.

