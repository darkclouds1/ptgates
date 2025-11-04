<?php
/**
 * Plugin Name: PTGates Exam Importer
 * Plugin URI: https://ptgates.com
 * Description: 문제은행 DB 일괄 삽입 및 TXT 파일에서 CSV 생성 기능을 제공하는 플러그인
 * Version: 1.0.0
 * Author: PTGates
 * Author URI: https://ptgates.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ptgates-exam-importer
 * Domain Path: /languages
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('PTGATES_EXAM_IMPORTER_VERSION', '1.0.0');
define('PTGATES_EXAM_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PTGATES_EXAM_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 플러그인 클래스
 */
class PTGates_Exam_Importer {
    
    /**
     * 싱글톤 인스턴스
     */
    private static $instance = null;
    
    /**
     * 싱글톤 인스턴스 반환
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 생성자
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_requests'));
        
        // 필요한 파일 포함
        require_once PTGATES_EXAM_IMPORTER_PLUGIN_DIR . 'includes/functions.php';
    }
    
    /**
     * 관리자 메뉴 추가
     */
    public function add_admin_menu() {
        // 메인 메뉴 추가
        add_menu_page(
            '문제은행 DB 일괄 삽입',              // 페이지 제목
            '문제은행 DB',                        // 메뉴 이름
            'manage_options',                     // 권한 (관리자)
            'ptgates-exam-importer',             // 메뉴 슬러그
            array($this, 'render_admin_page'),   // 콜백 함수
            'dashicons-database',                // 아이콘 (WordPress 기본 아이콘)
            30                                   // 메뉴 위치 (30 = 하단)
        );
        
        // 서브메뉴 추가 (메인 메뉴와 동일한 페이지)
        add_submenu_page(
            'ptgates-exam-importer',             // 부모 메뉴 슬러그
            '문제은행 DB 일괄 삽입',              // 페이지 제목
            'DB 삽입',                            // 서브메뉴 이름
            'manage_options',                     // 권한
            'ptgates-exam-importer',              // 메뉴 슬러그
            array($this, 'render_admin_page')    // 콜백 함수
        );
    }
    
    /**
     * 관리자 요청 처리
     */
    public function handle_admin_requests() {
        // AJAX 요청만 처리 (일반 페이지 요청은 render_admin_page에서 처리)
        if (isset($_GET['page']) && $_GET['page'] === 'ptgates-exam-importer') {
            // POST 요청 처리
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
                $this->handle_post_request();
            }
        }
    }
    
    /**
     * POST 요청 처리
     */
    private function handle_post_request() {
        global $wpdb;
        
        if ($_POST['action'] === 'download_file') {
            // 파일 다운로드 처리
            $file_path = PTGATES_EXAM_IMPORTER_PLUGIN_DIR . 'exam_data.csv';
            
            if (file_exists($file_path)) {
                $original_name = 'exam_data.csv';
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $original_name . '"');
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode(array('success' => false, 'message' => '파일을 찾을 수 없습니다.'));
                exit;
            }
        }
        
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_POST['action'] === 'import_csv') {
            ptgates_process_csv_import($wpdb);
        } else if ($_POST['action'] === 'generate_csv_from_txt') {
            ptgates_generate_csv_from_txt();
        } else {
            echo json_encode(array('success' => false, 'message' => '알 수 없는 작업입니다.'));
        }
        exit;
    }
    
    /**
     * 관리자 페이지 렌더링
     */
    public function render_admin_page() {
        global $wpdb;
        
        // 세션 시작
        if (!session_id()) {
            session_start();
        }
        
        // 웹 인터페이스 렌더링
        include_once PTGATES_EXAM_IMPORTER_PLUGIN_DIR . 'includes/import-interface.php';
    }
}

/**
 * 플러그인 활성화
 */
function ptgates_exam_importer_activate() {
    // 활성화 시 필요한 작업
}

/**
 * 플러그인 비활성화
 */
function ptgates_exam_importer_deactivate() {
    // 비활성화 시 필요한 작업
}

// 활성화/비활성화 훅 등록
register_activation_hook(__FILE__, 'ptgates_exam_importer_activate');
register_deactivation_hook(__FILE__, 'ptgates_exam_importer_deactivate');

// 플러그인 초기화
PTGates_Exam_Importer::get_instance();

