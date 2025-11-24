<?php
namespace PTG\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analyzer {

	public static function get_dashboard_stats( $user_id ) {
		return [
			'weak_subjects' => self::get_weak_subjects( $user_id ),
			'recent_accuracy' => self::get_recent_accuracy( $user_id ),
			'predicted_score' => self::get_predicted_score( $user_id ),
		];
	}

	private static function get_weak_subjects( $user_id ) {
		global $wpdb;
		$table_r = self::get_table_name('ptgates_user_results');
		$table_c = self::get_table_name('ptgates_categories');

		// Calculate accuracy per subject
		// Only consider last 100 results to reflect recent performance
		$sql = "SELECT c.subject, 
				COUNT(*) as total, 
				SUM(CASE WHEN r.is_correct = 1 THEN 1 ELSE 0 END) as correct 
				FROM $table_r r 
				JOIN $table_c c ON r.question_id = c.question_id 
				WHERE r.user_id = %d 
				GROUP BY c.subject 
				HAVING total >= 5 
				ORDER BY (SUM(CASE WHEN r.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(*)) ASC 
				LIMIT 5";
		
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ) );
		
		if ( ! empty( $wpdb->last_error ) ) {
			// error_log( '[PTG Analytics] DB Error in get_weak_subjects: ' . $wpdb->last_error );
			// Return empty array gracefully or throw exception depending on preference.
			// For now, return empty to avoid breaking the page completely.
			return [];
		}

		$data = [];
		foreach ( $results as $row ) {
			$accuracy = $row->total > 0 ? round( ( $row->correct / $row->total ) * 100 ) : 0;
			$data[] = [
				'subject' => $row->subject,
				'accuracy' => $accuracy,
				'total' => $row->total
			];
		}

		return $data;
	}

	private static function get_recent_accuracy( $user_id ) {
		global $wpdb;
		$table_r = self::get_table_name('ptgates_user_results');

		// Get last 10 quiz sessions (grouped by date or just last N records)
		// For simplicity, let's take last 50 answers
		$sql = "SELECT is_correct, attempted_at FROM $table_r WHERE user_id = %d ORDER BY attempted_at DESC LIMIT 50";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ) );

		if ( ! empty( $wpdb->last_error ) ) {
			// error_log( '[PTG Analytics] DB Error in get_recent_accuracy: ' . $wpdb->last_error );
			return 0;
		}

		$correct = 0;
		$total = count( $results );
		if ( $total === 0 ) return 0;

		foreach ( $results as $row ) {
			if ( $row->is_correct ) $correct++;
		}

		return round( ( $correct / $total ) * 100 );
	}

	private static function get_predicted_score( $user_id ) {
		// Simple prediction: Recent Accuracy * 100 (assuming 100 point scale)
		// In reality, this should be weighted by difficulty and subject distribution
		$accuracy = self::get_recent_accuracy( $user_id );
		return $accuracy; // Just return the percentage for now
	}

	/**
	 * Helper to get table name with prefix if needed
	 */
	private static function get_table_name( $base_name ) {
		global $wpdb;
		static $cache = [];
		
		if ( isset( $cache[ $base_name ] ) ) {
			return $cache[ $base_name ];
		}

		// Check if table exists without prefix first (legacy support)
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $base_name ) ) === $base_name ) {
			$cache[ $base_name ] = $base_name;
			return $base_name;
		}
		
		$prefixed = $wpdb->prefix . $base_name;
		$cache[ $base_name ] = $prefixed;
		return $prefixed;
	}
}
