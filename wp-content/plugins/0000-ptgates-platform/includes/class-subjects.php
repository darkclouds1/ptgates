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
	 * DB에서 과목 설정 로드 (Table: ptgates_subject)
	 */
	private static function load_map() {
		global $wpdb;

		// 단일 테이블에서 모든 설정 로드
		// 정렬: 교시 -> ID 순 (등록 순서가 곧 정렬 순서라고 가정)
		$rows = $wpdb->get_results( "SELECT * FROM ptgates_subject ORDER BY course_no ASC, id ASC", ARRAY_A );

		$map = [];

		if ( $rows ) {
			foreach ( $rows as $row ) {
				$course_num   = (int) $row['course_no'];
				$category     = $row['category'];
				$subcategory  = $row['subcategory']; // NULL이면 합계행
				$questions    = (int) $row['questions'];

				// 교시 초기화
				if ( ! isset( $map[ $course_num ] ) ) {
					$map[ $course_num ] = [
						'total'    => 0, // 나중에 집계
						'subjects' => [],
					];
				}

				// 과목(대분류) 초기화
				if ( ! isset( $map[ $course_num ]['subjects'][ $category ] ) ) {
					$map[ $course_num ]['subjects'][ $category ] = [
						'total' => 0,
						'subs'  => [],
						'codes' => [],
					];
				}

				if ( is_null( $subcategory ) || $subcategory === '' ) {
					// 합계행(subcategory가 NULL)은 카테고리 정의용으로만 사용하고, 문항 수 합계는 직접 계산함
					// (사용자 요청: "과목(대분류)의 합계는 같은 category 의 questions 의 합계야.")
				} else {
					// 세부 과목
					$map[ $course_num ]['subjects'][ $category ]['subs'][ $subcategory ] = $questions;
					$map[ $course_num ]['subjects'][ $category ]['codes'][ $subcategory ] = $subcategory;
					
					// 대분류 총점 누적 계산
					$map[ $course_num ]['subjects'][ $category ]['total'] += $questions;
				}
			}

			// 후처리: 교시별 총점 계산 및 과목 총점이 0인 경우(합계행 누락) 자동 합산
			foreach ( $map as $c_num => &$c_data ) {
				$session_total = 0;
				foreach ( $c_data['subjects'] as $cat_name => &$cat_data ) {
					// 합계행이 없어서 total이 0인 경우, 세부과목 합계로 채움
					if ( $cat_data['total'] === 0 && ! empty( $cat_data['subs'] ) ) {
						$cat_data['total'] = array_sum( $cat_data['subs'] );
					}
					$session_total += $cat_data['total'];
				}
				$c_data['total'] = $session_total;
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
