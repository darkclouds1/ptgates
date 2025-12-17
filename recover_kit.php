<?php
// Include WP Logic
require_once('wp-load.php');

// if (!current_user_can('administrator')) {
//    die('Access Denied');
// }

global $wpdb;

echo "<h2>Elementor Kit Status</h2>";

$active_kit_id = get_option('elementor_active_kit');
echo "Active Kit Option Value: <strong>" . var_export($active_kit_id, true) . "</strong><br>";

if ($active_kit_id) {
    $kit = get_post($active_kit_id);
    if ($kit) {
        echo "Active Kit Post Found: {$kit->post_title} (ID: {$kit->ID})<br>";
        echo "Status: <strong>{$kit->post_status}</strong><br>";
        
        if ($kit->post_status === 'trash') {
            echo "<p style='color:red'>Active Kit is in TRASH!</p>";
            // Auto Restore
            wp_untrash_post($active_kit_id);
            echo "<p style='color:green'>Attempted to restore Kit from Trash.</p>";
        }
    } else {
        echo "<p style='color:red'>Active Kit ID exists in options but Post is MISSING from database!</p>";
    }
} else {
    echo "<p style='color:red'>No Active Kit ID configured in options.</p>";
}

// Check ALL Elementor Library Posts
echo "<h3>All Elementor Library Posts (Publish/Draft/Private)</h3>";
$all_libs = $wpdb->get_results("SELECT ID, post_title, post_status, post_type, post_author FROM {$wpdb->posts} WHERE post_type = 'elementor_library' OR post_type = 'elementor-hf'");

if ($all_libs) {
    echo "<ul>";
    foreach ($all_libs as $p) {
        echo "<li>[{$p->ID}] <strong>{$p->post_title}</strong> (Status: {$p->post_status}, Type: {$p->post_type}, Author: {$p->post_author})</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>No Elementor Library posts found (other than Kit?).</p>";
}

// 1. Deep Inspect Kit CSS File
echo "<h3>1. Kit CSS File Inspection</h3>";
$active_kit_id = get_option('elementor_active_kit');
$upload_dir = wp_upload_dir();
$elementor_css_dir = $upload_dir['basedir'] . '/elementor/css';
$kit_css_path = $elementor_css_dir . '/post-' . $active_kit_id . '.css';

echo "Expected Kit CSS Path: " . $kit_css_path . "<br>";
if (file_exists($kit_css_path)) {
    echo "File Exists. Size: " . filesize($kit_css_path) . " bytes.<br>";
    echo "Last Modified: " . date("Y-m-d H:i:s", filemtime($kit_css_path)) . "<br>";
    // Force Delete to ensure regeneration
    unlink($kit_css_path);
    echo "<span style='color:orange'>[Action] Deleted physical CSS file to force regeneration.</span><br>";
} else {
    echo "<span style='color:red'>File DOES NOT exist!</span><br>";
}

// 2. Clear Elementor Cache (Hard)
echo "<h3>2. Hard Cache Clear</h3>";
delete_option('elementor_files_manager_metadata'); 
if (class_exists('\Elementor\Plugin')) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
    echo "<span style='color:green'>Triggered Elementor Clear Cache.</span><br>";
}

// 3. Inspect Header/Footer Conditions
echo "<h3>3. Header/Footer Conditions check</h3>";
$templates = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'elementor_library' AND post_status = 'publish'");
foreach ($templates as $t) {
    $conditions = get_post_meta($t->ID, '_elementor_conditions', true);
    if ($conditions) {
        echo "[{$t->ID}] {$t->post_title}: Conditions found (" . print_r($conditions, true) . ")<br>";
    } else {
        // echo "[{$t->ID}] {$t->post_title}: No explicit global conditions.<br>";
    }
}

// 4. Force Flush Rewrite Rules (sometimes needed for CSS routes)
flush_rewrite_rules();
echo "<h3>4. Flushed Rewrite Rules</h3>";

// 5. Restore Missing Display Conditions (Header/Footer)
echo "<h3>5. Restoring Display Conditions</h3>";
$targets = [
    168 => 'Header',
    369 => 'Footer'
];

foreach ($targets as $id => $label) {
    $post = get_post($id);
    if ($post && $post->post_status === 'publish') {
        $current = get_post_meta($id, '_elementor_conditions', true);
        if (empty($current)) {
            update_post_meta($id, '_elementor_conditions', ['include/general']);
            echo "<p style='color:green'>Restored 'Entire Site' condition for <strong>{$label} ({$id})</strong>.</p>";
        } else {
            echo "<p>Condition already exists for {$label} ({$id}): " . print_r($current, true) . "</p>";
        }
    }
}

