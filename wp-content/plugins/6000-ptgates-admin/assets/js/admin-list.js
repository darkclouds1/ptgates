/**
 * PTGates Admin 문제 목록 JavaScript
 */

(function($) { 
    'use strict';
    
    let currentPage = 1;
    let currentSession = '';
    let currentSubject = '';
    let currentSubsubject = '';
    let currentExamYear = '';
    let currentExamSession = '';
    let currentSearch = '';
    let subjectsData = [];

    const defaultOptionLabels = {
        year: '년도',
        examSession: '회차',
        session: '교시',
        subject: '과목',
        subsubject: '세부과목'
    };

    function resetSelectOptions($select, label) {
        $select.html('<option value="">' + label + '</option>');
    }
    
    // 초기화
    $(document).ready(function() {
        loadExamYears();
        loadSessions();
        // 초기 로드 시 문제 목록은 가져오지 않음 (검색 시에만)
        $('#ptg-questions-list').html('<p style="text-align: center; color: #666; padding: 40px;">검색 또는 필터를 사용하여 문제를 조회하세요.</p>');
        
        // 검색 버튼
        $('#ptg-search-btn').on('click', function() {
            currentSearch = $('#ptg-search-input').val().trim();
            currentPage = 1;
            loadQuestions();
        });
        
        // 검색 입력 엔터키
        $('#ptg-search-input').on('keypress', function(e) {
            if (e.which === 13) {
                $('#ptg-search-btn').click();
            }
        });
        
        // 초기화 버튼
        $('#ptg-clear-search').on('click', function() {
            $('#ptg-search-input').val('');
            currentSearch = '';
            currentSession = '';
            currentSubject = '';
            currentSubsubject = '';
            currentExamYear = '';
            currentExamSession = '';
            $('#ptg-year-filter').val('');
            $('#ptg-session-filter').val('');
            resetSelectOptions($('#ptg-exam-session-filter'), defaultOptionLabels.examSession);
            resetSelectOptions($('#ptg-subject-filter'), defaultOptionLabels.subject);
            resetSelectOptions($('#ptg-subsubject-filter'), defaultOptionLabels.subsubject);
            currentPage = 1;
            loadSubjects();
            $('#ptg-questions-list').html('<p style="text-align: center; color: #666; padding: 40px;">검색 또는 필터를 사용하여 문제를 조회하세요.</p>');
            $('#ptg-result-count').hide();
            $('#ptg-pagination').html('');
        });

        // 년도 필터 변경
        $('#ptg-year-filter').on('change', function() {
            currentExamYear = $(this).val();
            currentExamSession = '';
            resetSelectOptions($('#ptg-exam-session-filter'), defaultOptionLabels.examSession);
            if (currentExamYear) {
                loadExamSessions(currentExamYear);
            }
            // 필터 변경 시 자동 조회하지 않음 (검색 버튼 클릭 시에만 조회)
        });

        // 회차 필터 변경
        $('#ptg-exam-session-filter').on('change', function() {
            currentExamSession = $(this).val();
            // 필터 변경 시 자동 조회하지 않음 (검색 버튼 클릭 시에만 조회)
        });
        
        // 교시 필터 변경
        $('#ptg-session-filter').on('change', function() {
            currentSession = $(this).val();
            currentSubject = '';
            currentSubsubject = '';
            resetSelectOptions($('#ptg-subject-filter'), defaultOptionLabels.subject);
            resetSelectOptions($('#ptg-subsubject-filter'), defaultOptionLabels.subsubject);
            if (currentSession) {
                loadSubjects(currentSession);
            } else {
                loadSubjects();
            }
            // 필터 변경 시 자동 조회하지 않음 (검색 버튼 클릭 시에만 조회)
        });
        
        // 과목 필터 변경
        $('#ptg-subject-filter').on('change', function() {
            currentSubject = $(this).val();
            currentSubsubject = '';
            resetSelectOptions($('#ptg-subsubject-filter'), defaultOptionLabels.subsubject);
            if (currentSubject) {
                updateSubsubjects(currentSubject);
            }
            // 필터 변경 시 자동 조회하지 않음 (검색 버튼 클릭 시에만 조회)
        });
        
        // 세부과목 필터 변경
        $('#ptg-subsubject-filter').on('change', function() {
            currentSubsubject = $(this).val();
            // 필터 변경 시 자동 조회하지 않음 (검색 버튼 클릭 시에만 조회)
        });
        
        // 모달 닫기
        $('.ptg-edit-modal-close, #ptg-cancel-btn').on('click', function() {
            $('#ptg-edit-modal').hide();
        });
        
        // 모달 외부 클릭 시 닫기
        $('#ptg-edit-modal').on('click', function(e) {
            if ($(e.target).is('#ptg-edit-modal')) {
                $(this).hide();
            }
        });
        
        // 저장 버튼
        $('#ptg-save-btn').on('click', function() {
            saveQuestion();
        });
    });
    
    /**
     * 년도 목록 로드
     */
    function loadExamYears() {
        $.ajax({
            url: ptgAdmin.apiUrl + 'exam-years',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ptgAdmin.nonce);
            },
            success: function(response) {
                if (response.success && Array.isArray(response.data)) {
                    const $select = $('#ptg-year-filter');
                    resetSelectOptions($select, defaultOptionLabels.year);
                    response.data.forEach(function(year) {
                        $select.append($('<option>', {
                            value: year,
                            text: year + '년'
                        }));
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('년도 목록 로드 실패:', status, error, xhr.responseJSON);
            }
        });
    }

    /**
     * 회차 목록 로드 (년도 기준)
     */
    function loadExamSessions(year) {
        if (!year) {
            resetSelectOptions($('#ptg-exam-session-filter'), defaultOptionLabels.examSession);
            return;
        }

        $.ajax({
            url: ptgAdmin.apiUrl + 'exam-sessions?year=' + encodeURIComponent(year),
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ptgAdmin.nonce);
            },
            success: function(response) {
                if (response.success && Array.isArray(response.data)) {
                    const $select = $('#ptg-exam-session-filter');
                    resetSelectOptions($select, defaultOptionLabels.examSession);
                    response.data.forEach(function(session) {
                        $select.append($('<option>', {
                            value: session,
                            text: session + '회'
                        }));
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('회차 목록 로드 실패:', status, error, xhr.responseJSON);
            }
        });
    }

    /**
     * 교시 목록 로드
     */
    function loadSessions() {
        $.ajax({
            url: ptgAdmin.apiUrl + 'sessions',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ptgAdmin.nonce);
            },
            success: function(response) {
                if (response.success && response.data && Array.isArray(response.data)) {
                    const $select = $('#ptg-session-filter');
                    resetSelectOptions($select, defaultOptionLabels.session);
                    
                    if (response.data.length > 0) {
                        response.data.forEach(function(session) {
                            $select.append($('<option>', {
                                value: session.id,
                                text: session.name
                            }));
                        });
                    }
                    
                    // 과목 목록도 로드
                    loadSubjects();
                } else {
                    console.error('교시 목록 응답 형식 오류:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('교시 목록 로드 실패:', status, error, xhr.responseJSON);
            }
        });
    }
    
    /**
     * 과목 목록 로드
     */
    function loadSubjects(session) {
        const url = ptgAdmin.apiUrl + 'subjects' + (session ? '?session=' + session : '');
        $.ajax({
            url: url,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ptgAdmin.nonce);
            },
            success: function(response) {
                if (response.success && response.data) {
                    subjectsData = response.data;
                    const $select = $('#ptg-subject-filter');
                    resetSelectOptions($select, defaultOptionLabels.subject);
                    
                    response.data.forEach(function(item) {
                        $select.append($('<option>', {
                            value: item.name,
                            text: item.name,
                            'data-session': item.session,
                            'data-subsubjects': JSON.stringify(item.subsubjects)
                        }));
                    });
                }
            },
            error: function() {
                console.error('과목 목록 로드 실패');
            }
        });
    }
    
    /**
     * 세부과목 목록 업데이트
     */
    function updateSubsubjects(subjectName) {
        const $subjectSelect = $('#ptg-subject-filter');
        const selectedOption = $subjectSelect.find('option:selected');
        const subsubjectsJson = selectedOption.attr('data-subsubjects');
        
        if (subsubjectsJson) {
            try {
                const subsubjects = JSON.parse(subsubjectsJson);
                const $subSelect = $('#ptg-subsubject-filter');
                resetSelectOptions($subSelect, defaultOptionLabels.subsubject);
                
                subsubjects.forEach(function(subsubject) {
                    $subSelect.append($('<option>', {
                        value: subsubject,
                        text: subsubject
                    }));
                });
            } catch (e) {
                console.error('세부과목 파싱 오류:', e);
            }
        }
    }
    
    /**
     * 문제 목록 로드
     */
    function loadQuestions() {
        const params = {
            page: currentPage,
            per_page: 20
        };
        
        if (currentSubsubject) {
            params.subsubject = currentSubsubject;
        } else if (currentSubject) {
            params.subject = currentSubject;
        }

        if (currentExamYear) {
            params.exam_year = currentExamYear;
        }

        if (currentExamSession) {
            params.exam_session = currentExamSession;
        }

        const sessionValue = $('#ptg-session-filter').val();
        if (sessionValue) {
            params.exam_course = sessionValue.endsWith('교시') ? sessionValue : sessionValue + '교시';
        }
        
        if (currentSearch) {
            params.search = currentSearch;
        }
        
        // 교시는 필터링에 직접 사용하지 않음 (과목/세부과목으로 필터링)
        
        console.debug('PTG Admin loadQuestions params:', params);
        console.log('[PTG Admin] loadQuestions params:', params);
        $('#ptg-questions-list').html('<p class="ptg-loading">로딩 중...</p>');
        
        $.ajax({
            url: ptgAdmin.apiUrl + 'questions',
            method: 'GET',
            data: params,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ptgAdmin.nonce);
            },
            success: function(response) {
                if (response.success && response.data) {
                    renderQuestions(response.data.questions);
                    renderPagination(response.data);
                    // 검색 결과 개수 표시
                    updateResultCount(response.data.total, params);
                } else {
                    $('#ptg-questions-list').html('<p>문제를 불러올 수 없습니다.</p>');
                    $('#ptg-result-count').hide();
                }
            },
            error: function() {
                $('#ptg-questions-list').html('<p>문제를 불러오는 중 오류가 발생했습니다.</p>');
            }
        });
    }
    
    /**
     * 텍스트 정리 함수 (_x000D_ 제거 및 줄바꿈 처리)
     */
    function cleanText(text) {
        if (!text) return '';
        // _x000D_ 제거 (Windows 줄바꿈 문자 \r\n의 유니코드 표현)
        return text.replace(/_x000D_/g, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    }
    
    /**
     * 문제 목록 렌더링
     */
    function renderQuestions(questions) {
        if (questions.length === 0) {
            $('#ptg-questions-list').html('<p>문제가 없습니다.</p>');
            return;
        }
        
        let html = '<div class="ptg-questions-grid">';
        
        questions.forEach(function(q) {
            // 전체 내용 표시 (생략 없음)
            const content = cleanText(q.content || '');
            const explanation = cleanText(q.explanation || '');
            
            // 년도, 회차, 교시 정보 추출 (첫 번째 값만 표시)
            const year = q.exam_years ? q.exam_years.split(',')[0] : '';
            const session = q.exam_sessions ? q.exam_sessions.split(',')[0] : '';
            const course = q.exam_courses ? q.exam_courses.split(',')[0] : '';
            const mainSubject = q.main_subjects ? q.main_subjects.split(',')[0] : '';
            const subsubject = q.subsubjects ? q.subsubjects.split(',')[0] : (q.subjects ? q.subjects.split(',')[0] : '');
            
            // 메타 정보 조합 (년도, 회차, 교시, 과목(대분류))
            const metaParts = [];
            if (year) metaParts.push(year + '년');
            if (session) metaParts.push(session + '회');
            if (course) metaParts.push(course);
            if (mainSubject) metaParts.push(mainSubject);
            const metaInfo = metaParts.length > 0 ? metaParts.join(' ') : '-';
            
            html += `
                <div class="ptg-question-card">
                    <div class="ptg-question-header">
                        <div class="ptg-question-id-info">
                            <strong>문제 ID: ${q.question_id}</strong>
                            <span class="ptg-question-meta-info">${metaInfo}</span>
                        </div>
                        <span class="ptg-question-subsubjects">${subsubject || '-'}</span>
                    </div>
                    <div class="ptg-question-content">
                        <div class="ptg-question-field">
                            <label>지문:</label>
                            <div class="ptg-question-text">${escapeHtml(content)}</div>
                        </div>
                        <div class="ptg-question-field">
                            <label>정답:</label>
                            <div class="ptg-question-text">${escapeHtml(q.answer || '-')}</div>
                        </div>
                        <div class="ptg-question-field">
                            <label>해설:</label>
                            <div class="ptg-question-text">${escapeHtml(explanation)}</div>
                        </div>
                        <div class="ptg-question-meta">
                            <span>난이도: ${q.difficulty || '-'}</span>
                            <span>활성: ${q.is_active ? '예' : '아니오'}</span>
                        </div>
                    </div>
                    <div class="ptg-question-actions">
                        <button class="ptg-btn-edit" data-id="${q.question_id}">✏️ 편집</button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        $('#ptg-questions-list').html(html);
        
        // 편집 버튼 이벤트
        $('.ptg-btn-edit').on('click', function() {
            const questionId = $(this).data('id');
            loadQuestionForEdit(questionId);
        });
    }
    
    /**
     * 페이지네이션 렌더링
     */
    function renderPagination(data) {
        if (data.total_pages <= 1) {
            $('#ptg-pagination').html('');
            return;
        }
        
        let html = '<div class="ptg-pagination-controls">';
        
        if (data.page > 1) {
            html += `<button class="ptg-pagination-btn" data-page="${data.page - 1}">이전</button>`;
        }
        
        html += `<span>페이지 ${data.page} / ${data.total_pages} (총 ${data.total}개)</span>`;
        
        if (data.page < data.total_pages) {
            html += `<button class="ptg-pagination-btn" data-page="${data.page + 1}">다음</button>`;
        }
        
        html += '</div>';
        $('#ptg-pagination').html(html);
        
        $('.ptg-pagination-btn').on('click', function() {
            currentPage = $(this).data('page');
            loadQuestions();
        });
    }
    
    /**
     * 편집을 위한 문제 로드
     */
    function loadQuestionForEdit(questionId) {
        $.ajax({
            url: ptgAdmin.apiUrl + 'questions/' + questionId,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ptgAdmin.nonce);
            },
            success: function(response) {
                if (response.success && response.data) {
                    const q = response.data;
                    $('#ptg-edit-question-id').val(q.question_id);
                    // _x000D_ 제거 후 표시
                    $('#ptg-edit-content').val(cleanText(q.content || ''));
                    $('#ptg-edit-answer').val(q.answer || '');
                    $('#ptg-edit-explanation').val(cleanText(q.explanation || ''));
                    $('#ptg-edit-difficulty').val(q.difficulty || 2);
                    $('#ptg-edit-is-active').prop('checked', q.is_active == 1);
                    $('#ptg-edit-modal').show();
                }
            },
            error: function() {
                alert('문제를 불러오는 중 오류가 발생했습니다.');
            }
        });
    }
    
    /**
     * 문제 저장
     */
    function saveQuestion() {
        const questionId = $('#ptg-edit-question-id').val();
        if (!questionId) {
            alert('문제 ID가 없습니다.');
            return;
        }
        
        const data = {
            content: $('#ptg-edit-content').val(),
            answer: $('#ptg-edit-answer').val(),
            explanation: $('#ptg-edit-explanation').val(),
            difficulty: parseInt($('#ptg-edit-difficulty').val()),
            is_active: $('#ptg-edit-is-active').is(':checked')
        };
        
        $.ajax({
            url: ptgAdmin.apiUrl + 'questions/' + questionId,
            method: 'PATCH',
            data: JSON.stringify(data),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ptgAdmin.nonce);
            },
            success: function(response) {
                if (response.success) {
                    alert('저장되었습니다.');
                    $('#ptg-edit-modal').hide();
                    loadQuestions();
                } else {
                    alert('저장에 실패했습니다: ' + (response.message || '알 수 없는 오류'));
                }
            },
            error: function(xhr) {
                const error = xhr.responseJSON;
                alert('저장에 실패했습니다: ' + (error ? error.message : '알 수 없는 오류'));
            }
        });
    }
    
    /**
     * 검색 결과 개수 표시
     */
    function updateResultCount(total, params) {
        const $countEl = $('#ptg-result-count');
        
        if (total > 0) {
            let conditionText = '';
            const conditions = [];
            
            if (params.search) {
                conditions.push('검색: "' + params.search + '"');
            }
            if (params.subsubject) {
                conditions.push('세부과목: ' + params.subsubject);
            } else if (params.subject) {
                conditions.push('과목: ' + params.subject);
            }
            if (params.exam_year) {
                conditions.push('년도: ' + params.exam_year);
            }
            if (params.exam_session) {
                conditions.push('회차: ' + params.exam_session);
            }
            if (params.exam_course) {
                conditions.push('교시: ' + params.exam_course);
            }
            
            if (conditions.length > 0) {
                conditionText = ' (' + conditions.join(', ') + ')';
            }
            
            $countEl.text('총 ' + total.toLocaleString() + '개' + conditionText).show();
        } else {
            $countEl.hide();
        }
    }
    
    /**
     * HTML 이스케이프
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
})(jQuery);

