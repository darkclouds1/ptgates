<?php
require_once('wp-load.php');
header('Content-Type: text/plain; charset=utf-8');

$query = "9,900원";
$root = "e:\\proj\\ptgates.com\\wp-content\\plugins\\5100-ptgates-dashboard";

echo "Searching for '$query' in $root and DB...\n\n";

// 1. Recursive File Search
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iter as $path => $dir) {
    if ($dir->isFile()) {
        $content = file_get_contents($path);
        if (strpos($content, $query) !== false) {
            echo "[FILE MATCH] $path\n";
        }
    }
}

// 2. DB Search
global $wpdb;
// Products
$products = $wpdb->get_results("SELECT * FROM ptgates_products");
foreach ($products as $p) {
    echo "Checking Product {$p->id} ({$p->title})...\n";
    foreach ($p as $k => $v) {
        if (strpos($v, $query) !== false) {
            echo "[DB MATCH] Product $k: $v\n";
        }
    }
}

// Pages
$posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_content LIKE '%9,900원%'");
foreach ($posts as $p) {
    echo "[DB MATCH] Post {$p->ID}: {$p->post_title}\n";
}

echo "Done.\n";
