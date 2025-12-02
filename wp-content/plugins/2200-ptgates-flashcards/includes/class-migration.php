<?php
namespace PTG\Flashcards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Migration {
	public static function run_migrations() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sets_table = 'ptgates_flashcard_sets';
		$cards_table = 'ptgates_flashcards';

		$sql_sets = "CREATE TABLE $sets_table (
			set_id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			set_name varchar(255) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (set_id),
			KEY user_id (user_id)
		) $charset_collate;";

		$sql_cards = "CREATE TABLE $cards_table (
			card_id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			set_id bigint(20) NOT NULL,
			source_type varchar(50) DEFAULT 'custom',
			source_id bigint(20) DEFAULT 0,
			front_custom longtext,
			back_custom longtext,
			review_count int(11) DEFAULT 0,
			next_due_date date DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (card_id),
			KEY user_set (user_id, set_id),
			KEY next_due (next_due_date)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_sets );
		dbDelta( $sql_cards );

		// Ensure Default Set (ID 1) exists
		// Note: AUTO_INCREMENT might skip 1 if rows were deleted, so we force ID 1 if possible or check existence.
		// Since we can't easily force ID on auto_increment in generic SQL without tricks, 
		// we just check if ID 1 exists. If table is empty, we insert.
		// Actually, for consistency, we should try to insert with ID 1 explicitly if it doesn't exist.
		
		$exists = $wpdb->get_var( "SELECT set_id FROM $sets_table WHERE set_id = 1" );
		if ( ! $exists ) {
			$wpdb->insert( $sets_table, [
				'set_id'   => 1,
				'user_id'  => 0, // System/Global or just placeholder
				'set_name' => '모의고사 암기카드', // Default name
			] );
		}
	}
}
