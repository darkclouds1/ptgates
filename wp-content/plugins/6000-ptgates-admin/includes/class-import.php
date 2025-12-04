<?php
/**
 * ptgates 문제은행 DB 일괄 삽입 웹 인터페이스
 * 
 * WordPress 플러그인: 6000-ptgates-admin
 * 
 * CSV 구조 (필수 컬럼):
 * - content: 문제 본문 전체 (지문, 보기, 이미지 경로 등 포함)
 * - answer: 정답 (객관식 번호, 주관식 답)
 * - explanation: 문제 해설 내용 (선택)
 * - type: 문제 유형 (예: 객관식, 주관식)
 * - difficulty: 난이도 (1:하, 2:중, 3:상, 기본값: 2)
 * - exam_year: 시험 시행 연도 (예: 2024)
 * - exam_session: 시험 회차 (예: 52, 선택)
 * - exam_course: 교시 구분 (예: 1교시, 2교시)
 * - subject: 과목명 (예: 해부학, 물리치료학)
 * - source_company: 문제 출처 (선택, 기본값: null)
 * 
 * 사용법:
 * 1. 웹 브라우저: WordPress 관리자 페이지 → PTGates 문제 → CSV 일괄 삽입
 *    - 파일 선택 버튼으로 CSV 파일 업로드
 *    - 시작 버튼 클릭하여 데이터 삽입
 * 
 * 2. CLI: php wp-content/plugins/6000-ptgates-admin/includes/class-import.php [파일경로]
 *    - 파일경로 생략 시 같은 폴더의 exam_data.csv 사용
 */

// 직접 접근 방지 (WordPress 환경이 아닐 때만)
if ( ! defined( 'ABSPATH' ) && php_sapi_name() !== 'cli' ) {
	exit;
}

// CLI 환경 감지
$is_cli = (php_sapi_name() === 'cli');

if ($is_cli) {
    // CLI 환경: wp-config.php에서 DB 정보만 읽어서 직접 연결
    // 플러그인 디렉토리에서 WordPress 루트까지 경로 계산
    $wp_root = dirname(dirname(dirname(dirname(__DIR__))));
    require_once($wp_root . '/wp-includes/class-wpdb.php');
    
    $wp_config_path = $wp_root . '/wp-config.php';
    $wp_config_content = file_get_contents($wp_config_path);
    
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $wp_config_content, $db_name_match);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $wp_config_content, $db_user_match);
    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $wp_config_content, $db_password_match);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $wp_config_content, $db_host_match);
    preg_match("/\\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]\s*;/", $wp_config_content, $table_prefix_match);
    
    $db_name = $db_name_match[1];
    $db_user = $db_user_match[1];
    $db_password = $db_password_match[1];
    $db_host = $db_host_match[1];
    $table_prefix = !empty($table_prefix_match) ? $table_prefix_match[1] : 'wp_';
    
    $wpdb = new wpdb($db_user, $db_password, $db_name, $db_host);
    $wpdb->set_prefix($table_prefix);
    
    // CLI 환경: 명령줄 인수로 파일 경로 받기
    $csv_file_name = isset($argv[1]) ? $argv[1] : plugin_dir_path(__FILE__) . '../exam_data.csv';
    
    if (!file_exists($csv_file_name)) {
        die("오류: CSV 파일을 찾을 수 없습니다: {$csv_file_name}\n");
    }
    
    echo "ptgates 문제은행 DB 일괄 삽입 시작\n";
    echo "CSV 파일 경로: " . realpath($csv_file_name) . "\n";
    echo "시작 시간: " . date('Y-m-d H:i:s') . "\n\n";
    
    // CLI용 CSV 처리
    $separator = ',';
    $questions_table = 'ptgates_questions';
    $categories_table = 'ptgates_categories';
    $required_fields = array('content', 'answer', 'exam_course', 'subject');
    
    $file = fopen($csv_file_name, 'r');
    if (!$file) {
        die("오류: CSV 파일을 열 수 없습니다.\n");
    }
    
    $header = fgetcsv($file, 0, $separator);
    if (!$header) {
        die("오류: CSV 파일 헤더를 읽을 수 없습니다.\n");
    }
    
    // BOM 제거 및 헤더 정리
    $header = array_map(function($value) {
        // UTF-8 BOM 제거
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        // 앞뒤 공백 제거
        $value = trim($value);
        // 소문자로 변환
        return strtolower($value);
    }, $header);
    
    $missing_fields = array_diff($required_fields, $header);
    if (!empty($missing_fields)) {
        die("오류: 필수 컬럼이 누락되었습니다: " . implode(', ', $missing_fields) . "\n");
    }
    
    echo "필수 컬럼 확인 완료: " . implode(', ', $header) . "\n\n";
    
    $import_count = 0;
    $line_number = 1;
    $wpdb->query('START TRANSACTION');
    
    // exam_year/exam_session 자동 생성용 변수
    $current_year = intval(date('Y')); // 현재 연도
    $has_exam_year = in_array('exam_year', $header);
    $has_exam_session = in_array('exam_session', $header);
    
    // exam_session이 없으면 DB에서 최대값을 가져와서 +1 (파일 전체에 동일한 값 사용)
    $auto_session_value = null;
    if (!$has_exam_session) {
        // DB에서 현재 연도의 최대 exam_session 값 조회
        $max_session_query = $wpdb->prepare(
            "SELECT MAX(c.exam_session) as max_session 
             FROM {$categories_table} c
             INNER JOIN {$questions_table} q ON c.question_id = q.question_id
             WHERE q.is_active = 1 
             AND c.exam_year = %d
             AND c.exam_session IS NOT NULL",
            $current_year
        );
        $max_session = $wpdb->get_var($max_session_query);
        
        if ($max_session && $max_session >= 1000) {
            $auto_session_value = intval($max_session) + 1;
        } else {
            $auto_session_value = 1001; // 기본값
        }
        
        echo "⚠️ exam_session 컬럼이 없습니다. 이 파일 전체에 {$auto_session_value}회를 부여합니다.\n";
    }
    
    if (!$has_exam_year) {
        echo "⚠️ exam_year 컬럼이 없습니다. 자동으로 {$current_year}년으로 설정합니다.\n";
    }
    echo "\n";
    
    try {
        while (($row = fgetcsv($file, 0, $separator)) !== FALSE) {
            $line_number++;
            if (empty(array_filter($row))) continue;
            
            if (count($header) !== count($row)) {
                throw new Exception("CSV 데이터 컬럼 수 불일치! (라인: {$line_number})");
            }
            
            $data = array_combine($header, array_map('trim', $row));
            
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("필수 필드가 비어있습니다! (라인: {$line_number}, 필드: {$field})");
                }
            }
            
            $answer = trim($data['answer']);
            $clean_answer = $answer;
            if (preg_match('/^([①②③④⑤⑥⑦⑧⑨⑩]|\d+)/u', $answer, $matches)) {
                $circle_numbers = array('①' => '1', '②' => '2', '③' => '3', '④' => '4', '⑤' => '5',
                                       '⑥' => '6', '⑦' => '7', '⑧' => '8', '⑨' => '9', '⑩' => '10');
                if (isset($circle_numbers[$matches[1]])) {
                    $clean_answer = $circle_numbers[$matches[1]];
                } elseif (is_numeric($matches[1])) {
                    $clean_answer = $matches[1];
                }
            }
            
            $explanation = !empty($data['explanation']) ? $data['explanation'] : null;
            $type = !empty($data['type']) ? trim($data['type']) : '객관식';
            if (!in_array($type, array('객관식', '주관식', '서술형'))) $type = '객관식';
            
            $difficulty = isset($data['difficulty']) ? intval($data['difficulty']) : 2;
            if ($difficulty < 1 || $difficulty > 3) $difficulty = 2;
            
            // exam_year 처리: 없으면 현재 연도로 설정
            if ($has_exam_year && isset($data['exam_year']) && !empty($data['exam_year'])) {
                $exam_year = intval($data['exam_year']);
                if ($exam_year < 1900 || $exam_year > 2100) {
                    throw new Exception("시험 연도가 유효하지 않습니다! (라인: {$line_number}, 연도: {$exam_year})");
                }
            } else {
                $exam_year = $current_year;
            }
            
            // exam_session 처리: 파일 전체에 동일한 값 사용
            if ($has_exam_session && isset($data['exam_session']) && !empty($data['exam_session'])) {
                $exam_session = intval($data['exam_session']);
            } else {
                // 파일 전체에 동일한 exam_session 값 사용 (이미 파일 시작 시 결정됨)
                $exam_session = $auto_session_value;
            }
            $exam_course = trim($data['exam_course']);
            $subject = trim($data['subject']);
            $source_company = isset($data['source_company']) && !empty($data['source_company']) ? trim($data['source_company']) : null;
            
            // 중복 체크: content와 시험 정보 조합으로 확인
            $duplicate_check = $wpdb->prepare(
                "SELECT q.question_id 
                 FROM {$questions_table} q
                 INNER JOIN {$categories_table} c ON q.question_id = c.question_id
                 WHERE q.content = %s 
                 AND c.exam_year = %d 
                 AND c.exam_course = %s 
                 AND c.subject = %s",
                $data['content'],
                $exam_year,
                $exam_course,
                $subject
            );
            
            if ($exam_session !== null) {
                $duplicate_check .= $wpdb->prepare(" AND c.exam_session = %d", $exam_session);
            } else {
                $duplicate_check .= " AND c.exam_session IS NULL";
            }
            
            $duplicate_check .= " LIMIT 1";
            $existing_question_id = $wpdb->get_var($duplicate_check);
            
            if ($existing_question_id) {
                echo "라인 {$line_number}: 중복 데이터 건너뜀 (이미 존재하는 문제)\n";
                continue;
            }
            
            $question_data = array(
                'content' => $data['content'],
                'answer' => $clean_answer,
                'explanation' => $explanation,
                'type' => $type,
                'difficulty' => $difficulty,
                'is_active' => 1,
            );
            
            $result = $wpdb->insert($questions_table, $question_data);
            if ($result === false) {
                throw new Exception("질문 데이터 삽입 오류! (라인: {$line_number}, 오류: {$wpdb->last_error})");
            }
            
            $question_id = $wpdb->insert_id;
            if (!$question_id || $question_id <= 0) {
                throw new Exception("질문 ID를 가져올 수 없습니다! (라인: {$line_number})");
            }
            
            $category_data = array(
                'question_id' => $question_id,
                'exam_year' => $exam_year,
                'exam_session' => $exam_session,
                'exam_course' => $exam_course,
                'subject' => $subject,
                'source_company' => $source_company,
            );
            
            $result = $wpdb->insert($categories_table, $category_data);
            if ($result === false) {
                throw new Exception("분류 데이터 삽입 오류! (라인: {$line_number}, 오류: {$wpdb->last_error})");
            }
            
            $import_count++;
            if ($import_count % 100 == 0) {
                echo "진행 중... {$import_count}개 처리 완료\n";
            }
        }
        
        $wpdb->query('COMMIT');
        echo "\n✅ 성공적으로 {$import_count}개의 문제를 삽입했습니다!\n";
        echo "완료 시간: " . date('Y-m-d H:i:s') . "\n";
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        echo "\n❌ 데이터 삽입 실패!\n";
        echo "오류 메시지: " . $e->getMessage() . "\n";
        echo "삽입 중단 시점 문제 수: " . $import_count . " (롤백됨)\n";
        echo "오류 발생 라인: {$line_number}\n";
        if ($wpdb->last_error) {
            echo "데이터베이스 오류: " . $wpdb->last_error . "\n";
        }
        exit(1);
    }
    
    fclose($file);
    exit(0);
    
} else {
    // 웹 환경: WordPress 플러그인 환경
    // WordPress가 이미 로드되어 있으면 $wpdb 사용, 아니면 직접 로드
    if ( ! defined( 'ABSPATH' ) ) {
        // WordPress가 로드되지 않은 경우 (직접 접근 시도)
        $wp_root = dirname(dirname(dirname(dirname(__DIR__))));
        require_once($wp_root . '/wp-load.php');
    }
    global $wpdb;
    
    // 관리자 권한 체크 함수
    function check_admin_permission() {
        // WordPress 함수 사용 가능 여부 확인
        $wp_loaded = function_exists('is_user_logged_in') && function_exists('current_user_can');
        
        // AJAX 요청 여부 확인 (Content-Type 또는 X-Requested-With 헤더 확인)
        $is_ajax = (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_POST['action']) && in_array($_POST['action'], array('import_csv', 'generate_csv_from_txt', 'get_subject_statistics', 'get_category_statistics', 'delete_exam_data', 'get_question_statistics')))
        );
        
        // AJAX 요청인 경우 헤더 먼저 설정
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
        // WordPress가 로드되지 않은 경우
        if (!$wp_loaded) {
            if ($is_ajax) {
                http_response_code(500);
                echo json_encode(array(
                    'success' => false,
                    'message' => 'WordPress가 로드되지 않았습니다.',
                    'error' => 'wp_not_loaded'
                ));
                exit;
            } else {
                die('WordPress가 로드되지 않았습니다.');
            }
        }
        
        // 로그인 여부 확인
        if (!is_user_logged_in()) {
            if ($is_ajax) {
                http_response_code(401);
                echo json_encode(array(
                    'success' => false,
                    'message' => '로그인이 필요합니다.',
                    'login_required' => true
                ));
                exit;
            } else {
                // 일반 요청인 경우 로그인 페이지로 리다이렉트
                if (function_exists('wp_login_url') && function_exists('wp_redirect')) {
                    $login_url = wp_login_url($_SERVER['REQUEST_URI']);
                    wp_redirect($login_url);
                    exit;
                } else {
                    die('로그인이 필요합니다.');
                }
            }
        }
        
        // 관리자 권한 확인
        if (!current_user_can('manage_options')) {
            if ($is_ajax) {
                http_response_code(403);
                echo json_encode(array(
                    'success' => false,
                    'message' => '이 페이지에 접근할 권한이 없습니다. 관리자 권한이 필요합니다.',
                    'permission_denied' => true
                ));
                exit;
            } else {
                // 일반 요청인 경우 403 에러 표시
                if (function_exists('wp_die')) {
                    wp_die(
                        '이 페이지에 접근할 권한이 없습니다.',
                        '권한 없음',
                        array('response' => 403)
                    );
                } else {
                    http_response_code(403);
                    die('이 페이지에 접근할 권한이 없습니다.');
                }
            }
        }
    }
    
    // 모든 요청에 대해 관리자 권한 체크
    check_admin_permission();
    
    // 세션 시작 (마지막 업로드 파일 정보 저장용)
    if (!session_id()) {
        session_start();
    }

    // AJAX 요청 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // AJAX 요청인 경우 출력 버퍼링 시작 (WordPress 출력 간섭 방지)
        $is_ajax_action = in_array($_POST['action'], array('get_subject_statistics', 'get_category_statistics', 'delete_exam_data', 'import_csv', 'generate_csv_from_txt', 'get_question_statistics', 'update_exam_session'));
        if ($is_ajax_action) {
            // 기존 출력 버퍼가 있으면 모두 정리
            while (ob_get_level()) {
                ob_end_clean();
            }
            // 새 출력 버퍼 시작
            ob_start();
        }
        if ($_POST['action'] === 'download_file') {
            // 파일 다운로드 처리
            $file_path = __DIR__ . '/exam_data.csv';
            
            if (file_exists($file_path)) {
                $original_name = 'exam_data.csv'; // 항상 exam_data.csv로 다운로드
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
        
        // Content-Type 헤더는 check_admin_permission에서 이미 설정됨 (AJAX인 경우)
        // 하지만 다시 설정해도 문제없음
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_POST['action'] === 'import_csv') {
            process_csv_import($wpdb);
            // 출력 버퍼 정리 및 플러시
            if ($is_ajax_action && ob_get_level()) {
                ob_end_flush();
            }
            exit; // 함수 내부에서 exit하지 않는 경우를 대비
        } else if ($_POST['action'] === 'generate_csv_from_txt') {
            generate_csv_from_txt();
            if ($is_ajax_action && ob_get_level()) {
                ob_end_flush();
            }
            exit;
        } else if ($_POST['action'] === 'get_subject_statistics') {
            get_subject_statistics($wpdb);
            // 출력 버퍼 정리 및 플러시
            if ($is_ajax_action && ob_get_level()) {
                ob_end_flush();
            }
            exit; // JSON 출력 후 종료
        } else if ($_POST['action'] === 'get_category_statistics') {
            get_category_statistics($wpdb);
            // 출력 버퍼 정리 및 플러시
            if ($is_ajax_action && ob_get_level()) {
                ob_end_flush();
            }
            exit; // JSON 출력 후 종료
        } else if ($_POST['action'] === 'get_question_statistics') {
            get_question_statistics_ajax($wpdb);
            // 출력 버퍼 정리 및 플러시
            if ($is_ajax_action && ob_get_level()) {
                ob_end_flush();
            }
            exit; // JSON 출력 후 종료
        } else if ($_POST['action'] === 'delete_exam_data') {
            delete_exam_data($wpdb);
            if ($is_ajax_action && ob_get_level()) {
                ob_end_flush();
            }
            exit;
        } else if ($_POST['action'] === 'update_exam_session') {
            update_exam_session($wpdb);
            if ($is_ajax_action && ob_get_level()) {
                ob_end_flush();
            }
            exit;
        } else {
            echo json_encode(array('success' => false, 'message' => '알 수 없는 작업입니다.'));
            if ($is_ajax_action && ob_get_level()) {
                ob_end_flush();
            }
            exit;
        }
    }
}

