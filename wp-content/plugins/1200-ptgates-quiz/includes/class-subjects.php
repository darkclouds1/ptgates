<?php
/**
 * PTGates Quiz - 교시 / 과목 / 세부과목 정적 정의
 *
 * - 데이터 출처: 사용자 정의 교과 구조
 * - 용도: 교시/과목/세부과목 셀렉트 옵션, 문항 수 비율 계산 등에서 공통 사용
 *
 * 주의:
 * - 이 클래스는 "정적 설정" 역할만 합니다. DB 스키마(ptGates_subject)와
 *   동기화가 필요하면 이 파일을 기준으로 마이그레이션을 작성하세요.
 */

namespace PTG\Quiz;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Subjects {
	/**
	 * 교시 / 과목 / 세부과목별 문항수 정의
	 *
	 * 구조:
	 * [
	 *   교시번호(int) => [
	 *     'total'    => 교시 전체 문항 수(int),
	 *     'subjects' => [
	 *       과목명(string) => [
	 *         'total' => 해당 과목 총 문항 수(int),
	 *         'subs'  => [
	 *           세부과목명(string) => 문항 수(int),
	 *           ...
	 *         ]
	 *       ],
	 *       ...
	 *     ]
	 *   ],
	 *   ...
	 * ]
	 *
	 * 예시:
	 * - self::MAP[1]['subjects']['물리치료 기초']['subs']['해부생리학'] === 22
	 */
	public const MAP = [
		// 1교시 (총 105문항)
		1 => [
			'total'    => 105,
			'subjects' => [
				// 물리치료 기초 60문항
				'물리치료 기초' => [
					'total' => 60,
					'subs'  => [
						'해부생리학'      => 22,
						'운동학'         => 12,
						'물리적 인자치료' => 16,
						'공중보건학'     => 10,
					],
				],
				// 물리치료 진단평가 45문항
				'물리치료 진단평가' => [
					'total' => 45,
					'subs'  => [
						'근골격계 물리치료 진단평가' => 10,
						'신경계 물리치료 진단평가'   => 16,
						'진단평가 원리'              => 6,
						'심폐혈관계 검사 및 평가'    => 4,
						'기타 계통 검사'             => 2,
						'임상의사결정'              => 7,
					],
				],
			],
		],

		// 2교시 (총 85문항)
		2 => [
			'total'    => 85,
			'subjects' => [
				// 물리치료 중재 65문항
				'물리치료 중재' => [
					'total' => 65,
					'subs'  => [
						'근골격계 중재'     => 28,
						'신경계 중재'       => 25,
						'심폐혈관계 중재'   => 5,
						'림프, 피부계 중재' => 2,
						'물리치료 문제해결' => 5,
					],
				],
				// 의료관계법규 20문항
				'의료관계법규' => [
					'total' => 20,
					'subs'  => [
						'의료법'         => 5,
						'의료기사법'     => 5,
						'노인복지법'     => 4,
						'장애인복지법'   => 3,
						'국민건강보험법' => 3,
					],
				],
			],
		],
	];

	/**
	 * 사용 예시 (How to use this MAP)
	 *
	 * 1) 교시 목록 가져오기
	 *    $sessions = \PTG\Quiz\Subjects::get_sessions(); // [1, 2]
	 *
	 * 2) 특정 교시의 상위 과목 목록
	 *    $subjects = \PTG\Quiz\Subjects::get_subjects_for_session(1);
	 *    // 예: ['물리치료 기초', '물리치료 진단평가']
	 *
	 * 3) 특정 교시+과목의 세부과목 목록
	 *    $subs = \PTG\Quiz\Subjects::get_subsubjects(1, '물리치료 기초');
	 *    // 예: ['해부생리학', '운동학', '물리적 인자치료', '공중보건학']
	 *
	 * 4) 특정 교시+과목+세부과목의 문항 수
	 *    $count = \PTG\Quiz\Subjects::get_count(1, '물리치료 기초', '해부생리학'); // 22
	 *
	 * 5) 총 문항 수 대비 비율 계산(예: 세부과목 비중)
	 *    $totalOfSession = self::MAP[1]['total']; // 105
	 *    $value = \PTG\Quiz\Subjects::get_count(1, '물리치료 기초', '해부생리학'); // 22
	 *    $ratio = $value !== null && $totalOfSession > 0 ? ($value / $totalOfSession) : 0.0;
	 *
	 * 6) 과목 총 문항 수 접근(정적 맵 직접 접근)
	 *    $subjectTotal = self::MAP[1]['subjects']['물리치료 기초']['total']; // 60
	 */

	/**
	 * 교시 목록 반환 (예: [1, 2])
	 *
	 * @return int[]
	 */
	public static function get_sessions(): array {
		return array_keys( self::MAP );
	}

	/**
	 * 특정 교시의 상위 과목 목록 반환
	 *
	 * @param int $session
	 * @return string[]
	 */
	public static function get_subjects_for_session( int $session ): array {
		if ( ! isset( self::MAP[ $session ]['subjects'] ) ) {
			return [];
		}
		return array_keys( self::MAP[ $session ]['subjects'] );
	}

	/**
	 * 특정 교시+과목의 세부과목 목록 반환
	 *
	 * @param int    $session
	 * @param string $subject
	 * @return string[]
	 */
	public static function get_subsubjects( int $session, string $subject ): array {
		if ( ! isset( self::MAP[ $session ]['subjects'][ $subject ]['subs'] ) ) {
			return [];
		}
		return array_keys( self::MAP[ $session ]['subjects'][ $subject ]['subs'] );
	}

	/**
	 * 특정 교시+과목+세부과목의 문항 수 반환
	 *
	 * 없으면 null 반환.
	 */
	public static function get_count( int $session, string $subject, string $subsubject ): ?int {
		if ( ! isset( self::MAP[ $session ]['subjects'][ $subject ]['subs'][ $subsubject ] ) ) {
			return null;
		}
		return (int) self::MAP[ $session ]['subjects'][ $subject ]['subs'][ $subsubject ];
	}

	/**
	 * 세부과목명으로 상위 과목명을 찾아 반환합니다.
	 *
	 * @param string $subsubject 세부과목명
	 * @return string|null 상위 과목명 또는 찾지 못한 경우 null
	 */
	public static function get_subject_from_subsubject( string $subsubject ): ?string {
		$needle = trim( (string) $subsubject );
		if ( $needle === '' ) {
			return null;
		}

		foreach ( self::MAP as $session_data ) {
			if ( empty( $session_data['subjects'] ) || ! is_array( $session_data['subjects'] ) ) {
				continue;
			}

			foreach ( $session_data['subjects'] as $subject_name => $subject_meta ) {
				if ( empty( $subject_meta['subs'] ) || ! is_array( $subject_meta['subs'] ) ) {
					continue;
				}

				foreach ( $subject_meta['subs'] as $sub_name => $count ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
					if ( self::is_subsubject_match( $needle, $sub_name ) ) {
						return $subject_name;
					}
				}
			}
		}

		return null;
	}

	/**
	 * 세부과목명이 동일하거나 유사한지 비교합니다.
	 */
	private static function is_subsubject_match( string $needle, string $candidate ): bool {
		if ( $needle === $candidate ) {
			return true;
		}

		$normalized_needle    = preg_replace( '/\s+|·/u', '', $needle );
		$normalized_candidate = preg_replace( '/\s+|·/u', '', $candidate );

		if ( $normalized_needle === $normalized_candidate ) {
			return true;
		}

		if ( stripos( $needle, $candidate ) !== false || stripos( $candidate, $needle ) !== false ) {
			return true;
		}

		return false;
	}
}


