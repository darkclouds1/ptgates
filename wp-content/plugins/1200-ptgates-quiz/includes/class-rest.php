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
		\t// 추가: 교시/과목/세부과목 조회 라우트 (현재 서버에서 class-api.php가 로드되지 않는 경우 대비)
		\tregister_rest_route( $this->namespace, '/sessions', [
		\t\t'methods'             => WP_REST_Server::READABLE,
		\t\t'callback'            => [ $this, 'get_sessions' ],
		\t\t'permission_callback' => '__return_true',
		\t] );

		\tregister_rest_route( $this->namespace, '/subjects', [
		\t\t'methods'             => WP_REST_Server::READABLE,
		\t\t'callback'            => [ $this, 'get_subjects' ],
		\t\t'permission_callback' => '__return_true',
		\t\t'args'                => [
		\t\t\t'session' => [ 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint' ],
		\t\t],
		\t] );

		\tregister_rest_route( $this->namespace, '/subsubjects', [
		\t\t'methods'             => WP_REST_Server::READABLE,
		\t\t'callback'            => [ $this, 'get_subsubjects' ],
		\t\t'permission_callback' => '__return_true',
		\t\t'args'                => [
		\t\t\t'session' => [ 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ],
		\t\t\t'subject' => [ 'type' => 'string',  'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
		\t\t],
		\t] );

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
	 * 교시 목록 (ptGates_subject)
	 */
	public function get_sessions( WP_REST_Request $request ) {
		global $wpdb;
		$table = 'ptGates_subject';
		$sql = "SELECT DISTINCT `course_no` FROM `{$table}` ORDER BY `course_no` ASC";
		$rows = $wpdb->get_col( $sql );
		if ( empty( $rows ) ) {
			return new WP_REST_Response( [ 1, 2 ], 200 );
		}
		$sessions = array_map( 'intval', $rows );
		return new WP_REST_Response( $sessions, 200 );
	}

	/**
	 * 과목 목록 (ptGates_subject.category) - 선택 교시 기준
	 */
	public function get_subjects( WP_REST_Request $request ) {
		global $wpdb;
		$session = absint( $request->get_param( 'session' ) );
		$table   = 'ptGates_subject';

		if ( $session ) {
			$sql  = "SELECT DISTINCT `category` FROM `{$table}` WHERE `course_no` = %d AND `category` IS NOT NULL AND `category` != '' ORDER BY `category` ASC";
			$rows = $wpdb->get_col( $wpdb->prepare( $sql, $session ) );
		} else {
			$sql  = "SELECT DISTINCT `category` FROM `{$table}` WHERE `category` IS NOT NULL AND `category` != '' ORDER BY `category` ASC";
			$rows = $wpdb->get_col( $sql );
		}

		if ( empty( $rows ) ) {
			// 기본값 (요청 편의)
			if ( $session === 1 ) {
				return new WP_REST_Response( [ '물리치료 기초', '물리치료 진단평가' ], 200 );
			}
			if ( $session === 2 ) {
				return new WP_REST_Response( [ '물리치료 중재', '의료관계법규' ], 200 );
			}
			return new WP_REST_Response( [], 200 );
		}

		return new WP_REST_Response( array_values( array_unique( array_map( 'strval', $rows ) ) ), 200 );
	}

	/**
	 * 세부과목 목록 (ptGates_subject.subcategory) - 교시+과목 기준
	 */
	public function get_subsubjects( WP_REST_Request $request ) {
		global $wpdb;
		$session = absint( $request->get_param( 'session' ) );
		$subject = sanitize_text_field( $request->get_param( 'subject' ) );
		$table   = 'ptGates_subject';

		if ( ! $session || ! $subject ) {
			return new WP_REST_Response( [], 200 );
		}

		$sql  = $wpdb->prepare(
			"SELECT DISTINCT `subcategory` FROM `{$table}`
             WHERE `course_no` = %d AND `category` = %s
               AND `subcategory` IS NOT NULL AND `subcategory` != ''
             ORDER BY `subcategory` ASC",
			$session,
			$subject
		);
		$rows = $wpdb->get_col( $sql );

		if ( empty( $rows ) ) {
			// 기본값 (요청 편의)
			if ( $session === 1 && $subject === '물리치료 기초' ) {
				return new WP_REST_Response( [ '해부생리', '운동학', '물리적 인자치료', '공중보건학' ], 200 );
			}
			if ( $session === 1 && $subject === '물리치료 진단평가' ) {
				return new WP_REST_Response( [ '근골격계 물리치료 진단평가', '신경계 물리치료 진단평가', '진단평가 원리', '심폐혈관계 검사 및 평가', '기타 계통 검사', '임상의사결정' ], 200 );
			}
			if ( $session === 2 && $subject === '물리치료 중재' ) {
				return new WP_REST_Response( [ '근골격계 중재', '신경계 중재', '심폐혈관계 중재', '림프, 피부계 중재', '물리치료 문제해결' ], 200 );
			}
			if ( $session === 2 && $subject === '의료관계법규' ) {
				return new WP_REST_Response( [ '의료법', '의료기사법', '노인복지법', '장애인복지법', '국민건강보험법' ], 200 );
			}
			return new WP_REST_Response( [], 200 );
		}

		return new WP_REST_Response( array_values( array_unique( array_map( 'strval', $rows ) ) ), 200 );
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
