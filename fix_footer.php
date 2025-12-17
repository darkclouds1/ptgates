<?php
/**
 * Fix Footer Script (Auto-Config Version)
 * Run this via Browser: https://ptgates.com/fix_footer.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Elementor Footer Repair (Auto-Config)</h1>";

// 1. Build Creds by parsing wp-config.php
$config_file = __DIR__ . '/wp-config.php';
$content = file_get_contents($config_file);

// Extract Constants
function get_const($name, $content) {
    if (preg_match("/define\(\s*['\"]" . preg_quote($name, '/') . "['\"]\s*,\s*['\"](.*?)['\"]\s*\);/", $content, $m)) {
        return $m[1];
    }
    return null;
}

$db_name = get_const('DB_NAME', $content);
$db_user = get_const('DB_USER', $content);
$db_pass = get_const('DB_PASSWORD', $content);
$db_host = get_const('DB_HOST', $content);

echo "Parsed Config: Host=$db_host | User=$db_user | DB=$db_name<br>";

// 2. Try Connect
$pdo = null;
$hosts_to_try = [$db_host, '127.0.0.1', 'localhost'];
$errors = [];

foreach ($hosts_to_try as $h) {
    if (empty($h)) continue;
    try {
        echo "Attempting connection to <strong>$h</strong>... ";
        $dsn = "mysql:host=$h;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        echo "<span style='color:green'>Success!</span><br>";
        break; // Stop if connected
    } catch (PDOException $e) {
        echo "<span style='color:red'>Failed.</span><br>";
        $errors[] = "$h: " . $e->getMessage();
    }
}

if (!$pdo) {
    die("<h3>All Connection Attempts Failed</h3>" . implode('<br>', $errors));
}

// 3. Execute Fix
$footer_id = 369;
echo "<hr>Checking Footer ID $footer_id...<br>";

try {
    // Check Post
    $stmt = $pdo->prepare("SELECT ID, post_status, post_author, post_content FROM wp_posts WHERE ID = ?");
    $stmt->execute([$footer_id]);
    $row = $stmt->fetch();

    if ($row) {
        $status = $row['post_status'];
        $len = strlen($row['post_content']);
        $current_author = $row['post_author'];
        
        // Find Proper Admin ID
        $admin_id = 1; // Default fallback
        $user_sql = "SELECT user_id FROM wp_usermeta WHERE meta_key = 'wp_capabilities' AND meta_value LIKE '%administrator%' LIMIT 1";
        $u_stmt = $pdo->query($user_sql);
        if ($u_row = $u_stmt->fetch()) {
            $admin_id = $u_row['user_id'];
            echo "Found Valid Admin ID: <strong>$admin_id</strong><br>";
        } else {
             // Fallback: Get ANY user
             $any = $pdo->query("SELECT ID FROM wp_users LIMIT 1")->fetch();
             if ($any) {
                 $admin_id = $any['ID'];
                 echo "No Admin found via meta, using User ID: $admin_id<br>";
             }
        }

        echo "Status: <strong>$status</strong> | Author: $current_author | Size: $len bytes<br>";

        // Fix Status/Author
        if ($status !== 'publish' || $current_author != $admin_id) {
            $pdo->prepare("UPDATE wp_posts SET post_status='publish', post_author=? WHERE ID=?")->execute([$admin_id, $footer_id]);
            echo " -> Fixed Status/Author (Assigned to User $admin_id).<br>";
        }

        // Check _elementor_data (Critical for Editor)
        $data_stmt = $pdo->prepare("SELECT meta_value FROM wp_postmeta WHERE post_id=? AND meta_key='_elementor_data'");
        $data_stmt->execute([$footer_id]);
        $data_row = $data_stmt->fetch();
        $has_data = $data_row && !empty($data_row['meta_value']);
        
        echo "Elementor Data: " . ($has_data ? "Found" : "<strong style='color:red'>MISSING</strong>") . "<br>";

        // Restore Content & Data if either is missing
        if ($len < 100 || !$has_data) {
            echo "Corruption detected (Content or Data missing). Searching revisions...<br>";
            
            // Find revision that has both content and data
            $rev_sql = "
                SELECT p.ID, p.post_content, m.meta_value as elem_data 
                FROM wp_posts p 
                LEFT JOIN wp_postmeta m ON (p.ID = m.post_id AND m.meta_key = '_elementor_data')
                WHERE p.post_parent = ? 
                AND p.post_type = 'revision' 
                AND LENGTH(p.post_content) > 100
                AND m.meta_value IS NOT NULL
                ORDER BY p.ID DESC LIMIT 1
            ";
            
            $rev_stmt = $pdo->prepare($rev_sql);
            $rev_stmt->execute([$footer_id]);
            $rev = $rev_stmt->fetch();
            
            if ($rev) {
                 // Restore Content
                 $pdo->prepare("UPDATE wp_posts SET post_content=? WHERE ID=?")->execute([$rev['post_content'], $footer_id]);
                 
                 // Restore Data
                 $pdo->prepare("DELETE FROM wp_postmeta WHERE post_id=? AND meta_key='_elementor_data'")->execute([$footer_id]);
                 $pdo->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_elementor_data', ?)")->execute([$footer_id, $rev['elem_data']]);
                 
                 echo " -> <span style='color:green'>Restored Content AND Data from Revision {$rev['ID']}.</span><br>";
            } else {
                echo " -> <span style='color:red'>No valid revisions (with data) found.</span><br>";
            }
        }

        // Fix Conditions Meta
        $meta_stmt = $pdo->prepare("SELECT meta_id FROM wp_postmeta WHERE post_id=? AND meta_key='_elementor_conditions'");
        $meta_stmt->execute([$footer_id]);
        if (!$meta_stmt->fetch()) {
            // Serialized: ['include/general']
            $val = 'a:1:{i:0;s:15:"include/general";}'; 
            $pdo->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_elementor_conditions', ?)")->execute([$footer_id, $val]);
             echo " -> <span style='color:green'>Added Display Conditions.</span><br>";
        } else {
             echo " -> Display Conditions OK.<br>";
        }

    } else {
        echo "<span style='color:red'>Footer ID $footer_id Not Found in table.</span>";
    }

} catch (PDOException $e) {
    die("DB Error during fix: " . $e->getMessage());
}

echo "<h2>DONE. Check Footer on site.</h2>";
