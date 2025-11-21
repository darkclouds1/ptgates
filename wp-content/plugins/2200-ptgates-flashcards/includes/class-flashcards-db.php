<?php

if (!defined('ABSPATH')) {
    exit;
}

class PTG_Flashcards_DB {
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'ptgates_flashcards';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            front_content longtext NOT NULL,
            back_content longtext NOT NULL,
            source_id bigint(20) DEFAULT 0,
            subject varchar(255) DEFAULT '',
            box int(11) DEFAULT 0,
            next_review datetime DEFAULT CURRENT_TIMESTAMP,
            last_reviewed datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY next_review (next_review)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function create_card($data) {
        global $wpdb;
        $table_name = 'ptgates_flashcards'; // Direct table name without prefix
        
        $insert_data = [
            'user_id' => $data['user_id'],
            'front_text' => $data['front'],
            'back_text' => $data['back'],
            'ref_id' => isset($data['ref_id']) ? $data['ref_id'] : 0,
            'ref_type' => isset($data['ref_type']) ? $data['ref_type'] : 'question',
            'ease' => 2,
            'reviews' => 0
        ];
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            // Log the error for debugging
            error_log('PTG Flashcard Insert Error: ' . $wpdb->last_error);
            error_log('Insert Data: ' . print_r($insert_data, true));
            return false;
        }
        
        return $wpdb->insert_id;
    }

    public static function get_due_cards($user_id, $limit = 20) {
        global $wpdb;
        $table_name = 'ptgates_flashcards'; // Direct table name without prefix
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND next_due_date <= %s ORDER BY next_due_date ASC LIMIT %d",
            $user_id,
            current_time('mysql'),
            $limit
        ));
    }

    public static function update_srs($card_id, $rating) {
        global $wpdb;
        $table_name = 'ptgates_flashcards'; // Direct table name without prefix
        
        $card = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE card_id = %d", $card_id));
        if (!$card) return false;

        $ease = (int)$card->ease;
        
        // SRS Logic
        // Rating: 1 (Hard/Fail), 2 (Normal/Good), 3 (Easy)
        if ($rating == 1) {
            $ease = max(1, $ease - 1); // Decrease ease, min 1
        } elseif ($rating == 3) {
            $ease = min(5, $ease + 1); // Increase ease, max 5
        }
        
        // Calculate intervals based on ease
        $intervals = [0, 1, 3, 7, 14, 30]; // Days
        $days = isset($intervals[$ease]) ? $intervals[$ease] : 90;
        
        $next_due = date('Y-m-d', strtotime("+$days days", current_time('timestamp')));

        return $wpdb->update($table_name, [
            'ease' => $ease,
            'reviews' => $card->reviews + 1,
            'next_due_date' => $next_due,
            'last_reviewed_at' => current_time('mysql')
        ], ['card_id' => $card_id]);
    }
}
