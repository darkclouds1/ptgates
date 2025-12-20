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

// 3. Fetch History to find incorrect IDs
global $wpdb;
$history = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM ptgates_mock_history WHERE history_id = %d AND user_id = %d",
    $history_id,
    get_current_user_id()
));

if (!$history) {
    wp_die('시험 기록을 찾을 수 없습니다.');
}

$answers = json_decode($history->answers_json, true);
$wrong_ids = [];

if (is_array($answers)) {
    foreach ($answers as $ans) {
        if (empty($ans['is_correct']) || $ans['is_correct'] !== true) {
            
            // If subject filtering is needed, we need to verify if this question belongs to the subject.
            // Since we don't have subject map here easily without querying DB, 
            // and the user wants to see WRONG answers for THIS subject,
            // we should technically filter by subject.
            // However, optimization: Just fetch ALL wrong answers, then filter by subject in the Repo query if possible,
            // OR fetch metadata for these IDs.
            
            // Let's rely on Repo's subject filter.
            $wrong_ids[] = intval($ans['question_id']);
        }
    }
}

if (empty($wrong_ids)) {
    wp_die('오답이 없습니다.');
}

// 4. Fetch Questions via LegacyRepo
// We need to load LegacyRepo or use direct SQL. Use Repo if available.
// Assume PTG\Platform\LegacyRepo exists.
if (!class_exists('PTG\Platform\LegacyRepo')) {
    // Fallback or explicit require?
    // It should be loaded by Platform plugin.
    wp_die('플랫폼 플러그인이 활성화되지 않았습니다.');
}

$repo_args = [
    'limit' => -1, // No limit
    'subject' => $subject_name, // Filter by Subject
    'include_ids' => $wrong_ids // Filter by Wrong IDs
];

$repo = new \PTG\Platform\LegacyRepo();
$result = $repo->get_questions_with_categories($repo_args);
$questions = $result['questions'];

// HTML Render
get_header(); // Use site header or minimal? Site header is safer for styles.
?>

<div class="ptg-mock-review-container">
    <div class="ptg-review-header">
        <h1>오답 노트: <?php echo esc_html($subject_name); ?></h1>
        <p><?php echo count($questions); ?> 문제를 복습합니다.</p>
    </div>

    <?php if (empty($questions)): ?>
        <p class="ptg-no-data">해당 과목의 오답이 없거나 데이터를 불러올 수 없습니다.</p>
    <?php else: ?>
        <div class="ptg-review-list">
            <?php foreach ($questions as $idx => $q): ?>
                <div class="ptg-review-item">
                    <div class="ptg-review-q-header">
                        <span class="ptg-review-idx">문제 <?php echo $idx + 1; ?></span>
                        <span class="ptg-review-id">ID: <?php echo $q['id']; ?></span>
                    </div>
                    
                    <div class="ptg-review-content">
                        <?php echo wp_kses_post($q['content']); ?>
                        
                        <?php if (!empty($q['question_image'])): ?>
                             <?php 
                             // Image Path Construction (Assuming specific structure)
                             $cat_year = isset($q['category']['year']) ? $q['category']['year'] : '';
                             $cat_sess = isset($q['category']['session']) ? $q['category']['session'] : '';
                             if ($cat_year && $cat_sess) {
                                 $img_url = "/wp-content/uploads/ptgates-questions/$cat_year/$cat_sess/" . $q['question_image'];
                                 echo '<img src="' . esc_url($img_url) . '" class="ptg-q-image">';
                             }
                             ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($q['options'])): ?>
                        <ul class="ptg-review-options">
                            <?php foreach ($q['options'] as $oidx => $opt): ?>
                                <li class="ptg-review-option <?php echo ($oidx + 1 == $q['answer']) ? 'is-correct' : ''; ?>">
                                    <span class="ptg-opt-num"><?php echo $oidx + 1; ?></span>
                                    <?php echo esc_html($opt); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div class="ptg-review-explanation">
                        <h4>해설</h4>
                        <div class="ptg-expl-text">
                            <?php echo wp_kses_post($q['explanation']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .ptg-mock-review-container {
        max_width: 800px;
        margin: 40px auto;
        padding: 0 20px;
        font-family: 'Pretendard', sans-serif;
    }
    .ptg-review-header {
        text-align: center;
        margin-bottom: 40px;
    }
    .ptg-review-header h1 {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 10px;
    }
    .ptg-review-item {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }
    .ptg-review-q-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 16px;
        color: #64748b;
        font-size: 14px;
    }
    .ptg-review-idx {
        font-weight: 600;
        color: #3b82f6;
    }
    .ptg-review-content {
        font-size: 16px;
        line-height: 1.6;
        color: #1e293b;
        margin-bottom: 20px;
    }
    .ptg-q-image {
        max-width: 100%;
        height: auto;
        margin-top: 10px;
        border-radius: 8px;
    }
    .ptg-review-options {
        list-style: none;
        padding: 0;
        margin: 0 0 20px 0;
    }
    .ptg-review-option {
        padding: 8px 12px;
        margin-bottom: 4px;
        border-radius: 6px;
        background: #f8fafc;
        display: flex;
        gap: 10px;
    }
    .ptg-review-option.is-correct {
        background: #dbeafe;
        color: #1e40af;
        font-weight: 600;
    }
    .ptg-opt-num {
        display: inline-block;
        width: 20px;
        text-align: center;
        font-weight: bold;
    }
    .ptg-review-explanation {
        background: #f1f5f9;
        padding: 16px;
        border-radius: 8px;
    }
    .ptg-review-explanation h4 {
        margin: 0 0 8px 0;
        font-size: 14px;
        color: #475569;
    }
    .ptg-expl-text {
        font-size: 14px;
        color: #334155;
        line-height: 1.6;
    }
</style>

<?php get_footer(); ?>
