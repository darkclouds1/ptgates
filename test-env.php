<?php
require_once('wp-load.php');
header('Content-Type: application/json');

$response = [
    'mb_substr' => function_exists('mb_substr'),
    'mb_strpos' => function_exists('mb_strpos'),
    'mysqli' => class_exists('mysqli'),
    'wpdb' => isset($wpdb),
    'tables' => [],
];

if (isset($wpdb)) {
    $response['tables'] = $wpdb->get_col("SHOW TABLES LIKE 'ptgates_%'");
}

echo json_encode($response);

