<?php
// Load WordPress
require_once('wp-load.php');

$correct_key = 'SU5JTElURV9UUklQTEVERVNfS0VZU1RS';
$current_key = get_option('ptg_payment_sign_key');

echo "Current Key: " . $current_key . "\n";
echo "Correct Key: " . $correct_key . "\n";

if ($current_key !== $correct_key) {
    update_option('ptg_payment_sign_key', $correct_key);
    echo "Updated Sign Key to Correct Value.\n";
} else {
    echo "Sign Key is already correct.\n";
}

// Also check timestamp logic
$t1 = time() * 1000;
$t2 = round(microtime(true) * 1000);
echo "Time() * 1000: $t1\n";
echo "Microtime: $t2\n";
