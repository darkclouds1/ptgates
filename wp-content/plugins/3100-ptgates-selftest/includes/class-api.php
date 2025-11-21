<?php
namespace PTG\SelfTest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function register_routes() {
		$controller = new self();
		$controller->register();
	}

	public function register() {
		$namespace = 'ptg-selftest/v1';

		register_rest_route(
			$namespace,
			'/generate',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'generate_test' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	public function check_permission() {
		return is_user_logged_in();
	}

	public function generate_test( $request ) {
		$subject = $request->get_param( 'subject' );
		$count   = $request->get_param( 'count' );

		$ids = Generator::generate( [
			'subject' => $subject,
			'count'   => $count,
		] );

		if ( empty( $ids ) ) {
			return new \WP_Error( 'no_questions', '조건에 맞는 문제가 없습니다.', [ 'status' => 404 ] );
		}

		// Generate URL for the quiz page
		// Assuming there is a page with [ptg_quiz] shortcode.
		// We need to know the URL of that page. For now, we'll assume a standard page '/quiz' or similar,
		// OR we can return the IDs and let JS handle the redirection if the page is known.
		// Better: The plugin should probably have a setting for the Quiz Page URL.
		// For this MVP, we will assume the user is on a page that can render the quiz or we redirect to a generic quiz page.
		// Let's assume '/quiz' exists or we use the current page if it has the quiz shortcode (which it won't, this is the generator).
		// We'll return the IDs and let the frontend construct the URL to '/quiz'.
		
		return rest_ensure_response( [
			'success' => true,
			'ids'     => implode( ',', $ids ),
			'count'   => count( $ids )
		] );
	}
}
