<?php
$host = '127.0.0.1';
$user = 'ptgates';
$pass = 'PBrZmtfoJl8b8jQNd';
$db = 'ptgates';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT option_name, option_value FROM wp_options WHERE option_name IN ('ptg_portone_store_id', 'ptg_portone_channel_key')";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo $row["option_name"]. ": [" . $row["option_value"] . "]\n";
    }
} else {
    echo "0 results";
}
$conn->close();
?>
