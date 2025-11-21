<?php
namespace PTG\Reviewer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function register_routes() {
		$controller = new self();
		$controller->register();
	}

	public function register() {
		$namespace = 'ptg-review/v1';

		// Get today's review questions
		register_rest_route(
			$namespace,
			'/today',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_today_reviews' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Schedule a review manually (or update difficulty)
		register_rest_route(
			$namespace,
			'/schedule',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'schedule_review' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'question_id' => [
						'required' => true,
						'type'     => 'integer',
					],
					'difficulty'  => [
						'required' => true,
						'type'     => 'string',
						'enum'     => [ 'easy', 'normal', 'hard' ],
					],
				],
			]
		);
	}

	public function check_permission() {
		return is_user_logged_in();
	}

	public function get_today_reviews( $request ) {
		$user_id = get_current_user_id();
		$limit   = $request->get_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 20;

		$questions = Scheduler::get_todays_reviews( $user_id, $limit );

		return rest_ensure_response( $questions );
	}

	public function schedule_review( $request ) {
		$user_id     = get_current_user_id();
		$question_id = $request->get_param( 'question_id' );
		$difficulty  = $request->get_param( 'difficulty' );

		$success = Scheduler::schedule_review( $user_id, $question_id, $difficulty );

		if ( $success ) {
			return rest_ensure_response( [ 'success' => true, 'message' => 'Review scheduled.' ] );
		} else {
			return new \WP_Error( 'schedule_failed', 'Failed to schedule review.', [ 'status' => 500 ] );
		}
	}
}
