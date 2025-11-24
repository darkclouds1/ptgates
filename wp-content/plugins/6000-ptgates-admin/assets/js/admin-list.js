/**
 * PTGates Admin 문제 목록 JavaScript
 * Refactored to use Module Pattern and Namespace Event Binding
 * Updated: Inline Editing Support
 */

var PTGates_Admin_List = {
    // 설정값 (Selectors & Config)
    config: {
        apiUrl: '', // init에서 설정
        ajaxUrl: '', // init에서 설정
        nonce: '',  // init에서 설정
        selectors: {
            // Filters
            yearFilter: '#ptg-year-filter',
            examSessionFilter: '#ptg-exam-session-filter',
            sessionFilter: '#ptg-session-filter',
            subjectFilter: '#ptg-subject-filter',
            subsubjectFilter: '#ptg-subsubject-filter',
            
            // Search
            searchIdInput: '#ptg-search-id',
            searchInput: '#ptg-search-input',
            searchBtn: '#ptg-search-btn',
            clearBtn: '#ptg-clear-search',
            
            // List & Pagination
            listContainer: '#ptg-questions-list',
            paginationContainer: '#ptg-pagination',
            resultCount: '#ptg-result-count',
            
            // Inline Edit
            editTrigger: '.pt-admin-edit-btn',
            editWrapper: '.ptg-inline-edit-form',
            saveBtn: '.pt-btn-save-edit',
            cancelBtn: '.pt-btn-cancel-edit',
            
            // Question Card Elements
            card: '.ptg-question-card',
            viewContent: '.ptg-question-content',
            viewActions: '.ptg-question-actions'
        }
    },

    state: {
        currentPage: 1,
        currentSearch: '',
        currentSearchId: '',
        filters: {
            year: '',
            examSession: '',
            session: '',
            subject: '',
            subsubject: ''
        }
    },

    init: function() {
        console.log('[PTGates Admin] List Module Initialized');
        
        // 전역 설정 가져오기
        if (typeof ptgAdmin !== 'undefined') {
            this.config.apiUrl = ptgAdmin.apiUrl;
            this.config.ajaxUrl = ptgAdmin.ajaxUrl;
            this.config.nonce = ptgAdmin.nonce;
        } else {
            console.error('[PTGates Admin] ptgAdmin global object not found.');
            return;
        }

        this.bindEvents();
        this.loadInitialData();
    },

    bindEvents: function() {
        var self = this;
        var s = self.config.selectors;

        // 1. 편집 버튼 클릭 (Inline Edit)
        jQuery(document).off('click.ptAdminList', s.editTrigger).on('click.ptAdminList', s.editTrigger, function(e) {
            e.preventDefault();
            var $btn = jQuery(this);
            var $card = $btn.closest(s.card);
            var questionId = $btn.data('id');

            // 중복 실행 방지
            if ($card.find(s.editWrapper).length > 0) {
                return;
            }

            console.log('[PTGates Admin] Inline Edit clicked. ID:', questionId);
            self.startInlineEdit($card, questionId, $btn);
        });

        // 2. 삭제 버튼 클릭
        jQuery(document).off('click.ptAdminList', '.pt-admin-delete-btn').on('click.ptAdminList', '.pt-admin-delete-btn', function(e) {
            e.preventDefault();
            var $btn = jQuery(this);
            var questionId = $btn.data('id');
            
            // 확인 창
            if (!confirm('문제 ID ' + questionId + '를 정말 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.')) {
                return;
            }
            
            console.log('[PTGates Admin] Delete clicked. ID:', questionId);
            self.deleteQuestion(questionId, $btn);
        });

        // 3. 검색 버튼
        jQuery(document).on('click.ptAdminList', s.searchBtn, function() {
            self.state.currentSearch = jQuery(s.searchInput).val().trim();
            self.state.currentSearchId = jQuery(s.searchIdInput).val().trim();
            self.state.currentPage = 1;
            self.loadQuestions();
        });

        // 4. 검색 엔터키
        jQuery(document).on('keypress.ptAdminList', s.searchInput + ', ' + s.searchIdInput, function(e) {
            if (e.which === 13) {
                jQuery(s.searchBtn).click();
            }
        });

        // 5. 초기화 버튼
        jQuery(document).on('click.ptAdminList', s.clearBtn, function() {
            self.resetFilters();
        });

        // 6. 필터 변경 이벤트들
        jQuery(document).on('change.ptAdminList', s.yearFilter, function() {
            self.state.filters.year = jQuery(this).val();
            self.state.filters.examSession = '';
            self.resetSelectOptions(jQuery(s.examSessionFilter), '회차');
            if (self.state.filters.year) {
                self.loadExamSessions(self.state.filters.year);
            }
        });

        jQuery(document).on('change.ptAdminList', s.examSessionFilter, function() {
            self.state.filters.examSession = jQuery(this).val();
        });

        jQuery(document).on('change.ptAdminList', s.sessionFilter, function() {
            self.state.filters.session = jQuery(this).val();
            self.state.filters.subject = '';
            self.state.filters.subsubject = '';
            self.resetSelectOptions(jQuery(s.subjectFilter), '과목');
            self.resetSelectOptions(jQuery(s.subsubjectFilter), '세부과목');
            self.loadSubjects(self.state.filters.session);
        });

        jQuery(document).on('change.ptAdminList', s.subjectFilter, function() {
            self.state.filters.subject = jQuery(this).val();
            self.state.filters.subsubject = '';
            self.resetSelectOptions(jQuery(s.subsubjectFilter), '세부과목');
            if (self.state.filters.subject) {
                self.updateSubsubjects(self.state.filters.subject);
            }
        });

        jQuery(document).on('change.ptAdminList', s.subsubjectFilter, function() {
            self.state.filters.subsubject = jQuery(this).val();
        });

        // 7. 인라인 편집 - 취소
        jQuery(document).on('click.ptAdminList', s.cancelBtn, function(e) {
            e.preventDefault();
            var $wrapper = jQuery(this).closest(s.editWrapper);
            var $card = $wrapper.closest(s.card);
            
            // 편집 폼 제거 및 보기 모드 복구
            $wrapper.remove();
            $card.find(s.viewContent).show();
            $card.find(s.viewActions).show();
        });

        // 8. 인라인 편집 - 저장
        jQuery(document).on('click.ptAdminList', s.saveBtn, function(e) {
            e.preventDefault();
            var $wrapper = jQuery(this).closest(s.editWrapper);
            self.saveInlineEdit($wrapper);
        });

        // 9. 페이지네이션
        jQuery(document).on('click.ptAdminList', '.ptg-pagination-btn', function() {
            self.state.currentPage = jQuery(this).data('page');
            self.loadQuestions();
        });

        // 10. 이미지 미리보기 (Inline Edit)
        jQuery(document).on('change.ptAdminList', 'input[name="question_image"]', function(e) {
            var file = e.target.files[0];
            var $wrapper = jQuery(this).closest(s.editWrapper);
            var $previewContainer = $wrapper.find('.ptg-image-preview-container');
            
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    if ($previewContainer.length === 0) {
                        $previewContainer = jQuery('<div class="ptg-image-preview-container"><img class="ptg-image-preview"><p class="ptg-image-filename"></p></div>');
                        $wrapper.find('input[name="question_image"]').before($previewContainer);
                    }
                    $previewContainer.find('img').attr('src', e.target.result);
                    $previewContainer.find('.ptg-image-filename').text(file.name);
                }
                reader.readAsDataURL(file);
            }
        });

        // 11. 이미지 삭제 버튼
        jQuery(document).on('click.ptAdminList', '.ptg-btn-delete-image', function(e) {
            e.preventDefault();
            var $wrapper = jQuery(this).closest(s.editWrapper);
            
            if (confirm('이미지를 삭제하시겠습니까? 저장 시 반영됩니다.')) {
                $wrapper.find('input[name="delete_image"]').val('1');
                $wrapper.find('.ptg-image-preview-container').hide();
                $wrapper.find('input[name="question_image"]').val(''); // 파일 입력 초기화
            }

        });

        // 12. 인라인 편집 - 과목 변경
        jQuery(document).on('change.ptAdminList', '.ptg-subject-select', function() {
            var $wrapper = jQuery(this).closest(s.editWrapper);
            var subject = jQuery(this).val();
            self.updateEditSubsubjects($wrapper, subject);
        });
    },

    loadInitialData: function() {
        this.loadExamYears();
        this.loadSessions();
        // 초기 안내 메시지
        jQuery(this.config.selectors.listContainer).html('<p style="text-align: center; color: #666; padding: 40px;">검색 또는 필터를 사용하여 문제를 조회하세요.</p>');
    },

    resetFilters: function() {
        var s = this.config.selectors;
        jQuery(s.searchInput).val('');
        jQuery(s.searchIdInput).val('');
        this.state.currentSearch = '';
        this.state.currentSearchId = '';
        this.state.filters = { year: '', examSession: '', session: '', subject: '', subsubject: '' };
        this.state.currentPage = 1;

        jQuery(s.yearFilter).val('');
        jQuery(s.sessionFilter).val('');
        this.resetSelectOptions(jQuery(s.examSessionFilter), '회차');
        this.resetSelectOptions(jQuery(s.subjectFilter), '과목');
        this.resetSelectOptions(jQuery(s.subsubjectFilter), '세부과목');
        
        this.loadSubjects(); // Reload all subjects
        
        jQuery(s.listContainer).html('<p style="text-align: center; color: #666; padding: 40px;">검색 또는 필터를 사용하여 문제를 조회하세요.</p>');
        jQuery(s.resultCount).hide();
        jQuery(s.paginationContainer).html('');
    },

    resetSelectOptions: function($select, label) {
        $select.html('<option value="">' + label + '</option>');
    },

    // --- Data Loading Methods ---

    loadExamYears: function() {
        var self = this;
        jQuery.ajax({
            url: self.config.apiUrl + 'exam-years',
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && Array.isArray(response.data)) {
                    var $select = jQuery(self.config.selectors.yearFilter);
                    self.resetSelectOptions($select, '년도');
                    response.data.forEach(function(year) {
                        $select.append(jQuery('<option>', { value: year, text: year + '년' }));
                    });
                }
            }
        });
    },

    loadExamSessions: function(year) {
        var self = this;
        if (!year) return;
        jQuery.ajax({
            url: self.config.apiUrl + 'exam-sessions?year=' + encodeURIComponent(year),
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && Array.isArray(response.data)) {
                    var $select = jQuery(self.config.selectors.examSessionFilter);
                    self.resetSelectOptions($select, '회차');
                    response.data.forEach(function(session) {
                        $select.append(jQuery('<option>', { value: session, text: session + '회' }));
                    });
                }
            }
        });
    },

    loadSessions: function() {
        var self = this;
        jQuery.ajax({
            url: self.config.apiUrl + 'sessions',
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && Array.isArray(response.data)) {
                    var $select = jQuery(self.config.selectors.sessionFilter);
                    self.resetSelectOptions($select, '교시');
                    response.data.forEach(function(session) {
                        $select.append(jQuery('<option>', { value: session.id, text: session.name }));
                    });
                    self.loadSubjects();
                }
            }
        });
    },

    loadSubjects: function(session) {
        var self = this;
        var url = self.config.apiUrl + 'subjects' + (session ? '?session=' + session : '');
        jQuery.ajax({
            url: url,
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && response.data) {
                    var $select = jQuery(self.config.selectors.subjectFilter);
                    self.resetSelectOptions($select, '과목');
                    response.data.forEach(function(item) {
                        $select.append(jQuery('<option>', {
                            value: item.name,
                            text: item.name,
                            'data-session': item.session,
                            'data-subsubjects': JSON.stringify(item.subsubjects)
                        }));
                    });
                }
            }
        });
    },

    updateSubsubjects: function(subjectName) {
        var $subjectSelect = jQuery(this.config.selectors.subjectFilter);
        var selectedOption = $subjectSelect.find('option:selected');
        var subsubjectsJson = selectedOption.attr('data-subsubjects');
        
        if (subsubjectsJson) {
            try {
                var subsubjects = JSON.parse(subsubjectsJson);
                var $subSelect = jQuery(this.config.selectors.subsubjectFilter);
                this.resetSelectOptions($subSelect, '세부과목');
                subsubjects.forEach(function(subsubject) {
                    $subSelect.append(jQuery('<option>', { value: subsubject, text: subsubject }));
                });
            } catch (e) {
                console.error('세부과목 파싱 오류:', e);
            }
        }
    },

    loadQuestions: function(callback) {
        var self = this;
        var params = {
            page: self.state.currentPage,
            per_page: 20
        };

        // Add filters
        if (self.state.filters.subsubject) params.subsubject = self.state.filters.subsubject;
        else if (self.state.filters.subject) params.subject = self.state.filters.subject;

        if (self.state.filters.year) params.exam_year = self.state.filters.year;
        if (self.state.filters.examSession) params.exam_session = self.state.filters.examSession;
        
        var sessionValue = jQuery(self.config.selectors.sessionFilter).val();
        if (sessionValue) {
            params.exam_course = sessionValue.endsWith('교시') ? sessionValue : sessionValue + '교시';
        }

        if (self.state.currentSearch) params.search = self.state.currentSearch;
        if (self.state.currentSearchId) params.question_id = self.state.currentSearchId;

        console.log('[PTG Admin] loadQuestions params:', params);
        jQuery(self.config.selectors.listContainer).html('<p class="ptg-loading">로딩 중...</p>');

        jQuery.ajax({
            url: self.config.apiUrl + 'questions',
            method: 'GET',
            data: params,
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && response.data) {
                    self.renderQuestions(response.data.questions);
                    self.renderPagination(response.data);
                    self.updateResultCount(response.data.total, params);
                    
                    // 콜백이 있으면 실행
                    if (typeof callback === 'function') {
                        callback();
                    }
                } else {
                    jQuery(self.config.selectors.listContainer).html('<p>문제를 불러올 수 없습니다.</p>');
                    jQuery(self.config.selectors.resultCount).hide();
                    
                    // 콜백이 있으면 실행
                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            },
            error: function() {
                jQuery(self.config.selectors.listContainer).html('<p>문제를 불러오는 중 오류가 발생했습니다.</p>');
                
                // 콜백이 있으면 실행
                if (typeof callback === 'function') {
                    callback();
                }
            }
        });
    },

    // --- Rendering Methods ---

    renderQuestions: function(questions) {
        if (questions.length === 0) {
            jQuery(this.config.selectors.listContainer).html('<p>문제가 없습니다.</p>');
            return;
        }

        var html = '<div class="ptg-questions-grid">';
        var self = this;

        questions.forEach(function(q) {
            var content = self.cleanText(q.content || '');
            var explanation = q.explanation || ''; // 사용자 요청: 해설은 원본 그대로 표시

            var year = q.exam_years ? q.exam_years.split(',')[0] : '';
            var session = q.exam_sessions ? q.exam_sessions.split(',')[0] : '';
            var course = q.exam_courses ? q.exam_courses.split(',')[0] : '';
            var mainSubject = q.main_subjects ? q.main_subjects.split(',')[0] : '';
            var subsubject = q.subsubjects ? q.subsubjects.split(',')[0] : (q.subjects ? q.subjects.split(',')[0] : '');

            var metaParts = [];
            if (year) metaParts.push(year + '년');
            if (session) metaParts.push(session + '회');
            if (course) metaParts.push(course);
            if (mainSubject) metaParts.push(mainSubject);
            var metaInfo = metaParts.length > 0 ? metaParts.join(' ') : '-';

            // 이미지 아이콘 표시
            var imageIcon = q.question_image ? '<span class="ptg-image-indicator" title="이미지 있음">🖼️</span>' : '';

            html += `
                <div class="ptg-question-card">
                    <div class="ptg-question-header">
                        <div class="ptg-question-id-info">
                            <strong>문제 ID: ${q.question_id}</strong>
                            <span class="ptg-question-meta-info">${metaInfo}</span>
                            ${imageIcon}
                        </div>
                        <span class="ptg-question-subsubjects">${subsubject || '-'}</span>
                    </div>
                    <div class="ptg-question-content">
                        <div class="ptg-question-field">
                            <label>지문:</label>
                            <div class="ptg-question-text">${self.escapeHtml(content)}</div>
                        </div>
                        <div class="ptg-question-field">
                            <label>정답:</label>
                            <div class="ptg-question-text">${self.escapeHtml(q.answer || '-')}</div>
                        </div>
                        <div class="ptg-question-field">
                            <label>해설:</label>
                            <div class="ptg-question-text">${self.escapeHtml(explanation)}</div>
                        </div>
                        <div class="ptg-question-meta">
                            <span>난이도: ${q.difficulty || '-'}</span>
                            <span>활성: ${q.is_active ? '예' : '아니오'}</span>
                        </div>
                    </div>
                    <div class="ptg-question-actions">
                        <button class="pt-admin-edit-btn" data-id="${q.question_id}">✏️ 편집</button>
                        <button class="pt-admin-delete-btn" data-id="${q.question_id}">🗑️ 삭제</button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        jQuery(this.config.selectors.listContainer).html(html);
    },

    renderPagination: function(data) {
        if (data.total_pages <= 1) {
            jQuery(this.config.selectors.paginationContainer).html('');
            return;
        }

        var html = '<div class="ptg-pagination-controls">';
        if (data.page > 1) {
            html += `<button class="ptg-pagination-btn" data-page="${data.page - 1}">이전</button>`;
        }
        html += `<span>페이지 ${data.page} / ${data.total_pages} (총 ${data.total}개)</span>`;
        if (data.page < data.total_pages) {
            html += `<button class="ptg-pagination-btn" data-page="${data.page + 1}">다음</button>`;
        }
        html += '</div>';
        jQuery(this.config.selectors.paginationContainer).html(html);
    },

    updateResultCount: function(total, params) {
        var $countEl = jQuery(this.config.selectors.resultCount);
        if (total > 0) {
            var conditionText = '';
            var conditions = [];
            if (params.question_id) conditions.push('ID: ' + params.question_id);
            if (params.search) conditions.push('검색: "' + params.search + '"');
            if (params.subsubject) conditions.push('세부과목: ' + params.subsubject);
            else if (params.subject) conditions.push('과목: ' + params.subject);
            if (params.exam_year) conditions.push('년도: ' + params.exam_year);
            if (params.exam_session) conditions.push('회차: ' + params.exam_session);
            if (params.exam_course) conditions.push('교시: ' + params.exam_course);
            
            if (conditions.length > 0) conditionText = ' (' + conditions.join(', ') + ')';
            $countEl.text('총 ' + total.toLocaleString() + '개' + conditionText).show();
        } else {
            $countEl.hide();
        }
    },

    // --- Inline Edit Functionality ---

    startInlineEdit: function($card, questionId, $btn) {
        var self = this;
        var s = self.config.selectors;
        var originalBtnText = $btn.text();
        
        $btn.text('로딩...').prop('disabled', true);

        jQuery.ajax({
            url: self.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pt_get_question_edit_form',
                question_id: questionId,
                security: self.config.nonce
            },
            success: function(response) {
                $btn.text(originalBtnText).prop('disabled', false);

                if (response.success) {
                    // 1. Hide view mode
                    $card.find(s.viewContent).hide();
                    $card.find(s.viewActions).hide();

                    // 2. Append edit form
                    $card.append(response.data);
                    
                    // 3. Populate subjects
                    self.populateEditSubjects($card.find(s.editWrapper));
                } else {
                    alert('오류: ' + (response.data || '폼을 불러올 수 없습니다.'));
                }
            },
            error: function(xhr, status, error) {
                $btn.text(originalBtnText).prop('disabled', false);
                console.error('[PTGates Admin] AJAX Error:', status, error, xhr.responseText);
                alert('서버 통신 오류: ' + status + ' ' + error + '\n' + (xhr.responseText ? xhr.responseText.substring(0, 100) : ''));
            }
        });
    },

    saveInlineEdit: function($wrapper) {
        var self = this;
        var $btn = $wrapper.find(self.config.selectors.saveBtn);
        
        console.log('[PTGates Admin] saveInlineEdit called');
        console.log('[PTGates Admin] Wrapper length:', $wrapper.length);
        console.log('[PTGates Admin] Wrapper HTML (first 100 chars):', $wrapper.prop('outerHTML').substring(0, 100));
        console.log('[PTGates Admin] Data question-id:', $wrapper.data('question-id'));
        console.log('[PTGates Admin] Input question-id val:', $wrapper.find('input[name="question_id"]').val());

        // FormData 객체 생성 (파일 업로드 지원)
        var formData = new FormData();
        formData.append('action', 'pt_update_question_inline');
        formData.append('security', self.config.nonce);
        
        // Try to get ID from data attribute first, then input
        var questionId = $wrapper.data('question-id');
        if (!questionId) {
            questionId = $wrapper.find('input[name="question_id"]').val();
        }
        
        // Ensure it's an integer (or string that looks like one)
        if (questionId) {
            questionId = parseInt(questionId, 10);
        }
        console.log('[PTGates Admin] Final Resolved Question ID:', questionId);
        
        if (!questionId) {
            alert('오류: 문제 ID를 찾을 수 없습니다.');
            return;
        }

        // 카드 요소 참조 저장
        var $card = $wrapper.closest(self.config.selectors.card);

        formData.append('question_id', questionId);
        formData.append('content', $wrapper.find('textarea[name="content"]').val());
        formData.append('answer', $wrapper.find('input[name="answer"]').val());
        formData.append('explanation', $wrapper.find('textarea[name="explanation"]').val());
        formData.append('difficulty', $wrapper.find('select[name="difficulty"]').val());
        formData.append('is_active', $wrapper.find('input[name="is_active"]').is(':checked') ? 1 : 0);
        formData.append('delete_image', $wrapper.find('input[name="delete_image"]').val());
        
        // 과목/세부과목 추가
        formData.append('subject', $wrapper.find('select[name="subject"]').val());
        formData.append('subsubject', $wrapper.find('select[name="subsubject"]').val());
        
        // 파일 추가
        var fileInput = $wrapper.find('input[name="question_image"]')[0];
        if (fileInput && fileInput.files.length > 0) {
            formData.append('question_image', fileInput.files[0]);
        }

        $btn.text('저장 중...').prop('disabled', true);

        jQuery.ajax({
            url: self.config.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false, // 파일 전송 시 필수
            contentType: false, // 파일 전송 시 필수
            success: function(response) {
                if (response.success) {
                    // 편집 폼에서 입력된 값들 가져오기
                    var savedContent = $wrapper.find('textarea[name="content"]').val();
                    var savedAnswer = $wrapper.find('input[name="answer"]').val();
                    var savedExplanation = $wrapper.find('textarea[name="explanation"]').val();
                    var savedDifficulty = $wrapper.find('select[name="difficulty"]').val();
                    var savedIsActive = $wrapper.find('input[name="is_active"]').is(':checked');
                    var savedSubject = $wrapper.find('select[name="subject"]').val();
                    var savedSubsubject = $wrapper.find('select[name="subsubject"]').val();
                    
                    // 편집 폼 제거 전에 보기 모드 요소 확인
                    var $viewContent = $card.find(self.config.selectors.viewContent);
                    var $viewActions = $card.find(self.config.selectors.viewActions);
                    
                    // 보기 모드가 존재하는지 확인
                    if ($viewContent.length === 0 || $viewActions.length === 0) {
                        console.error('[PTGates Admin] View mode elements not found before removing edit form');
                        console.error('[PTGates Admin] Card HTML:', $card.prop('outerHTML').substring(0, 1000));
                        alert('오류: 보기 모드 요소를 찾을 수 없습니다. 페이지를 새로고침해주세요.');
                        $btn.text('저장').prop('disabled', false);
                        return;
                    }
                    
                    // 편집 폼 제거
                    $wrapper.remove();
                    
                    // 보기 모드 복구
                    $viewContent.show();
                    $viewActions.show();
                    
                    // 카드 내용 즉시 업데이트
                    self.updateQuestionCard($card, {
                        content: savedContent,
                        answer: savedAnswer,
                        explanation: savedExplanation,
                        difficulty: savedDifficulty,
                        is_active: savedIsActive,
                        subsubject: savedSubsubject || savedSubject
                    });
                    
                    // 저장한 카드 헤더로 스크롤
                    setTimeout(function() {
                        var cardHeader = $card.find('.ptg-question-header');
                        if (cardHeader.length > 0) {
                            var headerOffset = cardHeader.offset().top - 100; // 상단 여백 100px
                            window.scrollTo({
                                top: headerOffset,
                                behavior: 'smooth'
                            });
                        } else {
                            // 헤더를 찾지 못하면 카드 상단으로 스크롤
                            var cardOffset = $card.offset().top - 100;
                            window.scrollTo({
                                top: cardOffset,
                                behavior: 'smooth'
                            });
                        }
                    }, 100); // DOM 업데이트 대기
                    
                    alert('저장되었습니다.');
                } else {
                    alert('저장에 실패했습니다: ' + (response.data || '알 수 없는 오류'));
                    $btn.text('저장').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('[PTGates Admin] Save Error:', status, error, xhr.responseText);
                alert('서버 통신 오류: ' + status + ' ' + error + '\n' + (xhr.responseText ? xhr.responseText.substring(0, 100) : ''));
                $btn.text('저장').prop('disabled', false);
            }
        });
    },

    populateEditSubjects: function($wrapper) {
        var self = this;
        var $subjectSelect = $wrapper.find('.ptg-subject-select');
        var $subsubjectSelect = $wrapper.find('.ptg-subsubject-select');
        var selectedSubject = $subjectSelect.data('selected');
        var selectedSubsubject = $subsubjectSelect.data('selected');

        // Load subjects (reuse logic or cache?)
        // Since we might not have all subjects loaded in filters (if filtered by session), we should fetch all.
        // But for efficiency, let's try to use what we have or fetch if needed.
        // Simpler to fetch all subjects again or use a cached variable if we had one.
        // Let's fetch 'subjects' endpoint without session param to get all.
        
        jQuery.ajax({
            url: self.config.apiUrl + 'subjects',
            method: 'GET',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); },
            success: function(response) {
                if (response.success && response.data) {
                    $subjectSelect.html('<option value="">과목 선택</option>');
                    
                    // Deduplicate
                    var uniqueSubjects = {};
                    response.data.forEach(function(item) {
                        if (!uniqueSubjects[item.name]) {
                            uniqueSubjects[item.name] = {
                                name: item.name,
                                subsubjects: []
                            };
                        }
                        if (item.subsubjects && Array.isArray(item.subsubjects)) {
                            item.subsubjects.forEach(function(sub) {
                                if (uniqueSubjects[item.name].subsubjects.indexOf(sub) === -1) {
                                    uniqueSubjects[item.name].subsubjects.push(sub);
                                }
                            });
                        }
                    });

                    Object.values(uniqueSubjects).forEach(function(item) {
                        var option = jQuery('<option>', {
                            value: item.name,
                            text: item.name,
                            'data-subsubjects': JSON.stringify(item.subsubjects)
                        });
                        if (item.name === selectedSubject) {
                            option.prop('selected', true);
                        }
                        $subjectSelect.append(option);
                    });

                    // Trigger update for subsubjects
                    if (selectedSubject) {
                        self.updateEditSubsubjects($wrapper, selectedSubject, selectedSubsubject);
                    }
                }
            }
        });
    },

    updateEditSubsubjects: function($wrapper, subjectName, selectedSubsubject) {
        var $subjectSelect = $wrapper.find('.ptg-subject-select');
        var selectedOption = $subjectSelect.find('option:selected');
        var subsubjectsJson = selectedOption.attr('data-subsubjects');
        var $subSelect = $wrapper.find('.ptg-subsubject-select');
        
        $subSelect.html('<option value="">세부과목 선택</option>');

        if (subsubjectsJson) {
            try {
                var subsubjects = JSON.parse(subsubjectsJson);
                subsubjects.forEach(function(subsubject) {
                    var option = jQuery('<option>', { value: subsubject, text: subsubject });
                    if (selectedSubsubject && subsubject === selectedSubsubject) {
                        option.prop('selected', true);
                    }
                    $subSelect.append(option);
                });
            } catch (e) {
                console.error('세부과목 파싱 오류:', e);
            }
        }
    },

    /**
     * 문제 카드 업데이트 (저장 후)
     */
    updateQuestionCard: function($card, data) {
        var self = this;
        var s = self.config.selectors;
        
        // 보기 모드 컨텐츠 영역 찾기
        var $viewContent = $card.find(s.viewContent);
        if ($viewContent.length === 0) {
            console.error('[PTGates Admin] View content not found in card');
            console.error('[PTGates Admin] Card HTML:', $card.prop('outerHTML').substring(0, 500));
            return;
        }
        
        // 줄바꿈을 <br>로 변환하는 헬퍼 함수
        var escapeHtmlWithBreaks = function(text) {
            if (!text) return '';
            var escaped = self.escapeHtml(text);
            // 줄바꿈을 <br>로 변환
            escaped = escaped.replace(/\n/g, '<br>');
            return escaped;
        };
        
        // 모든 필드 찾기
        var $fields = $viewContent.find('.ptg-question-field');
        console.log('[PTGates Admin] Found fields:', $fields.length);
        
        // 지문 업데이트 (첫 번째 필드)
        if ($fields.length > 0) {
            var content = self.cleanText(data.content || '');
            var $contentText = $fields.eq(0).find('.ptg-question-text');
            if ($contentText.length > 0) {
                $contentText.html(escapeHtmlWithBreaks(content));
                console.log('[PTGates Admin] Content updated:', content.substring(0, 50));
            } else {
                console.error('[PTGates Admin] Content text element not found');
            }
        } else {
            console.error('[PTGates Admin] No fields found in view content');
        }
        
        // 정답 업데이트 (두 번째 필드)
        if ($fields.length > 1) {
            var $answerText = $fields.eq(1).find('.ptg-question-text');
            if ($answerText.length > 0) {
                $answerText.html(escapeHtmlWithBreaks(data.answer || '-'));
            }
        }
        
        // 해설 업데이트 (세 번째 필드)
        if ($fields.length > 2) {
            var $explanationText = $fields.eq(2).find('.ptg-question-text');
            if ($explanationText.length > 0) {
                $explanationText.html(escapeHtmlWithBreaks(data.explanation || ''));
            }
        }
        
        // 난이도 업데이트
        var difficultyText = data.difficulty || '-';
        if (data.difficulty === '1') difficultyText = '1 (하)';
        else if (data.difficulty === '2') difficultyText = '2 (중)';
        else if (data.difficulty === '3') difficultyText = '3 (상)';
        var $metaSpans = $viewContent.find('.ptg-question-meta span');
        if ($metaSpans.length > 0) {
            $metaSpans.eq(0).text('난이도: ' + difficultyText);
        }
        
        // 활성 상태 업데이트
        if ($metaSpans.length > 1) {
            $metaSpans.eq(1).text('활성: ' + (data.is_active ? '예' : '아니오'));
        }
        
        // 세부과목 업데이트
        if (data.subsubject) {
            $card.find('.ptg-question-subsubjects').text(data.subsubject);
        }
    },

    /**
     * 문제 삭제
     */
    deleteQuestion: function(questionId, $btn) {
        var self = this;
        var originalBtnText = $btn.text();
        
        // 삭제할 카드 찾기
        var $card = $btn.closest(self.config.selectors.card);
        
        $btn.text('삭제 중...').prop('disabled', true);
        
        jQuery.ajax({
            url: self.config.apiUrl + 'questions/' + questionId,
            method: 'DELETE',
            beforeSend: function(xhr) { 
                xhr.setRequestHeader('X-WP-Nonce', self.config.nonce); 
            },
            success: function(response) {
                if (response.success) {
                    // 카드 제거 (애니메이션 효과)
                    $card.fadeOut(300, function() {
                        $card.remove();
                        
                        // 현재 페이지에 카드가 없으면 빈 상태 메시지 표시
                        var $grid = jQuery(self.config.selectors.listContainer).find('.ptg-questions-grid');
                        if ($grid.length > 0 && $grid.find(self.config.selectors.card).length === 0) {
                            jQuery(self.config.selectors.listContainer).html('<p>문제가 없습니다.</p>');
                        }
                    });
                    
                    alert('문제가 삭제되었습니다.');
                } else {
                    alert('삭제에 실패했습니다: ' + (response.data || response.message || '알 수 없는 오류'));
                    $btn.text(originalBtnText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('[PTGates Admin] Delete Error:', status, error, xhr.responseText);
                alert('서버 통신 오류: ' + status + ' ' + error);
                $btn.text(originalBtnText).prop('disabled', false);
            }
        });
    },

    // --- Utilities ---

    cleanText: function(text) {
        if (!text) return '';
        var cleaned = text.replace(/_x000D_/g, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        cleaned = cleaned.replace(/\n{2,}\s*([①-⑳])/g, '\n$1');
        cleaned = cleaned.replace(/\n{2,}/g, '\n');
        return cleaned;
    },

    escapeHtml: function(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize on ready
jQuery(document).ready(function() {
    PTGates_Admin_List.init();
});
