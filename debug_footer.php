<?php
require_once('wp-load.php');

// Force Admin for CLI
wp_set_current_user(1);

echo "<h1>Elementor Footer Debug</h1>";

// 1. Find all Elementor Library Posts
$args = array(
    'post_type' => 'elementor_library',
    'post_status' => 'any',
    'posts_per_page' => -1,
);

$posts = get_posts($args);

echo "<table border='1' cellpadding='5'>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Status</th>
    <th>Author</th>
    <th>Type (Meta)</th>
    <th>Conditions</th>
    <th>Content Length</th>
    <th>Actions</th>
</tr>";

$found_footer = false;

foreach ($posts as $p) {
    $type = get_post_meta($p->ID, '_elementor_template_type', true);
    $conditions = get_post_meta($p->ID, '_elementor_conditions', true);
    $content_len = strlen($p->post_content);
    $is_footer = stripos($p->post_title, 'footer') !== false || $type === 'footer';
    
    $highlight = $is_footer ? "style='background-color:#e6f7ff'" : "";
    
    if ($is_footer) $found_footer = true;

    echo "<tr $highlight>";
    echo "<td>{$p->ID}</td>";
    echo "<td>{$p->post_title}</td>";
    echo "<td>{$p->post_status}</td>";
    echo "<td>{$p->post_author}</td>";
    echo "<td>{$type}</td>";
    echo "<td>" . (is_array($conditions) ? implode(', ', $conditions) : $conditions) . "</td>";
    echo "<td>{$content_len} bytes</td>";
    echo "<td>";
    if ($p->post_status == 'trash') echo "Start Restore... ";
    echo "</td>";
    echo "</tr>";
    
    // Detailed inspection for Footer
    if ($is_footer) {
        echo "<tr><td colspan='8' style='background-color:#f0f0f0; padding:10px;'>";
        echo "<strong>Revisions for ID {$p->ID}:</strong><br>";
        $revisions = wp_get_post_revisions($p->ID);
        if ($revisions) {
            foreach (array_slice($revisions, 0, 5) as $rev) {
                echo "Rev ID: {$rev->ID} | Date: {$rev->post_date} | Author: {$rev->post_author} | Len: " . strlen($rev->post_content) . "<br>";
            }
        } else {
            echo "No revisions found.";
        }
        echo "</td></tr>";
    }
}

echo "</table>";

if (!$found_footer) {
    echo "<h2 style='color:red'>WARNING: No 'Footer' template found!</h2>";
}
