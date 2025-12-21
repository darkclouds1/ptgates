<?php
/**
 * Template Name: Mock Exam Review
 * Used for secure review of incorrect answers.
 */

if (!defined('ABSPATH')) exit;

$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    wp_die('잘못된 접근입니다.');
}

// [FIX] URL Decode robustness: Space to +
$token = str_replace(' ', '+', $token);

// 1. Decrypt Token
// [FIX] Use SHA-256 to match backend key
$key = hash('sha256', wp_salt('auth'), true);
$data = base64_decode($token);
$iv = substr($data, 0, 16);
$ciphertext = substr($data, 16);

$json = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, 0, $iv);
$payload = json_decode($json, true);

if (!$payload || !isset($payload['hid']) || !isset($payload['uid'])) {
    // Debug info
    $err = openssl_error_string();
    $key_check = substr(bin2hex($key), 0, 8); // Check correct key usage
    $iv_len = strlen($iv);
    $data_len = strlen($data);
    $cipher_len = strlen($ciphertext);
    $decrypted_raw = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, 0, $iv);
    
    $msg = "유효하지 않은 토큰입니다.<br>";
    $msg .= "Error: $err<br>";
    $msg .= "KeyCheck: $key_check<br>";
    $msg .= "DataLen: $data_len, IVLen: $iv_len, CipherLen: $cipher_len<br>";
    $msg .= "Decrypted: " . ($decrypted_raw === false ? "FALSE" : substr($decrypted_raw, 0, 50));
    
    wp_die($msg);
}

// 2. Validate
if ($payload['uid'] != get_current_user_id()) {
    wp_die('권한이 없습니다.');
}

if (isset($payload['exp']) && $payload['exp'] < time()) {
    wp_die('만료된 링크입니다.');
}

$history_id = intval($payload['hid']);
$subject_name = isset($payload['sub']) ? $payload['sub'] : '';

// 3. Setup UI for Study Mode
// (Redundant History DB Query removed - we rely on the API to fetch data via study.js)

// 2. Enqueue Assets manually
$plugin_instance = \PTG_Study_Plugin::get_instance();
$plugin_dir_url = plugin_dir_url(PTG_STUDY_MAIN_FILE);

// Enqueue Platform CSS (for Dashboard UI)
$platform_css_url = plugins_url('0000-ptgates-platform/assets/css/platform.css');
$platform_css_path = WP_PLUGIN_DIR . '/0000-ptgates-platform/assets/css/platform.css';
if (file_exists($platform_css_path)) {
    wp_enqueue_style(
        'ptg-platform-style',
        $platform_css_url,
        [],
        filemtime($platform_css_path)
    );
}

// Enqueue CSS immediately
wp_enqueue_style(
    'ptg-study-style',
    $plugin_dir_url . 'assets/css/study.css',
    ['ptg-platform-style'], // Dependent on platform style
    filemtime(plugin_dir_path(PTG_STUDY_MAIN_FILE) . 'assets/css/study.css')
);

// HTML Render
get_header(); 

// 3. Render the Standard Study App Container
// 3. Render the Standard Study App Container
echo $plugin_instance->render_study_shortcode([]);

// 4. Initialize the App with Review Data via JS Injection
?>
<script type="text/javascript">
(function() {
    // Construct the review mode URL parameters for study.js
    var targetSubject = '<?php echo esc_js($subject_name); ?>';
    var mockExamId = '<?php echo esc_js($history_id); ?>'; 
    
    // Use replaceState to simulate these parameters being present from the start
    var newUrl = new URL(window.location);
    newUrl.searchParams.set('subject', targetSubject);
    newUrl.searchParams.set('mock_exam_id', mockExamId);
    newUrl.searchParams.set('wrong_only', '1'); 
    newUrl.searchParams.set('random', '0'); // [FIX] Disable random shuffle to keep exam order
    newUrl.searchParams.set('infinite_scroll', '1'); // [FIX] Enable infinite scroll explicitly
    
    // Update URL without reloading
    window.history.replaceState({path: newUrl.href}, '', newUrl.href);
})();
</script>

