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

		// Get notes
		register_rest_route(
			$namespace,
			'/notes',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_notes' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Create/Update note
		register_rest_route(
			$namespace,
			'/notes',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save_note' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Delete note
		register_rest_route(
			$namespace,
			'/notes/(?P<id>\d+)',
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_note' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	public function check_permission() {
		return is_user_logged_in();
	}

	public function get_notes( $request ) {
		global $wpdb;
		
		$user_id = get_current_user_id();
		$type    = $request->get_param( 'type' ); // 'question', 'theory', 'custom'
		$search  = $request->get_param( 'search' );
		$limit   = $request->get_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 20;
		$offset  = $request->get_param( 'offset' ) ? intval( $request->get_param( 'offset' ) ) : 0;

		$table = 'ptgates_user_notes';
		
		$where = [ 'user_id = %d' ];
		$args  = [ $user_id ];

		if ( $type && $type !== 'all' ) {
			$where[] = 'source_type = %s';
			$args[]  = $type;
		}

		if ( $search ) {
			$where[] = '(content LIKE %s OR tags LIKE %s)';
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$args[]  = $like;
			$args[]  = $like;
		}

		$where_sql = implode( ' AND ', $where );
		
		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$args[] = $limit;
		$args[] = $offset;

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

		return rest_ensure_response( $results );
	}

	public function save_note( $request ) {
		global $wpdb;
		
		// Ensure Platform Repo is available
		if ( ! class_exists( '\PTG\Platform\Repo' ) ) {
			return new \WP_Error( 'dependency_missing', 'Platform Core missing', [ 'status' => 500 ] );
		}

		$user_id = get_current_user_id();
		$note_id = $request->get_param( 'note_id' );
		
		$data = [
			'user_id'     => $user_id,
			'source_type' => $request->get_param( 'source_type' ) ?: 'custom',
			'source_id'   => $request->get_param( 'source_id' ),
			'content'     => $request->get_param( 'content' ),
			'tags'        => $request->get_param( 'tags' ),
		];

		// Validation
		if ( empty( $data['content'] ) ) {
			return new \WP_Error( 'invalid_param', 'Content is required', [ 'status' => 400 ] );
		}

		$repo  = \PTG\Platform\Repo::get_instance();
		$table = 'ptgates_user_notes';

		if ( $note_id ) {
			// Update
			// Verify ownership
			$existing = $repo->get( $table, [ 'note_id' => $note_id, 'user_id' => $user_id ] );
			if ( ! $existing ) {
				return new \WP_Error( 'not_found', 'Note not found or permission denied', [ 'status' => 404 ] );
			}
			
			$updated = $repo->update( $table, $data, [ 'note_id' => $note_id ] );
			return rest_ensure_response( [ 'success' => true, 'note_id' => $note_id ] );
		} else {
			// Create
			$data['created_at'] = current_time( 'mysql' );
			$new_id = $repo->insert( $table, $data );
			
			if ( $new_id ) {
				return rest_ensure_response( [ 'success' => true, 'note_id' => $new_id ] );
			} else {
				return new \WP_Error( 'db_error', 'Failed to create note', [ 'status' => 500 ] );
			}
		}
	}

	public function delete_note( $request ) {
		global $wpdb;
		
		if ( ! class_exists( '\PTG\Platform\Repo' ) ) {
			return new \WP_Error( 'dependency_missing', 'Platform Core missing', [ 'status' => 500 ] );
		}

		$user_id = get_current_user_id();
		$note_id = $request->get_param( 'id' );

		$repo  = \PTG\Platform\Repo::get_instance();
		$table = 'ptgates_user_notes';

		// Verify ownership
		$existing = $repo->get( $table, [ 'note_id' => $note_id, 'user_id' => $user_id ] );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', 'Note not found', [ 'status' => 404 ] );
		}

		$deleted = $repo->delete( $table, [ 'note_id' => $note_id ] );

		return rest_ensure_response( [ 'success' => (bool) $deleted ] );
	}
}