// 6. Restore Deleted Attachments (Media)
echo "<h3>6. Restoring Deleted Media (Attachments)</h3>";
// Check for attachments in trash
$trashed_attachments = $wpdb->get_results("SELECT ID, post_title, post_mime_type FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_type = 'attachment'");

if ($trashed_attachments) {
    echo "Found " . count($trashed_attachments) . " attachments in Trash.<br>";
    echo "<ul>";
    foreach ($trashed_attachments as $att) {
        wp_untrash_post($att->ID);
        // Reassign to Admin
        $arg = array('ID' => $att->ID, 'post_author' => 1);
        wp_update_post($arg);
        echo "<li>Restored & Reassigned Image: <strong>{$att->post_title}</strong> (ID: {$att->ID})</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No Media Attachments found in Trash.</p>";
}

// 7. Debug CSS Generation & Permissions
echo "<h3>7. Debugging CSS Generation & Permissions</h3>";
$upload_dir = wp_upload_dir();
$elementor_css_dir = $upload_dir['basedir'] . '/elementor/css';

echo "CSS Directory: " . $elementor_css_dir . "<br>";
if (is_writable($elementor_css_dir)) {
    echo "<span style='color:green'>Directory is Writable.</span><br>";
} else {
    echo "<span style='color:red'>Directory is NOT Writable! Check Permissions.</span><br>";
}

// 7. Manual Regeneration Required
echo "<h3>7. CSS Regeneration (Manual Step)</h3>";
echo "<p>Automatic regeneration caused an error. Please use the official Elementor Tool.</p>";

$admin_url = admin_url('admin.php?page=elementor-tools');
echo "<div style='background:#f0f0f1; padding:20px; border:1px solid #ccc;'>";
echo "<strong>Next Step:</strong><br>";
echo "1. Go to <a href='{$admin_url}' target='_blank' style='font-size:18px; font-weight:bold;'>Elementor > Tools</a><br>";
echo "2. Click <strong>'Regenerate Files & Data'</strong> (파일 및 데이터 재생성)<br>";
echo "3. Click <strong>'Save Changes'</strong> (변경사항 저장)<br>";
echo "4. Then Reload your Homepage.";
echo "</div>";

// 9. Restore Home Page (Front Page)
echo "<h3>9. Restoring Home Page</h3>";
$front_page_id = get_option('page_on_front');
echo "Configured Front Page ID: " . var_export($front_page_id, true) . "<br>";

if ($front_page_id) {
    $front_post = get_post($front_page_id);
    if ($front_post) {
        echo "Front Page Status: <strong>{$front_post->post_status}</strong><br>";
        if ($front_post->post_status === 'trash') {
            wp_untrash_post($front_page_id);
            // Reassign
            $arg = array('ID' => $front_page_id, 'post_author' => 1);
            wp_update_post($arg);
            echo "<span style='color:green'>SUCCESS: Home Page restored from Trash and reassigned to Admin.</span><br>";
        } else {
             echo "Home Page is active (not trash).<br>";
        }
    } else {
        echo "<span style='color:red'>Front Page ID {$front_page_id} configured, but Post object not found in DB (even in Trash?). checking raw query...</span><br>";
    }
} else {
    echo "No Static Front Page configured (showing latest posts?).<br>";
}

// List ALL Trashed Pages (just in case)
echo "<h4>All Pages in Trash</h4>";
$trashed_pages = $wpdb->get_results("SELECT ID, post_title, post_author FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_type = 'page'");
if ($trashed_pages) {
    echo "<ul>";
    foreach ($trashed_pages as $p) {
        wp_untrash_post($p->ID);
        // Reassign
        $arg = array('ID' => $p->ID, 'post_author' => 1);
        wp_update_post($arg);
        echo "<li>Restored Page: <strong>{$p->post_title}</strong> (ID: {$p->ID})</li>";
    }
    echo "</ul>";
} else {
    echo "No Pages found in Trash.";
}

// 10. Re-link Front Page Settings
echo "<h3>10. Re-linking Front Page Settings</h3>";
$current_front = get_option('page_on_front');
if (empty($current_front) || $current_front == 0) {
    // Try to find "Home" page
    $home_page = get_page_by_title('Home');
    if (!$home_page) {
        // Fallback: Check ID 67 from logs
        $home_page = get_post(67);
    }

    if ($home_page && $home_page->post_type === 'page') {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $home_page->ID);
        echo "<span style='color:green'>SUCCESS: Set Front Page to <strong>{$home_page->post_title}</strong> (ID: {$home_page->ID}).</span><br>";
    } else {
        echo "<span style='color:red'>Could not find a page named 'Home' to set as front page. Please set manually in Settings > Reading.</span><br>";
    }
} else {
    echo "Front Page is already configured (ID: {$current_front}).<br>";
}

