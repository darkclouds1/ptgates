<?php
namespace PTG\Study;

use PTG\Platform\LegacyRepo;

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
        $course_id = $request['course_id'];
        $subjects_param = $request->get_param('subjects');
        $limit = (int) $request->get_param('limit');
        if ($limit <= 0) {
			$limit = 50; // 기본값(프론트에서 세부과목 조회 시에는 명시적으로 10을 전달)
        }

		// 페이지네이션을 위한 offset (세부과목 단일 조회에서 사용)
		$offset = (int) $request->get_param('offset');
		if ($offset < 0) {
			$offset = 0;
		}

		// 랜덤 섞기 플래그 (세부과목 단일 조회에서 사용)
		$random = (bool) $request->get_param('random');

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

            // 집계 모드: 각 세부과목의 문제를 모두 모은 후 question_id ASC 정렬,
            // 그 다음 limit/offset으로 잘라서 반환 (페이지네이션).
            $questions_map = [];

            foreach ($subject_names as $subject_name) {
                $args = [
                    'subject'          => $subject_name,
                    'limit'            => 1000, // 세부과목당 충분히 큰 값
                    'offset'           => 0,
                    'exam_session_min' => 1000,
                ];

                $results = LegacyRepo::get_questions_with_categories($args);
                foreach ($results as $row) {
                    $questions_map[$row['question_id']] = $row;
                }
            }

            if (empty($questions_map)) {
                return new \WP_Error('no_questions', '해당 분류에 대한 문제가 없습니다.', ['status' => 404]);
            }

            $questions = array_values($questions_map);
            usort($questions, function($a, $b) {
                return $a['question_id'] <=> $b['question_id'];
            });

            $total_count = count($questions);

            // 페이지네이션 적용 (10문제씩 등)
            $paged_questions = array_slice($questions, $offset, $limit);

            $formatted_lessons = array_map(function($q) {
                return [
                    'id'          => $q['question_id'],
                    'title'       => '문제 #' . $q['question_id'],
                    'content'     => $q['content'],
                    'answer'      => $q['answer'],
                    'explanation' => $q['explanation'],
                    'category'    => [
                        'year'    => $q['exam_year'],
                        'subject' => $q['subject'],
                    ],
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
                'offset'    => $offset,
                'total'     => $total_count,
            ];

            return new \WP_REST_Response($response_data, 200);
        }

		$subject = urldecode($course_id);

		// 세부과목 단일 조회
		$args = [
			'subject'          => $subject,
			'limit'            => $random ? 1000 : $limit, // 랜덤일 때는 넉넉히 가져온 후 자르기
			'offset'           => $random ? 0 : $offset,
			// 전역 정책: 회차 1000 이상만
			'exam_session_min' => 1000,
		];

		$questions = LegacyRepo::get_questions_with_categories($args);
		$total_count = LegacyRepo::count_questions_with_categories([
			'subject'          => $subject,
			'exam_session_min' => 1000,
		]);

        if (empty($questions)) {
            return new \WP_Error('no_questions', '해당 과목에 대한 문제가 없습니다.', ['status' => 404]);
        }

		// 정렬/랜덤 처리
		if ($random) {
			// 랜덤 섞기 후 limit 만큼 잘라서 반환
			shuffle($questions);
			$questions = array_slice($questions, 0, $limit);
		} else {
			// question_id 오름차순 정렬 (학습용 순서)
			usort($questions, function($a, $b) {
				return $a['question_id'] <=> $b['question_id'];
			});
		}

		$formatted_lessons = array_map(function($q) {
			return [
				'id'          => $q['question_id'],
				'title'       => '문제 #' . $q['question_id'],
				'content'     => $q['content'],
				'answer'      => $q['answer'],
				'explanation' => $q['explanation'],
				'category'    => [
					'year'    => $q['exam_year'],
					'subject' => $q['subject'],
				]
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
}