<style>
    /* [FIX] Fix Header Right Checkbox Fonts */
    /* [FIX] Fix Header Right Checkbox Fonts */
    .ptg-study-header-right label,
    .ptg-controls-wrapper label {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        margin-right: 15px;
        color: #4a5568;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    
    /* [FIX] Style "Back to Course List" Button */
    /* Only target the back button, do not touch global header buttons */
    #back-to-courses,
    .ptg-study-dashboard-link {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        background: #edf2f7 !important;
        color: #4a5568 !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        padding: 8px 16px !important;
        border-radius: 6px !important;
        text-decoration: none !important;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
    }
    #back-to-courses:hover,
    .ptg-study-dashboard-link:hover {
        background: #e2e8f0 !important;
        color: #2d3748 !important;
        text-decoration: none !important;
    }
</style>
<!-- [FIX] Inject Critical Dashboard Styles (extracted from Plugin Class) -->
<style>
    /* Card Styles (Important Overrides) */
    .ptg-category[data-category-id="ptg-foundation"] .ptg-category-header {
        background: linear-gradient(180deg, #ecfeff 0%, #f0fdf4 100%) !important;
        border-bottom-color: #dcfce7 !important;
    }
    .ptg-category[data-category-id="ptg-foundation"] .ptg-session-badge {
        color: #064e3b !important;
        background: #d1fae5 !important;
        border-color: #10b981 !important;
    }
    .ptg-category[data-category-id="ptg-assessment"] .ptg-category-header {
        background: linear-gradient(180deg, #eff6ff 0%, #e0f2fe 100%) !important;
        border-bottom-color: #dbeafe !important;
    }
    .ptg-category[data-category-id="ptg-assessment"] .ptg-session-badge {
        color: #1e3a8a !important;
        background: #dbeafe !important;
        border-color: #60a5fa !important;
    }
    .ptg-category[data-category-id="ptg-intervention"] .ptg-category-header {
        background: linear-gradient(180deg, #f5f3ff 0%, #eef2ff 100%) !important;
        border-bottom-color: #e9d5ff !important;
    }
    .ptg-category[data-category-id="ptg-intervention"] .ptg-session-badge {
        color: #3730a3 !important;
        background: #e0e7ff !important;
        border-color: #818cf8 !important;
    }
    .ptg-category[data-category-id="ptg-medlaw"] .ptg-category-header {
        background: linear-gradient(180deg, #fffbeb 0%, #fef2f2 100%) !important;
        border-bottom-color: #fde68a !important;
    }
    .ptg-category[data-category-id="ptg-medlaw"] .ptg-session-badge {
        color: #7c2d12 !important;
        background: #fef3c7 !important;
        border-color: #f59e0b !important;
    }
    .ptg-session-badge {
        display: inline-block !important;
        padding: 2px 8px !important;
        margin-right: 8px !important;
        font-size: 12px !important;
        line-height: 1.4 !important;
        border-radius: 9999px !important;
        vertical-align: middle !important;
        background: #f3f4f6; /* Fallback */
        border: 1px solid #d1d5db;
    }
    .ptg-category {
        border-radius: 12px !important;
        overflow: hidden !important;
        box-shadow: 0 2px 8px rgba(15,23,42,0.04) !important;
        background: #fff;
        border: 1px solid #e5e7eb;
    }
    .ptg-category-header {
        padding: 14px 16px 8px 16px !important;
        border-bottom: 1px solid #f1f5f9;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%) !important;
    }
    /* [FIX] Layout Logic (Grid & Flex) */
    .ptg-course-categories {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
        gap: 20px !important;
    }
    .ptg-session-group {
        grid-column: 1 / -1 !important;
        padding: 0 !important;
        border-top: none !important;
    }
    .ptg-session-grid {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
        gap: 20px !important;
    }
    .ptg-category-title {
        margin: 0 0 6px 0 !important;
        font-size: 16px !important;
        font-weight: 700 !important;
        color: #0f172a !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
    }
    .ptg-course-categories {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
        gap: 20px !important;
    }
    .ptg-subject-list {
        display: grid !important;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)) !important;
        gap: 6px !important;
        padding: 12px 12px 14px 12px !important;
        margin: 0 !important;
    }
    .ptg-subject-item {
        border: 1px solid #e5e7eb !important;
        border-radius: 8px !important;
        background: #f8fafc !important;
        padding: 8px 12px !important;
    }
    .ptg-subject-item:hover {
        background: #eef2ff !important;
        border-color: #c7d2fe !important;
    }
    /* [FIX] Header Card Style */
    .ptg-study-header {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        margin-bottom: 18px !important;
        padding: 12px 14px !important;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 12px !important;
        box-shadow: 0 4px 12px rgba(15,23,42,0.06) !important;
    }
    .ptg-study-header-right {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        flex-shrink: 0 !important;
    }
    .ptg-study-header h2 {
        margin: 0 !important;
        font-size: 18px !important;
        font-weight: 700 !important;
        color: #0f172a !important;
        letter-spacing: -0.01em !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
    }
</style>

<?php
get_footer();
?>
