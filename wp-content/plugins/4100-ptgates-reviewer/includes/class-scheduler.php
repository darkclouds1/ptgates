<?php
namespace PTG\Reviewer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scheduler {

	/**
	 * Calculate next review date based on difficulty
	 *
	 * @param string $difficulty 'easy', 'normal', 'hard'
	 * @return string Y-m-d date string (KST)
	 */
	public static function calculate_next_due_date( $difficulty ) {
		$days_to_add = 1; // Default (Hard)

		switch ( $difficulty ) {
			case 'easy':
				$days_to_add = 5;
				break;
			case 'normal':
				$days_to_add = 3;
				break;
			case 'hard':
			default:
				$days_to_add = 1;
				break;
		}

		// Use Platform's timezone helper if available, otherwise fallback
		if ( class_exists( '\PTG\Platform\Rest' ) && method_exists( '\PTG\Platform\Rest', 'add_days_kst' ) ) {
			return \PTG\Platform\Rest::add_days_kst( $days_to_add );
		}

		// Fallback implementation
		$tz = new \DateTimeZone( 'Asia/Seoul' );
		$date = new \DateTime( 'now', $tz );
		$date->modify( "+{$days_to_add} days" );
		return $date->format( 'Y-m-d' );
	}

	/**
	 * Get questions scheduled for today (or overdue)
	 *
	 * @param int $user_id
	 * @param int $limit
	 * @return array List of question objects
	 */
	public static function get_todays_reviews( $user_id, $limit = 20 ) {
		global $wpdb;

		$table_states = 'ptgates_user_states';
		$table_questions = 'ptgates_questions';

		// Current date in KST
		$today = '';
		if ( class_exists( '\PTG\Platform\Rest' ) && method_exists( '\PTG\Platform\Rest', 'today_kst' ) ) {
			$today = \PTG\Platform\Rest::today_kst();
		} else {
			$tz = new \DateTimeZone( 'Asia/Seoul' );
			$date = new \DateTime( 'now', $tz );
			$today = $date->format( 'Y-m-d' );
		}

		// Query:
		// 1. Needs review is true OR
		// 2. Next due date is today or past
		// AND question is active
		$sql = $wpdb->prepare(
			"SELECT q.question_id, q.content, q.type, s.difficulty, s.next_due_date
			FROM {$table_states} s
			JOIN {$table_questions} q ON s.question_id = q.question_id
			WHERE s.user_id = %d
			AND q.is_active = 1
			AND (
				s.needs_review = 1 
				OR (s.next_due_date IS NOT NULL AND s.next_due_date <= %s)
			)
			ORDER BY s.next_due_date ASC, s.updated_at ASC
			LIMIT %d",
			$user_id,
			$today,
			$limit
		);

		$results = $wpdb->get_results( $sql );

		return $results;
	}

	/**
	 * Schedule a review for a question
	 *
	 * @param int $user_id
	 * @param int $question_id
	 * @param string $difficulty
	 * @return bool Success
	 */
	public static function schedule_review( $user_id, $question_id, $difficulty ) {
		global $wpdb;
		
		// Ensure Platform classes are loaded
		if ( ! class_exists( '\PTG\Platform\Repo' ) ) {
			return false;
		}

		$next_due = self::calculate_next_due_date( $difficulty );
		$table = 'ptgates_user_states'; // Repo handles prefix

		// Check if state exists
		$repo = \PTG\Platform\Repo::get_instance();
		$existing = $repo->get( $table, [ 'user_id' => $user_id, 'question_id' => $question_id ] );

		$data = [
			'difficulty'    => $difficulty,
			'next_due_date' => $next_due,
			'needs_review'  => 0, // Clear "needs review" flag as it is now scheduled
			'updated_at'    => current_time( 'mysql' )
		];

		if ( $existing ) {
			return $repo->update( $table, $data, [ 'state_id' => $existing->state_id ] );
		} else {
			$data['user_id'] = $user_id;
			$data['question_id'] = $question_id;
			return $repo->insert( $table, $data );
		}
	}
}
