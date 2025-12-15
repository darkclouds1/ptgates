<?php
namespace PTG\Flashcards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function register_routes() {
		$controller = new self();
		$controller->register();
	}

	public function register() {
		$namespace = 'ptg-flash/v1';

		// Get Sets
		register_rest_route(
			$namespace,
			'/sets',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_sets' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Create Set (Legacy)
		register_rest_route(
			$namespace,
			'/sets',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_set' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Create Set from Subject (New)
		register_rest_route(
			$namespace,
			'/create-set',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_set_from_subject' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Delete Set
		register_rest_route(
			$namespace,
			'/sets/(?P<id>\d+)',
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_set' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Get Cards
		register_rest_route(
			$namespace,
			'/cards',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_cards' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'set_id'      => [
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
					'mode'        => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'source_type' => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'source_id'   => [
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Create Card
		register_rest_route(
			$namespace,
			'/cards',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_card' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Review Card (Update Due Date)
		register_rest_route(
			$namespace,
			'/review',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'review_card' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	public function check_permission() {
		return is_user_logged_in();
	}

	public function get_sets( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$sets_table = 'ptgates_flashcard_sets';
		$cards_table = 'ptgates_flashcards';

        // Suppress errors to prevent HTML output in JSON response
        $wpdb->hide_errors();
		
		// Sync dynamic sets (Bookmark, Review, Wrong)
		$this->sync_dynamic_sets($user_id);
		
		$today = current_time( 'Y-m-d' );
		
		$sql = "
			SELECT s.*, 
				COUNT(c.card_id) as total_cards,
				SUM(CASE WHEN c.card_id IS NOT NULL AND (c.next_due_date IS NULL OR c.next_due_date <= %s) THEN 1 ELSE 0 END) as due_cards
			FROM {$sets_table} s
			LEFT JOIN {$cards_table} c ON s.set_id = c.set_id AND c.user_id = %d
			WHERE (s.user_id = %d OR s.set_id = 1)
			GROUP BY s.set_id
			ORDER BY s.created_at DESC
		";
		
		$prepared_sql = $wpdb->prepare( $sql, $today, $user_id, $user_id );
		// error_log( '[DEBUG] get_sets SQL: ' . $prepared_sql );
		
		$results = $wpdb->get_results( $prepared_sql );
        
        if ( $wpdb->last_error ) {
            error_log( 'PTG Flashcards API Error (get_sets): ' . $wpdb->last_error );
            return rest_ensure_response( [] );
        }

		// Map set_id=1 name to '모의고사 암기카드'
		foreach ( $results as $set ) {
			if ( $set->set_id == 1 ) {
				$set->set_name = '학습 암기카드';
				$set->is_default = true;
			}
		}
        
		return rest_ensure_response( $results );
	}

	public function create_set( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$name    = sanitize_text_field( $request->get_param( 'set_name' ) );

		if ( empty( $name ) ) {
			return new \WP_Error( 'invalid_param', 'Set name is required', [ 'status' => 400 ] );
		}

		$table = 'ptgates_flashcard_sets';
		$wpdb->insert( $table, [
			'user_id'  => $user_id,
			'set_name' => $name,
		] );

		return rest_ensure_response( [ 'set_id' => $wpdb->insert_id ] );
	}

	public function delete_set( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$set_id  = $request->get_param( 'id' );

		$sets_table = 'ptgates_flashcard_sets';
		$cards_table = 'ptgates_flashcards';

		// 1. Delete all cards in this set
		$wpdb->delete( $cards_table, [ 'set_id' => $set_id, 'user_id' => $user_id ] );

		// 2. Delete the set IF NOT set_id=1
		if ( $set_id != 1 ) {
			$wpdb->delete( $sets_table, [ 'set_id' => $set_id, 'user_id' => $user_id ] );
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	public function create_set_from_subject( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		
		$random  = filter_var( $request->get_param( 'random' ), FILTER_VALIDATE_BOOLEAN );
		$session = $request->get_param( 'session' );
		$subject = $request->get_param( 'subject' );
		$subsubject = $request->get_param( 'subsubject' );
		$limit   = $request->get_param( 'limit' );

		if ( $limit === 'full' ) {
			$limit = 1000; 
		} else {
			$limit = intval( $limit );
			if ( $limit <= 0 ) $limit = 5;
		}

		// 1. Prepare Set Name
		$new_set_name = '';
		$session_name = $session . '교시'; // Default to "1교시" format
		
		// Resolve Session Name from Map if available
		if ( $session && class_exists( '\PTG\Quiz\Subjects' ) && method_exists( '\PTG\Quiz\Subjects', 'get_map' ) ) {
			$map = \PTG\Quiz\Subjects::get_map();
			if ( ! empty( $map ) && isset( $map[ $session ]['name'] ) ) {
				$session_name = $map[ $session ]['name'];
			}
		}

		$mode = $request->get_param( 'mode' ); // 'random', 'flashcard', 'bookmark', 'review', 'wrong'
		
		if ( $random ) {
			if ( empty($session) && empty($subject) && empty($subsubject) ) {
				$new_set_name = '모의 암기카드 [R]';
			} elseif ( $subsubject ) {
				$new_set_name = $subsubject . ' [R]';
			} elseif ( $subject ) {
				$new_set_name = $subject . ' [R]';
			} elseif ( $session ) {
				$new_set_name = $session_name . ' [R]';
			}
		} elseif ( $mode === 'bookmark' ) {
			$new_set_name = '북마크';
		} elseif ( $mode === 'review' ) {
			$new_set_name = '복습 문제';
		} elseif ( $mode === 'wrong' ) {
			$new_set_name = '틀린 문제';
		} else {
			// Flashcard Only Mode
			if ( $subsubject ) {
				$new_set_name = $subsubject;
			} elseif ( $subject ) {
				$new_set_name = $subject;
			} elseif ( $session ) {
				$new_set_name = $session_name;
			}
		}

		// 2. Check for Duplicates (Pre-check)
		// Skip check ONLY if it's "Flashcard Only" AND "All" (Redirect case)
		$is_redirect_case = ( ! $random && empty($session) && empty($subject) && empty($subsubject) && ! in_array( $mode, ['bookmark', 'review', 'wrong'] ) );
		
		if ( ! $is_redirect_case && $new_set_name ) {
			$sets_table = 'ptgates_flashcard_sets';
			$existing_set = $wpdb->get_var( $wpdb->prepare(
				"SELECT set_id FROM $sets_table WHERE user_id = %d AND set_name = %s",
				$user_id,
				$new_set_name
			) );

			if ( $existing_set ) {
				return new \WP_Error( 'duplicate_set', '이미 있는 과목입니다. 새로 만들려면 삭제 후 만들어야 합니다.', [ 'status' => 409 ] );
			}
		}

		// 3. Fetch Questions (BEFORE creating set)
		$questions = [];
		$source_cards = [];
		$q_table = 'ptgates_questions';
		$c_table = 'ptgates_categories';
		$cards_table = 'ptgates_flashcards';

		if ( $random ) {
			// --- RANDOM MODE LOGIC ---
			// Case A: Quota-based Fetching (Only used when 'Full' is selected)
			// DISABLED for Flashcards: Users prefer 'All Available' (e.g. 70 questions) rather than 'Mock Exam Quota' (e.g. 20 questions)
			if ( false && $limit === 1000 && empty( $subsubject ) && class_exists( '\PTG\Quiz\Subjects' ) && method_exists( '\PTG\Quiz\Subjects', 'get_map' ) ) {
				$map = \PTG\Quiz\Subjects::get_map();

				foreach ( $map as $sess_key => $sess_data ) {
					if ( $session && $session != $sess_key ) continue;

					if ( ! empty( $sess_data['subjects'] ) ) {
						foreach ( $sess_data['subjects'] as $subj_name => $subj_data ) {
							if ( $subject && $subject != $subj_name ) continue;

							if ( ! empty( $subj_data['subs'] ) ) {
								foreach ( $subj_data['subs'] as $sub_name => $count ) {
									$sub_limit = (int) $count;
									if ( $sub_limit <= 0 ) continue;

									$sub_sql = $wpdb->prepare(
										"SELECT q.*, c.subject as category_subject, c.exam_year, c.exam_session 
										  FROM $q_table q 
										  JOIN $c_table c ON q.question_id = c.question_id 
										  WHERE q.is_active = 1 AND c.exam_session >= 1000 AND c.subject = %s 
										  ORDER BY RAND() LIMIT %d",
										$sub_name,
										$sub_limit
									);
									
									$sub_questions = $wpdb->get_results( $sub_sql, ARRAY_A );
									if ( ! empty( $sub_questions ) ) {
										$questions = array_merge( $questions, $sub_questions );
									}
								}
							}
						}
					}
				}

			} 
			// Case B: Simple Fetching (Specific sub-subject selected or fallback)
			else {
				$sql = "
					SELECT q.*, c.subject as category_subject, c.exam_year, c.exam_session
					FROM $q_table q
					JOIN $c_table c ON q.question_id = c.question_id
					WHERE q.is_active = 1 AND c.exam_session >= 1000
				";
				$query_args = [];

				if ( $subsubject ) {
					$sql .= " AND c.subject = %s";
					$query_args[] = $subsubject;
				} elseif ( $subject ) {
					$sql .= " AND c.subject IN (SELECT subject FROM $c_table WHERE subject LIKE %s)";
					$query_args[] = $subject . '%';
				} elseif ( $session ) {
					$sql .= " AND c.exam_course = %s";
					$query_args[] = $session . '교시'; // Append '교시'
				}

				$sql .= " ORDER BY RAND() LIMIT %d";
				$query_args[] = $limit;

				$questions = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ), ARRAY_A );
			}
		} elseif ( in_array( $mode, ['bookmark', 'review', 'wrong'] ) ) {
			// --- DYNAMIC MODES ---
			$states_table = 'ptgates_user_states';
			$q_table = 'ptgates_questions';
			
			$where = "user_id = %d";
			$args = [ $user_id ];
			
			if ( $mode === 'bookmark' ) {
				$where .= " AND bookmarked = 1";
			} elseif ( $mode === 'review' ) {
				$where .= " AND needs_review = 1";
			} elseif ( $mode === 'wrong' ) {
				$where .= " AND last_result = 'wrong'";
			}
			
			// Fetch Question IDs
			$q_ids = $wpdb->get_col( $wpdb->prepare( "SELECT question_id FROM $states_table WHERE $where", $args ) );
			
			if ( ! empty( $q_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $q_ids ), '%d' ) );
				$questions = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM $q_table WHERE question_id IN ($placeholders)",
					$q_ids
				), ARRAY_A );
			}
		} else {
			// --- FLASHCARD ONLY MODE LOGIC ---
			// Source: Set 1 (User's cards in Default Set)
			
			if ( $is_redirect_case ) {
				// Just redirect
			} else {
				// Check if Set 1 has ANY cards first
				$total_default_cards = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM $cards_table WHERE user_id = %d AND set_id = 1",
					$user_id
				) );
				
				if ( ! $total_default_cards ) {
					return new \WP_Error( 'empty_default_set', '학습 암기카드(기본)에 저장된 카드가 없습니다. 먼저 문제를 풀며 암기카드를 추가해주세요.', [ 'status' => 404 ] );
				}

				// Filter: JOIN with Question & Category
				$sql = "
					SELECT c.* 
					FROM $cards_table c
					JOIN $q_table q ON c.source_id = q.question_id
					JOIN $c_table cat ON q.question_id = cat.question_id
					WHERE c.user_id = %d AND c.set_id = 1 AND c.source_type = 'question'
				";
				$query_args = [ $user_id ];

				if ( $subsubject ) {
					$sql .= " AND cat.subject = %s";
					$query_args[] = $subsubject;
				} elseif ( $subject ) {
					// Parent Subject Filter
					if ( class_exists( '\PTG\Quiz\Subjects' ) ) {
						$map = [];
						if ( class_exists( '\PTG\Quiz\Subjects' ) && method_exists( '\PTG\Quiz\Subjects', 'get_map' ) ) {
							$map = \PTG\Quiz\Subjects::get_map();
						}
						$target_subs = [];
						foreach ( $map as $sess_data ) {
							if ( isset( $sess_data['subjects'][$subject]['subs'] ) ) {
								$target_subs = array_merge( $target_subs, array_keys( $sess_data['subjects'][$subject]['subs'] ) );
							}
						}
						
						if ( ! empty( $target_subs ) ) {
							$placeholders = implode( ',', array_fill( 0, count( $target_subs ), '%s' ) );
							$sql .= " AND cat.subject IN ($placeholders)";
							$query_args = array_merge( $query_args, $target_subs );
						} else {
							$sql .= " AND cat.subject LIKE %s";
							$query_args[] = $subject . '%';
						}
					} else {
						$sql .= " AND cat.subject LIKE %s";
						$query_args[] = $subject . '%';
					}
				} elseif ( $session ) {
					$sql .= " AND cat.exam_course = %s";
					$query_args[] = $session . '교시'; // Append '교시'
				}

				// Limit? Usually copy all, but respect limit if set
				if ( $limit && $limit !== 1000 ) { // 1000 is our internal 'full' default
					$sql .= " LIMIT %d";
					$query_args[] = $limit;
				}

				error_log( 'PTG Flashcards Debug SQL: ' . $sql );
				error_log( 'PTG Flashcards Debug Args: ' . print_r( $query_args, true ) );

				$source_cards = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ) );
				error_log( 'PTG Flashcards Result Count: ' . count( $source_cards ) );
			}
		}

		if ( empty( $questions ) && empty( $source_cards ) && ! $is_redirect_case ) {
			return new \WP_Error( 'no_questions', '조건에 맞는 문제가 없습니다. (선택한 범위에 해당하는 학습 암기카드가 없습니다.)', [ 'status' => 404 ] );
		}

		// 4. Create Set (Now that we have data)
		if ( $is_redirect_case ) {
			return rest_ensure_response( [ 'set_id' => 1, 'message' => 'Redirect to default set' ] );
		}
		
		$sets_table = 'ptgates_flashcard_sets';
		$wpdb->insert( $sets_table, [
			'user_id'  => $user_id,
			'set_name' => $new_set_name,
		] );
		$target_set_id = $wpdb->insert_id;

		if ( ! $target_set_id ) {
			return new \WP_Error( 'db_error', '세트 생성 실패', [ 'status' => 500 ] );
		}

		// 5. Insert Cards
		$count = 0;
		
		if ( $random || in_array( $mode, ['bookmark', 'review', 'wrong'] ) ) {
			$upload_dir = wp_upload_dir();
			$base_url = $upload_dir['baseurl'] . '/ptgates-questions';

			foreach ( $questions as $q ) {
				$front = $q['content'] ?? '';
				$year = $q['exam_year'] ?? '';
				$session = $q['exam_session'] ?? '';

				// Check for choices (①, (1), 1.) to insert Image OR Spacer
				if ( preg_match( '/(①|\(1\)|1\.)/', $front, $matches, PREG_OFFSET_CAPTURE ) ) {
					$offset = $matches[0][1];
					$before = substr( $front, 0, $offset );
					$after = substr( $front, $offset );
					
					// Insert Spacer (1.3x gap request)
					$spacer = '<div class="ptg-q-spacer"></div>';
					
					// Insert Image if matches
					if ( $year && $session && ! empty( $q['question_image'] ) ) {
						$full_img_url = "$base_url/$year/$session/" . $q['question_image'];
						$img_html = '<div class="ptg-card-image"><img src="' . esc_url( $full_img_url ) . '" alt="Question Image" /></div>';
						
						$front = $before . $img_html . $spacer . $after;
					} else {
						// No image, just spacer
						$front = $before . $spacer . $after;
					}
				} else {
					// No choices found? Just append image if exists
					if ( $year && $session && ! empty( $q['question_image'] ) ) {
						$full_img_url = "$base_url/$year/$session/" . $q['question_image'];
						$img_html = '<div class="ptg-card-image"><img src="' . esc_url( $full_img_url ) . '" alt="Question Image" /></div>';
						$front .= $img_html;
					}
				}

				$answer = $q['answer'] ?? '';
				$explanation = $q['explanation'] ?? '';
				
				if ( empty( $front ) ) continue;

				$wpdb->insert( $cards_table, [
					'user_id'      => $user_id,
					'set_id'       => $target_set_id,
					'source_type'  => 'question',
					'source_id'    => $q['question_id'],
					'front_custom' => $front,
					'back_custom'  => "<strong>정답: " . $answer . "</strong><br>" . $explanation,
				] );
				$count++;
			}
		} else {
			foreach ( $source_cards as $card ) {
				$wpdb->insert( $cards_table, [
					'user_id'      => $user_id,
					'set_id'       => $target_set_id,
					'source_type'  => $card->source_type,
					'source_id'    => $card->source_id,
					'front_custom' => $card->front_custom,
					'back_custom'  => $card->back_custom,
					'next_due_date'=> current_time( 'Y-m-d' ),
				] );
				$count++;
			}
		}

		return rest_ensure_response( [ 'set_id' => $target_set_id, 'count' => $count ] );
	}

	public function get_cards( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$set_id  = $request->get_param( 'set_id' );
		$mode    = $request->get_param( 'mode' ); // 'review' or 'all'

		$table = 'ptgates_flashcards';
		$cat_table = 'ptgates_categories';
		
		// Select fields + Category info
		$sql = "
			SELECT f.*, 
				c.exam_course, c.subject
			FROM $table f
			LEFT JOIN $cat_table c ON (f.source_id = c.question_id AND f.source_type = 'question')
			WHERE f.user_id = %d
		";
		
		$args = [ $user_id ];

		if ( $set_id ) {
			$sql .= " AND f.set_id = %d";
			$args[] = $set_id;
		}

		if ( $mode === 'review' ) {
			$today = current_time( 'Y-m-d' );
			$sql .= " AND (f.next_due_date IS NULL OR f.next_due_date <= %s)";
			$args[] = $today;
		}

		$sql .= " ORDER BY f.next_due_date ASC, f.card_id ASC";

		$prepared_sql = $wpdb->prepare( $sql, $args );
		// error_log( '[DEBUG] get_cards SQL: ' . $prepared_sql );

		$results = $wpdb->get_results( $prepared_sql );

		return rest_ensure_response( $results );
	}

	public function create_card( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		
		$front     = $request->get_param( 'front' ); // mapped to front_custom
		$back      = $request->get_param( 'back' );  // mapped to back_custom
		$source_id = $request->get_param( 'source_id' );
		$set_id    = $request->get_param( 'set_id' );

		if ( empty( $set_id ) ) {
			$set_id = 1; // Default Set
		}

		// Ensure Default Set exists (if set_id is 1)
		if ( $set_id == 1 ) {
			$sets_table = 'ptgates_flashcard_sets';
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT set_id FROM $sets_table WHERE set_id = %d", 1 ) );
			
			if ( ! $exists ) {
				$wpdb->insert( $sets_table, [
					'set_id'   => 1,
					'user_id'  => 0, // System default
					'set_name' => '학습 암기카드',
				] );
			}
		}

		$table = 'ptgates_flashcards';
		$wpdb->insert( $table, [
			'user_id'      => $user_id,
			'set_id'       => $set_id,
			'source_type'  => 'question',
			'source_id'    => $source_id,
			'front_custom' => $front,
			'back_custom'  => $back,
			'next_due_date'=> current_time( 'Y-m-d' ),
		] );

		return rest_ensure_response( [ 'card_id' => $wpdb->insert_id ] );
	}

	public function review_card( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$card_id = $request->get_param( 'card_id' );
		$quality = $request->get_param( 'quality' ); // 0-5 scale (SM-2 style) or simple 'easy','hard'

		// Simplified SM-lite logic
		// Easy (+5), Good (+3), Hard (+1)
		$days = 1;
		if ( $quality === 'easy' ) $days = 5;
		elseif ( $quality === 'good' ) $days = 3;
		
		$next_due = date( 'Y-m-d', strtotime( "+$days days", current_time( 'timestamp' ) ) );

		$table = 'ptgates_flashcards';
		$wpdb->update(
			$table,
			[
				'next_due_date' => $next_due,
				'review_count'  => $wpdb->prefix . 'review_count + 1' // This syntax is wrong for wpdb->update, need raw query or fetch-update
			],
			[ 'card_id' => $card_id, 'user_id' => $user_id ]
		);
		
		// Fix review_count increment manually
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET review_count = review_count + 1 WHERE card_id = %d", $card_id ) );

		return rest_ensure_response( [ 'success' => true, 'next_due' => $next_due ] );
	}

	private function sync_dynamic_sets( $user_id ) {
		global $wpdb;
		$sets_table = 'ptgates_flashcard_sets';
		$cards_table = 'ptgates_flashcards';
		$states_table = 'ptgates_user_states';
		$questions_table = 'ptgates_questions';

		// Define dynamic sets mapping
		$dynamic_sets = [
			'bookmark' => [ 'name' => '북마크', 'col' => 'bookmarked', 'val' => 1 ],
			'review'   => [ 'name' => '복습 문제', 'col' => 'needs_review', 'val' => 1 ],
			'wrong'    => [ 'name' => '틀린 문제', 'col' => 'last_result', 'val' => 'wrong' ]
		];

		foreach ( $dynamic_sets as $key => $config ) {
			// 1. Check if set exists
			$set_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT set_id FROM $sets_table WHERE user_id = %d AND set_name = %s",
				$user_id, $config['name']
			) );

			if ( ! $set_id ) continue; // Only sync if set exists

			// 2. Get current valid Question IDs from user states
			// Note: last_result is a string, others are int(1)
			if ( $key === 'wrong' ) {
				$valid_q_ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT question_id FROM $states_table WHERE user_id = %d AND last_result = 'wrong'",
					$user_id
				) );
			} else {
				$col = $config['col'];
				$valid_q_ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT question_id FROM $states_table WHERE user_id = %d AND $col = 1",
					$user_id
				) );
			}

			// 3. Get current Card Source IDs in this set
			$current_cards = $wpdb->get_results( $wpdb->prepare(
				"SELECT card_id, source_id FROM $cards_table WHERE user_id = %d AND set_id = %d AND source_type = 'question'",
				$user_id, $set_id
			) );

			$current_source_ids = [];
			$card_map = [];
			foreach ( $current_cards as $c ) {
				$current_source_ids[] = $c->source_id;
				$card_map[$c->source_id] = $c->card_id;
			}

			// 4. Calculate Diff
			$to_add = array_diff( $valid_q_ids, $current_source_ids );
			$to_remove = array_diff( $current_source_ids, $valid_q_ids );

			// 5. Add new cards
			if ( ! empty( $to_add ) ) {
				// Fetch question details for new cards
				$categories_table = 'ptgates_categories';
				$placeholders = implode( ',', array_fill( 0, count( $to_add ), '%d' ) );
				
				$questions = $wpdb->get_results( $wpdb->prepare(
					"SELECT q.question_id, q.content, q.answer, q.explanation, q.question_image, c.exam_year, c.exam_session 
                     FROM $questions_table q
                     LEFT JOIN $categories_table c ON q.question_id = c.question_id
                     WHERE q.question_id IN ($placeholders)",
					$to_add
				), ARRAY_A );

				foreach ( $questions as $q ) {
					$front_content = $q['content'];
                    if ( ! empty( $q['question_image'] ) ) {
                        try {
                            // Construct full image URL: /wp-content/uploads/ptgates-questions/{year}/{session}/{filename}
                            $upload_dir = wp_upload_dir();
                            $base_url = $upload_dir['baseurl'] . '/ptgates-questions';
                            
                            $year = $q['exam_year'] ?? ''; // Should be available from JOIN
                            $session = $q['exam_session'] ?? ''; 
                            
                            if ( $year && $session ) {
                                $full_img_url = "$base_url/$year/$session/" . $q['question_image'];
                                
                                $img_html = '<div class="ptg-card-image"><img src="' . esc_url( $full_img_url ) . '" alt="Question Image" /></div>';
                                // Check for choices (①, (1), 1.)
                                if ( preg_match( '/(①|\(1\)|1\.)/', $front_content, $matches, PREG_OFFSET_CAPTURE ) ) {
                                    $offset = $matches[0][1];
                                    $front_content = substr( $front_content, 0, $offset ) . $img_html . substr( $front_content, $offset );
                                } else {
                                    $front_content .= $img_html;
                                }
                            }
                        } catch ( \Exception $e ) {
                             error_log( 'PTG Flashcard Image Injection Error: ' . $e->getMessage() );
                        } catch ( \Throwable $t ) {
                             error_log( 'PTG Flashcard Image Injection Fatal: ' . $t->getMessage() );
                        }
                    }

					$wpdb->insert( $cards_table, [
						'user_id'      => $user_id,
						'set_id'       => $set_id,
						'source_type'  => 'question',
						'source_id'    => $q['question_id'],
						'source_id'    => $q['question_id'],
						'front_custom' => $front_content,
						'back_custom'  => "<strong>정답: " . $q['answer'] . "</strong><br>" . $q['explanation'],
						'next_due_date'=> current_time( 'Y-m-d' ),
					] );
				}
			}

			// 6. Remove invalid cards
			if ( ! empty( $to_remove ) ) {
				$remove_card_ids = [];
				foreach ( $to_remove as $source_id ) {
					if ( isset( $card_map[$source_id] ) ) {
						$remove_card_ids[] = $card_map[$source_id];
					}
				}
				
				if ( ! empty( $remove_card_ids ) ) {
					$ids_str = implode( ',', array_map( 'absint', $remove_card_ids ) );
					$wpdb->query( "DELETE FROM $cards_table WHERE card_id IN ($ids_str)" );
				}
			}
		}
	}
}
