<?php
/**
 * Persistence & Loading Verification Script
 */

define('WP_USE_THEMES', false);
require_once dirname(__FILE__) . '/../../../wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';



echo "<pre>";
echo "==================================================\n";
echo "🧪 PTGates Persistence & Loading Verification\n";
echo "==================================================\n\n";

// 1. Setup User
$user_login = 'test_persist_' . time();
$user_id = wp_create_user($user_login, 'password', $user_login . '@example.com');
if (is_wp_error($user_id)) {
    die("Failed to create user: " . $user_id->get_error_message());
}
wp_set_current_user($user_id);
update_user_meta($user_id, 'ptg_premium_status', 'active'); // Avoid limits
echo "[INFO] Created User ID: $user_id\n";

// Ensure REST API hooks are fired
include_once WP_PLUGIN_DIR . '/2200-ptgates-flashcards/includes/class-flashcards-api.php';
include_once WP_PLUGIN_DIR . '/2200-ptgates-flashcards/includes/class-flashcards-db.php';
\PTG_Flashcards_DB::create_tables(); // Ensure table exists
\PTG_Flashcards_API::register_routes();

do_action('rest_api_init');

// 2. Test Quiz Loading (Fix 1 Verification)
echo "\n[Test 1: Quiz Loading]\n";
$request = new WP_REST_Request('GET', '/ptg-quiz/v1/questions');
$request->set_param('limit', 1);
$response = rest_do_request($request);

if ($response->is_error()) {
    echo "[FAIL] Quiz Load Error: " . $response->get_error_message() . "\n";
    $question_id = 0;
} else {
    $data = $response->get_data();
    if ($data['success'] && !empty($data['data'])) {
        $question_id = $data['data'][0];
        echo "[PASS] Quiz Loaded Successfully. Question ID: $question_id\n";
    } else {
        echo "[FAIL] Quiz Loaded but empty or invalid format.\n";
        print_r($data);
        $question_id = 0;
    }
}

if ($question_id) {
    // 3. Test Bookmark (Fix 2)
    echo "\n[Test 2: Bookmark Persistence]\n";
    $req = new WP_REST_Request('PATCH', "/ptg-quiz/v1/questions/$question_id/state");
    $req->set_param('bookmarked', true);
    $req->set_header('X-WP-Nonce', wp_create_nonce('wp_rest')); // Add Nonce
    $res = rest_do_request($req);
    
    global $wpdb;
    $state = $wpdb->get_row("SELECT * FROM ptgates_user_states WHERE user_id = $user_id AND question_id = $question_id");
    if ($state && $state->bookmarked == 1) {
        echo "[PASS] Bookmark Saved.\n";
    } else {
        echo "[FAIL] Bookmark Not Saved.\n";
        if (is_wp_error($res)) {
            echo "API Error: " . $res->get_error_message() . "\n";
        } else {
            echo "API Response: " . print_r($res->get_data(), true) . "\n";
        }
    }

    // 4. Test Review (Fix 2)
    echo "\n[Test 3: Review Persistence]\n";
    $req = new WP_REST_Request('PATCH', "/ptg-quiz/v1/questions/$question_id/state");
    $req->set_param('needs_review', true);
    $req->set_header('X-WP-Nonce', wp_create_nonce('wp_rest')); // Add Nonce
    $res = rest_do_request($req);
    
    $state = $wpdb->get_row("SELECT * FROM ptgates_user_states WHERE user_id = $user_id AND question_id = $question_id");
    if ($state && $state->needs_review == 1) {
        echo "[PASS] Review Status Saved.\n";
    } else {
        echo "[FAIL] Review Status Not Saved.\n";
        if (is_wp_error($res)) {
            echo "API Error: " . $res->get_error_message() . "\n";
        }
    }

    // 5. Test Memo (Fix 2)
    echo "\n[Test 4: Memo Persistence]\n";
    $req = new WP_REST_Request('POST', "/ptg-quiz/v1/questions/$question_id/memo");
    $req->set_body_params(['content' => 'Test Memo Content']);
    $req->set_header('X-WP-Nonce', wp_create_nonce('wp_rest')); // Add Nonce
    $res = rest_do_request($req);
    
    if ($res->get_status() == 404) {
        echo "[FAIL] Memo Endpoint Not Found (404).\n";
    } else {
        // Check DB (assuming table ptgates_user_memos)
        $memo = $wpdb->get_row("SELECT * FROM ptgates_user_memos WHERE user_id = $user_id AND question_id = $question_id");
        if ($memo && $memo->content === 'Test Memo Content') {
            echo "[PASS] Memo Saved.\n";
        } else {
            echo "[FAIL] Memo Not Saved.\n";
            if (is_wp_error($res)) {
                echo "API Error: " . $res->get_error_message() . "\n";
            } else {
                echo "API Response: " . print_r($res->get_data(), true) . "\n";
            }
        }
    }

    // 6. Test Flashcard (Fix 2)
    echo "\n[Test 5: Flashcard Persistence]\n";
    $req = new WP_REST_Request('POST', "/ptg-flashcards/v1/cards");
    $req->set_header('Content-Type', 'application/json');
    $req->set_body(json_encode([
        'front' => 'Test Front',
        'back' => 'Test Back',
        'source_id' => $question_id,
        'subject' => 'Test Subject'
    ]));
    $req->set_header('X-WP-Nonce', wp_create_nonce('wp_rest')); // Add Nonce
    $res = rest_do_request($req);
    
    if (is_wp_error($res)) {
        echo "[FAIL] Flashcard API Error: " . $res->get_error_message() . "\n";
    } else {
        $data = $res->get_data();
        if (isset($data['id'])) {
            $card_id = $data['id'];
            $card = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ptgates_flashcards WHERE id = $card_id");
            if ($card) {
                echo "[PASS] Flashcard Saved (ID: $card_id).\n";
            } else {
                echo "[FAIL] Flashcard ID returned but record not found in DB.\n";
            }
        } else {
            echo "[FAIL] Flashcard API did not return ID.\n";
            echo "Response: " . print_r($data, true) . "\n";
        }
    }
}

// Cleanup
wp_delete_user($user_id);
echo "\n[INFO] Cleaned up user.\n";
