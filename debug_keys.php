<?php
require_once('wp-load.php');

$store_id = get_option('ptg_portone_store_id');
$channel_key = get_option('ptg_portone_channel_key');

echo "Store ID: [" . $store_id . "]\n";
echo "Channel Key: [" . $channel_key . "]\n";
echo "Length of Channel Key: " . strlen($channel_key) . "\n";
?>
