<?php
namespace PTG\SelfTest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Generator {

	public static function generate( $args ) {
		global $wpdb;

		$subject = isset( $args['subject'] ) ? sanitize_text_field( $args['subject'] ) : '';
		$count   = isset( $args['count'] ) ? intval( $args['count'] ) : 20;
		
		// Base query
		$table_q = 'ptgates_questions';
		$table_c = 'ptgates_categories';

		$sql = "SELECT q.question_id 
				FROM $table_q q 
				JOIN $table_c c ON q.question_id = c.question_id 
				WHERE q.is_active = 1 
				AND c.exam_session >= 1000"; // Only generated questions

		$query_args = [];

		if ( ! empty( $subject ) && $subject !== 'all' ) {
			$sql .= " AND c.subject = %s";
			$query_args[] = $subject;
		}

		// Randomize
		$sql .= " ORDER BY RAND() LIMIT %d";
		$query_args[] = $count;

		$results = $wpdb->get_col( $wpdb->prepare( $sql, $query_args ) );

		return $results;
	}
}