// Excel 파일을 CSV 형식으로 변환하는 함수
function convert_excel_to_csv($file_path, $file_extension) {
    // PhpSpreadsheet 사용 시도 (우선)
    $vendor_autoload_paths = array(
        dirname(dirname(__DIR__)) . '/vendor/autoload.php',
        __DIR__ . '/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    );
    
    $phpspreadsheet_loaded = false;
    foreach ($vendor_autoload_paths as $autoload_path) {
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
            $phpspreadsheet_loaded = true;
            break;
        }
    }
    
    if ($phpspreadsheet_loaded && class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // CSV 형식으로 변환
            $csv_data = array();
            foreach ($rows as $row) {
                $csv_row = array();
                foreach ($row as $cell) {
                    // 셀 값 이스케이프
                    $cell_value = is_null($cell) ? '' : (string)$cell;
                    $cell_value = str_replace('"', '""', $cell_value);
                    if (strpos($cell_value, ',') !== false || strpos($cell_value, '"') !== false || strpos($cell_value, "\n") !== false) {
                        $cell_value = '"' . $cell_value . '"';
                    }
                    $csv_row[] = $cell_value;
                }
                $csv_data[] = implode(',', $csv_row);
            }
            
            return implode("\n", $csv_data);
        } catch (Exception $e) {
            throw new Exception('Excel 파일 읽기 실패: ' . $e->getMessage());
        }
    }
    
    // PhpSpreadsheet가 없으면 간단한 Excel 파서 사용 (.xlsx만 지원)
    if ($file_extension === 'xlsx') {
        return convert_xlsx_to_csv_simple($file_path);
    } elseif ($file_extension === 'xls') {
        throw new Exception('.xls 파일은 PhpSpreadsheet 라이브러리가 필요합니다. Composer로 설치해주세요: composer require phpoffice/phpspreadsheet\n또는 Excel에서 .xlsx 형식으로 저장해주세요.');
    }
    
    throw new Exception('지원하지 않는 파일 형식입니다.');
}

// 간단한 .xlsx 파일 파서 (PhpSpreadsheet 없이)
function convert_xlsx_to_csv_simple($file_path) {
    if (!class_exists('ZipArchive')) {
        throw new Exception('Excel 파일을 처리하려면 PHP ZipArchive 확장이 필요합니다.');
    }
    
    if (!file_exists($file_path)) {
        throw new Exception('Excel 파일을 찾을 수 없습니다: ' . $file_path);
    }
    
    $zip = new ZipArchive();
    $zip_result = $zip->open($file_path);
    if ($zip_result !== TRUE) {
        $error_msg = 'Excel 파일을 열 수 없습니다. ';
        switch ($zip_result) {
            case ZipArchive::ER_OK: $error_msg .= '에러 없음'; break;
            case ZipArchive::ER_MULTIDISK: $error_msg .= '멀티디스크 ZIP 아카이브는 지원하지 않습니다'; break;
            case ZipArchive::ER_RENAME: $error_msg .= '임시 파일 이름 변경 실패'; break;
            case ZipArchive::ER_CLOSE: $error_msg .= 'ZIP 아카이브 닫기 실패'; break;
            case ZipArchive::ER_SEEK: $error_msg .= '시크 오류'; break;
            case ZipArchive::ER_READ: $error_msg .= '읽기 오류'; break;
            case ZipArchive::ER_WRITE: $error_msg .= '쓰기 오류'; break;
            case ZipArchive::ER_CRC: $error_msg .= 'CRC 오류'; break;
            case ZipArchive::ER_ZIPCLOSED: $error_msg .= 'ZIP 아카이브가 닫혀있습니다'; break;
            case ZipArchive::ER_NOENT: $error_msg .= '파일을 찾을 수 없습니다'; break;
            case ZipArchive::ER_EXISTS: $error_msg .= '파일이 이미 존재합니다'; break;
            case ZipArchive::ER_OPEN: $error_msg .= '파일을 열 수 없습니다'; break;
            case ZipArchive::ER_TMPOPEN: $error_msg .= '임시 파일을 만들 수 없습니다'; break;
            case ZipArchive::ER_ZLIB: $error_msg .= 'Zlib 오류'; break;
            case ZipArchive::ER_MEMORY: $error_msg .= '메모리 할당 실패'; break;
            case ZipArchive::ER_CHANGED: $error_msg .= '항목이 변경되었습니다'; break;
            case ZipArchive::ER_COMPNOTSUPP: $error_msg .= '압축 방법이 지원되지 않습니다'; break;
            case ZipArchive::ER_EOF: $error_msg .= '예기치 않은 EOF'; break;
            case ZipArchive::ER_INVAL: $error_msg .= '잘못된 인수'; break;
            case ZipArchive::ER_NOZIP: $error_msg .= 'ZIP 아카이브가 아닙니다'; break;
            case ZipArchive::ER_INTERNAL: $error_msg .= '내부 오류'; break;
            case ZipArchive::ER_INCONS: $error_msg .= 'ZIP 아카이브 불일치'; break;
            case ZipArchive::ER_REMOVE: $error_msg .= '파일 삭제 실패'; break;
            case ZipArchive::ER_DELETED: $error_msg .= '항목이 삭제되었습니다'; break;
            default: $error_msg .= '알 수 없는 오류 (코드: ' . $zip_result . ')'; break;
        }
        throw new Exception($error_msg);
    }
    
    // 공유 문자열 읽기
    $shared_strings = array();
    $shared_strings_xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($shared_strings_xml !== false) {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($shared_strings_xml);
        if ($xml !== false) {
            // 네임스페이스 등록
            $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $si_nodes = $xml->xpath('//main:si');
            if (empty($si_nodes)) {
                // 네임스페이스 없이 시도
                $si_nodes = $xml->xpath('//si');
            }
            foreach ($si_nodes as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string)$si->t;
                } else {
                    // 여러 텍스트 노드가 있는 경우
                    $text_nodes = $si->xpath('.//t');
                    if (empty($text_nodes)) {
                        $text_nodes = $si->xpath('.//main:t');
                    }
                    foreach ($text_nodes as $t) {
                        $text .= (string)$t;
                    }
                }
                $shared_strings[] = $text;
            }
        }
        libxml_clear_errors();
    }
    
    // 첫 번째 시트 읽기
    $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheet_xml === false) {
        $zip->close();
        throw new Exception('Excel 파일에서 시트를 찾을 수 없습니다.');
    }
    
    $zip->close();
    
    // 시트 XML 파싱
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_string($sheet_xml);
    if ($xml === false) {
        $xml_errors = libxml_get_errors();
        $error_msg = 'Excel 파일의 시트 데이터를 파싱할 수 없습니다.';
        if (!empty($xml_errors)) {
            $error_msg .= ' XML 오류: ' . $xml_errors[0]->message;
        }
        libxml_clear_errors();
        throw new Exception($error_msg);
    }
    libxml_clear_errors();
    
    // 네임스페이스 등록
    $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    
    // 셀 데이터 읽기
    $rows_data = array();
    $cells = $xml->xpath('//main:c');
    if (empty($cells)) {
        // 네임스페이스 없이 시도
        $cells = $xml->xpath('//c');
    }
    
    if (empty($cells)) {
        throw new Exception('Excel 파일에서 셀 데이터를 찾을 수 없습니다. 파일이 비어있거나 손상되었을 수 있습니다.');
    }
    
    foreach ($cells as $cell) {
        $r = (string)$cell['r']; // 셀 주소 (예: A1, B2)
        if (empty($r)) continue;
        
        $t = (string)$cell['t']; // 타입 (s = 공유 문자열)
        
        // v 노드 찾기
        $v = '';
        if (isset($cell->v)) {
            $v = trim((string)$cell->v);
        } else {
            // 네임스페이스가 있는 경우
            $v_nodes = $cell->xpath('.//main:v');
            if (empty($v_nodes)) {
                $v_nodes = $cell->xpath('.//v');
            }
            if (!empty($v_nodes)) {
                $v = trim((string)$v_nodes[0]);
            }
        }
        
        // 행과 열 추출 (예: A1 -> row=1, col=0)
        preg_match('/([A-Z]+)(\d+)/', $r, $matches);
        if (count($matches) !== 3) continue;
        
        $col_letters = $matches[1];
        $row_num = intval($matches[2]) - 1; // 0부터 시작
        
        // 열 번호 계산 (A=0, B=1, ..., Z=25, AA=26, ...)
        $col_num = 0;
        for ($i = 0; $i < strlen($col_letters); $i++) {
            $col_num = $col_num * 26 + (ord($col_letters[$i]) - ord('A') + 1);
        }
        $col_num--; // 0부터 시작하도록 조정
        
        // 셀 값 결정
        $cell_value = '';
        if ($t === 's' && $v !== '' && is_numeric($v)) {
            // 공유 문자열 인덱스
            $shared_index = intval($v);
            if (isset($shared_strings[$shared_index])) {
                $cell_value = $shared_strings[$shared_index];
            }
        } elseif ($v !== '') {
            // 직접 값 (숫자, 날짜 등)
            $cell_value = $v;
        }
        // $v가 비어있으면 $cell_value도 빈 문자열로 유지 (빈 셀)
        
        // 행 데이터에 저장 (빈 셀도 저장)
        if (!isset($rows_data[$row_num])) {
            $rows_data[$row_num] = array();
        }
        $rows_data[$row_num][$col_num] = $cell_value;
    }
    
    // CSV 형식으로 변환
    $csv_data = array();
    if (empty($rows_data)) {
        throw new Exception('Excel 파일에서 데이터를 읽을 수 없습니다. 파일이 비어있거나 손상되었을 수 있습니다.');
    }
    
    $max_row = max(array_keys($rows_data));
    $max_col = 0;
    
    // 최대 열 번호 찾기
    foreach ($rows_data as $row_data) {
        if (!empty($row_data)) {
            $row_max_col = max(array_keys($row_data));
            if ($row_max_col > $max_col) {
                $max_col = $row_max_col;
            }
        }
    }
    
    for ($row = 0; $row <= $max_row; $row++) {
        $csv_row = array();
        if (isset($rows_data[$row]) && !empty($rows_data[$row])) {
            for ($col = 0; $col <= $max_col; $col++) {
                $cell_value = isset($rows_data[$row][$col]) ? $rows_data[$row][$col] : '';
                // CSV 이스케이프
                $cell_value = str_replace('"', '""', $cell_value);
                if (strpos($cell_value, ',') !== false || strpos($cell_value, '"') !== false || strpos($cell_value, "\n") !== false) {
                    $cell_value = '"' . $cell_value . '"';
                }
                $csv_row[] = $cell_value;
            }
        } else {
            // 빈 행도 처리 (모든 열에 빈 값)
            for ($col = 0; $col <= $max_col; $col++) {
                $csv_row[] = '';
            }
        }
        $csv_data[] = implode(',', $csv_row);
    }
    
    if (empty($csv_data)) {
        throw new Exception('Excel 파일에서 데이터를 읽을 수 없습니다. 파일이 비어있거나 손상되었을 수 있습니다.');
    }
    
    return implode("\n", $csv_data);
}

