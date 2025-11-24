<?php
/**
 * Plugin Name: PTG KST Logger
 * Description: 모든 로그의 타임스탬프를 한국 시간(KST)으로 설정. WordPress 코어가 UTC로 설정한 후에도 KST 타임스탬프를 사용합니다.
 * Version: 1.0.0
 * Author: PTGates
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 한국 시간(KST)으로 에러 로그 기록
 * 
 * WordPress는 내부적으로 UTC를 사용하지만,
 * 로그 파일에는 한국 시간(KST) 타임스탬프를 사용합니다.
 * 
 * @param string $message 로그 메시지
 * @return void
 */
if (!function_exists('ptg_error_log_kst')) {
    function ptg_error_log_kst($message) {
        // 한국 시간대로 DateTime 객체 생성 (서버는 이미 KST로 설정됨)
        $timezone = new DateTimeZone('Asia/Seoul');
        $datetime = new DateTime('now', $timezone);
        
        // WordPress 로그 형식과 일치: [dd-Mon-YYYY HH:mm:ss T]
        $timestamp = $datetime->format('d-M-Y H:i:s T'); // 예: 23-Nov-2025 08:49:44 KST
        
        // 타임스탬프와 메시지를 함께 기록
        error_log("[{$timestamp}] {$message}");
    }
}

/**
 * 기존 error_log() 호출을 자동으로 KST로 변환
 * 
 * PHP의 error_log()는 date_default_timezone을 사용하므로,
 * WordPress가 UTC로 설정한 상태에서는 UTC 타임스탬프가 기록됩니다.
 * 
 * 이 함수는 WordPress의 UTC 설정과 관계없이 항상 KST를 사용합니다.
 * 
 * @param string $message 로그 메시지
 * @param int $message_type 메시지 타입 (기본: 0, error_log)
 * @param string|null $destination 대상 (기본: null, PHP error_log 설정 사용)
 * @param string|null $extra_headers 추가 헤더 (메일용)
 * @return bool
 */
if (!function_exists('ptg_error_log')) {
    function ptg_error_log($message, $message_type = 0, $destination = null, $extra_headers = null) {
        // 한국 시간 타임스탬프 추가
        $timezone = new DateTimeZone('Asia/Seoul');
        $datetime = new DateTime('now', $timezone);
        $timestamp = $datetime->format('d-M-Y H:i:s T');
        
        // 타임스탬프가 포함된 메시지 생성
        $timestamped_message = "[{$timestamp}] {$message}";
        
        // 원본 error_log 호출 (타임스탬프는 PHP가 자동으로 추가하지 않도록 메시지에 포함)
        return error_log($timestamped_message, $message_type, $destination, $extra_headers);
    }
}

/**
 * muplugins_loaded 훅에서 로드 확인 (선택적)
 * WordPress 코어 로드 직후 실행
 */
add_action('muplugins_loaded', function() {
    // mu-plugin이 정상적으로 로드되었는지 확인 (디버깅용, 나중에 제거 가능)
    // ptg_error_log_kst('[PTG KST Logger] mu-plugin 로드 완료');
}, 1);

