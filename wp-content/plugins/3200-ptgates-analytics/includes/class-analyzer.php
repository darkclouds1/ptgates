<?php
namespace PTG\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analyzer {

	public static function get_dashboard_stats( $user_id ) {
		return [
			'all_subject_stats' => self::get_all_subject_stats( $user_id ),
			'recent_accuracy' => self::get_recent_accuracy( $user_id ),
			'predicted_score' => self::get_predicted_score( $user_id ),
			'learning_streak' => self::get_learning_streak( $user_id ),
			'learning_velocity' => self::get_learning_velocity( $user_id ),
			'subject_radar' => self::get_subject_radar_data( $user_id ),
		];
	}

	private static function get_all_subject_stats( $user_id ) {
		global $wpdb;
		$table_r = self::get_table_name('ptgates_user_results');
		$table_c = self::get_table_name('ptgates_categories');

		// 1. Get User Performance (Correct / Attempted)
		$sql_user = "SELECT c.subject, 
				COUNT(*) as attempted, 
				SUM(CASE WHEN r.is_correct = 1 THEN 1 ELSE 0 END) as correct 
				FROM $table_r r 
				JOIN $table_c c ON r.question_id = c.question_id 
				WHERE r.user_id = %d 
				GROUP BY c.subject";
		
		$user_results = $wpdb->get_results( $wpdb->prepare( $sql_user, $user_id ), OBJECT_K ); // Key by subject

		// 2. Get Total Available Questions per Subject from DB (Dynamic)
		// Note: We could use Subjects::MAP for static totals, but DB is more accurate for actual available content
		$sql_total = "SELECT subject, COUNT(*) as total_available 
					  FROM $table_c 
					  GROUP BY subject";
		$total_results = $wpdb->get_results( $sql_total, OBJECT_K );

		// 3. Build Master List from Subjects::MAP to ensure order and completeness
		$data = [];
		
		// Ensure Subjects class is loaded
		if ( ! class_exists( '\PTG\Quiz\Subjects' ) ) {
			// Fallback if class not loaded (should be loaded by platform)
			return [];
		}

		$sessions = \PTG\Quiz\Subjects::MAP;

		foreach ( $sessions as $session_id => $session_data ) {
			foreach ( $session_data['subjects'] as $subject_name => $subject_info ) {
				// Iterate sub-subjects
				foreach ( $subject_info['subs'] as $sub_name => $static_count ) {
					
					$user_stat = isset( $user_results[ $sub_name ] ) ? $user_results[ $sub_name ] : null;
					$total_stat = isset( $total_results[ $sub_name ] ) ? $total_results[ $sub_name ] : null;

					$attempted = $user_stat ? (int)$user_stat->attempted : 0;
					$correct = $user_stat ? (int)$user_stat->correct : 0;
					// Use DB count if available, otherwise fallback to static map count
					$total_available = $total_stat ? (int)$total_stat->total_available : $static_count;

					$accuracy = $attempted > 0 ? round( ( $correct / $attempted ) * 100 ) : 0;

					$data[] = [
						'subject' => $sub_name,
						'parent_subject' => $subject_name,
						'accuracy' => $accuracy,
						'attempted' => $attempted,
						'correct' => $correct,
						'total_available' => $total_available
					];
				}
			}
		}

		return $data;
	}

	private static function get_recent_accuracy( $user_id ) {
		global $wpdb;
		$table_r = self::get_table_name('ptgates_user_results');

		// Get last 50 answers
		$sql = "SELECT is_correct, attempted_at FROM $table_r WHERE user_id = %d ORDER BY attempted_at DESC LIMIT 50";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ) );

		if ( ! empty( $wpdb->last_error ) ) {
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
		$accuracy = self::get_recent_accuracy( $user_id );
		return $accuracy; 
	}

	private static function get_learning_streak( $user_id ) {
		global $wpdb;
		$table_r = self::get_table_name('ptgates_user_results');

		$sql = "SELECT DATE(attempted_at) as date FROM $table_r WHERE user_id = %d GROUP BY date ORDER BY date DESC LIMIT 30";
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ) );

		if ( empty( $results ) ) return 0;

		$streak = 0;
		$today = new \DateTime('now', new \DateTimeZone('Asia/Seoul'));
		$yesterday = clone $today;
		$yesterday->modify('-1 day');
		
		$today_str = $today->format('Y-m-d');
		$yesterday_str = $yesterday->format('Y-m-d');

		$dates = array_column($results, 'date');
		
		// Check if studied today or yesterday to start the streak
		if ( !in_array($today_str, $dates) && !in_array($yesterday_str, $dates) ) {
			return 0;
		}

		$check_date = in_array($today_str, $dates) ? $today : $yesterday;

		foreach ( $dates as $date_str ) {
			if ( $date_str === $check_date->format('Y-m-d') ) {
				$streak++;
				$check_date->modify('-1 day');
			} else {
				if ( $date_str < $check_date->format('Y-m-d') ) {
					break;
				}
			}
		}

		return $streak;
	}

	private static function get_learning_velocity( $user_id ) {
		global $wpdb;
		$table_r = self::get_table_name('ptgates_user_results');

		$sql = "SELECT DATE(attempted_at) as date, COUNT(*) as count 
				FROM $table_r 
				WHERE user_id = %d AND attempted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
				GROUP BY date 
				ORDER BY date ASC";
		
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ) );
		
		$data = [];
		for ($i = 6; $i >= 0; $i--) {
			$date = date('Y-m-d', strtotime("-$i days"));
			$found = false;
			foreach ($results as $row) {
				if ($row->date === $date) {
					$data[] = ['date' => $date, 'count' => (int)$row->count];
					$found = true;
					break;
				}
			}
			if (!$found) {
				$data[] = ['date' => $date, 'count' => 0];
			}
		}

		return $data;
	}

	private static function get_subject_radar_data( $user_id ) {
		global $wpdb;
		$table_r = self::get_table_name('ptgates_user_results');
		$table_c = self::get_table_name('ptgates_categories');

		$sql = "SELECT c.subject, 
				COUNT(*) as total, 
				SUM(CASE WHEN r.is_correct = 1 THEN 1 ELSE 0 END) as correct 
				FROM $table_r r 
				JOIN $table_c c ON r.question_id = c.question_id 
				WHERE r.user_id = %d 
				GROUP BY c.subject 
				HAVING total >= 1
				ORDER BY total DESC 
				LIMIT 6"; 
		
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ) );
		
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
