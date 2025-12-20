<?php
require_once('wp-load.php');
global $wpdb;
$cols = $wpdb->get_results("SHOW COLUMNS FROM ptgates_categories");
foreach ($cols as $c) {
    echo $c->Field . "\n";
}
