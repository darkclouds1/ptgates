<?php
// Load WordPress
require_once('wp-load.php');

global $wpdb;

echo "Checking Elementor Library Posts...\n";
$posts = $wpdb->get_results("SELECT ID, post_title, post_author, post_status, post_type FROM {$wpdb->posts} WHERE post_type = 'elementor_library'");

if ($posts) {
    foreach ($posts as $p) {
        echo "[{$p->ID}] {$p->post_title} (Author: {$p->post_author}) - Status: {$p->post_status}\n";
    }
} else {
    echo "No elementor_library posts found.\n";
}

echo "\nChecking ALL posts in Trash...\n";
$trashed = $wpdb->get_results("SELECT ID, post_title, post_author, post_type FROM {$wpdb->posts} WHERE post_status = 'trash'");
if ($trashed) {
    foreach ($trashed as $p) {
        echo "[TRASHED] [{$p->ID}] {$p->post_title} (Type: {$p->post_type})\n";
    }
} else {
    echo "No posts in trash.\n";
}

echo "\nChecking Kit Manager Options...\n";
$active_kit = get_option('elementor_active_kit');
echo "Active Kit ID: " . $active_kit . "\n";

if ($active_kit) {
    $kit_post = get_post($active_kit);
    if ($kit_post) {
        echo "Active Kit Post Found: {$kit_post->post_title} (Status: {$kit_post->post_status})\n";
    } else {
        echo "Active Kit Post ID ({$active_kit}) DOES NOT EXIST in Database.\n";
    }
}
