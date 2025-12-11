<?php
/**
 * PTGates Platform - 교시 / 과목 / 세부과목 정적 정의
 *
 * - 데이터 출처: 사용자 정의 교과 구조 (DB: ptgates_exam_course_config, ptgates_subject_config)
 * - 용도: 교시/과목/세부과목 셀렉트 옵션, 문항 수 비율 계산 등에서 공통 사용
 * - 위치: 0000-ptgates-platform (플랫폼 코어) - 최초 로드 시 자동 메모리에 로드
 *
 * 주의:
 * - 이 클래스는 DB 설정을 로드하여 정적 맵처럼 동작합니다.
 * - 원본 위치: 1200-ptgates-quiz/includes/class-subjects.php (호환성 유지)
 * 
 * ⚠️ 중요: 과목 및 세부 과목 순서 규칙
 * - DB의 sort_order를 따릅니다.
 * - 문항(question)만 랜덤하게 섞여야 합니다.
 * - 자세한 요구사항: docs/subject-question-rules.md 참조
 */

namespace PTG\Quiz;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// 중복 로드 방지: 이미 로드되었으면 중단
if ( class_exists( '\PTG\Quiz\Subjects' ) ) {
	return;
}

class Subjects {
	/**
	 * 교시 / 과목 / 세부과목별 문항수 정의 (DB 로드)
	 *
	 * @var array|null
	 */
	private static $map = null;

	/**
	 * 데이터 로드 여부 확인 및 로드
	 */
	public static function init() {
		if ( self::$map === null ) {
			self::load_map();
		}
	}

	/**
	 * DB에서 과목 설정 로드
	 */
	private static function load_map() {
		global $wpdb;

		// 1. 교시 설정 로드
		$course_config = $wpdb->get_results( "SELECT * FROM ptgates_exam_course_config WHERE is_active = 1", ARRAY_A );
		
		// 2. 과목 설정 로드 (정렬: sort_order)
		$subject_config = $wpdb->get_results( "SELECT * FROM ptgates_subject_config WHERE is_active = 1 ORDER BY sort_order ASC", ARRAY_A );

		$map = [];

		// 교시 초기화
		if ( $course_config ) {
			foreach ( $course_config as $course ) {
				// exam_course가 '1교시', '2교시' 형태라고 가정하고 숫자만 추출하거나 매핑
				$course_num = (int) preg_replace( '/[^0-9]/', '', $course['exam_course'] );
				if ( $course_num > 0 ) {
					$map[ $course_num ] = [
						'total'    => (int) $course['total_questions'],
						'subjects' => [],
					];
				}
			}
		}

		// 과목 및 세부과목 구성
		if ( $subject_config ) {
			foreach ( $subject_config as $row ) {
				$course_num = (int) preg_replace( '/[^0-9]/', '', $row['exam_course'] );
				$main_subject = $row['subject_category']; // 대분류
				$sub_subject = $row['subject']; // 세부과목
				$count = (int) $row['question_count'];
				$subject_code = $row['subject_code']; // 코드

				if ( isset( $map[ $course_num ] ) ) {
					// 대분류가 없으면 초기화
					if ( ! isset( $map[ $course_num ]['subjects'][ $main_subject ] ) ) {
						$map[ $course_num ]['subjects'][ $main_subject ] = [
							'total' => 0,
							'subs'  => [],
							'codes' => [], // subject_code 매핑 추가
						];
					}

					// 세부과목 추가
					$map[ $course_num ]['subjects'][ $main_subject ]['subs'][ $sub_subject ] = $count;
					$map[ $course_num ]['subjects'][ $main_subject ]['codes'][ $sub_subject ] = $subject_code;
					
					// 대분류 총점 누적
					$map[ $course_num ]['subjects'][ $main_subject ]['total'] += $count;
				}
			}
		}

		self::$map = $map;
	}

	/**
	 * 교시 목록 반환 (예: [1, 2])
	 *
	 * @return int[]
	 */
	public static function get_sessions(): array {
		self::init();
		return array_keys( self::$map );
	}

	/**
	 * 특정 교시의 상위 과목 목록 반환
	 *
	 * @param int $session
	 * @return string[]
	 */
	public static function get_subjects_for_session( int $session ): array {
		self::init();
		if ( ! isset( self::$map[ $session ]['subjects'] ) ) {
			return [];
		}
		return array_keys( self::$map[ $session ]['subjects'] );
	}

