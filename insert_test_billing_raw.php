<?php
$host = 'localhost';
$user = 'ptgates';
$pass = ')ZPN07xSn6R87-tH';
$db   = 'ptgates';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Connected successfully\n";

// Find user ID
$username = 'admin';
$user_id = 1; // Default

$result = $mysqli->query("SELECT ID FROM wp_users WHERE user_login = '$username'");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['ID'];
    echo "Found user '$username' with ID: $user_id\n";
} else {
    echo "User '$username' not found. Using default ID: $user_id\n";
}

$table_name = 'ptgates_billing_history';

// Clear existing test data for this user
// $mysqli->query("DELETE FROM $table_name WHERE user_id = $user_id");

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
    $sql = "INSERT INTO $table_name (user_id, order_id, pg_transaction_id, transaction_type, product_name, payment_method, amount, currency, status, transaction_date, memo) VALUES (
        {$row['user_id']},
        '{$row['order_id']}',
        '{$row['pg_transaction_id']}',
        '{$row['transaction_type']}',
        '{$row['product_name']}',
        '{$row['payment_method']}',
        {$row['amount']},
        '{$row['currency']}',
        '{$row['status']}',
        '{$row['transaction_date']}',
        '{$row['memo']}'
    )";

    if ($mysqli->query($sql) === TRUE) {
        echo "New record created successfully for " . $row['transaction_date'] . "\n";
    } else {
        echo "Error: " . $sql . "\n" . $mysqli->error . "\n";
    }
}

$mysqli->close();
?>
