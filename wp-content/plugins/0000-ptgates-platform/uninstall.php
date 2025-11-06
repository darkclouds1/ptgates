<?php
/**
 * 플러그인 언인스톨 처리
 * 
 * 주의: 플랫폼 전용 테이블만 삭제 (기존 3개 테이블 절대 삭제 금지)
 */

// 직접 접근 방지
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 사용자 확인
if (!current_user_can('activate_plugins')) {
    return;
}

// 확인 옵션 (보안을 위해 언인스톨 시에도 확인 필요)
$confirm = isset($_POST['ptg_platform_confirm_uninstall']) && $_POST['ptg_platform_confirm_uninstall'] === '1';

if (!$confirm) {
    // 확인 없이 삭제하지 않음
    return;
}

global $wpdb;

// 플랫폼 전용 테이블 목록 (기존 3개 테이블 제외)
$platform_tables = array(
    'ptgates_exam_sessions',
    'ptgates_exam_session_items',
    'ptgates_user_states',
    'ptgates_user_notes',
    'ptgates_user_drawings',
    'ptgates_review_schedule',
    'ptgates_flashcard_sets',
    'ptgates_flashcards',
    'ptgates_highlights',
    'ptgates_exam_presets',
);

// 테이블 삭제 (CASCADE로 인해 외래키가 있는 테이블도 자동 삭제됨)
foreach ($platform_tables as $table) {
    $table_name = $wpdb->prefix . $table;
    $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
}

// 삭제된 테이블 로그
error_log('PTGates Platform: 플랫폼 전용 테이블 삭제 완료');