// 11. Final Verification & Flush
echo "<h3>11. Final Verification</h3>";
$final_front_id = get_option('page_on_front');
$final_post = get_post($final_front_id);
if ($final_post) {
    echo "Current Front Page: <strong>{$final_post->post_title}</strong> (ID: {$final_post->ID}) - Status: <strong>{$final_post->post_status}</strong><br>";
    if ($final_post->post_status !== 'publish') {
         // Force Publish
         wp_update_post(array('ID' => $final_front_id, 'post_status' => 'publish'));
         echo "<span style='color:green'>Forced status to PUBLISH.</span><br>";
    }
}

flush_rewrite_rules(true); // Hard Flush
echo "<strong style='color:blue'>[Action] Hard Flushed Rewrite Rules.</strong><br>";

// 12. Menu & Permalink Debug
echo "<h3>12. Menu & Permalink Debug</h3>";
// Check Menu Items in Trash
$trashed_menus = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_type = 'nav_menu_item'");
if ($trashed_menus) {
    echo "Found " . count($trashed_menus) . " menu items in Trash. Restoring...<br>";
    echo "<ul>";
    foreach ($trashed_menus as $m) {
        wp_untrash_post($m->ID);
        // Reassign
        wp_update_post(array('ID' => $m->ID, 'post_author' => 1));
        echo "<li>Restored Menu Item ID: {$m->ID}</li>";
    }
    echo "</ul>";
} else {
    echo "No Menu Items found in Trash.<br>";
}

// 13. Fix Permalink Structure (Force %postname%)
echo "<h3>13. Fix Permalink Structure</h3>";
$current_perm = get_option('permalink_structure');
echo "Current Structure: '" . $current_perm . "'<br>";

// 13. Revert to Plain Permalinks (Safe Mode)
echo "<h3>13. Revert to Plain Permalinks (Safe Mode)</h3>";
global $wp_rewrite;
// Force Plain
$wp_rewrite->set_permalink_structure(''); 
update_option('permalink_structure', ''); 
echo "<strong style='color:orange'>[Action] Reverted to PLAIN permalinks (e.g. ?page_id=123) to fix 404s.</strong><br>";

// Flush
$wp_rewrite->flush_rules();
flush_rewrite_rules(true);
echo "Flushed Rewrite Rules.<br>";


// 14. Deep Inspect 'Login' Page (ID 35)
echo "<h3>14. Login Page Inspection</h3>";
$login_page = get_post(35);
if ($login_page) {
    echo "ID: 35 | Title: {$login_page->post_title} | Status: <strong>{$login_page->post_status}</strong> | Type: {$login_page->post_type}<br>";
    echo "GUID: {$login_page->guid}<br>";
    
    if ($login_page->post_status !== 'publish') {
        wp_update_post(array('ID' => 35, 'post_status' => 'publish'));
        echo "<span style='color:green'>Forced Login Page to PUBLISH.</span><br>";
    }
} else {
    echo "<span style='color:red'>Page ID 35 NOT FOUND in DB.</span><br>";
}

// 15. Inspect Page Content (Page 35)
echo "<h3>15. Login Page Content Debug</h3>";
$page_id = 35;
$p = get_post($page_id);
if ($p) {
    echo "<strong>Raw post_content:</strong><br>";
    echo "<textarea style='width:100%;height:100px'>" . esc_textarea($p->post_content) . "</textarea><br>";
    
    $elem_data = get_post_meta($page_id, '_elementor_data', true);
    if ($elem_data) {
        echo "<strong>Elementor Data Found:</strong> Yes (Length: " . strlen($elem_data) . ")<br>";
    } else {
        echo "<strong style='color:red'>Elementor Data MISSING.</strong><br>";
    }

    // Check Revisions
    $revisions = wp_get_post_revisions($page_id);
    if ($revisions) {
        echo "Found " . count($revisions) . " revisions.<br>";
        $latest_rev = array_values($revisions)[0];
        echo "Latest Revision ID: {$latest_rev->ID} (Author: {$latest_rev->post_author})<br>";
        
        // Restore Latest Revision if current is empty
        if (empty($p->post_content) && empty($elem_data)) {
            wp_restore_post_revision($latest_rev->ID);
            echo "<span style='color:green'>[Action] Auto-Restored content from Revision {$latest_rev->ID}.</span><br>";
        }
    } else {
        echo "No revisions found.<br>";
    }
    
    // Default Ultimate Member Login Shortcode Fallback
    if (empty($p->post_content) && empty($elem_data)) {
        // Ultimate Member default login form usually ID comes from UM settings.
        // But let's try injecting a simple shortcode if completely empty
        // $um_login_id = get_option('um_login_form'); 
        // if($um_login_id) wp_update_post(['ID'=>$page_id, 'post_content'=>"[ultimatemember form_id=\"{$um_login_id}\"]"]);
    }

}

