<?php
namespace PTG\MyNote;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function register_routes() {
		$controller = new self();
		$controller->register();
	}

	public function register() {
		$namespace = 'ptg-mynote/v1';

		// Get memos (목록 조회)
		register_rest_route(
			$namespace,
			'/memos',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_memos' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Get question detail (문제 상세 조회 - 1100 study 재사용)
		register_rest_route(
			$namespace,
			'/questions/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_question_detail' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Delete memo
		register_rest_route(
			$namespace,
			'/memos/(?P<id>\d+)',
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_memo' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Update memo
		register_rest_route(
			$namespace,
			'/memos/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_memo' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'content' => [
						'required' => true,
					],
				],
			]
		);
	}

	public function check_permission() {
		return is_user_logged_in();
	}

	/**
	 * 메모 목록 조회 (과목/세부과목 정보 포함)
	 */
	public function get_memos( $request ) {
		global $wpdb;
		
		$user_id = get_current_user_id();
		$search  = $request->get_param( 'search' );
		$sort    = $request->get_param( 'sort' ) ?: 'date'; // 'date', 'subject', 'subsubject'
		$order   = $request->get_param( 'order' ) ?: 'desc'; // 'asc', 'desc'
		$limit   = $request->get_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 50;
		$offset  = $request->get_param( 'offset' ) ? intval( $request->get_param( 'offset' ) ) : 0;

		$memo_table = 'ptgates_user_memos';
		$cat_table  = 'ptgates_categories';
		
		// Subjects 클래스 로드
		if ( ! class_exists( '\PTG\Quiz\Subjects' ) ) {
			$subjects_file = WP_PLUGIN_DIR . '/1200-ptgates-quiz/includes/class-subjects.php';
			if ( file_exists( $subjects_file ) ) {
				require_once $subjects_file;
			}
		}

		$where = [ "m.user_id = %d" ];
		$args  = [ $user_id ];

		if ( $search ) {
			$where[] = 'm.content LIKE %s';
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$args[]  = $like;
		}

		$where_sql = implode( ' AND ', $where );
		
		// 정렬 처리
		$order_by = 'm.updated_at DESC'; // 기본값: 등록일자 내림차순
		if ( $sort === 'subject' ) {
			$order_by = 'main_subject ' . strtoupper( $order ) . ', c.subject ' . strtoupper( $order );
		} elseif ( $sort === 'subsubject' ) {
			$order_by = 'c.subject ' . strtoupper( $order );
		} elseif ( $sort === 'date' ) {
			$order_by = 'm.updated_at ' . strtoupper( $order );
		}

		// JOIN으로 과목 정보 가져오기
		// ptgates_ 테이블은 prefix 없이 직접 사용 (백틱으로 감싸기)
		// ptgates_categories는 한 question_id에 여러 행이 있을 수 있으므로 첫 번째 행만 가져오기
		// 성능 최적화: 서브쿼리로 첫 번째 category_id만 선택 후 JOIN
		$sql = "
			SELECT 
				m.id,
				m.user_id,
				m.question_id,
				m.content,
				m.updated_at,
				c.subject as sub_subject,
				c.exam_course,
				c.exam_session
			FROM `ptgates_user_memos` m
			LEFT JOIN (
				SELECT 
					c1.question_id,
					c1.subject,
					c1.exam_course,
					c1.exam_session
				FROM `ptgates_categories` c1
				INNER JOIN (
					SELECT question_id, MIN(category_id) as min_category_id
					FROM `ptgates_categories`
					GROUP BY question_id
				) c2 ON c1.question_id = c2.question_id AND c1.category_id = c2.min_category_id
			) c ON m.question_id = c.question_id
			WHERE {$where_sql}
			ORDER BY {$order_by}
			LIMIT %d OFFSET %d
		";
		
		$args[] = $limit;
		$args[] = $offset;

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

		// 대분류 과목 정보 추가 (Map에서 역추적)
		// 성능 최적화: exam_course로 교시를 파악하여 해당 세션만 확인, 없을 때만 모든 세션 확인
		if ( class_exists( '\PTG\Quiz\Subjects' ) && is_array( $results ) && ! empty( $results ) ) {
			// 세션 목록을 한 번만 가져오기 (캐싱)
			$all_sessions = \PTG\Quiz\Subjects::get_sessions();
			
			foreach ( $results as &$memo ) {
				$main_subject = '';
				$sub_subject = isset( $memo->sub_subject ) ? trim( $memo->sub_subject ) : '';
				
				// exam_course에서 교시 번호 추출 (예: "1교시" -> 1, "2교시" -> 2)
				$session = null;
				if ( isset( $memo->exam_course ) && ! empty( $memo->exam_course ) ) {
					$exam_course = trim( $memo->exam_course );
					// "1교시", "2교시", "제1교시", "1 교시" 등 다양한 형식 처리
					if ( preg_match( '/(\d+)\s*교시/', $exam_course, $matches ) ) {
						$session = intval( $matches[1] );
					} elseif ( is_numeric( $exam_course ) ) {
						$session = intval( $exam_course );
					}
				}

				if ( $sub_subject ) {
					// exam_course로 교시를 파악했으면 해당 세션만 확인 (빠른 경로)
					if ( $session && in_array( $session, $all_sessions, true ) ) {
						$subjects = \PTG\Quiz\Subjects::get_subjects_for_session( $session );
						foreach ( $subjects as $subj ) {
							$subs = \PTG\Quiz\Subjects::get_subsubjects( $session, $subj );
							if ( in_array( $sub_subject, $subs, true ) ) {
								$main_subject = $subj;
								break; // 찾으면 즉시 종료
							}
						}
					} else {
						// exam_course가 없거나 유효하지 않으면 모든 세션 확인
						foreach ( $all_sessions as $sess ) {
							$subjects = \PTG\Quiz\Subjects::get_subjects_for_session( $sess );
							foreach ( $subjects as $subj ) {
								$subs = \PTG\Quiz\Subjects::get_subsubjects( $sess, $subj );
								if ( in_array( $sub_subject, $subs, true ) ) {
									$main_subject = $subj;
									break 2; // 두 개의 루프 모두 종료
								}
							}
							// 찾았으면 더 이상 확인하지 않음
							if ( $main_subject ) {
								break;
							}
						}
					}
				}

				$memo->main_subject = $main_subject;
			}
		}

		// 전체 개수 조회 (페이지네이션용)
		// ptgates_ 테이블은 prefix 없이 직접 사용 (백틱으로 감싸기)
		$count_sql = "
			SELECT COUNT(*) 
			FROM `ptgates_user_memos` m
			WHERE {$where_sql}
		";
		$total_count = $wpdb->get_var( $wpdb->prepare( $count_sql, array_slice( $args, 0, -2 ) ) );

		return rest_ensure_response( [
			'items' => $results,
			'total' => intval( $total_count ),
			'limit' => $limit,
			'offset' => $offset
		] );
	}

	/**
	 * 문제 상세 조회 (1100 study API 재사용)
	 */
	public function get_question_detail( $request ) {
		$question_id = absint( $request->get_param( 'id' ) );
		
		if ( ! $question_id ) {
			return new \WP_Error( 'invalid_param', 'Question ID is required', [ 'status' => 400 ] );
		}

		// 1100 study API 재사용
		if ( ! class_exists( '\PTG\Study\Study_API' ) ) {
			$study_api_file = WP_PLUGIN_DIR . '/1100-ptgates-study/includes/class-api.php';
			if ( file_exists( $study_api_file ) ) {
				require_once $study_api_file;
			}
		}

		// 단일 문제 조회를 위한 임시 request 생성
		$temp_request = new \WP_REST_Request( 'GET', '/ptg-study/v1/questions/' . $question_id );
		
		// 1100 study의 문제 조회 로직이 있다면 사용, 없으면 직접 조회
		global $wpdb;
		$question = $wpdb->get_row( $wpdb->prepare(
			"SELECT q.*, c.subject, c.exam_year, c.exam_session 
			FROM `ptgates_questions` q
			LEFT JOIN `ptgates_categories` c ON q.question_id = c.question_id
			WHERE q.question_id = %d
			LIMIT 1",
			$question_id
		) );

		if ( ! $question ) {
			return new \WP_Error( 'not_found', 'Question not found', [ 'status' => 404 ] );
		}

		// 메모 정보도 함께 조회
		$user_id = get_current_user_id();
		$memo = $wpdb->get_row( $wpdb->prepare(
			"SELECT content FROM `ptgates_user_memos` 
			WHERE user_id = %d AND question_id = %d 
			LIMIT 1",
			$user_id,
			$question_id
		) );

		return rest_ensure_response( [
			'question' => $question,
			'memo' => $memo ? $memo->content : ''
		] );
	}

	/**
	 * 메모 삭제
	 */
	public function delete_memo( $request ) {
		global $wpdb;
		
		$user_id = get_current_user_id();
		$memo_id = absint( $request->get_param( 'id' ) );

		if ( ! $memo_id ) {
			return new \WP_Error( 'invalid_param', 'Memo ID is required', [ 'status' => 400 ] );
		}

		$table = 'ptgates_user_memos';

		// 소유권 확인
		// ptgates_ 테이블은 prefix 없이 직접 사용 (백틱으로 감싸기)
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM `ptgates_user_memos` WHERE id = %d AND user_id = %d",
			$memo_id,
			$user_id
		) );

		if ( ! $existing ) {
			return new \WP_Error( 'not_found', 'Memo not found or permission denied', [ 'status' => 404 ] );
		}

		$deleted = $wpdb->delete( $table, [ 'id' => $memo_id ], [ '%d' ] );

		return rest_ensure_response( [ 'success' => (bool) $deleted ] );
	}

	/**
	 * 메모 수정
	 */
	public function update_memo( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$memo_id = absint( $request->get_param( 'id' ) );
		$content = $request->get_param( 'content' );

		if ( ! $memo_id ) {
			return new \WP_Error( 'invalid_param', 'Memo ID is required', [ 'status' => 400 ] );
		}

		if ( null === $content ) {
			return new \WP_Error( 'invalid_param', 'Content is required', [ 'status' => 400 ] );
		}

		$table = 'ptgates_user_memos';

		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM `ptgates_user_memos` WHERE id = %d AND user_id = %d",
			$memo_id,
			$user_id
		) );

		if ( ! $existing ) {
			return new \WP_Error( 'not_found', 'Memo not found or permission denied', [ 'status' => 404 ] );
		}

		$sanitized_content = sanitize_textarea_field( wp_unslash( $content ) );
		$sanitized_content = wp_specialchars_decode( $sanitized_content, ENT_QUOTES );
		$updated_at        = current_time( 'mysql' );

		$updated = $wpdb->update(
			$table,
			[
				'content'    => $sanitized_content,
				'updated_at' => $updated_at,
			],
			[ 'id' => $memo_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		if ( false === $updated ) {
			return new \WP_Error( 'db_error', 'Failed to update memo', [ 'status' => 500 ] );
		}

		return rest_ensure_response(
			[
				'success'    => true,
				'content'    => $sanitized_content,
				'updated_at' => $updated_at,
			]
		);
	}
}