	/**
	 * 특정 교시+과목의 세부과목 목록 반환
	 *
	 * @param int    $session
	 * @param string $subject
	 * @return string[]
	 */
	public static function get_subsubjects( int $session, string $subject ): array {
		self::init();
		if ( ! isset( self::$map[ $session ]['subjects'][ $subject ]['subs'] ) ) {
			return [];
		}
		return array_keys( self::$map[ $session ]['subjects'][ $subject ]['subs'] );
	}

	/**
	 * 특정 교시+과목+세부과목의 문항 수 반환
	 *
	 * 없으면 null 반환.
	 */
	public static function get_count( int $session, string $subject, string $subsubject ): ?int {
		self::init();
		if ( ! isset( self::$map[ $session ]['subjects'][ $subject ]['subs'][ $subsubject ] ) ) {
			return null;
		}
		return (int) self::$map[ $session ]['subjects'][ $subject ]['subs'][ $subsubject ];
	}

	/**
	 * 세부과목명으로 subject_code 반환
	 */
	public static function get_code( int $session, string $subject, string $subsubject ): ?string {
		self::init();
		if ( ! isset( self::$map[ $session ]['subjects'][ $subject ]['codes'][ $subsubject ] ) ) {
			return null;
		}
		return self::$map[ $session ]['subjects'][ $subject ]['codes'][ $subsubject ];
	}

	/**
	 * Cache for subject lookups to avoid repeated DB queries.
	 *
	 * @var array<string, string|null>
	 */
	private static $subject_cache = [];

	/**
	 * 세부과목명으로 상위 과목명을 찾아 반환합니다.
	 *
	 * DB(ptgates_categories)의 subject_category 컬럼을 조회하여 반환합니다.
	 * 성능을 위해 메모리 캐싱을 사용합니다.
	 *
	 * @param string $subsubject 세부과목명
	 * @return string|null 상위 과목명 또는 찾지 못한 경우 null
	 */
	public static function get_subject_from_subsubject( string $subsubject ): ?string {
		global $wpdb;

		$needle = trim( (string) $subsubject );
		if ( $needle === '' ) {
			return null;
		}

		// Check memory cache first
		if ( array_key_exists( $needle, self::$subject_cache ) ) {
			return self::$subject_cache[ $needle ];
		}

		// Query DB for parent subject category
		$table_name = 'ptgates_categories';
		
		// Prepare query
		$query = $wpdb->prepare(
			"SELECT subject_category FROM {$table_name} WHERE subject = %s AND subject_category IS NOT NULL AND subject_category != '' LIMIT 1",
			$needle
		);

		$parent_subject = $wpdb->get_var( $query );

		// Cache the result (even if null)
		self::$subject_cache[ $needle ] = $parent_subject;

		return $parent_subject;
	}

	/**
	 * 출제 비율 계산 (subject_code 기준)
	 * 
	 * @param string $subject_code
	 * @return float 비율 (0.0 ~ 1.0)
	 */
	public static function get_distribution_ratio( string $subject_code ): float {
		self::init();
		
		// 전체 문항 수 합계 계산 (모든 교시 포함)
		$total_questions_all = 0;
		$target_count = 0;

		foreach ( self::$map as $session_data ) {
			foreach ( $session_data['subjects'] as $main_sub ) {
				foreach ( $main_sub['subs'] as $sub_name => $count ) {
					$total_questions_all += $count;
					$code = $main_sub['codes'][$sub_name] ?? '';
					if ( $code === $subject_code ) {
						$target_count = $count;
					}
				}
			}
		}

		if ( $total_questions_all === 0 ) {
			return 0.0;
		}

		return $target_count / $total_questions_all;
	}

	/**
	 * 모의고사 출제 문항 수 계산
	 * 
	 * @param int $n 요청 문항 수
	 * @param string $subject_code 과목 코드
	 * @return int 할당된 문항 수
	 */
	public static function get_questions_for_exam( int $n, string $subject_code ): int {
		$ratio = self::get_distribution_ratio( $subject_code );
		return (int) round( $ratio * $n );
	}

	/**
	 * 전체 맵 반환 (디버깅용)
	 */
	public static function get_map() {
		self::init();
		return self::$map;
	}
}