// CSV 처리 함수
function process_csv_import($wpdb) {
    // PHP 에러 출력 방지 (JSON 응답을 위해)
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    // 모든 에러를 JSON으로 반환하기 위해 try-catch로 감싸기
    try {
        $separator = ',';
        $questions_table = 'ptgates_questions';
        $categories_table = 'ptgates_categories';
        
        $required_fields = array('content', 'answer', 'exam_course', 'subject');
        
        $log = array();
        $import_count = 0;
        $error_count = 0;
        
        // 파일 업로드 확인
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array(
                'success' => false,
                'message' => 'CSV 파일 업로드에 실패했습니다. 파일을 선택해주세요.'
            ));
            return;
        }
    
    $uploaded_file = $_FILES['csv_file']['tmp_name'];
    $original_filename = $_FILES['csv_file']['name'];
    
    // 파일 확장자 확인
    $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $is_excel_file = in_array($file_extension, array('xlsx', 'xls'));
    $is_csv_file = ($file_extension === 'csv');
    
    if (!$is_csv_file && !$is_excel_file) {
        echo json_encode(array(
            'success' => false,
            'message' => 'CSV 또는 Excel 파일만 업로드 가능합니다. 파일 확장자를 확인해주세요. (업로드된 파일: .' . $file_extension . ')'
        ));
        return;
    }
    
    // Excel 파일인 경우 CSV로 변환
    $csv_content = null;
    if ($is_excel_file) {
        try {
            $csv_content = convert_excel_to_csv($uploaded_file, $file_extension);
            $csv_lines = explode("\n", $csv_content);
            $log[] = "Excel 파일을 CSV로 변환 완료: " . $original_filename;
            $log[] = "변환된 CSV 라인 수: " . count($csv_lines) . " (헤더 포함)";
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
            return;
        }
    }
    
    // CSV 파일인 경우 내용 검증
    if ($is_csv_file) {
        // 파일 내용 검증 (Excel 파일 시그니처 확인)
        $file_content_start = file_get_contents($uploaded_file, false, null, 0, 100);
        if ($file_content_start !== false) {
            // Excel 파일 시그니처 확인 (ZIP 파일 시그니처: PK)
            if (strpos($file_content_start, 'PK') === 0 && strpos($file_content_start, '[Content_Types].xml') !== false) {
                // Excel 파일이지만 .csv 확장자인 경우, Excel로 처리 시도
                try {
                    $csv_content = convert_excel_to_csv($uploaded_file, 'xlsx');
                    $log[] = "Excel 파일(.csv 확장자)을 CSV로 변환 완료: " . $original_filename;
                    $is_excel_file = true;
                } catch (Exception $e) {
                    echo json_encode(array(
                        'success' => false,
                        'message' => 'Excel 파일이 감지되었습니다. 파일 확장자를 .xlsx 또는 .xls로 변경하거나, CSV로 변환해주세요.'
                    ));
                    return;
                }
            }
            
            // 바이너리 데이터가 많은지 확인 (제어 문자 비율) - CSV 파일인 경우만
            $binary_char_count = preg_match_all('/[\x00-\x08\x0E-\x1F]/', $file_content_start);
            if ($binary_char_count > strlen($file_content_start) * 0.1) {
                echo json_encode(array(
                    'success' => false,
                    'message' => '바이너리 파일이 감지되었습니다. CSV 텍스트 파일 또는 Excel 파일만 업로드 가능합니다.'
                ));
                return;
            }
        }
    }
    
    // 업로드된 파일을 같은 디렉토리에 exam_data.csv로 저장 (다운로드용)
    $saved_file_path = __DIR__ . '/exam_data.csv';
    
    // Excel 파일에서 변환된 CSV 내용이 있으면 사용, 없으면 원본 파일 사용
    if ($csv_content !== null) {
        // 변환된 CSV 내용을 임시 파일에 저장
        $temp_csv_file = tempnam(sys_get_temp_dir(), 'excel_csv_');
        file_put_contents($temp_csv_file, $csv_content);
        $file_to_process = $temp_csv_file;
        
        // 변환된 CSV를 exam_data.csv로 저장
        file_put_contents($saved_file_path, $csv_content);
    } else {
        // 원본 CSV 파일 사용
        copy($uploaded_file, $saved_file_path);
        $file_to_process = $uploaded_file;
    }
    
    // 파일 열기
    $file = fopen($file_to_process, 'r');
    if (!$file) {
        echo json_encode(array(
            'success' => false,
            'message' => 'CSV 파일을 열 수 없습니다.'
        ));
        return;
    }
    
    $log[] = "파일 열기 성공: " . $_FILES['csv_file']['name'];
    
    // 헤더 읽기
    $header = fgetcsv($file, 0, $separator);
    if (!$header) {
        fclose($file);
        if (isset($temp_csv_file)) {
            @unlink($temp_csv_file);
        }
        echo json_encode(array(
            'success' => false,
            'message' => 'CSV 파일 헤더를 읽을 수 없습니다.'
        ));
        return;
    }
    
    // BOM 제거 및 헤더 정리
    $header = array_map(function($value) {
        // UTF-8 BOM 제거
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        // 앞뒤 공백 제거
        $value = trim($value);
        // 소문자로 변환
        return strtolower($value);
    }, $header);
    
    // 필수 필드 확인
    $missing_fields = array_diff($required_fields, $header);
    if (!empty($missing_fields)) {
        fclose($file);
        echo json_encode(array(
            'success' => false,
            'message' => '필수 컬럼이 누락되었습니다: ' . implode(', ', $missing_fields) . 
                        ' | 실제 헤더: ' . implode(', ', $header) . 
                        ' | 필수 필드: ' . implode(', ', $required_fields)
        ));
        return;
    }
    
    $log[] = "필수 컬럼 확인 완료: " . implode(', ', $header);
    
    // 덮어쓰기 옵션 확인
    $overwrite_mode = isset($_POST['overwrite']) && $_POST['overwrite'] === '1';
    
    $line_number = 1;
    $wpdb->query('START TRANSACTION');
    
    // exam_year/exam_session 자동 생성용 변수
    $current_year = intval(date('Y')); // 현재 연도
    $has_exam_year = in_array('exam_year', $header);
    $has_exam_session = in_array('exam_session', $header);
    
    // exam_session이 없으면 DB에서 최대값을 가져와서 +1 (파일 전체에 동일한 값 사용)
    $auto_session_value = null;
    if (!$has_exam_session) {
        // DB에서 현재 연도의 최대 exam_session 값 조회
        $max_session_query = $wpdb->prepare(
            "SELECT MAX(c.exam_session) as max_session 
             FROM {$categories_table} c
             INNER JOIN {$questions_table} q ON c.question_id = q.question_id
             WHERE q.is_active = 1 
             AND c.exam_year = %d
             AND c.exam_session IS NOT NULL",
            $current_year
        );
        $max_session = $wpdb->get_var($max_session_query);
        
        if ($max_session && $max_session >= 1000) {
            $auto_session_value = intval($max_session) + 1;
        } else {
            $auto_session_value = 1001; // 기본값
        }
        
        $log[] = "⚠️ exam_session 컬럼이 없습니다. 이 파일 전체에 {$auto_session_value}회를 부여합니다.";
    }
    
    if (!$has_exam_year) {
        $log[] = "⚠️ exam_year 컬럼이 없습니다. 자동으로 {$current_year}년으로 설정합니다.";
    }
    
    // 덮어쓰기 모드인 경우 기존 데이터 삭제 (특정 연도/회차만)
    // 주의: 덮어쓰기 모드는 현재 파일의 연도/회차에 해당하는 데이터만 삭제합니다.
    if ($overwrite_mode) {
        // 현재 파일의 연도와 회차를 먼저 확인해야 함
        // 하지만 아직 파일을 읽지 않았으므로, 여기서는 삭제하지 않고
        // 각 행 처리 시 중복 체크 후 업데이트하도록 함
        // 전체 삭제는 위험하므로 제거함
        $log[] = "⚠️ 덮어쓰기 모드: 중복되는 문제만 업데이트합니다. (기존 데이터는 삭제하지 않습니다)";
    }
    
    try {
        while (($row = fgetcsv($file, 0, $separator)) !== FALSE) {
            $line_number++;
            
            if (empty(array_filter($row))) {
                continue;
            }
            
            if (count($header) !== count($row)) {
                throw new Exception("CSV 데이터 컬럼 수 불일치! (라인: {$line_number})");
            }
            
            $data = array_combine($header, array_map('trim', $row));
            
            // 필수 필드 검증
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("필수 필드가 비어있습니다! (라인: {$line_number}, 필드: {$field})");
                }
            }
            
            // 데이터 정규화
            $answer = trim($data['answer']);
            $clean_answer = $answer;
            if (preg_match('/^([①②③④⑤⑥⑦⑧⑨⑩]|\d+)/u', $answer, $matches)) {
                $circle_numbers = array('①' => '1', '②' => '2', '③' => '3', '④' => '4', '⑤' => '5',
                                       '⑥' => '6', '⑦' => '7', '⑧' => '8', '⑨' => '9', '⑩' => '10');
                if (isset($circle_numbers[$matches[1]])) {
                    $clean_answer = $circle_numbers[$matches[1]];
                } elseif (is_numeric($matches[1])) {
                    $clean_answer = $matches[1];
                }
            }
            
            $explanation = !empty($data['explanation']) ? $data['explanation'] : null;

            // Explanation cleanup logic
            if ($explanation) {
                // 1. Replace _x000D_ with \n
                $explanation = str_replace('_x000D_', "\n", $explanation);

                // 2. Normalize newlines (\r\n, \r -> \n)
                $explanation = str_replace(array("\r\n", "\r"), "\n", $explanation);

                // 3. Ensure (오답 해설) is on a new line if it's not at the start
                $explanation = preg_replace('/(?<!\n)\s*(\(오답 해설\))/u', "\n$1", $explanation);
                
                // Optional: Trim extra whitespace
                $explanation = trim($explanation);
            }

            $type = !empty($data['type']) ? trim($data['type']) : '객관식';
            if (!in_array($type, array('객관식', '주관식', '서술형'))) {
                $type = '객관식';
            }
            
            $difficulty = isset($data['difficulty']) ? intval($data['difficulty']) : 2;
            if ($difficulty < 1 || $difficulty > 3) {
                $difficulty = 2;
            }
            
            // exam_year 처리: 없으면 현재 연도로 설정
            if ($has_exam_year && isset($data['exam_year']) && !empty($data['exam_year'])) {
                $exam_year = intval($data['exam_year']);
                if ($exam_year < 1900 || $exam_year > 2100) {
                    throw new Exception("시험 연도가 유효하지 않습니다! (라인: {$line_number}, 연도: {$exam_year})");
                }
            } else {
                $exam_year = $current_year;
            }
            
            // exam_session 처리: 파일 전체에 동일한 값 사용
            if ($has_exam_session && isset($data['exam_session']) && !empty($data['exam_session'])) {
                $exam_session = intval($data['exam_session']);
            } else {
                // 파일 전체에 동일한 exam_session 값 사용 (이미 파일 시작 시 결정됨)
                $exam_session = $auto_session_value;
            }
            
            $exam_course = trim($data['exam_course']);
            $subject = trim($data['subject']);
            $source_company = isset($data['source_company']) && !empty($data['source_company']) 
                ? trim($data['source_company']) 
                : null;
            
            // 중복 체크: content와 시험 정보 조합으로 확인
            // 덮어쓰기 모드일 때는 exam_session을 무시하여 같은 문제를 찾음
            $duplicate_check = $wpdb->prepare(
                "SELECT q.question_id 
                 FROM {$questions_table} q
                 INNER JOIN {$categories_table} c ON q.question_id = c.question_id
                 WHERE q.content = %s 
                 AND c.exam_year = %d 
                 AND c.exam_course = %s 
                 AND c.subject = %s",
                $data['content'],
                $exam_year,
                $exam_course,
                $subject
            );
            
            // 덮어쓰기 모드가 아닐 때만 exam_session을 포함하여 체크
            // 덮어쓰기 모드일 때는 exam_session을 무시하여 같은 문제를 찾아 업데이트
            if (!$overwrite_mode) {
                // exam_session이 있는 경우에도 포함하여 체크
                if ($exam_session !== null) {
                    $duplicate_check .= $wpdb->prepare(" AND c.exam_session = %d", $exam_session);
                } else {
                    $duplicate_check .= " AND c.exam_session IS NULL";
                }
            }
            // 덮어쓰기 모드일 때는 exam_session 조건을 추가하지 않음
            
            $duplicate_check .= " ORDER BY c.exam_session DESC, q.question_id DESC LIMIT 1";
            
            $existing_question_id = $wpdb->get_var($duplicate_check);
            
            if ($existing_question_id) {
                // 덮어쓰기 모드가 아니면 중복 건너뛰기
                if (!$overwrite_mode) {
                    $log[] = "라인 {$line_number}: 중복 데이터 건너뜀 (이미 존재하는 문제)";
                    continue;
                } else {
                    // 덮어쓰기 모드: 기존 데이터 업데이트
                    // 기존 데이터의 exam_session을 유지 (같은 회차로 유지)
                    $existing_session = $wpdb->get_var($wpdb->prepare(
                        "SELECT exam_session FROM {$categories_table} WHERE question_id = %d",
                        $existing_question_id
                    ));
                    
                    // 기존 exam_session이 있으면 유지, 없으면 새로운 값 사용
                    $final_exam_session = ($existing_session !== null) ? $existing_session : $exam_session;
                    
                    $question_data = array(
                        'content'     => $data['content'],
                        'answer'      => $clean_answer,
                        'explanation' => $explanation,
                        'type'        => $type,
                        'difficulty'  => $difficulty,
                        'is_active'   => 1,
                    );
                    
                    $result = $wpdb->update($questions_table, $question_data, array('question_id' => $existing_question_id));
                    if ($result === false) {
                        throw new Exception("질문 데이터 업데이트 오류! (라인: {$line_number}, 오류: {$wpdb->last_error})");
                    }
                    
                    $category_data = array(
                        'exam_year'      => $exam_year,
                        'exam_session'   => $final_exam_session, // 기존 exam_session 유지
                        'exam_course'    => $exam_course,
                        'subject'        => $subject,
                        'source_company' => $source_company,
                    );
                    
                    $result = $wpdb->update($categories_table, $category_data, array('question_id' => $existing_question_id));
                    if ($result === false) {
                        throw new Exception("분류 데이터 업데이트 오류! (라인: {$line_number}, 오류: {$wpdb->last_error})");
                    }
                    
                    $import_count++;
                    if ($import_count % 10 == 0) {
                        $log[] = "진행 중... {$import_count}개 처리 완료 (업데이트 포함)";
                    }
                    continue;
                }
            }
            
            // 중복이 없으면 새로 삽입
            // ptgates_questions 테이블에 삽입
            $question_data = array(
                'content'     => $data['content'],
                'answer'      => $clean_answer,
                'explanation' => $explanation,
                'type'        => $type,
                'difficulty'  => $difficulty,
                'is_active'   => 1,
            );
            
            $result = $wpdb->insert($questions_table, $question_data);
            if ($result === false) {
                throw new Exception("질문 데이터 삽입 오류! (라인: {$line_number}, 오류: {$wpdb->last_error})");
            }
            
            $question_id = $wpdb->insert_id;
            if (!$question_id || $question_id <= 0) {
                throw new Exception("질문 ID를 가져올 수 없습니다! (라인: {$line_number})");
            }
            
            // ptgates_categories 테이블에 삽입
            $category_data = array(
                'question_id'    => $question_id,
                'exam_year'      => $exam_year,
                'exam_session'   => $exam_session,
                'exam_course'    => $exam_course,
                'subject'        => $subject,
                'source_company' => $source_company,
            );
            
            $result = $wpdb->insert($categories_table, $category_data);
            if ($result === false) {
                throw new Exception("분류 데이터 삽입 오류! (라인: {$line_number}, 오류: {$wpdb->last_error})");
            }
            
            $import_count++;
            
            // 진행 상황 업데이트 (10개마다)
            if ($import_count % 10 == 0) {
                $log[] = "진행 중... {$import_count}개 처리 완료";
            }
        }
        
        $wpdb->query('COMMIT');
        $log[] = "✅ 성공적으로 {$import_count}개의 문제를 삽입했습니다!";
        $log[] = "완료 시간: " . date('Y-m-d H:i:s');
        
        // 자동 데이터 정리
        $log[] = "🔧 데이터 정리 중...";
        
        // 1. 문제 앞의 숫자 + 점(".") 제거
        $number_cleaned = $wpdb->query(
            "UPDATE {$questions_table} 
             SET content = REGEXP_REPLACE(content, '^[0-9]+\\\\.\\\\s*', '')
             WHERE content REGEXP '^[0-9]+\\\\.'"
        );
        
        if ($number_cleaned !== false && $number_cleaned > 0) {
            $log[] = "✅ {$number_cleaned}개 문제의 앞 번호를 제거했습니다.";
        }
        
        // 2. content 필드 정리 (_x000D_ 및 \r\n 제거)
        $content_cleaned = $wpdb->query(
            "UPDATE {$questions_table} 
             SET content = REPLACE(REPLACE(content, '_x000D_', '\n'), '\r\n', '\n')
             WHERE content LIKE '%_x000D_%' OR content LIKE '%\r\n%'"
        );
        
        // 3. explanation 필드 정리
        $explanation_cleaned = $wpdb->query(
            "UPDATE {$questions_table} 
             SET explanation = REPLACE(REPLACE(explanation, '_x000D_', '\n'), '\r\n', '\n')
             WHERE explanation LIKE '%_x000D_%' OR explanation LIKE '%\r\n%'"
        );
        
        $total_cleaned = ($content_cleaned !== false ? $content_cleaned : 0) + ($explanation_cleaned !== false ? $explanation_cleaned : 0);
        if ($total_cleaned > 0) {
            $log[] = "✅ {$total_cleaned}개 레코드의 특수문자를 정리했습니다.";
        } else {
            $log[] = "✅ 정리할 특수문자가 없습니다.";
        }
        
        // 마지막 업로드 파일 정보를 세션에 저장
        $_SESSION['last_uploaded_file'] = array(
            'original_filename' => $original_filename,
            'upload_time' => time()
        );
        
        echo json_encode(array(
            'success' => true,
            'import_count' => $import_count,
            'log' => $log,
            'original_filename' => $original_filename
        ));
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        $log[] = "❌ 데이터 삽입 실패!";
        $log[] = "오류 메시지: " . $e->getMessage();
        $log[] = "삽입 중단 시점 문제 수: " . $import_count . " (롤백됨)";
        $log[] = "오류 발생 라인: {$line_number}";
        
        if ($wpdb->last_error) {
            $log[] = "데이터베이스 오류: " . $wpdb->last_error;
        }
        
        // 실패해도 파일 정보는 저장 (다운로드 가능하도록)
        if (isset($original_filename)) {
            $_SESSION['last_uploaded_file'] = array(
                'original_filename' => $original_filename,
                'upload_time' => time()
            );
        }
        
        echo json_encode(array(
            'success' => false,
            'import_count' => $import_count,
            'log' => $log,
            'message' => $e->getMessage(),
            'original_filename' => isset($original_filename) ? $original_filename : null
        ));
    } finally {
        // 파일 닫기 및 임시 파일 정리
        if (isset($file) && is_resource($file)) {
            fclose($file);
        }
        if (isset($temp_csv_file) && file_exists($temp_csv_file)) {
            @unlink($temp_csv_file);
        }
    }
    } catch (Exception $outer_e) {
        // 최상위 에러 처리 (Excel 파서 등에서 발생하는 에러)
        echo json_encode(array(
            'success' => false,
            'import_count' => isset($import_count) ? $import_count : 0,
            'log' => isset($log) ? $log : array(),
            'message' => $outer_e->getMessage(),
            'original_filename' => isset($original_filename) ? $original_filename : null
        ));
    }
}

