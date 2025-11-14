<?php
/**
 * Plugin Name: PTGates Study
 * Plugin URI: https://ptgates.com
 * Description: PTGates 학습 모듈 - 과목/단원 브라우징, 이론 콘텐츠 제공.
 * Version: 0.1.0
 * Author: PTGates
 * Author URI: https://ptgates.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ptgates-study
 * Requires Plugins: 0000-ptgates-platform
 * Requires PHP: 8.1
 * Requires at least: 6.0
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 메인 파일 경로 상수 정의
define('PTG_STUDY_MAIN_FILE', __FILE__);

/**
 * 의존성 플러그인(0000-ptgates-platform) 활성화 확인
 */
function ptg_study_check_dependencies() {
    // 필요한 플러그인 목록
    $required_plugins = [
        '0000-ptgates-platform/ptgates-platform.php' => 'PTGates Platform',
    ];

    // 현재 활성화된 플러그인 목록 가져오기
    $active_plugins = get_option('active_plugins');
    
    foreach ($required_plugins as $plugin_path => $plugin_name) {
        if (!in_array($plugin_path, $active_plugins)) {
            // 플러그인 비활성화
            deactivate_plugins(plugin_basename(__FILE__));
            
            // 관리자에게 알림
            add_action('admin_notices', function() use ($plugin_name) {
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo '<strong>PTGates Study</strong> 플러그인이 비활성화되었습니다. ';
                echo '필수 플러그인인 <strong>' . esc_html($plugin_name) . '</strong>을 먼저 활성화해주세요.';
                echo '</p></div>';
            });
            
            // 다른 플러그인이 비활성화 알림을 표시하도록 허용
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
            return false;
        }
    }
    return true;
}

/**
 * 플러그인 로드 시 의존성 확인
 */
function ptg_study_init() {
    // PTG Platform 플러그인이 활성화되어 있는지 확인
    if (!function_exists('ptg_platform_is_active') || !ptg_platform_is_active()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>PTGates Study</strong> 플러그인을 사용하려면 <strong>PTGates Platform</strong> 플러그인을 먼저 활성화해야 합니다.</p></div>';
        });
        
        // study 플러그인 비활성화
        deactivate_plugins(plugin_basename(PTG_STUDY_MAIN_FILE));

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
        return;
    }

    // 의존성이 충족되었을 때만 플러그인 메인 클래스 로드
    require_once plugin_dir_path(__FILE__) . 'class-ptg-study-plugin.php';
    PTG_Study_Plugin::get_instance();
}
add_action('plugins_loaded', 'ptg_study_init');


