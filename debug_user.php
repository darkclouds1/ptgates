<?php
require_once('wp-load.php');

$username = 'ptgates_test';
$user = get_user_by('login', $username);

if ($user) {
    echo "User found by login: " . $user->user_login . "\n";
    echo "ID: " . $user->ID . "\n";
    echo "Email: " . $user->user_email . "\n";
} else {
    echo "User NOT found by login: $username\n";
    // Search similar
    global $wpdb;
    $results = $wpdb->get_results("SELECT ID, user_login, user_email FROM {$wpdb->users} WHERE user_login LIKE '%ptgates%'");
    echo "Similar users:\n";
    print_r($results);
}
