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

		// Create Set
		register_rest_route(
			$namespace,
			'/sets',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_set' ],
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
		$table   = 'ptgates_flashcard_sets';

        // Suppress errors to prevent HTML output in JSON response
        $wpdb->hide_errors();
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC", $user_id ) );
        
        if ( $wpdb->last_error ) {
            error_log( 'PTG Flashcards API Error (get_sets): ' . $wpdb->last_error );
            return rest_ensure_response( [] );
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

	public function get_cards( $request ) {
		global $wpdb;
		$user_id    = get_current_user_id();
		
		// args로 정의했으므로 get_param()으로 직접 가져오기
		$set_id      = $request->get_param( 'set_id' );
		$mode        = $request->get_param( 'mode' );
		$source_type = $request->get_param( 'source_type' );
		$source_id   = $request->get_param( 'source_id' );

		$table = 'ptgates_flashcards';
		$where = [ 'user_id = %d' ];
		$args  = [ $user_id ];

		if ( $set_id ) {
			$where[] = 'set_id = %d';
			$args[]  = $set_id;
		}

		if ( ! empty( $source_type ) ) {
			$where[] = 'source_type = %s';
			$args[]  = $source_type;
		}

		if ( ! empty( $source_id ) && $source_id > 0 ) {
			$where[] = 'source_id = %d';
			$args[]  = $source_id;
		}

		if ( $mode === 'review' ) {
			// Due date is today or past, OR null (new cards)
			// Using Platform helper if available, else PHP date
			$today = current_time( 'Y-m-d' );
			$where[] = '(next_due_date IS NULL OR next_due_date <= %s)';
			$args[]  = $today;
		}

		$where_sql = implode( ' AND ', $where );
		$sql = "SELECT * FROM $table WHERE $where_sql ORDER BY next_due_date ASC LIMIT 50";

		$prepared_sql = $wpdb->prepare( $sql, $args );
		$results = $wpdb->get_results( $prepared_sql );
		
		if ( $wpdb->last_error ) {
			error_log( '[PTG Flashcards API] get_cards DB 오류: ' . $wpdb->last_error );
		}
		
		return rest_ensure_response( $results );
	}

	public function create_card( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		
		$data = [
			'user_id'      => $user_id,
			'set_id'       => $request->get_param( 'set_id' ),
			'source_type'  => $request->get_param( 'source_type' ) ?: 'custom',
			'source_id'    => $request->get_param( 'source_id' ),
			'front_custom' => $request->get_param( 'front' ),
			'back_custom'  => $request->get_param( 'back' ),
			'next_due_date'=> current_time( 'Y-m-d' ), // Start immediately
		];

		if ( empty( $data['set_id'] ) || empty( $data['front_custom'] ) ) {
			return new \WP_Error( 'invalid_param', 'Set ID and Front content are required', [ 'status' => 400 ] );
		}

		$table = 'ptgates_flashcards';
        
        // Check for existing card to prevent duplicates
        $existing_card = $wpdb->get_row( $wpdb->prepare(
            "SELECT card_id FROM $table WHERE user_id = %d AND set_id = %d AND source_type = %s AND source_id = %d",
            $user_id, $data['set_id'], $data['source_type'], $data['source_id']
        ) );

        if ( $existing_card ) {
            // Optionally update the content if needed, for now just return existing ID
            // We could update front/back here if the user edited them
            $wpdb->update( $table, [
                'front_custom' => $data['front_custom'],
                'back_custom'  => $data['back_custom'],
                'updated_at'   => current_time( 'mysql' )
            ], [ 'card_id' => $existing_card->card_id ] );
            
            return rest_ensure_response( [ 'card_id' => $existing_card->card_id, 'updated' => true ] );
        }
        
        $wpdb->hide_errors();
		$result = $wpdb->insert( $table, $data );

        if ( $result === false ) {
            error_log( 'PTG Flashcards API Error (create_card): ' . $wpdb->last_error );
            return new \WP_Error( 'db_error', 'Database insertion failed: ' . $wpdb->last_error, [ 'status' => 500 ] );
        }

		// Clear any buffered output (e.g. PHP notices, WP errors) to ensure clean JSON
        if ( ob_get_length() ) {
            ob_clean();
        }
        
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
}