// TXT 파일을 CSV로 변환하는 함수
function generate_csv_from_txt() {
    $log = array();
    
    // 파일 업로드 확인
    if (!isset($_FILES['txt_file']) || $_FILES['txt_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(array(
            'success' => false,
            'message' => 'TXT 파일 업로드에 실패했습니다. 파일을 선택해주세요.'
        ));
        return;
    }
    
    $uploaded_file = $_FILES['txt_file']['tmp_name'];
    $original_filename = $_FILES['txt_file']['name'];
    
    // TXT 파일 읽기
    $text = file_get_contents($uploaded_file);
    if ($text === false) {
        echo json_encode(array(
            'success' => false,
            'message' => 'TXT 파일을 읽을 수 없습니다.'
        ));
        return;
    }
    
    $log[] = "TXT 파일 읽기 성공: " . $original_filename;
    
    // 파일명에서 연도와 회차 추출 (예: "2024년도 제52회 물리치료사 국가시험 해설.txt")
    $exam_year = null;
    $exam_session = null;
    
    if (preg_match('/(\d{4})년도/', $original_filename, $year_match)) {
        $exam_year = intval($year_match[1]);
    }
    if (preg_match('/제(\d+)회/', $original_filename, $session_match)) {
        $exam_session = intval($session_match[1]);
    }
    
    // POST로 전달된 경우 우선 사용
    if (isset($_POST['exam_year']) && !empty($_POST['exam_year'])) {
        $exam_year = intval($_POST['exam_year']);
    }
    if (isset($_POST['exam_session']) && !empty($_POST['exam_session'])) {
        $exam_session = intval($_POST['exam_session']);
    }
    
    if (!$exam_year) {
        echo json_encode(array(
            'success' => false,
            'message' => '연도를 확인할 수 없습니다. 파일명에 연도가 포함되어 있지 않거나, 직접 입력해주세요.'
        ));
        return;
    }
    
    // 정규식으로 문제별로 분리
    // 패턴: 문제번호. 문제내용\n정답: 정답\n해설 해설내용\n분류: 분류
    $pattern = '/(\d+)\.\s*(.*?)\n정답[:：]\s*([^\n]+)\n해설\s*(.*?)\n분류[:：]\s*(.*?)(?=\n\d+\.|\Z)/s';
    
    preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
    
    if (empty($matches)) {
        echo json_encode(array(
            'success' => false,
            'message' => 'TXT 파일 형식이 올바르지 않습니다. 문제를 찾을 수 없습니다.'
        ));
        return;
    }
    
    $log[] = "문제 파싱 완료: " . count($matches) . "개";
    
    try {
        // CSV 데이터를 메모리에서 생성 (파일로 저장하지 않음)
        // UTF-8 BOM 추가 (Excel에서 한글 깨짐 방지)
        $csv_data = "\xEF\xBB\xBF";
        
        // CSV 필드 이스케이프 함수
        $csv_escape = function($field) {
            $field = str_replace('"', '""', $field);
            if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
                return '"' . $field . '"';
            }
            return $field;
        };
        
        // 헤더 작성
        $header = array('exam_year', 'exam_session', 'exam_course', 'question_number', 'content', 'answer', 'explanation', 'subject');
        $csv_data .= implode(',', array_map($csv_escape, $header)) . "\n";
        
        // 데이터 작성
        $row_count = 0;
        foreach ($matches as $match) {
            $question_number = intval($match[1]); // 문제 번호
            $content = trim($match[2]); // 문제
            $answer = trim($match[3]); // 정답
            $explanation = trim($match[4]); // 해설
            $subject = trim($match[5]); // 분류
            
            // 교시 판단 (1~85는 1교시, 86~는 2교시)
            $exam_course = ($question_number <= 85) ? '1교시' : '2교시';
            
            // CSV 행 작성
            $row = array(
                $exam_year,
                $exam_session ? $exam_session : '',
                $exam_course,
                $question_number,
                $content,
                $answer,
                $explanation,
                $subject
            );
            
            $csv_data .= implode(',', array_map($csv_escape, $row)) . "\n";
            $row_count++;
        }
        
        $log[] = "CSV 데이터 생성 완료";
        $log[] = "총 {$row_count}개의 문제가 변환되었습니다.";
        $log[] = "연도: {$exam_year}, 회차: " . ($exam_session ? $exam_session : '미지정');
        
        echo json_encode(array(
            'success' => true,
            'message' => 'CSV 파일이 성공적으로 생성되었습니다.',
            'row_count' => $row_count,
            'exam_year' => $exam_year,
            'exam_session' => $exam_session,
            'log' => $log,
            'csv_data' => base64_encode($csv_data), // base64로 인코딩하여 전송
            'filename' => 'exam_data.csv'
        ));
        
    } catch (Exception $e) {
        $log[] = "예외 발생: " . $e->getMessage();
        echo json_encode(array(
            'success' => false,
            'message' => 'CSV 파일 생성 중 예외가 발생했습니다: ' . $e->getMessage(),
            'log' => $log
        ));
    }
}

// 현재 문제은행 통계 조회 함수
function get_question_statistics($wpdb) {
    $categories_table = 'ptgates_categories';
    $questions_table = 'ptgates_questions';
    
    // 년도, 회차, 교시별 문항 수 및 최근 생성일자 조회
    // questions 테이블의 created_at 컬럼 사용 (없으면 NULL 반환)
    $query = "
        SELECT 
            c.exam_year,
            c.exam_session,
            c.exam_course,
            COUNT(DISTINCT c.question_id) as question_count,
            MAX(q.created_at) as max_created_at
        FROM {$categories_table} c
        INNER JOIN {$questions_table} q ON c.question_id = q.question_id
        WHERE q.is_active = 1
        GROUP BY c.exam_year, c.exam_session, c.exam_course
        ORDER BY c.exam_year DESC, c.exam_session DESC, c.exam_course ASC
    ";
    
    $results = $wpdb->get_results($query);
    
    // 총 문항 수 조회
    $total_query = "
        SELECT COUNT(*) as total
        FROM {$questions_table}
        WHERE is_active = 1
    ";
    $total_count = $wpdb->get_var($total_query);
    
    return array(
        'statistics' => $results,
        'total_count' => $total_count
    );
}

// AJAX용 문제은행 통계 조회 함수
function get_question_statistics_ajax($wpdb) {
    $stats = get_question_statistics($wpdb);
    
    // 날짜 포맷팅
    $formatted_stats = array();
    foreach ($stats['statistics'] as $stat) {
        $formatted_date = '';
        if (isset($stat->max_created_at) && $stat->max_created_at) {
            $date_obj = new DateTime($stat->max_created_at);
            $formatted_date = $date_obj->format('Y-m-d H:i');
        }
        
        $formatted_stats[] = array(
            'exam_year' => $stat->exam_year,
            'exam_session' => $stat->exam_session,
            'exam_course' => $stat->exam_course,
            'question_count' => intval($stat->question_count),
            'formatted_date' => $formatted_date
        );
    }
    
    echo json_encode(array(
        'success' => true,
        'total_count' => intval($stats['total_count']),
        'statistics' => $formatted_stats
    ));
    // AJAX 요청인 경우 즉시 종료
    if (isset($_POST['action']) && $_POST['action'] === 'get_question_statistics') {
        // 출력 버퍼 정리 및 플러시
        while (ob_get_level()) {
            ob_end_flush();
        }
        exit;
    }
}

// 과목별 문항 수 조회 함수
function get_subject_statistics($wpdb) {
    $categories_table = 'ptgates_categories';
    $questions_table = 'ptgates_questions';
    
    // 필수 파라미터 확인
    if (!isset($_POST['exam_year']) || !isset($_POST['exam_course'])) {
        echo json_encode(array(
            'success' => false,
            'message' => '필수 파라미터가 누락되었습니다.'
        ));
        return;
    }
    
    $exam_year = intval($_POST['exam_year']);
    $exam_course = trim($_POST['exam_course']);
    $exam_session = isset($_POST['exam_session']) && !empty($_POST['exam_session']) 
        ? intval($_POST['exam_session']) 
        : null;
    
    // 과목별 문항 수 조회
    $query = $wpdb->prepare(
        "SELECT 
            c.subject,
            COUNT(DISTINCT c.question_id) as question_count
        FROM {$categories_table} c
        INNER JOIN {$questions_table} q ON c.question_id = q.question_id
        WHERE q.is_active = 1
        AND c.exam_year = %d
        AND c.exam_course = %s",
        $exam_year,
        $exam_course
    );
    
    if ($exam_session !== null) {
        $query .= $wpdb->prepare(" AND c.exam_session = %d", $exam_session);
    } else {
        $query .= " AND c.exam_session IS NULL";
    }
    
    $query .= " GROUP BY c.subject ORDER BY c.subject ASC";
    
    $results = $wpdb->get_results($query);
    
    // 총 문항 수 계산
    $total_count = 0;
    foreach ($results as $result) {
        $total_count += intval($result->question_count);
    }
    
    echo json_encode(array(
        'success' => true,
        'exam_year' => $exam_year,
        'exam_session' => $exam_session,
        'exam_course' => $exam_course,
        'subjects' => $results,
        'total_count' => $total_count
    ));
    // AJAX 요청인 경우 즉시 종료
    if (isset($_POST['action']) && $_POST['action'] === 'get_subject_statistics') {
        // 출력 버퍼 정리 및 플러시
        while (ob_get_level()) {
            ob_end_flush();
        }
        exit;
    }
}

// 대분류별 문항 수 조회 함수 (세부과목의 첫 단어로 대분류 정의)
function get_category_statistics($wpdb) {
    $categories_table = 'ptgates_categories';
    $questions_table = 'ptgates_questions';
    
    // 필수 파라미터 확인
    if (!isset($_POST['exam_year']) || !isset($_POST['exam_course'])) {
        echo json_encode(array(
            'success' => false,
            'message' => '필수 파라미터가 누락되었습니다.'
        ));
        return;
    }
    
    $exam_year = intval($_POST['exam_year']);
    $exam_course = trim($_POST['exam_course']);
    $exam_session = isset($_POST['exam_session']) && !empty($_POST['exam_session']) 
        ? intval($_POST['exam_session']) 
        : null;
    
    // 과목별 문항 수 조회 (괄호 포함)
    $query = $wpdb->prepare(
        "SELECT 
            c.subject,
            COUNT(DISTINCT c.question_id) as question_count
        FROM {$categories_table} c
        INNER JOIN {$questions_table} q ON c.question_id = q.question_id
        WHERE q.is_active = 1
        AND c.exam_year = %d
        AND c.exam_course = %s",
        $exam_year,
        $exam_course
    );
    
    if ($exam_session !== null) {
        $query .= $wpdb->prepare(" AND c.exam_session = %d", $exam_session);
    } else {
        $query .= " AND c.exam_session IS NULL";
    }
    
    $query .= " GROUP BY c.subject ORDER BY c.subject ASC";
    
    $results = $wpdb->get_results($query);
    
    // 대분류별로 그룹화 (세부과목의 첫 단어 추출)
    $category_groups = array();
    $total_count = 0;
    
    foreach ($results as $result) {
        $subject = $result->subject;
        $count = intval($result->question_count);
        
        // 세부과목의 첫 단어를 추출하여 대분류로 사용
        // 예: "해부학(골격계)" → "해부학"
        // 예: "물리치료학(운동치료)" → "물리치료학"
        // 예: "해부학 근육계" → "해부학"
        // 예: "물리적 인자치료" → "물리적 인자치료" (한 단어로 취급)
        $subject_trimmed = trim($subject);
        
        // "물리적"으로 시작하는 경우 다음 단어까지 포함
        if (preg_match('/^물리적\s+/', $subject_trimmed)) {
            // "물리적" + 다음 단어를 추출
            if (preg_match('/^물리적\s+([^\s\(\)]+)/', $subject_trimmed, $matches)) {
                $main_category = '물리적 ' . $matches[1];
            } else {
                // 다음 단어가 없으면 "물리적"만 사용
                $main_category = '물리적';
            }
        } else {
            // 공백으로 분리하여 첫 번째 단어 추출
            $words = preg_split('/[\s\(\)]+/', $subject_trimmed, 2);
            $main_category = !empty($words[0]) ? trim($words[0]) : $subject_trimmed;
        }
        
        // 대분류가 비어있으면 원본 과목명 사용
        if (empty($main_category)) {
            $main_category = $subject_trimmed;
        }
        
        if (!isset($category_groups[$main_category])) {
            $category_groups[$main_category] = 0;
        }
        
        $category_groups[$main_category] += $count;
        $total_count += $count;
    }
    
    // 배열로 변환 (정렬)
    $category_list = array();
    ksort($category_groups);
    foreach ($category_groups as $category => $count) {
        $category_list[] = array(
            'category' => $category,
            'question_count' => $count
        );
    }
    
    echo json_encode(array(
        'success' => true,
        'exam_year' => $exam_year,
        'exam_session' => $exam_session,
        'exam_course' => $exam_course,
        'categories' => $category_list,
        'total_count' => $total_count
    ));
    // AJAX 요청인 경우 즉시 종료
    if (isset($_POST['action']) && $_POST['action'] === 'get_category_statistics') {
        // 출력 버퍼 정리 및 플러시
        while (ob_get_level()) {
            ob_end_flush();
        }
        exit;
    }
}

// 특정 연도/회차/교시의 데이터 삭제 함수
function delete_exam_data($wpdb) {
    $questions_table = 'ptgates_questions';
    $categories_table = 'ptgates_categories';
    
    // 필수 파라미터 확인
    if (!isset($_POST['exam_year']) || !isset($_POST['exam_course'])) {
        echo json_encode(array(
            'success' => false,
            'message' => '필수 파라미터가 누락되었습니다.'
        ));
        return;
    }
    
    $exam_year = intval($_POST['exam_year']);
    $exam_course = trim($_POST['exam_course']);
    $exam_session = isset($_POST['exam_session']) && !empty($_POST['exam_session']) 
        ? intval($_POST['exam_session']) 
        : null;
    
    // 삭제할 문제 ID 목록 조회
    $query = $wpdb->prepare(
        "SELECT DISTINCT q.question_id 
         FROM {$questions_table} q
         INNER JOIN {$categories_table} c ON q.question_id = c.question_id
         WHERE q.is_active = 1
         AND c.exam_year = %d
         AND c.exam_course = %s",
        $exam_year,
        $exam_course
    );
    
    if ($exam_session !== null) {
        $query .= $wpdb->prepare(" AND c.exam_session = %d", $exam_session);
    } else {
        $query .= " AND c.exam_session IS NULL";
    }
    
    $question_ids = $wpdb->get_col($query);
    
    if (empty($question_ids)) {
        echo json_encode(array(
            'success' => false,
            'message' => '삭제할 데이터가 없습니다.'
        ));
        return;
    }
    
    $deleted_count = count($question_ids);
    
    // 트랜잭션 시작
    $wpdb->query('START TRANSACTION');
    
    try {
        // categories 테이블에서 삭제 (외래키 제약조건 때문에 먼저 삭제)
        $question_ids_int = array_map('intval', $question_ids);
        $question_ids_str = implode(',', $question_ids_int);
        
        // SQL 인젝션 방지: question_ids는 이미 intval로 정수 변환됨
        // 사용자 데이터 테이블에서 삭제 (외래키 제약조건이 없거나 확실하지 않은 경우를 대비해 명시적 삭제)
        $user_tables = ['ptgates_user_drawings', 'ptgates_user_memos', 'ptgates_user_notes', 'ptgates_user_states', 'ptgates_user_results'];
        foreach ($user_tables as $table) {
            // 테이블 존재 여부 확인
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
                if ($table === 'ptgates_user_notes') {
                    $wpdb->query("DELETE FROM {$table} WHERE ref_type = 'question' AND ref_id IN ({$question_ids_str})");
                } else {
                    $wpdb->query("DELETE FROM {$table} WHERE question_id IN ({$question_ids_str})");
                }
            }
        }

        // categories 테이블에서 삭제 (외래키 제약조건 때문에 먼저 삭제)
        $wpdb->query("DELETE FROM {$categories_table} WHERE question_id IN ({$question_ids_str})");
        
        // questions 테이블에서 삭제
        $wpdb->query("DELETE FROM {$questions_table} WHERE question_id IN ({$question_ids_str})");
        
        $wpdb->query('COMMIT');
        
        echo json_encode(array(
            'success' => true,
            'message' => "성공적으로 {$deleted_count}개의 문제를 삭제했습니다.",
            'deleted_count' => $deleted_count
        ));
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        echo json_encode(array(
            'success' => false,
            'message' => '데이터 삭제 중 오류가 발생했습니다: ' . $e->getMessage()
        ));
    }
}

