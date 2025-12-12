<?php
define('WP_USE_THEMES', false);
// Load WordPress environment
require_once 'e:\proj\ptgates.com\wp-load.php';

if (!current_user_can('manage_options')) {
    $user_id = 1; // Assume admin ID 1
    wp_set_current_user($user_id);
}

// Ensure the class is loaded
require_once 'e:\proj\ptgates.com\wp-content\plugins\6000-ptgates-admin\includes\class-api.php';

// Call the method
try {
    $response = \PTG\Admin\API::get_raw_subjects();
    echo "Response:\n";
    print_r($response);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
