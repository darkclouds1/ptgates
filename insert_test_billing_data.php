<?php
require_once 'wp-load.php';

// Try to find admin user
$user = get_user_by('login', 'admin');
if (!$user) {
    // Fallback to ID 1
    $user = get_user_by('id', 1);
}

if (!$user) {
    die("Admin user not found.\n");
}

$user_id = $user->ID;
echo "Found user: " . $user->user_login . " (ID: " . $user_id . ")\n";

global $wpdb;
$table_name = 'ptgates_billing_history';

// Check if table exists
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
    die("Table $table_name does not exist.\n");
}

// Clear existing test data for this user to avoid duplicates if run multiple times
// $wpdb->delete($table_name, ['user_id' => $user_id]); 

$data = [
    [
        'user_id' => $user_id,
        'order_id' => 'ORD-' . time() . '-1',
        'pg_transaction_id' => 't_1234567890',
        'transaction_type' => 'purchase',
        'product_name' => 'Premium Membership (1 Month)',
        'payment_method' => 'card',
        'amount' => 9900,
        'currency' => 'KRW',
        'status' => 'paid',
        'transaction_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'memo' => 'Test payment 1'
    ],
    [
        'user_id' => $user_id,
        'order_id' => 'ORD-' . time() . '-2',
        'pg_transaction_id' => 't_0987654321',
        'transaction_type' => 'renewal',
        'product_name' => 'Premium Membership (1 Month)',
        'payment_method' => 'card',
        'amount' => 9900,
        'currency' => 'KRW',
        'status' => 'paid',
        'transaction_date' => date('Y-m-d H:i:s', strtotime('-32 days')),
        'memo' => 'Test payment 2'
    ],
    [
        'user_id' => $user_id,
        'order_id' => 'ORD-' . time() . '-3',
        'pg_transaction_id' => '',
        'transaction_type' => 'purchase',
        'product_name' => 'Premium Membership (1 Month)',
        'payment_method' => 'card',
        'amount' => 9900,
        'currency' => 'KRW',
        'status' => 'failed',
        'transaction_date' => date('Y-m-d H:i:s', strtotime('-62 days')),
        'memo' => 'Test failed payment'
    ]
];

foreach ($data as $row) {
    $result = $wpdb->insert($table_name, $row);
    if ($result === false) {
        echo "Error inserting row: " . $wpdb->last_error . "\n";
    } else {
        echo "Inserted row for " . $row['transaction_date'] . "\n";
    }
}

echo "Done.\n";