// 시험 회차 업데이트 함수
function update_exam_session($wpdb) {
    $categories_table = 'ptgates_categories';
    
    $exam_year = isset($_POST['exam_year']) ? intval($_POST['exam_year']) : null;
    $exam_course = isset($_POST['exam_course']) ? sanitize_text_field(wp_unslash($_POST['exam_course'])) : '';
    $old_session_raw = isset($_POST['old_exam_session']) ? trim($_POST['old_exam_session']) : '';
    $new_session_raw = isset($_POST['new_exam_session']) ? trim($_POST['new_exam_session']) : '';
    
    if (empty($exam_year) || $exam_course === '') {
        echo json_encode(array(
            'success' => false,
            'message' => '필수 정보가 누락되었습니다.'
        ));
        return;
    }
    
    $old_session = ($old_session_raw === '') ? null : intval($old_session_raw);
    
    if ($new_session_raw === '') {
        $new_session = null;
    } else {
        if (!is_numeric($new_session_raw)) {
            echo json_encode(array(
                'success' => false,
                'message' => '회차는 숫자만 입력할 수 있습니다.'
            ));
            return;
        }
        $new_session = intval($new_session_raw);
    }
    
    $conditions = "exam_year = %d AND exam_course = %s";
    $condition_params = array($exam_year, $exam_course);
    if ($old_session === null) {
        $conditions .= " AND (exam_session IS NULL OR exam_session = 0)";
    } else {
        $conditions .= " AND exam_session = %d";
        $condition_params[] = $old_session;
    }
    
    if ($new_session === null) {
        $sql = $wpdb->prepare(
            "UPDATE {$categories_table} SET exam_session = NULL WHERE {$conditions}",
            $condition_params
        );
    } else {
        $params = $condition_params;
        array_unshift($params, $new_session);
        $sql = $wpdb->prepare(
            "UPDATE {$categories_table} SET exam_session = %d WHERE {$conditions}",
            $params
        );
    }
    
    $result = $wpdb->query($sql);
    
    if ($result === false) {
        echo json_encode(array(
            'success' => false,
            'message' => '회차 업데이트 중 오류가 발생했습니다: ' . $wpdb->last_error
        ));
        return;
    }
    
    if ($result === 0) {
        echo json_encode(array(
            'success' => false,
            'message' => '변경할 데이터가 없습니다. (이미 동일한 회차이거나 조건이 맞지 않습니다.)'
        ));
        return;
    }
    
    echo json_encode(array(
        'success' => true,
        'message' => '회차가 성공적으로 수정되었습니다.',
        'updated_rows' => $result,
        'new_exam_session' => $new_session
    ));
}

