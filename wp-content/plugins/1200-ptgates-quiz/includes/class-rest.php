<?php

namespace PTG\Quiz;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use PTG\Platform\Repo;

/**
 * Class PTG_Quiz_REST_Controller.
 */
class REST_Controller {
    /**
     * Namespace.
     *
     * @var string
     */
    protected $namespace = 'ptg-quiz/v1';

    /**
     * Register all routes.
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/questions/(?P<id>\d+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_question' ],
			'permission_callback' => '__return_true',
		] );

        register_rest_route($this->namespace, '/questions/(?P<id>\d+)/state', [
            [
                'methods'             => WP_REST_Server::READABLE, // GET
                'callback'            => [ $this, 'get_question_state' ],
                'permission_callback' => [ $this, 'check_read_permission' ],
                'args'                => [
                    'id' => [ 'validate_callback' => 'is_numeric' ]
                ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE, // POST, PUT, PATCH
                'callback'            => [ $this, 'update_question_state' ],
                'permission_callback' => [ $this, 'check_write_permission' ],
                'args'                => [
                    'id' => [ 'validate_callback' => 'is_numeric' ]
                ],
            ]
        ]);

        register_rest_route( $this->namespace, '/questions/(?P<id>\d+)/attempt', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'submit_attempt' ],
			'permission_callback' => [ $this, 'check_write_permission' ],
		] );
    }

    /**
     * Check permissions for reading data (logged in).
     */
    public function check_read_permission( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', 'You are not currently logged in.', [ 'status' => 401 ] );
        }
        return true;
    }

    /**
     * Check permissions for writing data (logged in + nonce).
     */
    public function check_write_permission( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', 'You are not currently logged in.', [ 'status' => 401 ] );
        }
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'rest_invalid_nonce', 'Invalid nonce.', [ 'status' => 403 ] );
        }
        return true;
    }

    /**
     * Get single question.
     */
    public function get_question( WP_REST_Request $request ) {
        $id = (int) $request['id'];
        $question = Repo::get_question_by_id( $id );

        if ( ! $question ) {
            return new WP_Error( 'rest_question_not_found', 'Question not found.', [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $question, 200 );
    }

    /**
     * Get user-specific state for a question (e.g., bookmarked).
     */
    public function get_question_state( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $question_id = (int) $request['id'];

        $state = Repo::get_user_question_state($user_id, $question_id);

        if (is_null($state)) {
            return new WP_REST_Response([
                'bookmarked' => false,
                'needs_review' => false,
                'memo' => ''
            ], 200);
        }

        return new WP_REST_Response($state, 200);
    }

    /**
     * Update user-specific state for a question.
     */
    public function update_question_state( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $question_id = (int) $request['id'];
        $params = $request->get_json_params();

        $valid_params = [];
        if (isset($params['bookmarked'])) {
            $valid_params['is_bookmarked'] = (bool) $params['bookmarked'];
        }
        if (isset($params['needs_review'])) {
            $valid_params['needs_review'] = (bool) $params['needs_review'];
        }
        if (isset($params['memo'])) {
            $valid_params['memo'] = sanitize_textarea_field($params['memo']);
        }

        if (empty($valid_params)) {
            return new WP_Error('rest_no_valid_params', 'No valid parameters to update.', ['status' => 400]);
        }

        $result = Repo::update_user_question_state($user_id, $question_id, $valid_params);

        if (!$result) {
            return new WP_Error('rest_update_failed', 'Failed to update question state.', ['status' => 500]);
        }

        return new WP_REST_Response(['success' => true, 'updated' => $valid_params], 200);
    }

    /**
     * Submit an attempt for a question.
     */
    public function submit_attempt( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $question_id = (int) $request['id'];
        $params = $request->get_json_params();

        if ( ! isset( $params['answer'] ) || ! isset($params['elapsed_time']) ) {
            return new WP_Error( 'rest_missing_params', 'Missing parameters.', [ 'status' => 400 ] );
        }

        $user_answer = sanitize_text_field( $params['answer'] );
        $elapsed_time = (int) $params['elapsed_time'];

        $is_correct = Repo::check_answer( $question_id, $user_answer );
        
        Repo::save_user_result( $user_id, $question_id, $user_answer, $is_correct, $elapsed_time );

        return new WP_REST_Response( [
            'question_id' => $question_id,
            'is_correct'  => $is_correct,
        ], 200 );
    }
}
