<?php
/**
 * PTGates Exam Importer - Functions
 * 
 * 플러그인에서 사용하는 핵심 함수들
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CSV 처리 함수
 */
function ptgates_process_csv_import($wpdb) {
    $separator = ',';
    $questions_table = 'ptgates_questions';
    $categories_table = 'ptgates_categories';
    
    $required_fields = array('content', 'answer', 'exam_year', 'exam_course', 'subject');
    
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
    
    // 업로드된 파일을 플러그인 디렉토리에 exam_data.csv로 저장 (다운로드용)
    $saved_file_path = PTGATES_EXAM_IMPORTER_PLUGIN_DIR . 'exam_data.csv';
    
    // 업로드된 파일을 exam_data.csv로 복사
    copy($uploaded_file, $saved_file_path);
    
    // 파일 열기
    $file = fopen($uploaded_file, 'r');
    if (!$file) {
        echo json_encode(array(
            'success' => false,
            'message' => 'CSV 파일을 열 수 없습니다.'
        ));
        return;
    }
    
    $log[] = "CSV 파일 열기 성공: " . $_FILES['csv_file']['name'];
    
    // 헤더 읽기
    $header = fgetcsv($file, 0, $separator);
    if (!$header) {
        fclose($file);
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
    
    // 덮어쓰기 모드인 경우 기존 데이터 삭제
    if ($overwrite_mode) {
        // 외래키 제약조건 때문에 categories를 먼저 삭제해야 함
        $wpdb->query("DELETE FROM {$categories_table}");
        $deleted_categories = $wpdb->rows_affected;
        $wpdb->query("DELETE FROM {$questions_table}");
        $deleted_questions = $wpdb->rows_affected;
        $log[] = "기존 데이터 삭제 완료: 질문 {$deleted_questions}개, 분류 {$deleted_categories}개";
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
            $type = !empty($data['type']) ? trim($data['type']) : '객관식';
            if (!in_array($type, array('객관식', '주관식', '서술형'))) {
                $type = '객관식';
            }
            
            $difficulty = isset($data['difficulty']) ? intval($data['difficulty']) : 2;
            if ($difficulty < 1 || $difficulty > 3) {
                $difficulty = 2;
            }
            
            $exam_year = intval($data['exam_year']);
            if ($exam_year < 1900 || $exam_year > 2100) {
                throw new Exception("시험 연도가 유효하지 않습니다! (라인: {$line_number}, 연도: {$exam_year})");
            }
            
            $exam_session = isset($data['exam_session']) && !empty($data['exam_session']) 
                ? intval($data['exam_session']) 
                : null;
            
            $exam_course = trim($data['exam_course']);
            $subject = trim($data['subject']);
            $source_company = isset($data['source_company']) && !empty($data['source_company']) 
                ? trim($data['source_company']) 
                : null;
            
            // 중복 체크: content와 시험 정보 조합으로 확인
            // 같은 연도, 회차, 교시, 과목에서 동일한 문제 본문이 있는지 확인
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
            
            // exam_session이 있는 경우에도 포함하여 체크
            if ($exam_session !== null) {
                $duplicate_check .= $wpdb->prepare(" AND c.exam_session = %d", $exam_session);
            } else {
                $duplicate_check .= " AND c.exam_session IS NULL";
            }
            
            $duplicate_check .= " LIMIT 1";
            
            $existing_question_id = $wpdb->get_var($duplicate_check);
            
            if ($existing_question_id) {
                // 덮어쓰기 모드가 아니면 중복 건너뛰기
                if (!$overwrite_mode) {
                    $log[] = "라인 {$line_number}: 중복 데이터 건너뜀 (이미 존재하는 문제)";
                    continue;
                } else {
                    // 덮어쓰기 모드: 기존 데이터 업데이트
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
                        'exam_session'   => $exam_session,
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
    }
    
    fclose($file);
}

/**
 * TXT 파일을 CSV로 변환하는 함수
 */
function ptgates_generate_csv_from_txt() {
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

/**
 * 현재 문제은행 통계 조회 함수
 */
function ptgates_get_question_statistics($wpdb) {
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

