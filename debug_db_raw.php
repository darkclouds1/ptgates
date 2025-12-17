<?php
// DB Config
$db_host = 'localhost';
$db_user = 'ptgates';
$db_pass = 'PBrZmtfoJl8b8jQNd+1LaI6dc+rye2Z/ES7omOIRrpM=';
$db_name = 'ptgates';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Raw DB Debug: Elementor Library</h1>";
echo "<table border='1'><tr><th>ID</th><th>Title</th><th>Status</th><th>Author</th><th>Content Len</th><th>Type meta</th></tr>";

$sql = "SELECT p.ID, p.post_title, p.post_status, p.post_author, LENGTH(p.post_content) as len 
        FROM wp_posts p 
        WHERE p.post_type = 'elementor_library' 
        ORDER BY p.ID DESC";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $id = $row['ID'];
        // Get Template Type
        $meta = $conn->query("SELECT meta_value FROM wp_postmeta WHERE post_id = $id AND meta_key = '_elementor_template_type'")->fetch_assoc();
        $type = $meta ? $meta['meta_value'] : 'N/A';

        echo "<tr>";
        echo "<td>{$row['ID']}</td>";
        echo "<td>{$row['post_title']}</td>";
        echo "<td>{$row['post_status']}</td>";
        echo "<td>{$row['post_author']}</td>";
        echo "<td>{$row['len']}</td>";
        echo "<td>{$type}</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6'>No results</td></tr>";
}
echo "</table>";

$conn->close();