// 16. Inspect Ultimate Member Form (ID 31)
echo "<h3>16. Ultimate Member Form Debug</h3>";
$form_id = 31; // Extracted from shortcode
$form_post = get_post($form_id);

if ($form_post) {
    echo "Form ID 31 Exists. Status: <strong>{$form_post->post_status}</strong><br>";
    if ($form_post->post_status === 'trash') {
        wp_untrash_post($form_id);
        wp_update_post(['ID' => $form_id, 'post_author' => 1]);
        echo "<span style='color:green'>Success: Restored UM Form 31 from Trash.</span><br>";
    }
} else {
    echo "<span style='color:red'>Form ID 31 NOT FOUND in DB. Checking Trash for ANY um_form...</span><br>";
}

// Restore ALL Trashed UM Forms
$trashed_forms = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_type = 'um_form'");
if ($trashed_forms) {
    echo "Found " . count($trashed_forms) . " UM Forms in Trash.<br>";
    echo "<ul>";
    foreach ($trashed_forms as $f) {
        wp_untrash_post($f->ID);
        wp_update_post(['ID' => $f->ID, 'post_author' => 1]);
        echo "<li>Restored Form: <strong>{$f->post_title}</strong> (ID: {$f->ID})</li>";
    }
    echo "</ul>";
} else {
    echo "No UM Forms found in Trash.<br>";
}

// 17. Sanitize Slugs (Remove __trashed suffix)
echo "<h3>17. Sanitize Restored Slugs</h3>";
$restored_posts = $wpdb->get_results("SELECT ID, post_name, post_title, post_type FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_name LIKE '%__trashed%'");

if ($restored_posts) {
    echo "Found " . count($restored_posts) . " posts with '__trashed' suffix. Fixing...<br>";
    echo "<ul>";
    foreach ($restored_posts as $rp) {
        $clean_slug = str_replace('__trashed', '', $rp->post_name);
        $clean_slug = preg_replace('/-\d+$/', '', $clean_slug); // Remove suffix numbers if any
        
        wp_update_post(array(
            'ID' => $rp->ID,
            'post_name' => $clean_slug
        ));
        echo "<li>Fixed Slug for <strong>{$rp->post_title}</strong>: {$rp->post_name} -> {$clean_slug}</li>";
    }
    echo "</ul>";
    // Flush after slug changes
    flush_rewrite_rules(true);
} else {
    echo "No corrupted slugs found.<br>";
}

// 18. Verify Menu Links Table
echo "<h3>18. Diagnostic Menu Link Test</h3>";
echo "Please click these links to test availability:<br>";
$menu_pages = [
    'Home' => 67, 
    'Services' => 110, 
    '과목|Study' => 270, 
    '실전|quiz' => 299, 
    '로그인' => 35
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Page Name</th><th>ID</th><th>Status</th><th>Permlink (Click to Test)</th></tr>";
foreach ($menu_pages as $name => $id) {
    $p = get_post($id);
    if ($p) {
        $url = get_permalink($id);
        echo "<tr>";
        echo "<td>{$name}</td>";
        echo "<td>{$id}</td>";
        echo "<td>{$p->post_status}</td>";
        echo "<td><a href='{$url}' target='_blank'>{$url}</a></td>";
        echo "</tr>";
    } else {
         echo "<tr><td>{$name}</td><td>{$id}</td><td colspan='2' style='color:red'>Not Found</td></tr>";
    }
}
echo "</table>";

// 19. Force Publish Recovered Pages (Draft -> Publish)
echo "<h3>19. Force Publish Recovered Pages</h3>";
$draft_ids = [110, 270, 299, 435, 437, 450, 508, 510, 615, 639, 644, 673, 685, 729]; // IDs originating from previous restore logs

foreach ($draft_ids as $did) {
    $dpost = get_post($did);
    if ($dpost && $dpost->post_status === 'draft') {
        wp_update_post(array('ID' => $did, 'post_status' => 'publish'));
        echo "<li>Forced <strong>{$dpost->post_title}</strong> (ID: {$did}) to PUBLISH status.</li>";
    }
}

// Re-check Menu Links
echo "<h4>Re-checking Menu Links Status</h4>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Page Name</th><th>ID</th><th>Status</th><th>Permlink (Click to Test)</th></tr>";
foreach ($menu_pages as $name => $id) {
    $p = get_post($id);
    if ($p) {
        $url = get_permalink($id);
        echo "<tr>";
        echo "<td>{$name}</td>";
        echo "<td>{$id}</td>";
        echo "<td>{$p->post_status}</td>";
        echo "<td><a href='{$url}' target='_blank'>{$url}</a></td>";
        echo "</tr>";
    }
}
echo "</table>";

echo "<h2>Done. Please Reload your Homepage now.</h2>";


