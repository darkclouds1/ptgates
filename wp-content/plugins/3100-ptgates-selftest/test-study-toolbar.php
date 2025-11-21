<?php
/**
 * Test script for 4-feature toolbar persistence in Study module
 * Test URL: http://ptgates.com/wp-content/plugins/3100-ptgates-selftest/test-study-toolbar.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Ensure Flashcards API is loaded and registered
if (!class_exists('PTG_Flashcards_API')) {
    require_once(WP_PLUGIN_DIR . '/2200-ptgates-flashcards/includes/class-flashcards-db.php');
    require_once(WP_PLUGIN_DIR . '/2200-ptgates-flashcards/includes/class-flashcards-api.php');
}

// Manually register flashcards REST routes if not already registered
if (class_exists('PTG_Flashcards_API')) {
    add_action('rest_api_init', ['PTG_Flashcards_API', 'register_routes'], 1);
    do_action('rest_api_init'); // Trigger REST API initialization
}

// Ensure user is logged in
if (!is_user_logged_in()) {
    wp_die('Please log in to run this test.');
}

$current_user = wp_get_current_user();
echo "<h1>Study Module Toolbar Persistence Test</h1>";
echo "<p>Testing user: {$current_user->display_name} (ID: {$current_user->ID})</p>";

// Generate a valid nonce for REST API
// For internal testing, we'll set the user context directly
wp_set_current_user($current_user->ID);
$nonce = wp_create_nonce('wp_rest');

echo "<p>Generated nonce: {$nonce}</p>";

// Test question ID (use first available question)
global $wpdb;
$test_question = $wpdb->get_row("SELECT question_id FROM ptgates_questions ORDER BY RAND() LIMIT 1");

if (!$test_question) {
    wp_die('No questions found in database. Please import questions first.');
}

$question_id = $test_question->question_id;
echo "<h2>Test Question ID: {$question_id}</h2>";

// Initialize test results
$results = [];

echo "<hr>";
echo "<h2>Running Tests...</h2>";

// Test 1: Bookmark
echo "<h3>Test 1: Bookmark Feature</h3>";
$bookmark_request = new WP_REST_Request('PATCH', "/ptg-quiz/v1/questions/{$question_id}/state");
$bookmark_request->set_body(json_encode(['bookmarked' => true]));
$bookmark_request->set_header('content-type', 'application/json');
$bookmark_request->set_header('X-WP-Nonce', $nonce);
$bookmark_response = rest_do_request($bookmark_request);

if (is_wp_error($bookmark_response)) {
    echo "<p style='color: red;'>❌ FAIL: " . $bookmark_response->get_error_message() . "</p>";
    $results['bookmark'] = 'FAIL';
} elseif ($bookmark_response->get_status() >= 400) {
    echo "<p style='color: red;'>❌ FAIL: HTTP " . $bookmark_response->get_status() . " - " . json_encode($bookmark_response->get_data()) . "</p>";
    $results['bookmark'] = 'FAIL';
} else {
    // Check DB
    $bookmark_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ptgates_user_states WHERE user_id = %d AND question_id = %d AND bookmarked = 1",
        $current_user->ID,
        $question_id
    ));
    
    if ($bookmark_record) {
        echo "<p style='color: green;'>✅ PASS: Bookmark saved to DB</p>";
        echo "<pre>" . print_r($bookmark_record, true) . "</pre>";
        $results['bookmark'] = 'PASS';
    } else {
        echo "<p style='color: red;'>❌ FAIL: Bookmark API succeeded but no DB record found</p>";
        $results['bookmark'] = 'FAIL';
    }
}

// Test 2: Review
echo "<h3>Test 2: Review Feature</h3>";
$review_request = new WP_REST_Request('PATCH', "/ptg-quiz/v1/questions/{$question_id}/state");
$review_request->set_body(json_encode(['needs_review' => true]));
$review_request->set_header('content-type', 'application/json');
$review_request->set_header('X-WP-Nonce', $nonce);
$review_response = rest_do_request($review_request);

if (is_wp_error($review_response)) {
    echo "<p style='color: red;'>❌ FAIL: " . $review_response->get_error_message() . "</p>";
    $results['review'] = 'FAIL';
} elseif ($review_response->get_status() >= 400) {
    echo "<p style='color: red;'>❌ FAIL: HTTP " . $review_response->get_status() . " - " . json_encode($review_response->get_data()) . "</p>";
    $results['review'] = 'FAIL';
} else {
    $review_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ptgates_user_states WHERE user_id = %d AND question_id = %d AND needs_review = 1",
        $current_user->ID,
        $question_id
    ));
    
    if ($review_record) {
        echo "<p style='color: green;'>✅ PASS: Review marked saved to DB</p>";
        echo "<pre>" . print_r($review_record, true) . "</pre>";
        $results['review'] = 'PASS';
    } else {
        echo "<p style='color: red;'>❌ FAIL: Review API succeeded but no DB record found</p>";
        $results['review'] = 'FAIL';
    }
}

// Test 3: Memo
echo "<h3>Test 3: Memo Feature</h3>";
$memo_content = "Test memo created at " . date('Y-m-d H:i:s');
$memo_request = new WP_REST_Request('POST', "/ptg-quiz/v1/questions/{$question_id}/memo");
$memo_request->set_body(json_encode(['content' => $memo_content]));
$memo_request->set_header('content-type', 'application/json');
$memo_request->set_header('X-WP-Nonce', $nonce);
$memo_response = rest_do_request($memo_request);

if (is_wp_error($memo_response)) {
    echo "<p style='color: red;'>❌ FAIL: " . $memo_response->get_error_message() . "</p>";
    $results['memo'] = 'FAIL';
} elseif ($memo_response->get_status() >= 400) {
    echo "<p style='color: red;'>❌ FAIL: HTTP " . $memo_response->get_status() . " - " . json_encode($memo_response->get_data()) . "</p>";
    $results['memo'] = 'FAIL';
} else {
    $memo_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ptgates_user_memos WHERE user_id = %d AND question_id = %d",
        $current_user->ID,
        $question_id
    ));
    
    if ($memo_record && $memo_record->content === $memo_content) {
        echo "<p style='color: green;'>✅ PASS: Memo saved to DB with correct content</p>";
        echo "<pre>" . print_r($memo_record, true) . "</pre>";
        $results['memo'] = 'PASS';
    } else {
        echo "<p style='color: red;'>❌ FAIL: Memo API succeeded but content mismatch or no DB record</p>";
        if ($memo_record) {
            echo "<p>Expected: {$memo_content}</p>";
            echo "<p>Got: {$memo_record->content}</p>";
        }
        $results['memo'] = 'FAIL';
    }
}

// Test 4: Flashcard
echo "<h3>Test 4: Flashcard Feature</h3>";
$flashcard_front = "Test flashcard front - Question ID {$question_id}";
$flashcard_back = "Test flashcard back - Created at " . date('Y-m-d H:i:s');
$flashcard_request = new WP_REST_Request('POST', "/ptg-flashcards/v1/cards");
$flashcard_request->set_body(json_encode([
    'front' => $flashcard_front,
    'back' => $flashcard_back,
    'ref_id' => $question_id,
    'ref_type' => 'question'
]));
$flashcard_request->set_header('content-type', 'application/json');
$flashcard_request->set_header('X-WP-Nonce', $nonce);
$flashcard_response = rest_do_request($flashcard_request);

if (is_wp_error($flashcard_response)) {
    echo "<p style='color: red;'>❌ FAIL: " . $flashcard_response->get_error_message() . "</p>";
    $results['flashcard'] = 'FAIL';
} elseif ($flashcard_response->get_status() >= 400) {
    echo "<p style='color: red;'>❌ FAIL: HTTP " . $flashcard_response->get_status() . " - " . json_encode($flashcard_response->get_data()) . "</p>";
    
    // Show last DB error if available
    global $wpdb;
    if ($wpdb->last_error) {
        echo "<p style='color: orange;'>DB Error: " . htmlspecialchars($wpdb->last_error) . "</p>";
    }
    
    // Try to manually check what's preventing the insert
    echo "<h4>Debug Info:</h4>";
    echo "<p>Attempting direct DB query to diagnose...</p>";
    
    $test_insert = $wpdb->insert('ptgates_flashcards', [
        'user_id' => $current_user->ID,
        'front_text' => 'Direct test',
        'back_text' => 'Direct test back',
        'ref_id' => $question_id,
        'ref_type' => 'question',
        'ease' => 2,
        'reviews' => 0
    ]);
    
    if ($test_insert === false) {
        echo "<p style='color: red;'>Direct insert also failed!</p>";
        echo "<p>DB Error: " . htmlspecialchars($wpdb->last_error) . "</p>";
        echo "<p>Last Query: " . htmlspecialchars($wpdb->last_query) . "</p>";
    } else {
        echo "<p style='color: green;'>Direct insert succeeded! (ID: {$wpdb->insert_id})</p>";
        echo "<p>This suggests the API layer has an issue, not the DB.</p>";
    }
    
    $results['flashcard'] = 'FAIL';
} else {
    $flashcard_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ptgates_flashcards WHERE user_id = %d AND ref_id = %d AND front_text = %s",
        $current_user->ID,
        $question_id,
        $flashcard_front
    ));
    
    if ($flashcard_record && $flashcard_record->back_text === $flashcard_back) {
        echo "<p style='color: green;'>✅ PASS: Flashcard saved to DB with correct content</p>";
        echo "<pre>" . print_r($flashcard_record, true) . "</pre>";
        $results['flashcard'] = 'PASS';
    } else {
        echo "<p style='color: red;'>❌ FAIL: Flashcard API succeeded but content mismatch or no DB record</p>";
        if ($flashcard_record) {
            echo "<p>Expected back: {$flashcard_back}</p>";
            echo "<p>Got back: {$flashcard_record->back_text}</p>";
        } else {
            echo "<p>No record found in DB</p>";
            // Show what's in the table for debugging
            $all_flashcards = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM ptgates_flashcards WHERE user_id = %d ORDER BY created_at DESC LIMIT 3",
                $current_user->ID
            ));
            echo "<p>Recent flashcards for this user:</p>";
            echo "<pre>" . print_r($all_flashcards, true) . "</pre>";
        }
        $results['flashcard'] = 'FAIL';
    }
}

// Summary
echo "<hr>";
echo "<h2>Test Summary</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Feature</th><th>Result</th></tr>";
foreach ($results as $feature => $result) {
    $color = $result === 'PASS' ? 'green' : 'red';
    $icon = $result === 'PASS' ? '✅' : '❌';
    echo "<tr><td>" . ucfirst($feature) . "</td><td style='color: {$color};'>{$icon} {$result}</td></tr>";
}
echo "</table>";

$pass_count = count(array_filter($results, function($r) { return $r === 'PASS'; }));
$total_count = count($results);

echo "<h3>Overall: {$pass_count}/{$total_count} tests passed</h3>";

if ($pass_count === $total_count) {
    echo "<p style='color: green; font-size: 20px; font-weight: bold;'>🎉 All tests PASSED!</p>";
} else {
    echo "<p style='color: red; font-size: 20px; font-weight: bold;'>⚠️ Some tests FAILED. Please review the errors above.</p>";
}