// 웹 인터페이스 표시 (GET 요청)
if (!$is_cli && $_SERVER['REQUEST_METHOD'] === 'GET') {
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ptgates 문제은행 DB 일괄 삽입</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
            display: inline-block;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .upload-section {
            background: #f9f9f9;
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .file-label {
            display: inline-block;
            padding: 12px 24px;
            background: #0073aa;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        
        .file-label:hover {
            background: #005a87;
        }
        
        .file-name {
            margin-top: 10px;
            color: #666;
            font-size: 14px;
        }
        
        .btn {
            padding: 12px 30px;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-primary {
            background: #00a32a;
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: #008a20;
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #666;
            color: white;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background: #555;
        }
        
        .log-section {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .log-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .log-container {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            min-height: 100px;
        }
        
        .log-entry {
            margin-bottom: 5px;
            padding: 2px 0;
        }
        
        .log-entry.success {
            color: #4ec9b0;
        }
        
        .log-entry.error {
            color: #f48771;
        }
        
        .log-entry.info {
            color: #569cd6;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 15px;
            display: none;
        }
        
        .progress-fill {
            height: 100%;
            background: #00a32a;
            transition: width 0.3s;
            width: 0%;
        }
        
        .status {
            margin-top: 15px;
            padding: 12px;
            border-radius: 4px;
            display: none;
        }
        
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .download-btn {
            margin-top: 10px;
            padding: 8px 16px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .download-btn:hover {
            background: #005a87;
        }
        
        .required-fields {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .required-fields h3 {
            font-size: 14px;
            margin-bottom: 8px;
            color: #856404;
        }
        
        .required-fields ul {
            margin-left: 20px;
            font-size: 13px;
            color: #856404;
            list-style: none;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .required-fields ul li {
            flex: 0 1 auto;
        }
        
        /* 팝업 모달 스타일 */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #005a87;
            font-size: 20px;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        .modal-close:hover {
            background: #f0f0f0;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .subject-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .subject-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .subject-item:last-child {
            border-bottom: none;
        }
        
        .subject-item:hover {
            background: #f5f5f5;
        }
        
        .subject-name {
            font-weight: 500;
            color: #333;
        }
        
        .subject-count {
            font-weight: bold;
            color: #0073aa;
            font-size: 16px;
        }
        
        .modal-footer {
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
            text-align: right;
        }
        
        .modal-total {
            font-size: 18px;
            font-weight: bold;
            color: #005a87;
        }
        
        .clickable-count {
            cursor: pointer;
            color: #0073aa;
            text-decoration: underline;
            transition: color 0.3s;
        }
        
        .clickable-count:hover {
            color: #005a87;
        }
        
        .clickable-course {
            cursor: pointer;
            color: #005a87;
            text-decoration: underline;
            transition: color 0.3s;
            font-weight: 500;
        }
        
        .clickable-course:hover {
            color: #0073aa;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .delete-row-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .delete-row-btn:hover {
            background: #c82333;
        }
        
        .delete-row-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        /* 접기/펼치기 아코디언 스타일 */
        .collapsible-section {
            margin-bottom: 15px;
        }
        
        .collapsible-header {
            cursor: pointer;
            user-select: none;
            padding: 15px;
            background: #fff9e6;
            border: 2px dashed #ffc107;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }
        
        .collapsible-header:hover {
            background: #fff3cd;
        }
        
        .collapsible-header h3 {
            margin: 0;
            color: #856404;
            font-size: 16px;
        }
        
        .collapsible-toggle {
            font-size: 18px;
            color: #856404;
            transition: transform 0.3s;
        }
        
        .collapsible-section.expanded .collapsible-toggle {
            transform: rotate(180deg);
        }
        
        .collapsible-content {
            display: none;
            padding: 20px;
            background: #fff9e6;
            border: 2px dashed #ffc107;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }
        
        .collapsible-section.expanded .collapsible-content {
            display: block !important;
        }
        
        /* CSV 컬럼 섹션 전용 스타일 */
        #csvColumnsSection .collapsible-content {
            display: none;
        }
        
        #csvColumnsSection.expanded .collapsible-content {
            display: block !important;
        }
        
        /* HOME 버튼 스타일 */
        .home-btn {
            display: inline-block;
            margin-left: 15px;
            padding: 6px 12px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
            vertical-align: middle;
        }
        
        .home-btn:hover {
            background: #005a87;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="margin-bottom: 10px;">
            <h1 style="margin-bottom: 0; display: inline-block;">📚 ptgates 문제은행 DB 일괄 삽입</h1>
            <a href="<?php echo home_url(); ?>" class="home-btn">HOME (ptgates.com)</a>
        </div>
        
        <?php
        // 현재 문제은행 통계 조회
        $stats = get_question_statistics($wpdb);
        
        // 시험회차 갯수 계산
        $unique_sessions = array();
        if (!empty($stats['statistics'])) {
            foreach ($stats['statistics'] as $stat) {
                if ($stat->exam_session !== null) {
                    $key = $stat->exam_year . '-' . $stat->exam_session;
                    $unique_sessions[$key] = true;
                }
            }
        }
        $session_count = count($unique_sessions);
        ?>
        
        <div class="statistics-section" style="background: #e8f4f8; border: 1px solid #b3d9e6; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: #005a87;">📊 문제은행 현황 </h3>
            <span style="font-size: 16px;">
                <strong>총 문항 수: <span style="color: #0073aa; font-size: 18px;"><?php echo number_format($stats['total_count']); ?></span>개</strong>
                <strong style="margin-left: 15px;">시험회차 갯수: <span style="color: #0073aa; font-size: 18px;"><?php echo number_format($session_count); ?></span>개</strong>
            </span>
            
            <?php if (!empty($stats['statistics'])): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 4px;">
                    <thead>
                        <tr style="background: #005a87; color: white;">
                            <th style="padding: 4px 8px; text-align: left; border: 1px solid #ddd; line-height: 1.2;">연도</th>
                            <th style="padding: 4px 8px; text-align: left; border: 1px solid #ddd; line-height: 1.2;">시험회차</th>
                            <th style="padding: 4px 8px; text-align: left; border: 1px solid #ddd; line-height: 1.2;">교시</th>
                            <th style="padding: 4px 8px; text-align: right; border: 1px solid #ddd; line-height: 1.2;">문항 수</th>
                            <th style="padding: 4px 8px; text-align: left; border: 1px solid #ddd; line-height: 1.2;">최근 업데이트</th>
                            <th style="padding: 4px 8px; text-align: center; border: 1px solid #ddd; line-height: 1.2;">삭제</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $prev_year = null;
                        $prev_session = null;
                        foreach ($stats['statistics'] as $index => $stat): 
                            $year = $stat->exam_year;
                            $session = $stat->exam_session;
                            $course = $stat->exam_course;
                            $count = $stat->question_count;
                            $max_created_at = isset($stat->max_created_at) ? $stat->max_created_at : null;
                            
                            // 날짜 포맷팅
                            $formatted_date = '';
                            if ($max_created_at) {
                                $date_obj = new DateTime($max_created_at);
                                $formatted_date = $date_obj->format('Y-m-d H:i');
                            }
                            
                            // 연도별 그룹핑을 위한 스타일
                            $row_class = '';
                            if ($prev_year !== null && $prev_year != $year) {
                                $row_class = 'border-top: 2px solid #005a87;';
                            }
                            $prev_year = $year;
                            $prev_session = $session;
                        ?>
                        <tr style="<?php echo $row_class; ?>">
                            <td style="padding: 2px 8px; border: 1px solid #ddd; line-height: 1.2;">
                                <?php echo htmlspecialchars($year); ?>
                            </td>
                            <td style="padding: 2px 8px; border: 1px solid #ddd; line-height: 1.2;">
                                <span class="editable-session"
                                      data-year="<?php echo htmlspecialchars($year); ?>"
                                      data-course="<?php echo htmlspecialchars($course); ?>"
                                      data-old-session="<?php echo $session !== null ? htmlspecialchars($session) : ''; ?>">
                                    <?php echo $session !== null ? htmlspecialchars($session) : '-'; ?>
                                </span>
                            </td>
                            <td style="padding: 2px 8px; border: 1px solid #ddd; line-height: 1.2;">
                                <span class="clickable-course" 
                                      data-year="<?php echo htmlspecialchars($year); ?>"
                                      data-session="<?php echo $session !== null ? htmlspecialchars($session) : ''; ?>"
                                      data-course="<?php echo htmlspecialchars($course); ?>">
                                    <?php echo htmlspecialchars($course); ?>
                                </span>
                            </td>
                            <td style="padding: 2px 8px; border: 1px solid #ddd; text-align: right; font-weight: bold; line-height: 1.2;">
                                <span class="clickable-count" 
                                      data-year="<?php echo htmlspecialchars($year); ?>"
                                      data-session="<?php echo $session !== null ? htmlspecialchars($session) : ''; ?>"
                                      data-course="<?php echo htmlspecialchars($course); ?>">
                                    <?php echo number_format($count); ?>개
                                </span>
                            </td>
                            <td style="padding: 2px 8px; border: 1px solid #ddd; line-height: 1.2; font-size: 12px; color: #666;">
                                <?php echo $formatted_date ? htmlspecialchars($formatted_date) : '-'; ?>
                            </td>
                            <td style="padding: 2px 8px; border: 1px solid #ddd; text-align: center; line-height: 1.2;">
                                <button class="delete-row-btn" 
                                        data-year="<?php echo htmlspecialchars($year); ?>"
                                        data-session="<?php echo $session !== null ? htmlspecialchars($session) : ''; ?>"
                                        data-course="<?php echo htmlspecialchars($course); ?>"
                                        data-count="<?php echo htmlspecialchars($count); ?>"
                                        title="이 행의 데이터 삭제">
                                    🗑️
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="color: #666; font-style: italic;">아직 등록된 문제가 없습니다.</p>
            <?php endif; ?>
        </div>
        
        <!-- CSV 컬럼 섹션 (접기/펼치기) -->
        <div class="collapsible-section" id="csvColumnsSection">
            <div class="collapsible-header" id="csvColumnsHeader">
                <h3>📋 CSV/Excel 컬럼 설명</h3>
                <span class="collapsible-toggle">▼</span>
            </div>
            <div class="collapsible-content">
                <ul id="csvColumnsList" style="margin: 0; padding-left: 20px; line-height: 1.6; list-style-type: disc;">
                    <?php
                    // 마지막 업로드 파일에서 헤더 읽기
                    $display_columns = array();
                    $file_path = __DIR__ . '/exam_data.csv';
                    if (file_exists($file_path)) {
                        $file = fopen($file_path, 'r');
                        if ($file) {
                            $header = fgetcsv($file, 0, ',');
                            if ($header) {
                                // BOM 제거 및 정리
                                $header = array_map(function($value) {
                                    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
                                    return trim($value);
                                }, $header);
                                $display_columns = $header;
                            }
                            fclose($file);
                        }
                    }
                    
                    // 파일이 없으면 기본 컬럼 목록 표시
                    if (empty($display_columns)) {
                        $display_columns = array('exam_course', 'question_number', 'content', 'answer', 'explanation', 'subject');
                    }
                    
                    // 컬럼 설명 매핑
                    $column_descriptions = array(
                        'exam_course' => '교시 구분',
                        'question_number' => '문제 번호',
                        'content' => '문제 본문',
                        'answer' => '정답',
                        'explanation' => '문제 해설',
                        'subject' => '과목명',
                    );
                    
                    // 필수 필드
                    $required_fields = array('content', 'answer', 'explanation','exam_course', 'subject');
                    
                    foreach ($display_columns as $col) {
                        $col_lower = strtolower($col);
                        $is_required = in_array($col_lower, $required_fields);
                        $description = isset($column_descriptions[$col_lower]) ? $column_descriptions[$col_lower] : '';
                        $required_mark = $is_required ? ' <span style="color: #dc3545;">(필수)</span>' : '';
                        echo '<li style="margin-bottom: 4px;"><strong>' . htmlspecialchars($col) . '</strong>' . $required_mark;
                        if ($description) {
                            echo ' <span style="color: #666; font-size: 13px;">- ' . htmlspecialchars($description) . '</span>';
                        }
                        echo '</li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
        
        <!-- CSV/Excel 파일 업로드 섹션 -->
        <div class="upload-section">
            <h3 style="margin-top: 0; margin-bottom: 15px; color: #333;">📁 CSV/Excel 파일 업로드</h3>
            <p style="margin-bottom: 15px; color: #666; font-size: 14px;">
                CSV 파일 또는 Excel 파일(.xlsx, .xls)을 업로드할 수 있습니다. Excel 파일은 자동으로 CSV로 변환됩니다.
            </p>
            <div class="file-input-wrapper">
                <input type="file" id="csvFile" accept=".csv,.xlsx,.xls" />
                <label for="csvFile" class="file-label">📁 CSV/Excel 파일 선택</label>
            </div>
            <div class="file-name" id="fileName">파일을 선택해주세요</div>
            
            <div style="margin-top: 20px;">
                <label style="display: block; margin-bottom: 10px;">
                    <input type="checkbox" id="overwriteMode" />
                    <span style="margin-left: 8px; color: #666;">기존 데이터 삭제 후 삽입 (덮어쓰기)</span>
                </label>
            </div>
            
            <div style="margin-top: 10px;">
                <button class="btn btn-primary" id="startBtn" disabled>🚀 시작</button>
                <button class="btn btn-secondary" id="clearBtn">초기화</button>
            </div>
        </div>
        
        <!-- TXT 파일에서 CSV 생성 섹션 (접기/펼치기) -->
        <div class="collapsible-section" id="txtToCsvSection">
            <div class="collapsible-header" id="txtToCsvHeader">
                <h3>📄 TXT 파일에서 CSV 생성</h3>
                <span class="collapsible-toggle">▼</span>
            </div>
            <div class="collapsible-content">
                <p style="margin-bottom: 15px; color: #666; font-size: 14px;">
                    TXT 파일을 업로드하면 exam_data.csv 파일이 자동으로 생성됩니다.
                </p>
                
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                        <div style="flex: 1; min-width: 150px;">
                            <label style="display: block; margin-bottom: 5px; color: #666; font-size: 14px;">연도 (선택사항)</label>
                            <input type="number" id="examYearInput" placeholder="예: 2024" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" />
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <label style="display: block; margin-bottom: 5px; color: #666; font-size: 14px;">회차 (선택사항)</label>
                            <input type="number" id="examSessionInput" placeholder="예: 52" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" />
                        </div>
                    </div>
                    <p style="margin-top: 8px; color: #856404; font-size: 12px;">
                        * 파일명에 "2024년도 제52회" 형식이 포함되어 있으면 자동으로 추출됩니다. 없으면 직접 입력해주세요.
                    </p>
                </div>
                
                <div class="file-input-wrapper">
                    <input type="file" id="txtFile" accept=".txt" />
                    <label for="txtFile" class="file-label" style="background: #ffc107; color: #000;">📄 TXT 파일 선택</label>
                </div>
                <div class="file-name" id="txtFileName">파일을 선택해주세요</div>
                
                <div style="margin-top: 15px;">
                    <button class="btn btn-primary" id="generateCsvBtn" disabled style="background: #ffc107; color: #000;">🔄 CSV 생성</button>
                    <button class="btn btn-secondary" id="clearTxtBtn">초기화</button>
                </div>
                
                <div style="margin-top: 15px; padding: 12px; background: #e7f3ff; border-left: 4px solid #0073aa; border-radius: 4px;">
                    <p style="margin: 0; color: #005a87; font-size: 14px; font-weight: 500;">
                        💡 생성된 CSV 파일을 열어서 데이터 검증 후 다음 단계 업로드 진행하세요.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="progress-bar" id="progressBar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        
        <div class="status" id="status"></div>
        
        <div id="lastFileSection">
        <?php
        // 마지막 업로드 파일 정보 확인
        $file_path = __DIR__ . '/exam_data.csv';
        $has_file = file_exists($file_path);
        $original_filename = 'exam_data.csv';
        $upload_time = '';
        
        // 세션에서 업로드 시간 가져오기
        $last_file = isset($_SESSION['last_uploaded_file']) ? $_SESSION['last_uploaded_file'] : null;
        if ($has_file && $last_file && isset($last_file['upload_time'])) {
            $upload_time = date('Y-m-d H:i:s', $last_file['upload_time']);
        } elseif ($has_file) {
            $upload_time = date('Y-m-d H:i:s', filemtime($file_path));
        }
        
        echo '<div class="status success" style="display: block;">';
        if ($has_file) {
            echo '<strong>📁 마지막 업로드 파일:</strong> ' . $original_filename;
            if ($upload_time) {
                echo ' <span style="color: #666; font-size: 12px;">(' . $upload_time . ')</span>';
            }
            echo '<br><br>';
            echo '<button class="download-btn" onclick="downloadFile()">📥 파일 다운로드</button>';
            echo '<span style="margin-left: 10px; color: #666; font-size: 14px;">"exam_data.csv"</span>';
        } else {
            echo '<strong>📁 마지막 업로드 파일:</strong> 없음';
            echo '<br><br>';
            echo '<button class="download-btn" disabled style="background: #ccc; cursor: not-allowed;">📥 파일 다운로드</button>';
        }
        echo '</div>';
        ?>
        </div>
        
        <div class="log-section">
            <div class="log-title">📋 진행 로그</div>
            <div class="log-container" id="logContainer">
                <div class="log-entry info">대기 중...</div>
            </div>
        </div>
    </div>
    
    <!-- 과목별 문항 수 팝업 모달 -->
    <div class="modal-overlay" id="subjectModal">
        <div class="modal-content">
            <button class="modal-close" id="modalClose">&times;</button>
            <div class="modal-header">
                <h3 id="modalTitle">과목별 문항 수</h3>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="loading">로딩 중...</div>
            </div>
            <div class="modal-footer">
                <div class="modal-total" id="modalTotal">총 0개</div>
            </div>
        </div>
    </div>
    
    <script>
        const csvFileInput = document.getElementById('csvFile');
        const fileNameDisplay = document.getElementById('fileName');
        const startBtn = document.getElementById('startBtn');
        const clearBtn = document.getElementById('clearBtn');
        const logContainer = document.getElementById('logContainer');
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');
        const statusDiv = document.getElementById('status');
        
        // TXT 파일 관련 변수
        const txtFileInput = document.getElementById('txtFile');
        const txtFileNameDisplay = document.getElementById('txtFileName');
        const generateCsvBtn = document.getElementById('generateCsvBtn');
        const clearTxtBtn = document.getElementById('clearTxtBtn');
        const examYearInput = document.getElementById('examYearInput');
        const examSessionInput = document.getElementById('examSessionInput');
        
        // CSV 컬럼 섹션 접기/펼치기
        const csvColumnsSection = document.getElementById('csvColumnsSection');
        const csvColumnsHeader = document.getElementById('csvColumnsHeader');
        
        if (csvColumnsHeader && csvColumnsSection) {
            csvColumnsHeader.addEventListener('click', function() {
                csvColumnsSection.classList.toggle('expanded');
                console.log('CSV 컬럼 섹션 토글:', csvColumnsSection.classList.contains('expanded'));
            });
        } else {
            console.warn('CSV 컬럼 섹션 요소를 찾을 수 없습니다.');
        }
        
        // TXT to CSV 섹션 접기/펼치기
        const txtToCsvSection = document.getElementById('txtToCsvSection');
        const txtToCsvHeader = document.getElementById('txtToCsvHeader');
        
        if (txtToCsvHeader) {
            txtToCsvHeader.addEventListener('click', function() {
                txtToCsvSection.classList.toggle('expanded');
            });
        }
        
        let isProcessing = false;
        
        // 파일 선택 이벤트
        csvFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // 파일 확장자 확인
                const fileName = file.name.toLowerCase();
                const isExcel = fileName.endsWith('.xlsx') || fileName.endsWith('.xls');
                const isCsv = fileName.endsWith('.csv');
                
                if (!isCsv && !isExcel) {
                    alert('❌ CSV 또는 Excel 파일만 업로드 가능합니다.\n\n지원 형식: .csv, .xlsx, .xls');
                    csvFileInput.value = '';
                    fileNameDisplay.textContent = '파일을 선택해주세요';
                    startBtn.disabled = true;
                    return;
                }
                
                fileNameDisplay.textContent = `선택된 파일: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
                startBtn.disabled = false;
                
                // Excel 파일인 경우 서버에서 처리하므로 컬럼 목록 업데이트 생략
                if (isExcel) {
                    // Excel 파일은 서버에서 변환 후 처리되므로 여기서는 파일명만 표시
                    updateColumnsList([]); // 빈 배열로 초기화
                    return;
                }
                
                // CSV 파일 헤더 읽어서 컬럼 목록 업데이트
                const reader = new FileReader();
                reader.onload = function(event) {
                    try {
                        const text = event.target.result;
                        
                        // 바이너리 데이터 감지 (Excel 파일 등)
                        if (text.includes('PK') && text.includes('[Content_Types].xml')) {
                            // Excel 파일이지만 .csv 확장자인 경우, 서버에서 처리하도록 허용
                            updateColumnsList([]);
                            return;
                        }
                        
                        // 텍스트가 너무 짧거나 비정상적인 경우
                        if (text.length < 10) {
                            throw new Error('파일 내용이 비어있거나 손상되었습니다.');
                        }
                        
                        // 제어 문자나 바이너리 데이터가 많은 경우 감지
                        const binaryCharCount = (text.match(/[\x00-\x08\x0E-\x1F]/g) || []).length;
                        if (binaryCharCount > text.length * 0.1) {
                            // Excel 파일일 수 있으므로 서버에서 처리하도록 허용
                            updateColumnsList([]);
                            return;
                        }
                        
                        const lines = text.split('\n');
                        if (lines.length > 0) {
                            // 첫 번째 줄에서 헤더 추출
                            const header = lines[0].split(',').map(col => col.trim().replace(/^[\xEF\xBB\xBF"]+|["\r]+$/g, ''));
                            
                            // 헤더가 비어있거나 이상한 경우
                            if (header.length === 0 || header.every(col => !col || col.length === 0)) {
                                throw new Error('CSV 헤더를 읽을 수 없습니다. 파일 형식을 확인해주세요.');
                            }
                            
                            updateColumnsList(header);
                        } else {
                            throw new Error('CSV 파일이 비어있습니다.');
                        }
                    } catch (error) {
                        console.error('파일 읽기 오류:', error);
                        // Excel 파일일 수 있으므로 서버에서 처리하도록 허용
                        updateColumnsList([]);
                    }
                };
                
                reader.onerror = function() {
                    // Excel 파일일 수 있으므로 서버에서 처리하도록 허용
                    updateColumnsList([]);
                };
                
                reader.readAsText(file, 'UTF-8');
            } else {
                fileNameDisplay.textContent = '파일을 선택해주세요';
                startBtn.disabled = true;
            }
        });
        
        // 컬럼 목록 업데이트 함수
        function updateColumnsList(columns) {
            const columnsList = document.getElementById('csvColumnsList');
            if (!columnsList) return;
            
            const columnDescriptions = {
                'exam_year': '시험 연도 (없으면 현재 연도 자동 설정)',
                'exam_session': '시험 회차 (없으면 1001회부터 자동 부여)',
                'exam_course': '교시 구분 (필수)',
                'question_number': '문제 번호',
                'content': '문제 본문 (필수)',
                'answer': '정답 (필수)',
                'explanation': '문제 해설',
                'subject': '과목명 (필수)',
                'source_company': '문제 출처'
            };
            
            const requiredFields = ['content', 'answer', 'exam_course', 'subject'];
            
            columnsList.innerHTML = '';
            columns.forEach(col => {
                const colLower = col.toLowerCase();
                const isRequired = requiredFields.includes(colLower);
                const description = columnDescriptions[colLower] || '';
                const requiredMark = isRequired ? ' <span style="color: #dc3545;">(필수)</span>' : '';
                
                const li = document.createElement('li');
                li.innerHTML = '<strong>' + escapeHtml(col) + '</strong>' + requiredMark + 
                               (description ? ': ' + escapeHtml(description) : '');
                columnsList.appendChild(li);
            });
        }
        
        // HTML 이스케이프 함수
        function escapeHtml(text) {
            // null, undefined 처리
            if (text === null || text === undefined) {
                return '';
            }
            // 숫자나 다른 타입을 문자열로 변환
            const str = String(text);
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return str.replace(/[&<>"']/g, m => map[m]);
        }
        
        // 로그 추가 함수
        function addLog(message, type = 'info') {
            const logEntry = document.createElement('div');
            logEntry.className = `log-entry ${type}`;
            logEntry.textContent = message;
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        // 마지막 파일 정보 업데이트 함수 (페이지 새로고침 없이)
        function updateLastFileInfo(filename) {
            const lastFileSection = document.getElementById('lastFileSection');
            if (!lastFileSection) return;
            
            const now = new Date();
            const uploadTime = now.toLocaleString('ko-KR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            lastFileSection.innerHTML = `
                <div class="status success" style="display: block;">
                    <strong>📁 마지막 업로드 파일:</strong> ${escapeHtml(filename)}
                    <span style="color: #666; font-size: 12px;">(${uploadTime})</span>
                    <br><br>
                    <button class="download-btn" onclick="downloadFile()">📥 파일 다운로드</button>
                    <span style="margin-left: 10px; color: #666; font-size: 14px;">"exam_data.csv"</span>
                </div>
            `;
        }
        
        // 문제은행 현황 새로고침 함수
        function refreshStatistics() {
            console.log('문제은행 현황 새로고침 시작...');
            const statisticsSection = document.querySelector('.statistics-section');
            if (!statisticsSection) {
                console.warn('통계 섹션을 찾을 수 없습니다.');
                return;
            }
            
            // 로딩 표시
            const originalContent = statisticsSection.innerHTML;
            statisticsSection.innerHTML = '<div style="text-align: center; padding: 20px;">로딩 중...</div>';
            
            // AJAX 요청
            const formData = new FormData();
            formData.append('action', 'get_question_statistics');
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href);
            console.log('AJAX 요청 전송:', window.location.href);
            
            xhr.addEventListener('load', function() {
                try {
                    if (xhr.status !== 200) {
                        throw new Error('HTTP 오류: ' + xhr.status);
                    }
                    
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        // 총 문항수 업데이트
                        const totalCountElement = statisticsSection.querySelector('p strong span');
                        if (totalCountElement) {
                            totalCountElement.textContent = numberFormat(response.total_count) + '개';
                        }
                        
                        // 테이블 업데이트
                        if (response.statistics && response.statistics.length > 0) {
                            let tableHtml = `
                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 4px;">
                                        <thead>
                                            <tr style="background: #005a87; color: white;">
                                                <th style="padding: 4px 8px; text-align: left; border: 1px solid #ddd; line-height: 1.2;">연도</th>
                                                <th style="padding: 4px 8px; text-align: left; border: 1px solid #ddd; line-height: 1.2;">시험회차</th>
                                                <th style="padding: 4px 8px; text-align: left; border: 1px solid #ddd; line-height: 1.2;">교시</th>
                                                <th style="padding: 4px 8px; text-align: right; border: 1px solid #ddd; line-height: 1.2;">문항 수</th>
                                                <th style="padding: 4px 8px; text-align: left; border: 1px solid #ddd; line-height: 1.2;">최근 업데이트</th>
                                                <th style="padding: 4px 8px; text-align: center; border: 1px solid #ddd; line-height: 1.2;">삭제</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                            `;
                            
                            let prevYear = null;
                            response.statistics.forEach(function(stat) {
                                const year = stat.exam_year;
                                const session = stat.exam_session;
                                const course = stat.exam_course;
                                const count = stat.question_count;
                                const formattedDate = stat.formatted_date || '-';
                                
                                // 연도별 그룹핑을 위한 스타일
                                let rowStyle = '';
                                if (prevYear !== null && prevYear != year) {
                                    rowStyle = 'border-top: 2px solid #005a87;';
                                }
                                prevYear = year;
                                
                                tableHtml += `
                                    <tr style="${rowStyle}">
                                        <td style="padding: 2px 8px; border: 1px solid #ddd; line-height: 1.2;">${escapeHtml(year)}</td>
                                        <td style="padding: 2px 8px; border: 1px solid #ddd; line-height: 1.2;">
                                            <span class="editable-session"
                                                  data-year="${escapeHtml(year)}"
                                                  data-course="${escapeHtml(course)}"
                                                  data-old-session="${session !== null ? escapeHtml(session) : ''}">
                                                ${session !== null ? escapeHtml(session) : '-'}
                                            </span>
                                        </td>
                                        <td style="padding: 2px 8px; border: 1px solid #ddd; line-height: 1.2;">
                                            <span class="clickable-course" 
                                                  data-year="${escapeHtml(year)}"
                                                  data-session="${session !== null ? escapeHtml(session) : ''}"
                                                  data-course="${escapeHtml(course)}">
                                                ${escapeHtml(course)}
                                            </span>
                                        </td>
                                        <td style="padding: 2px 8px; border: 1px solid #ddd; text-align: right; font-weight: bold; line-height: 1.2;">
                                            <span class="clickable-count" 
                                                  data-year="${escapeHtml(year)}"
                                                  data-session="${session !== null ? escapeHtml(session) : ''}"
                                                  data-course="${escapeHtml(course)}">
                                                ${numberFormat(count)}개
                                            </span>
                                        </td>
                                        <td style="padding: 2px 8px; border: 1px solid #ddd; line-height: 1.2; font-size: 12px; color: #666;">${escapeHtml(formattedDate)}</td>
                                        <td style="padding: 2px 8px; border: 1px solid #ddd; text-align: center; line-height: 1.2;">
                                            <button class="delete-row-btn" 
                                                    data-year="${escapeHtml(year)}"
                                                    data-session="${session !== null ? escapeHtml(session) : ''}"
                                                    data-course="${escapeHtml(course)}"
                                                    data-count="${escapeHtml(count)}"
                                                    title="이 행의 데이터 삭제">
                                                🗑️
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });
                            
                            tableHtml += `
                                        </tbody>
                                    </table>
                                </div>
                            `;
                            
                            // 시험회차 갯수 계산
                            const uniqueSessions = new Set();
                            if (response.statistics && response.statistics.length > 0) {
                                response.statistics.forEach(function(stat) {
                                    if (stat.exam_session !== null) {
                                        uniqueSessions.add(stat.exam_year + '-' + stat.exam_session);
                                    }
                                });
                            }
                            const sessionCount = uniqueSessions.size;

                            // 통계 섹션 업데이트
                            statisticsSection.innerHTML = `
                                <h3 style="margin-top: 0; color: #005a87;">📊 현재 문제은행 현황</h3>
                                <span style="font-size: 16px;">
                                    <strong>총 문항 수: <span style="color: #0073aa; font-size: 18px;">${numberFormat(response.total_count)}</span>개</strong>
                                    <strong style="margin-left: 15px;">시험회차 갯수: <span style="color: #0073aa; font-size: 18px;">${numberFormat(sessionCount)}</span>개</strong>
                                </span>
                                <div style="margin-bottom: 15px;"></div>
                                ${tableHtml}
                            `;
                            
                            // 이벤트 리스너 다시 연결
                            attachStatisticsEventListeners();
                            
                            // 새로고침 완료 로그
                            console.log('문제은행 현황이 성공적으로 새로고침되었습니다.');
                        } else {
                            statisticsSection.innerHTML = `
                                <h3 style="margin-top: 0; color: #005a87;">📊 현재 문제은행 현황</h3>
                                <span style="font-size: 16px;">
                                    <strong>총 문항 수: <span style="color: #0073aa; font-size: 18px;">${numberFormat(response.total_count)}</span>개</strong>
                                    <strong style="margin-left: 15px;">시험회차 갯수: <span style="color: #0073aa; font-size: 18px;">0</span>개</strong>
                                </span>
                                <div style="margin-bottom: 15px;"></div>
                                <p style="color: #666; font-style: italic;">아직 등록된 문제가 없습니다.</p>
                            `;
                            console.log('문제은행 현황이 새로고침되었습니다. (등록된 문제 없음)');
                        }
                    } else {
                        // 실패 시 원래 내용 복원
                        statisticsSection.innerHTML = originalContent;
                    }
                } catch (e) {
                    console.error('통계 새로고침 오류:', e);
                    console.error('응답 텍스트:', xhr.responseText);
                    // 오류 시 원래 내용 복원
                    statisticsSection.innerHTML = originalContent;
                    // 사용자에게 알림
                    const statusDiv = document.getElementById('status');
                    if (statusDiv) {
                        statusDiv.className = 'status warning';
                        statusDiv.innerHTML = '⚠️ 통계 새로고침 중 오류가 발생했습니다. 페이지를 새로고침해주세요.';
                        statusDiv.style.display = 'block';
                    }
                }
            });
            
            xhr.addEventListener('error', function() {
                console.error('통계 새로고침 네트워크 오류');
                // 오류 시 원래 내용 복원
                statisticsSection.innerHTML = originalContent;
                // 사용자에게 알림
                const statusDiv = document.getElementById('status');
                if (statusDiv) {
                    statusDiv.className = 'status warning';
                    statusDiv.innerHTML = '⚠️ 통계 새로고침 중 네트워크 오류가 발생했습니다. 페이지를 새로고침해주세요.';
                    statusDiv.style.display = 'block';
                }
            });
            
            xhr.addEventListener('timeout', function() {
                console.error('통계 새로고침 타임아웃');
                statisticsSection.innerHTML = originalContent;
                const statusDiv = document.getElementById('status');
                if (statusDiv) {
                    statusDiv.className = 'status warning';
                    statusDiv.innerHTML = '⚠️ 통계 새로고침이 시간 초과되었습니다. 페이지를 새로고침해주세요.';
                    statusDiv.style.display = 'block';
                }
            });
            
            // 타임아웃 설정 (10초)
            xhr.timeout = 10000;
            
            xhr.send(formData);
        }
        
        // 숫자 포맷팅 함수
        function numberFormat(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        // 통계 섹션 이벤트 리스너 연결 함수
        function attachStatisticsEventListeners() {
            // 문항 수 클릭 이벤트
            const clickableCounts = document.querySelectorAll('.clickable-count');
            clickableCounts.forEach(function(countElement) {
                // 기존 이벤트 리스너 제거 (중복 방지)
                const newElement = countElement.cloneNode(true);
                countElement.parentNode.replaceChild(newElement, countElement);
                
                newElement.addEventListener('click', function() {
                    const year = this.getAttribute('data-year');
                    const session = this.getAttribute('data-session');
                    const course = this.getAttribute('data-course');
                    
                    // 모달 제목 설정
                    let titleText = `${year}년`;
                    if (session) {
                        titleText += ` 제${session}회`;
                    }
                    titleText += ` ${course} 과목별 문항 수`;
                    modalTitle.textContent = titleText;
                    
                    // 모달 열기
                    subjectModal.classList.add('active');
                    modalBody.innerHTML = '<div class="loading">로딩 중...</div>';
                    modalTotal.textContent = '총 0개';
                    
                    // AJAX 요청
                    const formData = new FormData();
                    formData.append('action', 'get_subject_statistics');
                    formData.append('exam_year', year);
                    formData.append('exam_course', course);
                    if (session) {
                        formData.append('exam_session', session);
                    }
                    
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', window.location.href);
                    
                    xhr.addEventListener('load', function() {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            if (response.success) {
                                // 과목 목록 표시
                                if (response.subjects && response.subjects.length > 0) {
                                    let html = '<ul class="subject-list">';
                                    response.subjects.forEach(function(subject) {
                                        html += '<li class="subject-item">';
                                        html += '<span class="subject-name">' + escapeHtml(subject.subject) + '</span>';
                                        html += '<span class="subject-count">' + numberFormat(subject.question_count) + '개</span>';
                                        html += '</li>';
                                    });
                                    html += '</ul>';
                                    modalBody.innerHTML = html;
                                } else {
                                    modalBody.innerHTML = '<div class="loading">과목 데이터가 없습니다.</div>';
                                }
                                
                                // 총 문항 수 표시
                                modalTotal.textContent = '총 ' + numberFormat(response.total_count) + '개';
                            } else {
                                modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">오류: ' + escapeHtml(response.message || '데이터를 불러올 수 없습니다.') + '</div>';
                            }
                        } catch (e) {
                            modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">응답 처리 중 오류가 발생했습니다: ' + escapeHtml(e.message) + '</div>';
                        }
                    });
                    
                    xhr.addEventListener('error', function() {
                        modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">네트워크 오류가 발생했습니다.</div>';
                    });
                    
                    xhr.send(formData);
                });
            });
            
            // 교시 클릭 이벤트
            const clickableCourses = document.querySelectorAll('.clickable-course');
            clickableCourses.forEach(function(courseElement) {
                // 기존 이벤트 리스너 제거 (중복 방지)
                const newElement = courseElement.cloneNode(true);
                courseElement.parentNode.replaceChild(newElement, courseElement);
                
                newElement.addEventListener('click', function() {
                    const year = this.getAttribute('data-year');
                    const session = this.getAttribute('data-session');
                    const course = this.getAttribute('data-course');
                    
                    // 모달 제목 설정
                    let titleText = `${year}년`;
                    if (session) {
                        titleText += ` 제${session}회`;
                    }
                    titleText += ` ${course} 대분류별 문항 수`;
                    modalTitle.textContent = titleText;
                    
                    // 모달 열기
                    subjectModal.classList.add('active');
                    modalBody.innerHTML = '<div class="loading">로딩 중...</div>';
                    modalTotal.textContent = '총 0개';
                    
                    // AJAX 요청
                    const formData = new FormData();
                    formData.append('action', 'get_category_statistics');
                    formData.append('exam_year', year);
                    formData.append('exam_course', course);
                    if (session) {
                        formData.append('exam_session', session);
                    }
                    
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', window.location.href);
                    
                    xhr.addEventListener('load', function() {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            if (response.success) {
                                // 대분류 목록 표시
                                if (response.categories && response.categories.length > 0) {
                                    let html = '<ul class="subject-list">';
                                    response.categories.forEach(function(category) {
                                        html += '<li class="subject-item">';
                                        html += '<span class="subject-name">' + escapeHtml(category.category) + '</span>';
                                        html += '<span class="subject-count">' + numberFormat(category.question_count) + '개</span>';
                                        html += '</li>';
                                    });
                                    html += '</ul>';
                                    modalBody.innerHTML = html;
                                } else {
                                    modalBody.innerHTML = '<div class="loading">대분류 데이터가 없습니다.</div>';
                                }
                                
                                // 총 문항 수 표시
                                modalTotal.textContent = '총 ' + numberFormat(response.total_count) + '개';
                            } else {
                                modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">오류: ' + escapeHtml(response.message || '데이터를 불러올 수 없습니다.') + '</div>';
                            }
                        } catch (e) {
                            modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">응답 처리 중 오류가 발생했습니다: ' + escapeHtml(e.message) + '</div>';
                        }
                    });
                    
                    xhr.addEventListener('error', function() {
                        modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">네트워크 오류가 발생했습니다.</div>';
                    });
                    
                    xhr.send(formData);
                });
            });
            
            // 삭제 버튼 클릭 이벤트
            const deleteRowBtns = document.querySelectorAll('.delete-row-btn');
            deleteRowBtns.forEach(function(deleteBtn) {
                // 기존 이벤트 리스너 제거 (중복 방지)
                const newElement = deleteBtn.cloneNode(true);
                deleteBtn.parentNode.replaceChild(newElement, deleteBtn);
                
                newElement.addEventListener('click', function() {
                    const year = this.getAttribute('data-year');
                    const session = this.getAttribute('data-session');
                    const course = this.getAttribute('data-course');
                    const count = this.getAttribute('data-count');
                    
                    // 삭제 확인
                    let confirmMessage = `${year}년`;
                    if (session) {
                        confirmMessage += ` 제${session}회`;
                    }
                    confirmMessage += ` ${course}의 ${count}개 문제를 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.`;
                    
                    if (!confirm(confirmMessage)) {
                        return;
                    }
                    
                    // 버튼 비활성화
                    this.disabled = true;
                    this.textContent = '삭제 중...';
                    
                    // AJAX 요청
                    const formData = new FormData();
                    formData.append('action', 'delete_exam_data');
                    formData.append('exam_year', year);
                    formData.append('exam_course', course);
                    if (session) {
                        formData.append('exam_session', session);
                    }
                    
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', window.location.href);
                    
                    xhr.addEventListener('load', function() {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            if (response.success) {
                                alert('✅ ' + response.message);
                                // 통계 새로고침
                                refreshStatistics();
                            } else {
                                alert('❌ 삭제 실패: ' + response.message);
                                // 버튼 복원
                                newElement.disabled = false;
                                newElement.textContent = '🗑️';
                            }
                        } catch (e) {
                            alert('❌ 응답 처리 중 오류가 발생했습니다: ' + e.message);
                            // 버튼 복원
                            newElement.disabled = false;
                            newElement.textContent = '🗑️';
                        }
                    });
                    
                    xhr.addEventListener('error', function() {
                        alert('❌ 네트워크 오류가 발생했습니다.');
                        // 버튼 복원
                        newElement.disabled = false;
                        newElement.textContent = '🗑️';
                    });
                    
                    xhr.send(formData);
                });
            });
            
            // 시험회차 수정 이벤트
            const editableSessions = document.querySelectorAll('.editable-session');
            editableSessions.forEach(function(sessionElement) {
                if (sessionElement.getAttribute('data-edit-listener') === 'true') {
                    return;
                }
                sessionElement.setAttribute('data-edit-listener', 'true');
                
                sessionElement.addEventListener('click', function() {
                    const year = this.getAttribute('data-year');
                    const course = this.getAttribute('data-course');
                    const oldSession = this.getAttribute('data-old-session') || '';
                    const currentDisplay = this.textContent.trim();
                    const defaultValue = currentDisplay === '-' ? '' : currentDisplay;
                    
                    const newValue = prompt('새 시험 회차를 입력하세요. 비우면 회차 정보가 제거됩니다.', defaultValue);
                    if (newValue === null) {
                        return;
                    }
                    
                    const trimmed = newValue.trim();
                    if (trimmed !== '' && !/^\d+$/.test(trimmed)) {
                        alert('회차는 숫자만 입력해 주세요.');
                        return;
                    }
                    
                    const spanEl = this;
                    const originalText = spanEl.textContent;
                    spanEl.textContent = '...';
                    spanEl.classList.add('updating-session');
                    
                    const formData = new FormData();
                    formData.append('action', 'update_exam_session');
                    formData.append('exam_year', year);
                    formData.append('exam_course', course);
                    formData.append('old_exam_session', oldSession);
                    formData.append('new_exam_session', trimmed);
                    
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', window.location.href);
                    
                    xhr.addEventListener('load', function() {
                        spanEl.classList.remove('updating-session');
                        if (xhr.status !== 200) {
                            spanEl.textContent = originalText;
                            alert('회차 수정 중 오류가 발생했습니다. (' + xhr.status + ')');
                            return;
                        }
                        
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                const displayValue = trimmed === '' ? '-' : trimmed;
                                spanEl.textContent = displayValue;
                                spanEl.setAttribute('data-old-session', trimmed);
                                alert(response.message || '회차가 수정되었습니다.');
                                refreshStatistics();
                            } else {
                                spanEl.textContent = originalText;
                                alert(response.message || '회차 수정에 실패했습니다.');
                            }
                        } catch (e) {
                            spanEl.textContent = originalText;
                            alert('회차 수정 중 응답 처리 오류가 발생했습니다.');
                            console.error(e);
                        }
                    });
                    
                    xhr.addEventListener('error', function() {
                        spanEl.classList.remove('updating-session');
                        spanEl.textContent = originalText;
                        alert('네트워크 오류로 회차를 수정하지 못했습니다.');
                    });
                    
                    xhr.send(formData);
                });
            });
        }

        // 초기 로드 시 이벤트 연결
        attachStatisticsEventListeners();
        
        // 시작 버튼 클릭
        startBtn.addEventListener('click', function() {
            if (isProcessing) return;
            
            const file = csvFileInput.files[0];
            if (!file) {
                alert('파일을 선택해주세요.');
                return;
            }
            
            const fileName = file.name.toLowerCase();
            const isExcel = fileName.endsWith('.xlsx') || fileName.endsWith('.xls');
            const isCsv = fileName.endsWith('.csv');
            
            if (!isCsv && !isExcel) {
                alert('CSV 또는 Excel 파일만 업로드 가능합니다.\n\n지원 형식: .csv, .xlsx, .xls');
                return;
            }
            
            // UI 초기화
            logContainer.innerHTML = '';
            statusDiv.style.display = 'none';
            progressBar.style.display = 'block';
            progressFill.style.width = '0%';
            isProcessing = true;
            startBtn.disabled = true;
            startBtn.textContent = '처리 중...';
            
            addLog('파일 업로드 시작...', 'info');
            addLog(`파일명: ${file.name}`, 'info');
            
            // FormData 생성
            const formData = new FormData();
            formData.append('csv_file', file);
            formData.append('action', 'import_csv');
            
            // 덮어쓰기 모드 옵션 추가
            const overwriteMode = document.getElementById('overwriteMode').checked;
            formData.append('overwrite', overwriteMode ? '1' : '0');
            
            if (overwriteMode) {
                addLog('⚠️ 덮어쓰기 모드: 기존 데이터를 삭제합니다.', 'info');
            }
            
            // AJAX 요청
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressFill.style.width = percentComplete + '%';
                }
            });
            
            xhr.addEventListener('load', function() {
                progressFill.style.width = '100%';
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    // 로그 표시
                    if (response.log && response.log.length > 0) {
                        response.log.forEach(log => {
                            let logType = 'info';
                            if (log.includes('✅') || log.includes('성공')) {
                                logType = 'success';
                            } else if (log.includes('❌') || log.includes('오류') || log.includes('실패')) {
                                logType = 'error';
                            }
                            addLog(log, logType);
                        });
                    }
                    
                    // 상태 메시지
                    if (response.success) {
                        statusDiv.className = 'status success';
                        statusDiv.innerHTML = `✅ 성공적으로 ${response.import_count}개의 문제를 삽입했습니다!`;
                        statusDiv.style.display = 'block';
                        
                        // 파일 정보가 있으면 마지막 파일 정보 업데이트 (페이지 새로고침 없이)
                        if (response.original_filename) {
                            updateLastFileInfo(response.original_filename);
                        }
                        
                        // 문제은행 현황 목록 새로고침 (DB 커밋 완료 대기 후 실행)
                        setTimeout(function() {
                            refreshStatistics();
                        }, 500);
                    } else {
                        statusDiv.className = 'status error';
                        let errorMessage = response.message || '데이터 삽입에 실패했습니다.';
                        // \n을 <br>로 변환하여 줄바꿈 표시
                        errorMessage = errorMessage.replace(/\n/g, '<br>');
                        statusDiv.innerHTML = `❌ 오류: ${escapeHtml(errorMessage)}`;
                        statusDiv.style.display = 'block';
                        
                        // 실패해도 파일이 있으면 마지막 파일 정보 업데이트 (페이지 새로고침 없이)
                        if (response.original_filename) {
                            updateLastFileInfo(response.original_filename);
                        }
                    }
                    
                } catch (e) {
                    addLog('응답 파싱 오류: ' + e.message, 'error');
                    statusDiv.className = 'status error';
                    statusDiv.textContent = '응답 처리 중 오류가 발생했습니다.';
                    statusDiv.style.display = 'block';
                }
                
                isProcessing = false;
                startBtn.disabled = false;
                startBtn.textContent = '🚀 시작';
                progressBar.style.display = 'none';
            });
            
            xhr.addEventListener('error', function() {
                addLog('네트워크 오류가 발생했습니다.', 'error');
                statusDiv.className = 'status error';
                statusDiv.textContent = '네트워크 오류가 발생했습니다.';
                statusDiv.style.display = 'block';
                isProcessing = false;
                startBtn.disabled = false;
                startBtn.textContent = '🚀 시작';
                progressBar.style.display = 'none';
            });
            
            xhr.open('POST', window.location.href);
            xhr.send(formData);
        });
        
        // 초기화 버튼
        clearBtn.addEventListener('click', function() {
            csvFileInput.value = '';
            fileNameDisplay.textContent = '파일을 선택해주세요';
            logContainer.innerHTML = '<div class="log-entry info">대기 중...</div>';
            statusDiv.style.display = 'none';
            progressBar.style.display = 'none';
            startBtn.disabled = true;
            isProcessing = false;
            startBtn.textContent = '🚀 시작';
        });
        
        // 파일 다운로드 함수
        function downloadFile() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'download_file';
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // TXT 파일 선택 이벤트
        txtFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                txtFileNameDisplay.textContent = `선택된 파일: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
                generateCsvBtn.disabled = false;
                
                // 파일명에서 연도와 회차 추출하여 입력 필드에 자동 입력
                const filename = file.name;
                const yearMatch = filename.match(/(\d{4})년도/);
                const sessionMatch = filename.match(/제(\d+)회/);
                
                if (yearMatch && !examYearInput.value) {
                    examYearInput.value = yearMatch[1];
                }
                if (sessionMatch && !examSessionInput.value) {
                    examSessionInput.value = sessionMatch[1];
                }
            } else {
                txtFileNameDisplay.textContent = '파일을 선택해주세요';
                generateCsvBtn.disabled = true;
            }
        });
        
        // CSV 생성 버튼 클릭
        generateCsvBtn.addEventListener('click', function() {
            if (isProcessing) return;
            
            const file = txtFileInput.files[0];
            if (!file) {
                alert('TXT 파일을 선택해주세요.');
                return;
            }
            
            if (!file.name.toLowerCase().endsWith('.txt')) {
                alert('TXT 파일만 업로드 가능합니다.');
                return;
            }
            
            // UI 초기화
            logContainer.innerHTML = '';
            statusDiv.style.display = 'none';
            progressBar.style.display = 'block';
            progressFill.style.width = '0%';
            isProcessing = true;
            generateCsvBtn.disabled = true;
            generateCsvBtn.textContent = '처리 중...';
            
            addLog('TXT 파일 처리 시작...', 'info');
            addLog(`파일명: ${file.name}`, 'info');
            
            // FormData 생성
            const formData = new FormData();
            formData.append('txt_file', file);
            formData.append('action', 'generate_csv_from_txt');
            
            // 연도와 회차 추가
            if (examYearInput.value) {
                formData.append('exam_year', examYearInput.value);
            }
            if (examSessionInput.value) {
                formData.append('exam_session', examSessionInput.value);
            }
            
            // AJAX 요청
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressFill.style.width = percentComplete + '%';
                }
            });
            
            xhr.addEventListener('load', function() {
                progressFill.style.width = '100%';
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    // 로그 표시
                    if (response.log && response.log.length > 0) {
                        response.log.forEach(log => {
                            let logType = 'info';
                            if (log.includes('✅') || log.includes('성공') || log.includes('완료')) {
                                logType = 'success';
                            } else if (log.includes('❌') || log.includes('오류') || log.includes('실패')) {
                                logType = 'error';
                            }
                            addLog(log, logType);
                        });
                    }
                    
                    // 상태 메시지
                    if (response.success) {
                        statusDiv.className = 'status success';
                        statusDiv.innerHTML = `✅ CSV 파일이 성공적으로 생성되었습니다!<br>총 ${response.row_count}개의 문제가 변환되었습니다.`;
                        statusDiv.style.display = 'block';
                        
                        // CSV 데이터가 있으면 다운로드
                        if (response.csv_data && response.filename) {
                            try {
                                // base64 디코딩 후 UTF-8 바이트 배열로 변환
                                const binaryString = atob(response.csv_data);
                                const bytes = new Uint8Array(binaryString.length);
                                for (let i = 0; i < binaryString.length; i++) {
                                    bytes[i] = binaryString.charCodeAt(i);
                                }
                                
                                // Blob 생성 (UTF-8 BOM 포함)
                                const blob = new Blob([bytes], { type: 'text/csv;charset=utf-8;' });
                                
                                // 다운로드 링크 생성
                                const link = document.createElement('a');
                                const url = URL.createObjectURL(blob);
                                
                                link.setAttribute('href', url);
                                link.setAttribute('download', response.filename);
                                link.style.display = 'none';
                                
                                document.body.appendChild(link);
                                link.click();
                                
                                // 정리
                                document.body.removeChild(link);
                                URL.revokeObjectURL(url);
                                
                                addLog('CSV 파일 다운로드 시작됨', 'success');
                            } catch (e) {
                                addLog('CSV 다운로드 오류: ' + e.message, 'error');
                                statusDiv.innerHTML += '<br><br>⚠️ CSV 다운로드 중 오류가 발생했습니다: ' + e.message;
                            }
                        }
                    } else {
                        statusDiv.className = 'status error';
                        let errorMsg = `❌ 오류: ${response.message || 'CSV 생성에 실패했습니다.'}`;
                        
                        // 오류 상세 정보 표시
                        if (response.error_details) {
                            errorMsg += '<br><br><strong>오류 상세 정보:</strong><br>';
                            errorMsg += `파일 경로: ${response.error_details.csv_path || '알 수 없음'}<br>`;
                            errorMsg += `디렉토리 존재: ${response.error_details.directory_exists ? '예' : '아니오'}<br>`;
                            errorMsg += `디렉토리 쓰기 권한: ${response.error_details.directory_writable ? '예' : '아니오'}<br>`;
                            if (response.error_details.file_exists !== null) {
                                errorMsg += `파일 존재: ${response.error_details.file_exists ? '예' : '아니오'}<br>`;
                            }
                            if (response.error_details.file_writable !== null) {
                                errorMsg += `파일 쓰기 권한: ${response.error_details.file_writable ? '예' : '아니오'}<br>`;
                            }
                            if (response.error_details.current_user) {
                                errorMsg += `현재 사용자: ${response.error_details.current_user}<br>`;
                            }
                            if (response.error_details.file_owner) {
                                errorMsg += `파일 소유자: ${response.error_details.file_owner}<br>`;
                            }
                            if (response.error_details.dir_owner) {
                                errorMsg += `디렉토리 소유자: ${response.error_details.dir_owner}<br>`;
                            }
                            if (response.error_details.php_error && response.error_details.php_error.message) {
                                errorMsg += `PHP 오류: ${response.error_details.php_error.message}<br>`;
                            }
                            if (response.error_details.fix_commands && response.error_details.fix_commands.length > 0) {
                                errorMsg += '<br><strong>권한 수정 명령어:</strong><br>';
                                errorMsg += '<div style="background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-top: 10px;">';
                                response.error_details.fix_commands.forEach((cmd, idx) => {
                                    errorMsg += `${idx + 1}. ${cmd}<br>`;
                                });
                                errorMsg += '</div>';
                            }
                            if (response.error_details.fix_command) {
                                errorMsg += '<br><strong>권한 수정 명령어:</strong><br>';
                                errorMsg += '<div style="background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-top: 10px;">';
                                errorMsg += response.error_details.fix_command;
                                errorMsg += '</div>';
                            }
                        }
                        
                        statusDiv.innerHTML = errorMsg;
                        statusDiv.style.display = 'block';
                    }
                    
                } catch (e) {
                    addLog('응답 파싱 오류: ' + e.message, 'error');
                    statusDiv.className = 'status error';
                    statusDiv.textContent = '응답 처리 중 오류가 발생했습니다.';
                    statusDiv.style.display = 'block';
                }
                
                isProcessing = false;
                generateCsvBtn.disabled = false;
                generateCsvBtn.textContent = '🔄 CSV 생성';
                progressBar.style.display = 'none';
            });
            
            xhr.addEventListener('error', function() {
                addLog('네트워크 오류가 발생했습니다.', 'error');
                statusDiv.className = 'status error';
                statusDiv.textContent = '네트워크 오류가 발생했습니다.';
                statusDiv.style.display = 'block';
                isProcessing = false;
                generateCsvBtn.disabled = false;
                generateCsvBtn.textContent = '🔄 CSV 생성';
                progressBar.style.display = 'none';
            });
            
            xhr.open('POST', window.location.href);
            xhr.send(formData);
        });
        
        // TXT 파일 초기화 버튼
        clearTxtBtn.addEventListener('click', function() {
            txtFileInput.value = '';
            txtFileNameDisplay.textContent = '파일을 선택해주세요';
            examYearInput.value = '';
            examSessionInput.value = '';
            logContainer.innerHTML = '<div class="log-entry info">대기 중...</div>';
            statusDiv.style.display = 'none';
            progressBar.style.display = 'none';
            generateCsvBtn.disabled = true;
            isProcessing = false;
            generateCsvBtn.textContent = '🔄 CSV 생성';
        });
        
        // 과목별 문항 수 팝업 모달 관련
        const subjectModal = document.getElementById('subjectModal');
        const modalClose = document.getElementById('modalClose');
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        const modalTotal = document.getElementById('modalTotal');
        const clickableCounts = document.querySelectorAll('.clickable-count');
        
        // 모달 닫기
        function closeModal() {
            subjectModal.classList.remove('active');
        }
        
        modalClose.addEventListener('click', closeModal);
        subjectModal.addEventListener('click', function(e) {
            if (e.target === subjectModal) {
                closeModal();
            }
        });
        
        // ESC 키로 모달 닫기
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && subjectModal.classList.contains('active')) {
                closeModal();
            }
        });
        
        // 문항 수 클릭 이벤트
        clickableCounts.forEach(function(countElement) {
            countElement.addEventListener('click', function() {
                const year = this.getAttribute('data-year');
                const session = this.getAttribute('data-session');
                const course = this.getAttribute('data-course');
                
                // 모달 제목 설정
                let titleText = `${year}년`;
                if (session) {
                    titleText += ` 제${session}회`;
                }
                titleText += ` ${course} 과목별 문항 수`;
                modalTitle.textContent = titleText;
                
                // 모달 열기
                subjectModal.classList.add('active');
                modalBody.innerHTML = '<div class="loading">로딩 중...</div>';
                modalTotal.textContent = '총 0개';
                
                // AJAX 요청
                const formData = new FormData();
                formData.append('action', 'get_subject_statistics');
                formData.append('exam_year', year);
                formData.append('exam_course', course);
                if (session) {
                    formData.append('exam_session', session);
                }
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href);
                
                xhr.addEventListener('load', function() {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // 과목 목록 표시
                            if (response.subjects && response.subjects.length > 0) {
                                let html = '<ul class="subject-list">';
                                response.subjects.forEach(function(subject) {
                                    html += '<li class="subject-item">';
                                    html += '<span class="subject-name">' + escapeHtml(subject.subject) + '</span>';
                                    html += '<span class="subject-count">' + number_format(subject.question_count) + '개</span>';
                                    html += '</li>';
                                });
                                html += '</ul>';
                                modalBody.innerHTML = html;
                            } else {
                                modalBody.innerHTML = '<div class="loading">과목 데이터가 없습니다.</div>';
                            }
                            
                            // 총 문항 수 표시
                            modalTotal.textContent = '총 ' + number_format(response.total_count) + '개';
                        } else {
                            modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">오류: ' + escapeHtml(response.message || '데이터를 불러올 수 없습니다.') + '</div>';
                        }
                    } catch (e) {
                        modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">응답 처리 중 오류가 발생했습니다: ' + escapeHtml(e.message) + '</div>';
                    }
                });
                
                xhr.addEventListener('error', function() {
                    modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">네트워크 오류가 발생했습니다.</div>';
                });
                
                xhr.send(formData);
            });
        });
        
        // 숫자 포맷팅 함수 (천 단위 구분)
        function number_format(num) {
            return parseInt(num).toLocaleString('ko-KR');
        }
        
        // 교시 클릭 이벤트 (대분류별 문항 수)
        const clickableCourses = document.querySelectorAll('.clickable-course');
        
        clickableCourses.forEach(function(courseElement) {
            courseElement.addEventListener('click', function() {
                const year = this.getAttribute('data-year');
                const session = this.getAttribute('data-session');
                const course = this.getAttribute('data-course');
                
                // 모달 제목 설정
                let titleText = `${year}년`;
                if (session) {
                    titleText += ` 제${session}회`;
                }
                titleText += ` ${course} 대분류별 문항 수`;
                modalTitle.textContent = titleText;
                
                // 모달 열기
                subjectModal.classList.add('active');
                modalBody.innerHTML = '<div class="loading">로딩 중...</div>';
                modalTotal.textContent = '총 0개';
                
                // AJAX 요청
                const formData = new FormData();
                formData.append('action', 'get_category_statistics');
                formData.append('exam_year', year);
                formData.append('exam_course', course);
                if (session) {
                    formData.append('exam_session', session);
                }
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href);
                
                xhr.addEventListener('load', function() {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // 대분류 목록 표시
                            if (response.categories && response.categories.length > 0) {
                                let html = '<ul class="subject-list">';
                                response.categories.forEach(function(category) {
                                    html += '<li class="subject-item">';
                                    html += '<span class="subject-name">' + escapeHtml(category.category) + '</span>';
                                    html += '<span class="subject-count">' + number_format(category.question_count) + '개</span>';
                                    html += '</li>';
                                });
                                html += '</ul>';
                                modalBody.innerHTML = html;
                            } else {
                                modalBody.innerHTML = '<div class="loading">대분류 데이터가 없습니다.</div>';
                            }
                            
                            // 총 문항 수 표시
                            modalTotal.textContent = '총 ' + number_format(response.total_count) + '개';
                        } else {
                            modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">오류: ' + escapeHtml(response.message || '데이터를 불러올 수 없습니다.') + '</div>';
                        }
                    } catch (e) {
                        modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">응답 처리 중 오류가 발생했습니다: ' + escapeHtml(e.message) + '</div>';
                    }
                });
                
                xhr.addEventListener('error', function() {
                    modalBody.innerHTML = '<div class="loading" style="color: #dc3545;">네트워크 오류가 발생했습니다.</div>';
                });
                
                xhr.send(formData);
            });
        });
        
        // 삭제 버튼 클릭 이벤트
        const deleteRowBtns = document.querySelectorAll('.delete-row-btn');
        
        deleteRowBtns.forEach(function(deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                const year = this.getAttribute('data-year');
                const session = this.getAttribute('data-session');
                const course = this.getAttribute('data-course');
                const count = this.getAttribute('data-count');
                
                // 삭제 확인
                let confirmMessage = `${year}년`;
                if (session) {
                    confirmMessage += ` 제${session}회`;
                }
                confirmMessage += ` ${course}의 ${count}개 문제를 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.`;
                
                if (!confirm(confirmMessage)) {
                    return;
                }
                
                // 버튼 비활성화
                this.disabled = true;
                this.textContent = '삭제 중...';
                
                // AJAX 요청
                const formData = new FormData();
                formData.append('action', 'delete_exam_data');
                formData.append('exam_year', year);
                formData.append('exam_course', course);
                if (session) {
                    formData.append('exam_session', session);
                }
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href);
                
                xhr.addEventListener('load', function() {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            alert('✅ ' + response.message);
                            // 페이지 새로고침하여 목록 업데이트
                            window.location.reload();
                        } else {
                            alert('❌ 삭제 실패: ' + response.message);
                            // 버튼 복원
                            deleteBtn.disabled = false;
                            deleteBtn.textContent = '🗑️';
                        }
                    } catch (e) {
                        alert('❌ 응답 처리 중 오류가 발생했습니다: ' + e.message);
                        // 버튼 복원
                        deleteBtn.disabled = false;
                        deleteBtn.textContent = '🗑️';
                    }
                });
                
                xhr.addEventListener('error', function() {
                    alert('❌ 네트워크 오류가 발생했습니다.');
                    // 버튼 복원
                    deleteBtn.disabled = false;
                    deleteBtn.textContent = '🗑️';
                });
                
                xhr.send(formData);
            });
        });
    </script>
</body>
</html>
<?php
}
?>
