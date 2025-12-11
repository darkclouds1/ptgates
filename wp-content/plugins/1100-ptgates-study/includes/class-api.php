<?php
namespace PTG\Study;

use PTG\Platform\LegacyRepo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Study_API {
    public static function register_routes() {
        // 'courses'는 이제 과목 목록을 반환
        register_rest_route('ptg-study/v1', '/courses', [
            'methods' => 'GET',
            'callback' => [ __CLASS__, 'get_courses' ],
            'permission_callback' => '__return_true', // 실제 운영에서는 권한 체크 필요
        ]);
        
        // 'course_id'는 이제 URL 인코딩된 과목명을 받음
        register_rest_route('ptg-study/v1', '/courses/(?P<course_id>[^/]+)', [
            'methods' => 'GET',
            'callback' => [ __CLASS__, 'get_course_detail' ],
            'permission_callback' => '__return_true', // 실제 운영에서는 권한 체크 필요
            'args' => [
                'subjects' => [
                    'description' => '카테고리에 포함된 과목 ID 목록(쉼표 구분)',
                    'sanitize_callback' => function($param) {
                        if (is_array($param)) {
                            $param = implode(',', $param);
                        }
                        return sanitize_text_field($param);
                    },
                ],
                'limit' => [
                    'description' => '한 번에 가져올 최대 문제 수',
                    'sanitize_callback' => 'absint',
                ],
            ],
		]);

		register_rest_route(
			'ptg-study/v1',
			'/study-progress',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'log_study_progress' ],
				'permission_callback' => function() {
					return is_user_logged_in();
				},
				'args'                => [
					'question_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => function( $value ) {
							return absint( $value ) > 0;
						},
					],
				],
			]
		);
    }

    /**
     * 학습 가능한 과목 목록 (코스) 반환
     */
    public static function get_courses($request) {
        $subjects = LegacyRepo::get_available_subjects();
        $subjects = array_unique(array_filter(array_map('trim', $subjects)));

        if (empty($subjects)) {
            return new \WP_REST_Response([], 200);
        }

        $definitions = self::get_subject_categories();
        $grouped_subjects = [];
        $unmatched_subjects = [];

        foreach ($subjects as $subject) {
            $matched_key = null;
            foreach ($definitions as $key => $definition) {
                if (in_array($subject, $definition['aliases'], true)) {
                    $matched_key = $key;
                    break;
                }
            }

            if ($matched_key) {
                $grouped_subjects[$matched_key][] = $subject;
            } else {
                $unmatched_subjects[] = $subject;
            }
        }

        $courses = [];
        foreach ($definitions as $key => $definition) {
            if (empty($grouped_subjects[$key])) {
                continue;
            }

            sort($grouped_subjects[$key], SORT_LOCALE_STRING);

            $courses[] = [
                'id' => $key,
                'slug' => $key,
                'label' => $definition['label'],
                'title' => (isset($definition['emoji']) ? $definition['emoji'] . ' ' : '') . $definition['label'],
                'emoji' => $definition['emoji'] ?? '',
                'description' => $definition['description'] ?? '',
                'subjects' => array_map(function($subject) {
                    return [
                        'id' => rawurlencode($subject),
                        'title' => $subject,
                    ];
                }, $grouped_subjects[$key]),
            ];
        }

        if (!empty($unmatched_subjects)) {
            sort($unmatched_subjects, SORT_LOCALE_STRING);
            $courses[] = [
                'id' => 'others',
                'slug' => 'others',
                'label' => '기타 과목',
                'title' => '📚 기타 과목',
                'emoji' => '📚',
                'description' => '지정된 분류에 포함되지 않은 과목',
                'subjects' => array_map(function($subject) {
                    return [
                        'id' => rawurlencode($subject),
                        'title' => $subject,
                    ];
                }, $unmatched_subjects),
            ];
        }

        return new \WP_REST_Response($courses, 200);
    }

    private static function get_subject_categories() {
        return [
            'anatomy_physiology' => [
                'emoji' => '🧠',
                'label' => '해부생리학',
                'description' => '인체 구조와 생리 기초 이론',
                'aliases' => [
                    '해부생리',
                    '해부생리학',
                    '해부학',
                    '생리학',
                    '생리학 (병태생리)',
                    '생리학 (신경생리)',
                    '기능해부학',
                    '기능해부학 (Kinesiology)',
                    '기능해부학 / 생리학',
                    '신경해부학',
                ],
            ],
            'kinesiology' => [
                'emoji' => '💪',
                'label' => '운동학',
                'description' => '운동 역학과 보행 분석, 운동 생리',
                'aliases' => [
                    '생체역학 (Kinesiology)',
                    '보행분석 (Kinesiology)',
                    '운동생리학 / 운동치료학',
                ],
            ],
            'physical_agents' => [
                'emoji' => '⚡',
                'label' => '물리적 인자치료',
                'description' => '전기·수치료 등 물리적 치료 인자',
                'aliases' => [
                    '물리적 인자치료 (Electrotherapy)',
                    '물리적 인자치료 (Hydrotherapy)',
                    '물리적 인자치료 (Phototherapy)',
                    '물리적 인자치료 (Thermotherapy)',
                    '물리적 인자치료 / 진단평가',
                    '수중치료',
                ],
            ],
            'msk_assessment' => [
                'emoji' => '🦴',
                'label' => '근골격계 물리치료 진단평가',
                'description' => '근골격계 평가 및 기록',
                'aliases' => [
                    '물리치료 진단평가',
                    '물리치료 진단평가 (MMT / 근골격계)',
                    '물리치료 진단평가 (MMT)',
                    '물리치료 진단평가 (관절가동범위)',
                    '물리치료 진단평가 (근골격계)',
                    '물리치료 진단평가 (자세분석)',
                    '물리치료 진단평가 (기록)',
                    '물리치료 진단평가 (관리모델)',
                ],
            ],
            'neuro_assessment' => [
                'emoji' => '🧩',
                'label' => '신경계 물리치료 진단평가',
                'description' => '신경계·심폐·아동 평가 및 연구·윤리',
                'aliases' => [
                    '물리치료 진단평가 (신경계)',
                    '물리치료 진단평가 (심폐계)',
                    '물리치료 진단평가 (아동)',
                    '물리치료 진단평가 (아동) (2교시)',
                    '물리치료 진단평가 (ICF)',
                    '물리치료 진단평가 (연구방법론)',
                    '물리치료 진단평가 (연구윤리)',
                    '물리치료 진단평가 (의료윤리)',
                    '물리치료 진단평가 (피부)',
                ],
            ],
            'msk_intervention' => [
                'emoji' => '💪',
                'label' => '근골격계 중재',
                'description' => '근골격계 중심 중재 및 보조기',
                'aliases' => [
                    '물리치료 중재 (근골격계)',
                    '물리치료 중재 (근골격계/소아)',
                    '물리치료 중재 (도수치료)',
                    '물리치료 중재 (운동치료)',
                    '물리치료 중재 (운동치료/보행)',
                    '보조기 (Orthotics)',
                    '보조도구 (Assistive device)',
                ],
            ],
            'neuro_intervention' => [
                'emoji' => '🧠',
                'label' => '신경계 중재',
                'description' => '신경계 및 아동 중재 기법',
                'aliases' => [
                    '물리치료 중재 (신경계)',
                    '물리치료 중재 (신경계/근골격계)',
                    '물리치료 중재 (신경계/아동)',
                ],
            ],
            'cardiopulmonary_intervention' => [
                'emoji' => '❤️',
                'label' => '심폐혈관계 중재',
                'description' => '심폐계 및 운동생리 중재',
                'aliases' => [
                    '물리치료 중재 (심폐계)',
                    '물리치료 중재 (심폐계/운동생리)',
                ],
            ],
            'integumentary_intervention' => [
                'emoji' => '🩹',
                'label' => '피부계 중재',
                'description' => '피부 및 물리적 인자 기반 중재',
                'aliases' => [
                    '물리치료 중재 (피부)',
                    '물리치료 중재 (물리적인자치료/피부)',
                    '물리치료 중재 (물리적인자치료)',
                    '물리치료 중재 (물리적인자치료/수치료)',
                    '물리치료 중재 (림프)',
                ],
            ],
            'medical_law' => [
                'emoji' => '⚖️',
                'label' => '의료관계법규',
                'description' => '의료법규와 공중보건 관련 이론',
                'aliases' => [
                    '의료관계법규',
                    '공중보건학',
                    '공중보건학 (감염병)',
                    '공중보건학 (모자보건)',
                    '공중보건학 (보건교육)',
                    '공중보건학 (역학)',
                    '공중보건학 (인구보건)',
                    '공중보건학 (환경보건)',
                ],
            ],
        ];
    }
    /**
     * 특정 과목(코스)에 대한 학습자료(문제 목록) 반환
     */
    public static function get_course_detail($request) {
        // Subjects 클래스 로드 보장
        if ( ! class_exists( '\\PTG\\Quiz\\Subjects' ) ) {
            $platform_subjects_file = WP_PLUGIN_DIR . '/0000-ptgates-platform/includes/class-subjects.php';
            if ( file_exists( $platform_subjects_file ) ) {
                require_once $platform_subjects_file;
            } else {
                $quiz_subjects_file = WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php';
                if ( file_exists( $quiz_subjects_file ) ) {
                    require_once $quiz_subjects_file;
                }
            }
        }

        $course_id = $request['course_id'];
        $subjects_param = $request->get_param('subjects');
        $limit = (int) $request->get_param('limit');
        
        // 기본값 설정 (Map에서 덮어씌워질 예정)
        if ($limit <= 0) {
			$limit = 50; 
        }

        // 페이지네이션을 위한 offset
		$offset = (int) $request->get_param('offset');
		if ($offset < 0) {
			$offset = 0;
		}

        // 이미 조회된 문제 ID 목록 (콤마 구분 문자열)
        $exclude_ids_param = $request->get_param('exclude_ids');
        $exclude_ids = [];
        if (!empty($exclude_ids_param)) {
            $exclude_ids = array_map('absint', explode(',', $exclude_ids_param));
            $exclude_ids = array_filter($exclude_ids);
        }

		// 랜덤 섞기 플래그
		$random = (bool) $request->get_param('random');
        $wrong_only = $request->get_param('wrong_only') === '1';

        if (!empty($subjects_param)) {
            if (is_array($subjects_param)) {
                $subject_names = array_map('sanitize_text_field', $subjects_param);
            } else {
                $subject_names = array_map('sanitize_text_field', explode(',', $subjects_param));
            }

            $subject_names = array_filter(array_map('trim', $subject_names));

            if (empty($subject_names)) {
                return new \WP_Error('invalid_subjects', '선택된 과목 정보가 올바르지 않습니다.', ['status' => 400]);
            }

            $subject_category = $request->get_param('subject_category');

            // 프론트엔드 ID를 DB subject_category 값으로 매핑
            $category_map = [
                'ptg-foundation'   => '물리치료 기초',
                'ptg-assessment'   => '물리치료 진단평가',
                'ptg-intervention' => '물리치료 중재',
                'ptg-medlaw'       => '의료관계법규',
            ];
            
            if (isset($category_map[$subject_category])) {
                $subject_category = $category_map[$subject_category];
            }

            // [과목 선택 모드] Subjects::MAP에서 해당 과목의 총 문항 수(total)를 찾아 Limit 적용
            $max_items = 0;
            
            if ( class_exists( '\\PTG\\Quiz\\Subjects' ) ) {
                $map = \PTG\Quiz\Subjects::get_map();
                // 1. subject_category가 있으면 직접 조회 (가장 정확)
                if ( ! empty( $subject_category ) ) {
                    foreach ( $map as $session_data ) {
                        if ( isset( $session_data['subjects'][ $subject_category ]['total'] ) ) {
                            $max_items = (int) $session_data['subjects'][ $subject_category ]['total'];
                            break;
                        }
                    }
                }

                // 2. 못 찾았으면 세부과목 매칭 시도
                if ( $max_items === 0 ) {
                    foreach ( $map as $session_data ) {
                        if ( ! empty( $session_data['subjects'] ) ) {
                            foreach ( $session_data['subjects'] as $subj_name => $subj_data ) {
                                $is_match = false;
                                foreach ($subject_names as $req_sub) {
                                    $needle = preg_replace( '/\s+|·/u', '', $req_sub );
                                    if ( ! empty( $subj_data['subs'] ) ) {
                                        foreach ( $subj_data['subs'] as $sub_key => $sub_val ) {
                                            $candidate = preg_replace( '/\s+|·/u', '', $sub_key );
                                            if ( $needle === $candidate ) {
                                                $is_match = true;
                                                break 2; // Found sub-subject match
                                            }
                                        }
                                    }
                                }
                                
                                if ( $is_match && isset( $subj_data['total'] ) ) {
                                    $max_items = (int) $subj_data['total'];
                                    break 2; 
                                }
                            }
                        }
                    }
                }
            }

            // 집계 모드: 각 세부과목의 문제를 모두 모은 후 question_id ASC 정렬
            $questions_map = [];

            // 최적화: subject_category 컬럼이 없으므로 subjects 배열(IN 절)을 사용하여 한 번에 조회
            $args = [
                'subjects'         => $subject_names,
                'limit'            => ($max_items > 0) ? $max_items : 1000,
                'offset'           => 0,
                'exam_session_min' => 1000,
                'wrong_only_user_id' => $wrong_only ? get_current_user_id() : null,
            ];
            
            if ($random) {
                $args['random'] = true;
            }

            $results = LegacyRepo::get_questions_with_categories($args);
            foreach ($results as $row) {
                $questions_map[$row['question_id']] = $row;
            }

            if (empty($questions_map)) {
                // 틀린 문제만 보기 모드일 때는 404 에러 대신 빈 결과를 반환
                if ($wrong_only) {
                     return new \WP_REST_Response([
                        'id'        => $course_id,
                        'title'     => $category_label ?? $course_id,
                        'aggregate' => true,
                        'subjects'  => $subject_names,
                        'lessons'   => [],
                        'limit'     => $limit,
                        'offset'    => 0,
                        'total'     => 0,
                        'random'    => $random,
                    ], 200);
                }
                return new \WP_Error('no_questions', '해당 분류에 대한 문제가 없습니다.', ['status' => 404]);
            }

            $questions = array_values($questions_map);
            
            // Fix: Capture total count before filtering exclude_ids
            // This ensures the frontend receives the full count (e.g., 60) instead of the remaining count
            $total_count = count($questions);
            if ($max_items > 0 && $total_count > $max_items) {
                $total_count = $max_items;
            }
            
            // exclude_ids 필터링 (이미 클라이언트에 있는 문제 제외)
            if (!empty($exclude_ids)) {
                $questions = array_filter($questions, function($q) use ($exclude_ids) {
                    return !in_array((int)$q['question_id'], $exclude_ids, true);
                });
                $questions = array_values($questions); // 인덱스 재정렬
            }

            if ($random) {
                shuffle($questions);
            } else {
                usort($questions, function($a, $b) {
                    return $a['question_id'] <=> $b['question_id'];
                });
            }

            // Map에 정의된 총 문항 수로 전체 풀 제한 (랜덤이 아닐 때만 의미가 있거나, 전체 풀 사이즈 제한용)
            if ($max_items > 0 && count($questions) > $max_items) {
                $questions = array_slice($questions, 0, $max_items);
            }

            // $total_count is already set above

            // 페이지네이션 적용 (요청된 limit 사용)
            // exclude_ids가 있으면 이미 앞부분을 제외했으므로 offset은 0으로 처리해야 함 (특히 랜덤 모드에서 중요)
            $slice_offset = (!empty($exclude_ids)) ? 0 : $offset;
            $paged_questions = array_slice($questions, $slice_offset, $limit);

            // 사용자 통계 조회
            $user_id = get_current_user_id();
            $question_ids = array_column($paged_questions, 'question_id');
            $user_stats = LegacyRepo::get_user_question_stats($user_id, $question_ids);
            $user_states = LegacyRepo::get_user_states($user_id, $question_ids);

            $formatted_lessons = array_map(function($q) use ($user_stats, $user_states) {
                $stats = isset($user_stats[$q['question_id']]) ? $user_stats[$q['question_id']] : null;
                $state = isset($user_states[$q['question_id']]) ? $user_states[$q['question_id']] : null;
                
                if ($state && $stats) {
                    $stats = array_merge($stats, $state);
                } elseif ($state) {
                    $stats = $state;
                }
                return [
                    'id'          => $q['question_id'],
                    'title'       => '문제 #' . $q['question_id'],
                    'content'     => $q['content'],
                    'answer'      => $q['answer'],
                    'explanation' => $q['explanation'],
                    'question_image' => isset($q['question_image']) ? $q['question_image'] : null,
                    'category'    => [
                        'year'    => $q['exam_year'],
                        'session' => isset($q['exam_session']) ? $q['exam_session'] : null,
                        'subject' => $q['subject'],
                    ],
                    'user_stats'  => $stats,
                ];
            }, $paged_questions);

            $definitions    = self::get_subject_categories();
            $category_label = $definitions[$course_id]['label'] ?? $course_id;

            $response_data = [
                'id'        => $course_id,
                'title'     => $category_label,
                'aggregate' => true,
                'subjects'  => $subject_names,
                'lessons'   => $formatted_lessons,
                'limit'     => $limit,
                'offset'    => (!empty($exclude_ids)) ? 0 : $offset,
                'total'     => $total_count,
            ];

            return new \WP_REST_Response($response_data, 200);
        }

		$subject = urldecode($course_id);
        $user_id = get_current_user_id();
        $is_smart_random = $random && $user_id;
        // $wrong_only extracted above

        // [세부과목 선택 모드] Subjects::MAP에서 해당 세부과목의 문항 수를 찾아 Limit 적용
        $max_items = 0;
        $matched_subject = $subject; // 기본값은 요청된 과목명

        // 0. DB에 정확히 일치하는 과목명이 있는지 먼저 확인 (Fuzzy Match로 인한 오작동 방지)
        // 예: DB에 '해부생리'가 있는데 '해부생리학'으로 매핑되는 문제 방지
        $db_subjects = LegacyRepo::get_available_subjects();
        $is_exact_db_match = in_array($subject, $db_subjects);

        if ( class_exists( '\\PTG\\Quiz\\Subjects' ) ) {
            $needle = urldecode($course_id);
            if (class_exists('Normalizer')) {
                $needle = \Normalizer::normalize($needle, \Normalizer::FORM_C);
            }
            $needle = preg_replace( '/\s+|·/u', '', $needle );

            $map = \PTG\Quiz\Subjects::get_map();
            foreach ( $map as $session_data ) {
                if ( ! empty( $session_data['subjects'] ) ) {
                    foreach ( $session_data['subjects'] as $subj_name => $subj_data ) {
                        if ( ! empty( $subj_data['subs'] ) ) {
                            foreach ( $subj_data['subs'] as $sub_name => $count ) {
                                // DB에 정확히 일치하는 과목이 있으면, Map 매핑보다 우선함 (단, max_items는 가져옴)
                                if ($is_exact_db_match && $sub_name !== $subject) {
                                    // DB에 '해부생리'가 있고 요청도 '해부생리'인데, Map의 '해부생리학'과 매칭되려 하면 건너뜀
                                    // 단, Map에 '해부생리'라는 키가 있으면 매칭됨
                                    if (preg_replace( '/\s+|·/u', '', $sub_name ) !== $needle) {
                                         continue;
                                    }
                                }

                                $candidate = $sub_name;
                                if (class_exists('Normalizer')) {
                                    $candidate = \Normalizer::normalize($candidate, \Normalizer::FORM_C);
                                }
                                $candidate = preg_replace( '/\s+|·/u', '', $candidate );
                                
                                if ( $needle === $candidate || stripos($needle, $candidate) !== false || stripos($candidate, $needle) !== false ) {
                                    $max_items = (int) $count;
                                    // DB에 정확한 매칭이 있으면 그 이름을 유지, 아니면 Map의 이름을 사용
                                    if (!$is_exact_db_match) {
                                        $matched_subject = $sub_name; 
                                    }
                                    break 3; // Found match
                                }
                            }
                        }
                    }
                }
            }
        }

        // Legacy DB와 신규 Config 간의 과목명 불일치 보정
        $legacy_subject_map = [
            // '해부생리' => '해부생리학', // DB에 '해부생리'로 저장된 경우 그대로 조회해야 함
            '운동학' => '운동학', // 일치
            // 필요 시 추가
        ];

        if ( isset( $legacy_subject_map[ $matched_subject ] ) ) {
            $matched_subject = $legacy_subject_map[ $matched_subject ];
        }

        $repo_limit = $limit;
        if ($max_items > 0) {
             // Optimization: Request fewer items if we know we are near the limit
             $repo_limit = min($limit, max(0, $max_items - $offset));
        }

		// 세부과목 단일 조회
		$args = [
			'subject'          => $matched_subject,
			'limit'            => ($random && !$is_smart_random) ? 1000 : $repo_limit, 
			'offset'           => (!empty($exclude_ids)) ? 0 : ($random ? 0 : $offset), // exclude_ids가 있으면 offset 0
			'exam_session_min' => 1000,
            'random'           => $random,
            'smart_random_user_id' => $is_smart_random ? $user_id : null,
            'smart_random_exclude_correct' => $is_smart_random, 
            'wrong_only_user_id' => $wrong_only ? $user_id : null,
            'exclude_ids'      => $exclude_ids,
		];

		$questions = LegacyRepo::get_questions_with_categories($args);
		$total_count = LegacyRepo::count_questions_with_categories([
			'subject'          => $matched_subject,
			'exam_session_min' => 1000,
		]);
        
        // Fallback: If Map lookup failed, use DB count as max_items
        if ($max_items == 0) {
            $max_items = $total_count;
        }

        // Enforce Limit (Post-Fetch Slicing)
        // This ensures we never return more than allowed, even if Repo returned more or random mode fetched duplicates
        $remaining = max(0, $max_items - $offset);
        if (count($questions) > $remaining) {
            $questions = array_slice($questions, 0, $remaining);
        }
        
        // Update total_count for response to match the effective limit
        $total_count = $max_items;

        if (empty($questions) && $offset < $max_items) {
            // 틀린 문제만 보기 모드일 때는 404 에러 대신 빈 결과를 반환하여 클라이언트에서 처리하도록 함
            if ($wrong_only) {
                return new \WP_REST_Response([
                    'id'      => urlencode($subject),
                    'title'   => $subject,
                    'lessons' => [],
                    'limit'   => $limit,
                    'offset'  => 0,
                    'total'   => 0,
                    'random'  => $random,
                ], 200);
            }
            return new \WP_Error('no_questions', '해당 과목에 대한 문제가 없습니다.', ['status' => 404]);
        }

		// 정렬/랜덤 처리
		if ($random) {
            if (!$is_smart_random) {
                // Legacy Random (PHP Shuffle for guests)
			    shuffle($questions);
			    $questions = array_slice($questions, 0, $limit);
            }
            // Smart random is already sorted and limited by SQL
		} else {
			// question_id 오름차순 정렬 (학습용 순서)
			usort($questions, function($a, $b) {
				return $a['question_id'] <=> $b['question_id'];
			});
		}

		// 사용자 통계 조회
        $user_id = get_current_user_id();
        $question_ids = array_column($questions, 'question_id');
        $user_stats = LegacyRepo::get_user_question_stats($user_id, $question_ids);
        $user_states = LegacyRepo::get_user_states($user_id, $question_ids);

		$formatted_lessons = array_map(function($q) use ($user_stats, $user_states) {
            $stats = isset($user_stats[$q['question_id']]) ? $user_stats[$q['question_id']] : null;
            $state = isset($user_states[$q['question_id']]) ? $user_states[$q['question_id']] : null;
            
            if ($state && $stats) {
                $stats = array_merge($stats, $state);
            } elseif ($state) {
                $stats = $state;
            }
			return [
				'id'          => $q['question_id'],
				'title'       => '문제 #' . $q['question_id'],
				'content'     => $q['content'],
				'answer'      => $q['answer'],
				'explanation' => $q['explanation'],
				'question_image' => isset($q['question_image']) ? $q['question_image'] : null,
				'category'    => [
					'year'    => $q['exam_year'],
					'session' => isset($q['exam_session']) ? $q['exam_session'] : null,
					'subject' => $q['subject'],
				],
                'user_stats'  => $stats,
			];
		}, $questions);

		$response_data = [
			'id'      => urlencode($subject),
			'title'   => $subject,
			'lessons' => $formatted_lessons,
			'limit'   => $limit,
			'offset'  => $random ? 0 : $offset,
			'total'   => $total_count,
			'random'  => $random,
		];

        return new \WP_REST_Response($response_data, 200);
    }

    /**
     * 사용자의 Study 진행 기록을 저장합니다.
     */
    public static function log_study_progress( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new \WP_Error( 'unauthorized', '로그인이 필요합니다.', [ 'status' => 401 ] );
        }

        $question_id = absint( $request->get_param( 'question_id' ) );
        if ( $question_id <= 0 ) {
            return new \WP_Error( 'invalid_question', '유효한 문제 ID가 필요합니다.', [ 'status' => 400 ] );
        }

        $question = LegacyRepo::get_questions_with_categories(
            [
                'question_id' => $question_id,
                'limit'       => 1,
            ]
        );

        if ( empty( $question ) ) {
            return new \WP_Error( 'not_found', '해당 문제를 찾을 수 없습니다.', [ 'status' => 404 ] );
        }

        $states_table = self::ensure_user_states_table();
        $wpdb->suppress_errors( true );

        $existing_state = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$states_table}` WHERE `user_id` = %d AND `question_id` = %d LIMIT 1",
                $user_id,
                $question_id
            ),
            ARRAY_A
        );

        $current_time_utc = current_time( 'mysql', true );
        $new_count        = $existing_state ? ( (int) $existing_state['study_count'] + 1 ) : 1;

        // 트리거가 자동으로 last_study_date를 설정하므로 study_count만 포함
        $data = [
            'study_count' => $new_count,
            // last_study_date는 INSERT/UPDATE 트리거가 자동으로 설정
            // updated_at도 트리거가 자동으로 설정
        ];

        if ( $existing_state ) {
            // 트리거가 자동으로 last_study_date를 업데이트하므로 study_count만 업데이트
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE `{$states_table}` 
                    SET `study_count` = %d 
                    WHERE `user_id` = %d AND `question_id` = %d",
                    $new_count,
                    $user_id,
                    $question_id
                )
            );
        } else {
            // INSERT 시 트리거가 자동으로 last_study_date를 설정하므로 명시적으로 설정하지 않음
            $insert_data = array_merge(
                [
                    'user_id'        => $user_id,
                    'question_id'    => $question_id,
                    'bookmarked'     => 0,
                    'needs_review'   => 0,
                    'quiz_count'     => 0,
                    'last_quiz_date' => null,
                    'last_result'    => null,
                    'last_answer'    => null,
                    // updated_at과 last_study_date는 INSERT 트리거가 자동으로 설정
                ],
                $data
            );

            $wpdb->insert(
                $states_table,
                $insert_data,
                [
                    '%d', // user_id
                    '%d', // question_id
                    '%d', // bookmarked
                    '%d', // needs_review
                    '%d', // quiz_count
                    '%s', // last_quiz_date
                    '%s', // last_result
                    '%s', // last_answer
                    '%s', // updated_at
                    '%d', // study_count
                    '%s', // last_study_date
                ]
            );
        }

        $wpdb->suppress_errors( false );

        return rest_ensure_response(
            [
                'question_id' => $question_id,
                'study_count' => $new_count,
            ]
        );
    }

    /**
     * ptgates_user_states 테이블을 보장합니다.
     */
    private static function ensure_user_states_table() {
        global $wpdb;

        $states_table = 'ptgates_user_states';

        $existing_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $states_table ) );
        if ( $existing_table !== $states_table ) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE IF NOT EXISTS `{$states_table}` (
                `user_id` bigint(20) unsigned NOT NULL,
                `question_id` bigint(20) unsigned NOT NULL,
                `bookmarked` tinyint(1) NOT NULL DEFAULT 0,
                `needs_review` tinyint(1) NOT NULL DEFAULT 0,
                `study_count` int(11) unsigned NOT NULL DEFAULT 0,
                `quiz_count` int(11) unsigned NOT NULL DEFAULT 0,
                `last_result` enum('correct','wrong') DEFAULT NULL,
                `last_answer` varchar(255) DEFAULT NULL,
                `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                `last_study_date` datetime DEFAULT NULL,
                `last_quiz_date` datetime DEFAULT NULL,
                PRIMARY KEY (`user_id`,`question_id`),
                KEY `idx_flags` (`bookmarked`,`needs_review`)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
        }

        return $states_table;
    }
}


