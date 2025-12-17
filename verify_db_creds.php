<?php
/**
 * Verify DB Credentials
 * Run via: https://ptgates.com/verify_db_creds.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>DB Credential Test</h1>";

$hosts = ['localhost', '127.0.0.1'];
$user = 'ptgates';
$db = 'ptgates';
$raw_pass = 'PBrZmtfoJl8b8jQNd+1LaI6dc+rye2Z/ES7omOIRrpM=';
$decoded_pass = base64_decode($raw_pass);

echo "Testing Raw Password: " . $raw_pass . "<br>";
// echo "Testing Decoded Password: " . bin2hex($decoded_pass) . " (HEX)<br>";

function try_connect($host, $u, $p, $dbname) {
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $u, $p, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        return "SUCCESS";
    } catch (PDOException $e) {
        return "FAILED: " . $e->getMessage();
    }
}


$users_to_try = [
    ['ptgates', $raw_pass],
    ['ptgates', $decoded_pass],
    ['root', 'root'],
    ['root', ''],
];

$hosts_ports = [
    '127.0.0.1', 
    '127.0.0.1:3307', 
    'localhost'
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Host</th><th>User</th><th>Pass Length</th><th>Result</th></tr>";

foreach ($hosts_ports as $host) {
    foreach ($users_to_try as $pair) {
        $u = $pair[0];
        $p = $pair[1];
        
        $res = try_connect($host, $u, $p, $db);
        $color = strpos($res, 'SUCCESS') !== false ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td>$host</td>";
        echo "<td>$u</td>";
        echo "<td>" . strlen($p) . "</td>";
        echo "<td style='color:$color'>$res</td>";
        echo "</tr>";
    }
}
echo "</table>";

