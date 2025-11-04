<?php
/**
 * PTGates Exam Importer - Import Interface
 * 
 * 웹 인터페이스 HTML/CSS/JS
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// WordPress admin wrapper 시작
?>
<div class="wrap">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        .ptgates-container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .ptgates-container h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
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
    </style>
    
    <div class="ptgates-container">
        <h1>📚 ptgates 문제은행 DB 일괄 삽입</h1>
        <p class="subtitle">CSV 파일을 업로드하여 문제 데이터를 데이터베이스에 삽입합니다.</p>
        
        <?php
        // 현재 문제은행 통계 조회
        global $wpdb;
        $stats = ptgates_get_question_statistics($wpdb);
        ?>
        
        <div class="statistics-section" style="background: #e8f4f8; border: 1px solid #b3d9e6; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: #005a87;">📊 현재 문제은행 현황</h3>
            <p style="margin-bottom: 15px; font-size: 16px;">
                <strong>총 문항 수: <span style="color: #0073aa; font-size: 18px;"><?php echo number_format($stats['total_count']); ?></span>개</strong>
            </p>
            
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
                                <?php echo $session !== null ? htmlspecialchars($session) : '-'; ?>
                            </td>
                            <td style="padding: 2px 8px; border: 1px solid #ddd; line-height: 1.2;">
                                <?php echo htmlspecialchars($course); ?>
                            </td>
                            <td style="padding: 2px 8px; border: 1px solid #ddd; text-align: right; font-weight: bold; line-height: 1.2;">
                                <?php echo number_format($count); ?>개
                            </td>
                            <td style="padding: 2px 8px; border: 1px solid #ddd; line-height: 1.2; font-size: 12px; color: #666;">
                                <?php echo $formatted_date ? htmlspecialchars($formatted_date) : '-'; ?>
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
        
        <div class="required-fields">
            <h3>CSV 컬럼:</h3>
            <ul id="csvColumnsList">
                <?php
                // 마지막 업로드 파일에서 헤더 읽기
                $display_columns = array();
                $file_path = PTGATES_EXAM_IMPORTER_PLUGIN_DIR . 'exam_data.csv';
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
                    $display_columns = array('exam_year', 'exam_session', 'exam_course', 'question_number', 'content', 'answer', 'explanation', 'subject');
                }
                
                // 컬럼 설명 매핑
                $column_descriptions = array(
                    'exam_year' => '시험 연도 (필수)',
                    'exam_session' => '시험 회차',
                    'exam_course' => '교시 구분 (필수)',
                    'question_number' => '문제 번호',
                    'content' => '문제 본문 (필수)',
                    'answer' => '정답 (필수)',
                    'explanation' => '문제 해설',
                    'subject' => '과목명 (필수)',
                    'source_company' => '문제 출처'
                );
                
                // 필수 필드
                $required_fields = array('content', 'answer', 'exam_year', 'exam_course', 'subject');
                
                foreach ($display_columns as $col) {
                    $col_lower = strtolower($col);
                    $is_required = in_array($col_lower, $required_fields);
                    $description = isset($column_descriptions[$col_lower]) ? $column_descriptions[$col_lower] : '';
                    $required_mark = $is_required ? ' <span style="color: #dc3545;">(필수)</span>' : '';
                    echo '<li><strong>' . htmlspecialchars($col) . '</strong>' . $required_mark;
                    if ($description) {
                        echo ': ' . htmlspecialchars($description);
                    }
                    echo '</li>';
                }
                ?>
            </ul>
        </div>
        
        <!-- TXT 파일에서 CSV 생성 섹션 -->
        <div class="upload-section" style="background: #fff9e6; border-color: #ffc107; margin-bottom: 30px;">
            <h3 style="margin-top: 0; margin-bottom: 15px; color: #856404;">📄 TXT 파일에서 CSV 생성</h3>
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
        
        <!-- CSV 파일 업로드 섹션 -->
        <div class="upload-section">
            <h3 style="margin-top: 0; margin-bottom: 15px; color: #333;">📁 CSV 파일 업로드</h3>
            <div class="file-input-wrapper">
                <input type="file" id="csvFile" accept=".csv" />
                <label for="csvFile" class="file-label">📁 CSV 파일 선택</label>
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
        
        <div class="progress-bar" id="progressBar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        
        <div class="status" id="status"></div>
        
        <div id="lastFileSection">
        <?php
        // 마지막 업로드 파일 정보 확인
        $file_path = PTGATES_EXAM_IMPORTER_PLUGIN_DIR . 'exam_data.csv';
        $has_file = file_exists($file_path);
        $original_filename = 'exam_data.csv';
        $upload_time = '';
        
        // 세션에서 업로드 시간 가져오기
        if (!session_id()) {
            session_start();
        }
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
        
        let isProcessing = false;
        
        // admin_url 함수 (WordPress)
        const adminUrl = '<?php echo admin_url('admin.php?page=ptgates-exam-importer'); ?>';
        
        // 파일 선택 이벤트
        csvFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                fileNameDisplay.textContent = `선택된 파일: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
                startBtn.disabled = false;
                
                // CSV 파일 헤더 읽어서 컬럼 목록 업데이트
                const reader = new FileReader();
                reader.onload = function(event) {
                    const text = event.target.result;
                    const lines = text.split('\n');
                    if (lines.length > 0) {
                        // 첫 번째 줄에서 헤더 추출
                        const header = lines[0].split(',').map(col => col.trim().replace(/^[\xEF\xBB\xBF"]+|["\r]+$/g, ''));
                        updateColumnsList(header);
                    }
                };
                reader.readAsText(file);
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
                'exam_year': '시험 연도 (필수)',
                'exam_session': '시험 회차',
                'exam_course': '교시 구분 (필수)',
                'question_number': '문제 번호',
                'content': '문제 본문 (필수)',
                'answer': '정답 (필수)',
                'explanation': '문제 해설',
                'subject': '과목명 (필수)',
                'source_company': '문제 출처'
            };
            
            const requiredFields = ['content', 'answer', 'exam_year', 'exam_course', 'subject'];
            
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
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        // 로그 추가 함수
        function addLog(message, type = 'info') {
            const logEntry = document.createElement('div');
            logEntry.className = `log-entry ${type}`;
            logEntry.textContent = message;
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        // 시작 버튼 클릭
        startBtn.addEventListener('click', function() {
            if (isProcessing) return;
            
            const file = csvFileInput.files[0];
            if (!file) {
                alert('파일을 선택해주세요.');
                return;
            }
            
            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('CSV 파일만 업로드 가능합니다.');
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
                        
                        // 파일 정보가 있으면 페이지 새로고침하여 마지막 파일 정보 표시
                        if (response.original_filename) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        }
                    } else {
                        statusDiv.className = 'status error';
                        statusDiv.textContent = `❌ 오류: ${response.message || '데이터 삽입에 실패했습니다.'}`;
                        statusDiv.style.display = 'block';
                        
                        // 실패해도 파일이 있으면 페이지 새로고침하여 다운로드 버튼 표시
                        if (response.original_filename) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
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
            
            xhr.open('POST', adminUrl);
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
            form.action = adminUrl;
            
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
            
            xhr.open('POST', adminUrl);
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
    </script>
</div>

