<?php
namespace PTG\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function register_routes() {
		$controller = new self();
		$controller->register();
	}

	public function register() {
		$namespace = 'ptg-analytics/v1';

		register_rest_route(
			$namespace,
			'/dashboard',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_dashboard' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	public function check_permission() {
		return is_user_logged_in();
	}

	public function get_dashboard( $request ) {
		$user_id = get_current_user_id();
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[PTG Analytics] API Request received for user ID: $user_id" );
		}

		// Output buffering to catch any stray HTML (like DB errors)
		ob_start();

		try {
			$stats = Analyzer::get_dashboard_stats( $user_id );
			
			// Clean buffer
			$buffer_output = ob_get_clean();
			if ( ! empty( $buffer_output ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[PTG Analytics] Stray output caught: ' . $buffer_output );
			}

			$stats = $this->recursive_sanitize( $stats );
			return rest_ensure_response( $stats );
		} catch ( \Exception $e ) {
			ob_end_clean(); // Clean buffer on error too
			error_log( '[PTG Analytics] API Error: ' . $e->getMessage() );
			return new \WP_Error( 'analytics_error', $e->getMessage(), [ 'status' => 500 ] );
		} catch ( \Error $e ) {
			ob_end_clean();
			error_log( '[PTG Analytics] API Fatal Error: ' . $e->getMessage() );
			return new \WP_Error( 'analytics_fatal_error', $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), [ 'status' => 500 ] );
		}
	}

	private function recursive_sanitize( $data ) {
		if ( is_string( $data ) ) {
			if ( function_exists( 'mb_convert_encoding' ) ) {
				return mb_convert_encoding( $data, 'UTF-8', 'UTF-8' );
			}
			return $data;
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->recursive_sanitize( $value );
			}
			return $data;
		}
		return $data;
	}
}
